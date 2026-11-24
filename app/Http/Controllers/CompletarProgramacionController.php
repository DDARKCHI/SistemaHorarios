<?php

namespace App\Http\Controllers;

use App\Models;
use App\Services\{GeneracionCargaPendiente, DistribuidorCargaPendiente};
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CompletarProgramacionController extends Controller
{
    public function index(Request $request, GeneracionCargaPendiente $motor)
    {
        $datos = $request->validate(['periodo_id' => ['nullable', 'integer', 'exists:periodos_academicos,id'], 'version_id' => ['nullable', 'integer', 'exists:versiones_planificacion,id'],
            'carrera_id' => ['nullable', 'integer', 'exists:carreras,id'], 'q' => ['nullable', 'string', 'max:100'], 'page' => ['nullable', 'integer', 'min:1']]);
        $periodos = Models\PeriodoAcademico::with('universidad')->orderByDesc('anio')->orderByDesc('numero_semestre')->orderByDesc('id')->get();
        $periodo = isset($datos['periodo_id']) ? $periodos->firstWhere('id', $datos['periodo_id']) : ($periodos->firstWhere('estado', true) ?? $periodos->first());
        $versiones = Models\VersionPlanificacion::where('periodo_academico_id', $periodo?->id)->where('tipo', 'semestral')->orderByDesc('id')->get();
        $version = isset($datos['version_id']) ? $versiones->firstWhere('id', $datos['version_id']) : ($versiones->firstWhere('es_actual', true) ?? $versiones->first());
        if (isset($datos['version_id']) && ! $version) return redirect()->route('programacion.completar.index', ['periodo_id' => $periodo?->id])->withErrors(['version_id' => 'Selecciona una versión semestral del período elegido.']);
        $catalogo = $version ? $motor->catalogo($version) : ['filas' => collect(), 'duraciones' => collect()];
        $pendientes = $catalogo['filas']->sum('pendientes');
        $filas = $catalogo['filas']; $q = trim($datos['q'] ?? '');
        if (! empty($datos['carrera_id'])) $filas = $filas->filter(fn ($f) => $f['grupo']->actividadSeccion->seccion->carreras->contains('id', $datos['carrera_id']));
        if ($q !== '') $filas = $filas->filter(fn ($f) => Str::contains(mb_strtolower(implode(' ', [$f['grupo']->codigo, $f['grupo']->actividadSeccion->tipo,
            $f['grupo']->actividadSeccion->seccion->codigo, $f['grupo']->actividadSeccion->seccion->asignatura?->codigo, $f['grupo']->actividadSeccion->seccion->asignatura?->nombre])), mb_strtolower($q)));
        $pagina = $datos['page'] ?? 1;
        $grupos = new LengthAwarePaginator($filas->values()->forPage($pagina, 40)->values(), $filas->count(), 40, $pagina, ['path' => route('programacion.completar.index'), 'query' => $datos + ['periodo_id' => $periodo?->id, 'version_id' => $version?->id]]);
        if ($pagina > $grupos->lastPage()) return redirect()->to($grupos->url($grupos->lastPage()));
        $carreras = Models\Carrera::where('universidad_id', $periodo?->universidad_id)->orderBy('nombre')->get();
        $editable = $version && $version->estado === 'Borrador' && $periodo->estado && $periodo->universidad->estado;
        $duraciones = $catalogo['duraciones'];
        return view('programacion.completar.index', compact('periodos', 'periodo', 'versiones', 'version', 'carreras', 'datos', 'q', 'grupos', 'duraciones', 'pendientes', 'editable'));
    }

    public function generar(Request $request, GeneracionCargaPendiente $motor, DistribuidorCargaPendiente $distribuidor)
    {
        $datos = $request->validate(['version_id' => ['required', 'integer', 'exists:versiones_planificacion,id'], 'seleccion' => ['required', 'array', 'min:1', 'max:40'],
            'seleccion.*' => ['required', 'integer', 'distinct', 'exists:grupos_seccion,id'], 'sedes' => ['required', 'array', 'max:40'], 'sedes.*' => ['nullable', 'integer', 'exists:sedes,id'],
            'duracion' => ['required', 'integer', 'between:60,43200']]);
        $version = Models\VersionPlanificacion::findOrFail($datos['version_id']);
        $seleccion = array_map('intval', $datos['seleccion']); sort($seleccion);
        $sedes = collect($datos['sedes'])->only($seleccion)->map(fn ($s) => (int) $s)->all();
        $entrada = $motor->preparar($version, $seleccion, $sedes, (int) $datos['duracion']);
        $resultado = $distribuidor->distribuir($entrada['trabajos'], $entrada['opciones'], $entrada['paquetes'], $entrada['fijos']);
        if ($resultado['estado'] !== 'completo') throw ValidationException::withMessages(['generacion' => match ($resultado['estado']) {
            'sin_opciones' => 'Un grupo no tiene módulos comunes para sus docentes con la duración elegida. Revisa su disponibilidad aprobada.',
            'sin_solucion' => 'No se encontró una combinación sin topes con estos espacios y los módulos fijos. Revisa la disponibilidad, los requerimientos o la sede.',
            'limite' => 'La búsqueda alcanzó su límite sin completar todos los grupos seleccionados. Reduce la selección o revisa sus alternativas; esto no demuestra que no exista solución.',
            default => 'No hay carga pendiente que completar en la selección.',
        }]);
        if (! $distribuidor->valido($resultado['plan'], $entrada['trabajos'], $entrada['opciones'], $entrada['paquetes'], $entrada['fijos'])) throw ValidationException::withMessages(['generacion' => 'La propuesta no superó su comprobación. No se guardó ningún horario.']);
        $token = (string) Str::uuid();
        $propuestas = collect($request->session()->get('cargas_pendientes', []))->filter(fn ($p) => $p['creada'] >= now()->subMinutes(30)->timestamp)->take(-2)->all();
        $propuestas[$token] = ['token' => $token, 'usuario_id' => $request->user()->id, 'creada' => now()->timestamp, 'version_id' => $version->id,
            'seleccion' => $seleccion, 'sedes' => $sedes, 'duracion' => (int) $datos['duracion'], 'firma' => $entrada['firma'], 'plan' => $resultado['plan']];
        $request->session()->put('cargas_pendientes', $propuestas);
        return redirect()->route('programacion.completar.show', $token);
    }

    public function show(Request $request, string $token, GeneracionCargaPendiente $motor, DistribuidorCargaPendiente $distribuidor)
    {
        $validos = $request->validate(['page' => ['nullable', 'integer', 'min:1']]);
        $propuesta = $this->propuesta($request, $token); $version = Models\VersionPlanificacion::findOrFail($propuesta['version_id']);
        try { $entrada = $motor->preparar($version, $propuesta['seleccion'], $propuesta['sedes'], $propuesta['duracion']); }
        catch (ValidationException $e) { return redirect()->route('programacion.completar.index', ['periodo_id' => $version->periodo_academico_id, 'version_id' => $version->id])->withErrors($e->errors()); }
        if (! hash_equals($entrada['firma'], $propuesta['firma']) || ! $distribuidor->valido($propuesta['plan'], $entrada['trabajos'], $entrada['opciones'], $entrada['paquetes'], $entrada['fijos'])) return redirect()->route('programacion.completar.index', ['periodo_id' => $version->periodo_academico_id, 'version_id' => $version->id])->withErrors(['generacion' => 'La base o sus alternativas cambiaron. Prepara una propuesta nueva antes de guardar.']);
        $filas = $motor->presentar($entrada, $propuesta['plan']); $total = $filas->count();
        $conservados = $entrada['base']->count(); $restantes = max(0, $entrada['revision']['carga']['pendientes'] - $total); $avisosDisponibilidad = $entrada['revision']['disponibilidad'];
        $pagina = $validos['page'] ?? 1; $horarios = new LengthAwarePaginator($filas->forPage($pagina, 20)->values(), $total, 20, $pagina, ['path' => route('programacion.completar.show', $token)]);
        if ($pagina > $horarios->lastPage()) return redirect()->to($horarios->url($horarios->lastPage()));
        return response()->view('programacion.completar.propuesta', compact('version', 'token', 'horarios', 'total', 'conservados', 'restantes', 'avisosDisponibilidad'))->header('Cache-Control', 'no-store, private');
    }

    public function guardar(Request $request, string $token, GeneracionCargaPendiente $motor)
    {
        $request->validate(['confirmar' => ['accepted']]);
        $p = $this->propuesta($request, $token); $version = Models\VersionPlanificacion::findOrFail($p['version_id']);
        $nueva = $motor->guardar($version, $p, $request->user()->id, $request->ip());
        $request->session()->forget('cargas_pendientes.'.$token);
        return redirect()->route('programacion.completar.index', ['periodo_id' => $nueva->periodo_academico_id, 'version_id' => $nueva->id])->with('success', 'Propuesta guardada como borrador V'.$nueva->version.'. La versión actual se conserva. Puedes continuar completando este borrador o revisarlo para activación.');
    }
    private function propuesta(Request $request, string $token): array
    {
        $p = $request->session()->get('cargas_pendientes.'.$token);
        abort_unless(is_array($p) && (int) $p['usuario_id'] === (int) $request->user()->id && $p['creada'] >= now()->subMinutes(30)->timestamp, 410, 'La propuesta venció o pertenece a otra sesión. Prepara una nueva.');
        return $p;
    }
}

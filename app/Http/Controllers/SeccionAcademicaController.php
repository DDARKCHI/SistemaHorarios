<?php

namespace App\Http\Controllers;

use App\Models;
use App\Services\GestionOfertaPeriodo;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SeccionAcademicaController extends Controller
{
    public function index(Request $request)
    {
        $filtros = $request->validate(['periodo_id' => ['nullable', 'integer', 'exists:periodos_academicos,id'], 'carrera_id' => ['nullable', 'integer', 'exists:carreras,id'],
            'q' => ['nullable', 'string', 'max:100'], 'estado' => ['nullable', Rule::in(['activos', 'inactivos', 'todos'])], 'page' => ['nullable', 'integer', 'min:1']]);
        $periodos = Models\PeriodoAcademico::with('universidad')->orderByDesc('anio')->orderByDesc('numero_semestre')->orderByDesc('id')->get();
        $periodo = isset($filtros['periodo_id']) ? $periodos->firstWhere('id', $filtros['periodo_id']) : ($periodos->firstWhere('estado', true) ?? $periodos->first());
        $estado = $filtros['estado'] ?? 'activos'; $q = trim($filtros['q'] ?? '');
        $consulta = Models\Seccion::where('periodo_academico_id', $periodo?->id)->with('asignatura', 'carreras')->withCount('actividades', 'grupos');
        if ($estado !== 'todos') $consulta->where('estado_activo', $estado === 'activos');
        if (! empty($filtros['carrera_id'])) $consulta->whereHas('carreras', fn ($c) => $c->where('carreras.id', $filtros['carrera_id']));
        if ($q !== '') {
            $literal = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $q).'%';
            $consulta->where(fn ($s) => $s->whereRaw("COALESCE(codigo, '') LIKE ? ESCAPE '!'", [$literal])->orWhereHas('asignatura', fn ($a) => $a->whereRaw("codigo LIKE ? ESCAPE '!'", [$literal])->orWhereRaw("nombre LIKE ? ESCAPE '!'", [$literal])));
        }
        $secciones = $consulta->orderBy('asignatura_id')->orderBy('codigo')->orderBy('id')->paginate(12)->appends($filtros + ['periodo_id' => $periodo?->id]);
        if ($secciones->currentPage() > $secciones->lastPage()) return redirect()->to($secciones->url($secciones->lastPage()));
        $carreras = Models\Carrera::where('universidad_id', $periodo?->universidad_id)->orderBy('nombre')->get();
        $actual = $periodo?->versionesPlanificacion()->where('tipo', 'semestral')->where('es_actual', true)->latest('id')->first();
        $editable = $periodo?->estado && $periodo?->universidad->estado && (! $actual || $actual->estado === 'Borrador');
        return view('administracion.secciones.index', compact('filtros', 'periodos', 'periodo', 'secciones', 'carreras', 'actual', 'editable', 'estado', 'q'));
    }

    public function create(Request $request)
    {
        $request->validate(['periodo_id' => ['nullable', 'integer', 'exists:periodos_academicos,id']]);
        return $this->formulario(new Models\Seccion(['periodo_academico_id' => $request->integer('periodo_id') ?: null, 'inscritos' => 0, 'estado_activo' => true]));
    }
    public function edit(Models\Seccion $seccion) { return $this->formulario($seccion); }

    private function formulario(Models\Seccion $seccion)
    {
        $periodos = Models\PeriodoAcademico::with('universidad')->where('estado', true)->when($seccion->exists, fn ($p) => $p->orWhere('id', $seccion->periodo_academico_id))->orderByDesc('anio')->orderByDesc('numero_semestre')->get();
        $ramos = Models\Asignatura::with('universidad')->where('estado', true)->when($seccion->exists, fn ($r) => $r->orWhere('id', $seccion->asignatura_id))->orderBy('nombre')->get();
        $elegidas = $seccion->exists ? $seccion->carreras()->pluck('carreras.id')->all() : [];
        $carreras = Models\Carrera::with('universidad')->where('estado', true)->when($elegidas, fn ($c) => $c->orWhereIn('id', $elegidas))->orderBy('nombre')->get();
        return view('administracion.secciones.formulario', compact('seccion', 'periodos', 'ramos', 'carreras', 'elegidas'));
    }

    public function store(Request $request, GestionOfertaPeriodo $gestion)
    {
        $seccion = $gestion->guardarSeccion($request->all(), $request->user()->id, $request->ip());
        return redirect()->route('administracion.secciones.show', $seccion)->with('success', 'Sección creada. Revisa sus actividades y grupos antes de programar.');
    }
    public function update(Request $request, Models\Seccion $seccion, GestionOfertaPeriodo $gestion)
    {
        $gestion->guardarSeccion($request->all(), $request->user()->id, $request->ip(), $seccion->id);
        return redirect()->route('administracion.secciones.show', $seccion)->with('success', 'Sección actualizada.');
    }
    public function show(Models\Seccion $seccion)
    {
        $cerrados = fn ($h) => $h->whereHas('versionPlanificacion', fn ($v) => $v->whereIn('estado', ['Aprobado', 'Publicado']));
        $seccion->load(['asignatura', 'carreras', 'periodoAcademico.universidad', 'actividades' => fn ($a) => $a->withCount(['horarios as cierres_count' => $cerrados])->orderBy('tipo'),
            'actividades.grupos' => fn ($g) => $g->withCount(['horarios as cierres_count' => $cerrados])->orderBy('numero')->orderBy('id')]);
        $periodo = $seccion->periodoAcademico;
        $actual = $periodo->versionesPlanificacion()->where('tipo', 'semestral')->where('es_actual', true)->latest('id')->first();
        $editable = $periodo->estado && $periodo->universidad->estado && (! $actual || $actual->estado === 'Borrador');
        $carga = $seccion->actividades->where('estado', true)->sum(fn ($a) => (int) $a->cantidad_horas * $a->grupos->where('estado_activo', true)->count());
        $cerrada = $seccion->actividades->sum('cierres_count') > 0;
        return view('administracion.secciones.detalle', compact('seccion', 'actual', 'editable', 'carga', 'cerrada'));
    }

    public function actividad(Models\Seccion $seccion, ?int $id = null)
    {
        $registro = $id ? $seccion->actividades()->findOrFail($id) : new Models\ActividadSeccion(['seccion_id' => $seccion->id, 'estado' => true, 'cantidad_horas' => 1, 'cantidad_estudiantes' => $seccion->inscritos ?: $seccion->cupos]);
        $tipos = array_unique([...array_keys(GestionOfertaPeriodo::ACTIVIDADES), ...($id ? [$registro->tipo] : [])]);
        return view('administracion.secciones.actividad', compact('seccion', 'registro', 'tipos'));
    }
    public function guardarActividad(Request $request, Models\Seccion $seccion, GestionOfertaPeriodo $gestion, ?int $id = null)
    {
        if ($id) $seccion->actividades()->findOrFail($id);
        $gestion->guardarActividad($seccion->id, $request->all(), $request->user()->id, $request->ip(), $id);
        return redirect()->route('administracion.secciones.show', $seccion)->with('success', 'Actividad guardada. Revisa sus grupos y módulos requeridos.');
    }
    public function grupo(Models\Seccion $seccion, Models\ActividadSeccion $actividad, ?int $id = null)
    {
        abort_unless((int) $actividad->seccion_id === (int) $seccion->id, 404);
        $registro = $id ? $actividad->grupos()->findOrFail($id) : new Models\GrupoSeccion(['actividad_seccion_id' => $actividad->id, 'inscritos' => 0, 'estado_activo' => true]);
        return view('administracion.secciones.grupo', compact('seccion', 'actividad', 'registro'));
    }
    public function guardarGrupo(Request $request, Models\Seccion $seccion, Models\ActividadSeccion $actividad, GestionOfertaPeriodo $gestion, ?int $id = null)
    {
        abort_unless((int) $actividad->seccion_id === (int) $seccion->id, 404);
        if ($id) $actividad->grupos()->findOrFail($id);
        $gestion->guardarGrupo($actividad->id, $request->all(), $request->user()->id, $request->ip(), $id);
        return redirect()->route('administracion.secciones.show', $seccion)->with('success', 'Grupo guardado. Ya puedes revisar sus docentes y horarios.');
    }
    public function iniciar(Request $request, GestionOfertaPeriodo $gestion)
    {
        $datos = $request->validate(['periodo_id' => ['required', 'integer', 'exists:periodos_academicos,id'], 'confirmar' => ['accepted']]);
        $version = $gestion->iniciarBorrador($datos['periodo_id'], $request->user()->id, $request->ip());
        return redirect()->route('programacion.semestral', ['periodo_id' => $version->periodo_academico_id, 'version_id' => $version->id])->with('success', 'Borrador creado sin horarios asignados. Continúa con docentes, recursos y módulos.');
    }
}

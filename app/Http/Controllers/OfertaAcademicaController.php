<?php

namespace App\Http\Controllers;

use App\Models;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\{Rule, ValidationException};

class OfertaAcademicaController extends Controller
{
    public const MODULOS = ['horas_catedra' => 'Cátedra', 'horas_laboratorio' => 'Laboratorio', 'horas_ayudantia' => 'Ayudantía',
        'horas_taller' => 'Taller', 'horas_clinica' => 'Clínica', 'horas_practica' => 'Práctica', 'horas_otros' => 'Otros'];

    public function index(Request $request, string $tipo)
    {
        [$modelo, $titulo, $singular] = $this->catalogo($tipo);
        $filtros = $request->validate(['q' => ['nullable', 'string', 'max:100'], 'universidad_id' => ['nullable', 'integer', 'exists:universidades,id'],
            'estado' => ['nullable', Rule::in(['activos', 'inactivos', 'todos'])], 'anio' => ['nullable', 'integer', 'between:1900,2200'], 'page' => ['nullable', 'integer', 'min:1']]);
        $estado = $filtros['estado'] ?? 'activos'; $q = trim($filtros['q'] ?? '');
        $consulta = $modelo::with('universidad')->withCount('secciones');
        if ($tipo === 'periodos') $consulta->withCount('versionesPlanificacion');
        if ($estado !== 'todos') $consulta->where('estado', $estado === 'activos');
        if (! empty($filtros['universidad_id'])) $consulta->where('universidad_id', $filtros['universidad_id']);
        if ($tipo === 'periodos' && ! empty($filtros['anio'])) $consulta->where('anio', $filtros['anio']);
        if ($q !== '') {
            $literal = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $q).'%';
            $consulta->where(function ($c) use ($literal, $tipo) {
                $c->whereRaw("nombre LIKE ? ESCAPE '!'", [$literal]);
                if ($tipo === 'asignaturas') $c->orWhereRaw("codigo LIKE ? ESCAPE '!'", [$literal]);
                else $c->orWhereRaw("CAST(anio AS CHAR) LIKE ? ESCAPE '!'", [$literal]);
            });
        }
        if ($tipo === 'periodos') $consulta->orderByDesc('anio')->orderByDesc('numero_semestre');
        else $consulta->orderBy('nombre');
        $registros = $consulta->orderBy('id')->paginate(12)->withQueryString();
        if ($registros->currentPage() > $registros->lastPage()) return redirect()->to($registros->url($registros->lastPage()));
        $universidades = Models\Universidad::orderBy('nombre')->get();
        return view('administracion.oferta.index', compact('tipo', 'titulo', 'singular', 'registros', 'universidades', 'filtros', 'estado', 'q'));
    }

    public function create(string $tipo)
    {
        [$modelo] = $this->catalogo($tipo);
        $valores = ['estado' => true];
        if ($tipo === 'asignaturas') $valores += array_fill_keys(array_keys(self::MODULOS), 0) + ['es_libre_eleccion' => false, 'ramo_terminal' => false];
        return $this->formulario($tipo, new $modelo($valores));
    }

    public function edit(string $tipo, int $id)
    {
        [$modelo] = $this->catalogo($tipo);
        return $this->formulario($tipo, $modelo::findOrFail($id));
    }
    public function store(Request $request, string $tipo) { return $this->guardar($request, $tipo); }
    public function update(Request $request, string $tipo, int $id) { return $this->guardar($request, $tipo, $id); }

    private function formulario(string $tipo, $registro)
    {
        [, $titulo, $singular] = $this->catalogo($tipo);
        $universidades = Models\Universidad::where('estado', true)->when($registro->exists, fn ($q) => $q->orWhere('id', $registro->universidad_id))->orderBy('nombre')->get();
        $modulos = self::MODULOS;
        $referencias = $registro->exists && $tipo === 'periodos' ? $this->referenciasPeriodo($registro) : null;
        return view('administracion.oferta.formulario', compact('tipo', 'titulo', 'singular', 'registro', 'universidades', 'modulos', 'referencias'));
    }

    private function guardar(Request $request, string $tipo, ?int $id = null)
    {
        [$modelo, , $singular] = $this->catalogo($tipo);
        foreach (['nombre', 'codigo', 'modalidad', 'categoria', 'tipo_dictacion'] as $campo) {
            if (is_string($request->input($campo))) $request->merge([$campo => Str::squish($request->input($campo)) ?: null]);
        }
        $reglas = ['universidad_id' => ['required', 'integer', 'exists:universidades,id'], 'nombre' => ['required', 'string', 'max:'.($tipo === 'periodos' ? 50 : 200)], 'estado' => ['required', 'boolean']];
        if ($tipo === 'asignaturas') {
            $reglas += ['codigo' => ['required', 'string', 'max:30'], 'creditos' => ['nullable', 'integer', 'between:0,255'],
                'es_libre_eleccion' => ['required', 'boolean'], 'ramo_terminal' => ['required', 'boolean']];
            foreach (self::MODULOS as $campo => $etiqueta) $reglas[$campo] = ['required', 'integer', 'between:0,255'];
            foreach (['modalidad', 'categoria', 'tipo_dictacion'] as $campo) $reglas[$campo] = ['nullable', 'string', 'max:50'];
        } else {
            $reglas += ['anio' => ['required', 'integer', 'between:1900,2200'], 'numero_semestre' => ['required', 'integer', Rule::in([1, 2])],
                'fecha_inicio' => ['nullable', 'date_format:Y-m-d'], 'fecha_fin' => ['nullable', 'date_format:Y-m-d'], 'confirmar_archivo' => ['nullable', 'boolean']];
            if (is_string($request->input('fecha_inicio')) && $request->filled('fecha_inicio')) $reglas['fecha_fin'][] = 'after_or_equal:fecha_inicio';
        }
        $datos = $request->validate($reglas, ['nombre.required' => 'Indica el nombre.', 'codigo.required' => 'Indica el código del ramo.', 'fecha_fin.after_or_equal' => 'La fecha de término debe ser igual o posterior al inicio.']);
        $registro = DB::transaction(function () use ($request, $tipo, $modelo, $id, $datos, $singular) {
            $universidad = Models\Universidad::lockForUpdate()->findOrFail($datos['universidad_id']);
            $registro = $id ? $modelo::lockForUpdate()->findOrFail($id) : new $modelo;
            if ($id && (int) $registro->universidad_id !== (int) $universidad->id) $this->rechazar('universidad_id', 'La institución de un registro existente no puede cambiarse.');
            if ((! $id || ($datos['estado'] && ! $registro->estado)) && ! $universidad->estado) $this->rechazar('universidad_id', 'Activa primero la institución.');
            $otros = fn () => $modelo::where('universidad_id', $universidad->id)->when($id, fn ($q) => $q->whereKeyNot($id));
            if ($tipo === 'asignaturas') {
                if ((! $id || $datos['codigo'] !== $registro->codigo) && $otros()->whereRaw('LOWER(TRIM(codigo)) = ?', [mb_strtolower($datos['codigo'])])->exists()) $this->rechazar('codigo', 'Ya existe este código de ramo en la institución, incluso entre los inactivos.');
                $conReferencias = $id && ($registro->secciones()->exists() || $registro->asignaturasDocentes()->exists() || $registro->candidatosReemplazo()->exists() || DB::table('prioridades_recursos')->where('asignatura_id', $id)->exists());
                if ($conReferencias && $datos['codigo'] !== $registro->codigo) $this->rechazar('codigo', 'Este ramo tiene registros vinculados. Conserva el código para mantener su identidad y las futuras importaciones.');
                if ($id && ! $datos['estado'] && $registro->secciones()->where('estado_activo', true)->whereHas('periodoAcademico', fn ($p) => $p->where('estado', true))->exists()) $this->rechazar('estado', 'El ramo tiene secciones activas en un período vigente.');
            } else {
                if ((! $id || $datos['nombre'] !== $registro->nombre) && $otros()->whereRaw('LOWER(TRIM(nombre)) = ?', [mb_strtolower($datos['nombre'])])->exists()) $this->rechazar('nombre', 'Ya existe un período con este nombre en la institución.');
                if ((! $id || (int) $datos['anio'] !== (int) $registro->anio || (int) $datos['numero_semestre'] !== (int) $registro->numero_semestre)
                    && $otros()->where('anio', $datos['anio'])->where('numero_semestre', $datos['numero_semestre'])->exists()) $this->rechazar('numero_semestre', 'Ya existe este año y semestre en la institución, incluso entre los inactivos.');
                if ($id) {
                    $referencias = $this->referenciasPeriodo($registro);
                    if ($referencias['utilizado'] && ((int) $datos['anio'] !== (int) $registro->anio || (int) $datos['numero_semestre'] !== (int) $registro->numero_semestre)) $this->rechazar('anio', 'Este período tiene información académica vinculada. Conserva su año y semestre.');
                    if ($registro->estado && ! $datos['estado']) {
                        if (! ($datos['confirmar_archivo'] ?? false)) $this->rechazar('confirmar_archivo', 'Confirma el archivo del período antes de desactivarlo.');
                        if ($referencias['versiones_abiertas'] || $referencias['solicitudes_abiertas']) $this->rechazar('estado', 'Resuelve la programación semestral actual y las solicitudes pendientes antes de archivar el período.');
                    }
                }
            }
            unset($datos['confirmar_archivo']);
            $antes = $id ? $registro->getAttributes() : null;
            $registro->fill($datos);
            if ($registro->isDirty() || ! $id) {
                $registro->save();
                DB::table('historial_acciones')->insert(['usuario_id' => $request->user()->id, 'accion' => ($id ? 'Actualizar ' : 'Crear ').$singular,
                    'modulo' => 'Oferta académica', 'tabla_afectada' => $registro->getTable(), 'registro_id' => $registro->id,
                    'descripcion' => 'Gestión académica sin reemplazar secciones, actividades ni versiones.',
                    'datos' => json_encode(['antes' => $antes, 'despues' => $registro->getAttributes()], JSON_THROW_ON_ERROR), 'direccion_ip' => $request->ip(), 'created_at' => now(), 'updated_at' => now()]);
            }
            return $registro;
        }, 3);
        return redirect()->route('administracion.oferta.edit', ['tipo' => $tipo, 'id' => $registro->id])->with('success', 'Registro guardado correctamente.');
    }

    private function referenciasPeriodo(Models\PeriodoAcademico $periodo): array
    {
        $solicitudes = Models\SolicitudDisponibilidad::where('periodo_academico_id', $periodo->id);
        $abiertas = (clone $solicitudes)->where(fn ($s) => $s->where('estado', 'En revisión')->orWhere(fn ($p) => $p->where('estado', 'Pendiente')->where(fn ($f) => $f->whereNull('fecha_limite')->orWhereDate('fecha_limite', '>=', today()))))->exists()
            || Models\SolicitudCambioHorario::where('estado_activo', true)->whereIn('estado', ['Pendiente de respuesta', 'Respondida'])->where('fecha_limite', '>', now())
                ->whereHas('horario.versionPlanificacion', fn ($v) => $v->where('periodo_academico_id', $periodo->id))->exists();
        return ['utilizado' => $periodo->secciones()->exists() || $periodo->versionesPlanificacion()->exists() || $periodo->disponibilidadesProfesores()->exists()
            || $solicitudes->exists() || $periodo->asignaturasDocentes()->exists() || $periodo->candidatosReemplazo()->exists(),
            'versiones_abiertas' => $periodo->versionesPlanificacion()->where('tipo', 'semestral')->where('es_actual', true)->where('estado', '!=', 'Publicado')->count(), 'solicitudes_abiertas' => $abiertas];
    }

    private function catalogo(string $tipo): array
    {
        return match ($tipo) { 'asignaturas' => [Models\Asignatura::class, 'Asignaturas y ramos', 'asignatura'],
            'periodos' => [Models\PeriodoAcademico::class, 'Períodos académicos', 'período académico'], default => abort(404) };
    }
    private function rechazar(string $campo, string $mensaje): never { throw ValidationException::withMessages([$campo => $mensaje]); }
}

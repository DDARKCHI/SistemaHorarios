<?php

namespace App\Http\Controllers;

use App\Models\ActividadSeccion;
use App\Models\PeriodoAcademico;
use App\Models\PrioridadRecurso;
use App\Models\RequerimientoActividad;
use App\Models\Seccion;
use App\Models\TipoSala;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class RequerimientoActividadController extends Controller
{
    public function index(Request $request)
    {
        $validador = Validator::make($request->query(), [
            'periodo_id' => ['nullable', 'integer', 'min:1', 'exists:periodos_academicos,id'],
            'q' => ['nullable', 'string', 'max:120'], 'page' => ['nullable', 'integer', 'min:1'],
        ]);
        if ($validador->fails()) return redirect()->route('programacion.requerimientos.index')->withErrors($validador);
        $datos = $validador->validated();
        $periodos = PeriodoAcademico::orderByDesc('anio')->orderByDesc('numero_semestre')->orderByDesc('id')->get();
        $periodoId = $datos['periodo_id'] ?? $periodos->first()?->id;
        $contexto = $this->contexto($request, $periodoId);
        if ($contexto === null) return redirect()->route('programacion.requerimientos.index', ['periodo_id' => $periodoId])->withErrors(['version_id' => 'Selecciona una versión semestral del período elegido.']);
        $q = trim($datos['q'] ?? '');
        $consulta = ActividadSeccion::whereHas('seccion', fn ($query) => $query->where('periodo_academico_id', $periodoId))
            ->with(['seccion.asignatura', 'seccion.periodoAcademico', 'requerimientos' => fn ($query) => $query->orderByDesc('estado')->orderBy('id'),
                'requerimientos.tipoSala', 'requerimientos.prioridadRecurso']);
        if ($q !== '') {
            $literal = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $q).'%';
            $consulta->where(fn ($query) => $query->whereRaw("tipo LIKE ? ESCAPE '!'", [$literal])
                ->orWhereHas('seccion', fn ($s) => $s->whereRaw("codigo LIKE ? ESCAPE '!'", [$literal])
                    ->orWhereHas('asignatura', fn ($a) => $a->whereRaw("codigo LIKE ? ESCAPE '!'", [$literal])->orWhereRaw("nombre LIKE ? ESCAPE '!'", [$literal]))));
        }
        $actividades = $consulta->orderBy('seccion_id')->orderBy('id')->paginate(10)->appends($contexto + array_filter(['q' => $q], fn ($valor) => $valor !== ''));
        if ($actividades->currentPage() > $actividades->lastPage()) return redirect()->to($actividades->url($actividades->lastPage()));
        return view('programacion.requerimientos.index', compact('periodos', 'periodoId', 'q', 'contexto', 'actividades'));
    }

    public function create(Request $request, ActividadSeccion $actividad)
    {
        return $this->formulario($request, $actividad, new RequerimientoActividad(['tipo_requerimiento' => 'Sala', 'cantidad' => 1, 'estado' => true, 'obligatorio' => true]));
    }

    public function edit(Request $request, ActividadSeccion $actividad, RequerimientoActividad $requerimiento)
    {
        return $this->formulario($request, $actividad, $requerimiento);
    }

    public function store(Request $request, ActividadSeccion $actividad)
    {
        return $this->guardar($request, $actividad, new RequerimientoActividad);
    }

    public function update(Request $request, ActividadSeccion $actividad, RequerimientoActividad $requerimiento)
    {
        return $this->guardar($request, $actividad, $requerimiento);
    }

    private function formulario(Request $request, ActividadSeccion $actividad, RequerimientoActividad $requerimiento)
    {
        $actividad->load('seccion.asignatura', 'seccion.periodoAcademico');
        $this->exigirActiva($actividad);
        $contexto = $this->contexto($request, $actividad->seccion->periodo_academico_id);
        if ($contexto === null) return redirect()->route('programacion.requerimientos.index', ['periodo_id' => $actividad->seccion->periodo_academico_id])->withErrors(['version_id' => 'Selecciona una versión semestral del período elegido.']);
        $tipos = RequerimientoActividad::TIPOS;
        $actual = $requerimiento->tipo_requerimiento;
        if ($actual && ! isset($tipos[$actual])) $tipos[$actual] = $actual.' (tipo anterior)';
        $tiposSala = TipoSala::where('estado', true)->when($requerimiento->tipo_sala_id, fn ($q) => $q->orWhere('id', $requerimiento->tipo_sala_id))->orderBy('nombre')->get();
        $prioridades = PrioridadRecurso::where('estado', true)->when($requerimiento->prioridad_recurso_id, fn ($q) => $q->orWhere('id', $requerimiento->prioridad_recurso_id))->orderBy('nivel')->orderBy('nombre')->get();
        return view('programacion.requerimientos.formulario', compact('actividad', 'requerimiento', 'tipos', 'tiposSala', 'prioridades', 'contexto'));
    }

    private function guardar(Request $request, ActividadSeccion $actividad, RequerimientoActividad $requerimiento)
    {
        $nuevo = ! $requerimiento->exists;
        $contexto = DB::transaction(function () use ($request, $actividad, $requerimiento, $nuevo) {
            $actividad = ActividadSeccion::lockForUpdate()->findOrFail($actividad->id);
            $seccion = Seccion::lockForUpdate()->findOrFail($actividad->seccion_id);
            $periodo = PeriodoAcademico::lockForUpdate()->findOrFail($seccion->periodo_academico_id);
            $seccion->setRelation('periodoAcademico', $periodo); $actividad->setRelation('seccion', $seccion);
            $this->exigirActiva($actividad);
            $requerimiento = $nuevo ? new RequerimientoActividad : $actividad->requerimientos()->lockForUpdate()->findOrFail($requerimiento->id);
            $tipos = array_keys(RequerimientoActividad::TIPOS);
            if (! $nuevo) { $tipos[] = $requerimiento->tipo_requerimiento; $tipos[] = trim($requerimiento->tipo_requerimiento); }
            $tipo = $request->input('tipo_requerimiento');
            $normalizado = is_string($tipo) ? RequerimientoActividad::tipoNormalizado($tipo) : null;
            $datos = $request->validate([
                'tipo_requerimiento' => ['required', 'string', Rule::in($tipos)],
                'tipo_sala_id' => ['nullable', Rule::requiredIf($normalizado === 'Laboratorio móvil'), 'integer', Rule::exists('tipos_sala', 'id')],
                'prioridad_recurso_id' => ['nullable', 'integer', Rule::exists('prioridades_recursos', 'id')],
                'nombre_recurso' => [Rule::requiredIf($normalizado === 'Equipamiento'), 'nullable', 'string', 'max:100'],
                'cantidad' => ['required', 'integer', 'min:1', 'max:65535'], 'capacidad_minima' => ['nullable', 'integer', 'min:1', 'max:65535'],
                'obligatorio' => ['required', 'boolean'], 'estado' => ['required', 'boolean'], 'observaciones' => ['nullable', 'string', 'max:2000'],
            ], [
                'tipo_requerimiento.*' => 'Selecciona un tipo de requerimiento disponible.', 'tipo_sala_id.*' => 'Selecciona un tipo de sala registrado; es obligatorio para un laboratorio móvil.',
                'prioridad_recurso_id.*' => 'Selecciona una prioridad registrada.', 'nombre_recurso.*' => 'Indica el recurso solicitado (hasta 100 caracteres); es obligatorio para equipamiento.',
                'cantidad.*' => 'La cantidad debe ser un entero entre 1 y 65.535.', 'capacidad_minima.*' => 'La capacidad mínima debe ser un entero entre 1 y 65.535, o quedar vacía.',
                'obligatorio.*' => 'Selecciona si el requerimiento es obligatorio.', 'estado.*' => 'Selecciona un estado válido.', 'observaciones.*' => 'Las observaciones admiten hasta 2.000 caracteres.',
            ]);
            foreach (['tipo_sala_id' => TipoSala::class, 'prioridad_recurso_id' => PrioridadRecurso::class] as $campo => $modelo) {
                if (empty($datos[$campo])) { $datos[$campo] = null; continue; }
                $referencia = $modelo::lockForUpdate()->findOrFail($datos[$campo]);
                if (! $referencia->estado && ($nuevo || (int) $requerimiento->$campo !== (int) $referencia->id)) {
                    throw \Illuminate\Validation\ValidationException::withMessages([$campo => 'Selecciona un registro activo. Al editar puedes conservar el actual.']);
                }
            }
            if ($normalizado === 'Equipamiento' && ! empty($datos['tipo_sala_id'])) {
                throw \Illuminate\Validation\ValidationException::withMessages(['tipo_sala_id' => 'El tipo de sala corresponde a espacios; déjalo vacío para equipamiento.']);
            }
            $contexto = $this->contexto($request, $periodo->id);
            if ($contexto === null) throw \Illuminate\Validation\ValidationException::withMessages(['version_id' => 'La versión de regreso debe ser semestral y pertenecer al período de esta actividad.']);
            $antes = $nuevo ? null : $requerimiento->only($requerimiento->getFillable());
            $datos['tipo_requerimiento'] = $normalizado ?? $datos['tipo_requerimiento'];
            $requerimiento->fill($datos + ['actividad_seccion_id' => $actividad->id]);
            if (! $nuevo && ! $requerimiento->isDirty()) return $contexto;
            $requerimiento->save();
            DB::table('historial_acciones')->insert([
                'usuario_id' => $request->user()->id, 'accion' => $nuevo ? 'Crear requerimiento' : 'Actualizar requerimiento',
                'modulo' => 'Requerimientos de actividades', 'tabla_afectada' => 'requerimientos_actividad', 'registro_id' => $requerimiento->id,
                'descripcion' => 'Configuración de los recursos de una actividad compartida por las versiones de su período.',
                'datos' => json_encode(['periodo_id' => $periodo->id, 'actividad_id' => $actividad->id, 'antes' => $antes, 'despues' => $requerimiento->only($requerimiento->getFillable())], JSON_THROW_ON_ERROR),
                'direccion_ip' => $request->ip(), 'created_at' => now(), 'updated_at' => now(),
            ]);
            return $contexto;
        }, 3);
        return redirect()->route('programacion.requerimientos.index', $contexto)->with('success', $nuevo ? 'Requerimiento registrado correctamente.' : 'Requerimiento actualizado correctamente.');
    }

    private function exigirActiva(ActividadSeccion $actividad): void
    {
        abort_unless($actividad->estado && $actividad->seccion?->estado_activo && $actividad->seccion?->periodoAcademico?->estado,
            403, 'La actividad, la sección y el período deben estar activos para cambiar sus requerimientos.');
    }

    private function contexto(Request $request, ?int $periodoId): ?array
    {
        $validador = Validator::make($request->all(), ['version_id' => ['nullable', 'integer', 'min:1', Rule::exists('versiones_planificacion', 'id')
            ->where(fn ($q) => $q->where('periodo_academico_id', $periodoId)->where('tipo', 'semestral'))]]);
        if ($validador->fails()) return null;
        return array_filter(['periodo_id' => $periodoId, 'version_id' => $validador->validated()['version_id'] ?? null], fn ($valor) => $valor !== null);
    }
}

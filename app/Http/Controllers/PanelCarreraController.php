<?php

namespace App\Http\Controllers;

use App\Models;
use App\Services\AsignacionModulosHorario;
use App\Services\DatosPanelCarrera;
use App\Services\EdicionPanelCarrera;
use App\Services\ExportacionPanelCarrera;
use App\Services\MovimientosPanelCarrera;
use App\Support\PanelCarrera;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PanelCarreraController extends Controller
{
    public function index(Request $request, DatosPanelCarrera $datos)
    {
        $this->autorizar($request);
        $contexto = $this->contexto($request);
        ['version' => $version, 'carrera' => $carrera, 'periodo' => $periodo] = $contexto;
        $panel = $version && $carrera ? $this->panel($datos, $version, $carrera) : null;
        $editable = $version && $carrera && $this->editable($version);
        $puedeAsignarDocentes = $editable && $request->user()->can('asignar profesores');
        $puedeEditarHorarios = $editable && $request->user()->can('asignar horarios');
        $puedeGestionarVersiones = $request->user()->can('generar programacion semestral');
        $docentesDisponibles = $puedeAsignarDocentes
            ? Models\Profesor::where('universidad_id', $periodo->universidad_id)->where('estado', true)->orderBy('apellido')->orderBy('nombre')->get(['id', 'nombre', 'apellido']) : collect();
        $espaciosDisponibles = $puedeEditarHorarios ? $this->espaciosDisponibles($carrera) : [];
        $confirmaciones = collect($panel['confirmaciones'] ?? []);

        return response()->view('programacion.panel-carrera', $contexto + compact('panel', 'editable', 'puedeAsignarDocentes',
            'puedeEditarHorarios', 'puedeGestionarVersiones', 'docentesDisponibles', 'espaciosDisponibles', 'confirmaciones'))
            ->header('Cache-Control', 'private, no-store');
    }

    public function evaluar(Request $request, Models\Horario $horario, MovimientosPanelCarrera $movimientos)
    {
        $this->autorizar($request, 'asignar horarios');
        $this->verificarHorario($horario, $this->carreraId($request));
        abort_unless($this->editable($horario->versionPlanificacion), 409, 'El borrador seleccionado ya no está disponible para edición.');

        return response()->json(['opciones' => $movimientos->evaluar($horario)])->header('Cache-Control', 'private, no-store');
    }

    public function mover(Request $request, Models\Horario $horario, MovimientosPanelCarrera $movimientos)
    {
        $this->autorizar($request, 'asignar horarios');
        $carreraId = $this->carreraId($request);
        $this->verificarHorario($horario, $carreraId);
        $datos = $request->validate([
            'dia_id' => ['required', 'integer', 'min:1'], 'bloque_id' => ['required', 'integer', 'min:1'],
            'dia_origen' => ['required', 'integer', 'min:1'], 'bloque_origen' => ['required', 'integer', 'min:1'],
            'confirmar' => ['sometimes', 'boolean'],
        ]);
        $movimientos->mover($horario, $datos['dia_id'], $datos['bloque_id'], $datos['dia_origen'], $datos['bloque_origen'],
            $request->user()->id, (bool) ($datos['confirmar'] ?? false), $carreraId);

        return response()->json(['mensaje' => 'Horario guardado.']);
    }

    public function asignar(Request $request, Models\GrupoSeccion $grupo, EdicionPanelCarrera $edicion)
    {
        $this->autorizar($request, 'asignar profesores');
        $datos = $request->validate(['profesor_id' => ['required', 'integer', 'min:1']]);
        $version = $this->versionPost($request);
        $carreraId = $this->carreraId($request);
        $edicion->conGrupo($version, $grupo, $carreraId,
            fn ($vigente, $actual) => $edicion->asignarDocente($vigente, $actual, $datos['profesor_id'], $request->user()));

        return $this->volver($request, $version, $carreraId, 'Responsable del grupo guardado.');
    }

    public function confirmar(Request $request, Models\GrupoSeccion $grupo, EdicionPanelCarrera $edicion)
    {
        $this->autorizar($request, 'asignar profesores');
        $datos = $request->validate(['profesor_id' => ['required', 'integer', 'min:1'], 'confirmado' => ['required', 'boolean']]);
        $version = $this->versionPost($request);
        $carreraId = $this->carreraId($request);
        $edicion->conGrupo($version, $grupo, $carreraId,
            fn ($vigente, $actual) => $edicion->confirmar($vigente, $actual, $datos['profesor_id'], (bool) $datos['confirmado'], $request->user()));

        return $this->volver($request, $version, $carreraId, $datos['confirmado'] ? 'Confirmación registrada.' : 'Confirmación retirada.');
    }

    public function agregar(Request $request, Models\GrupoSeccion $grupo, EdicionPanelCarrera $edicion, AsignacionModulosHorario $asignaciones)
    {
        $this->autorizar($request, 'asignar horarios');
        $datos = $request->validate(['dia_id' => ['required', 'integer', 'exists:dias,id'], 'bloque_id' => ['required', 'integer', 'exists:bloques_horarios,id']]);
        $version = $this->versionPost($request);
        $carreraId = $this->carreraId($request);
        $edicion->conGrupo($version, $grupo, $carreraId, function ($vigente, $actual) use ($edicion, $asignaciones, $datos, $request) {
            $docentes = $edicion->docentesPlantilla($vigente);
            if ($docentes->isEmpty()) throw ValidationException::withMessages(['profesor_id' => 'Asigna un docente al grupo antes de agregar un módulo.']);
            $asignaciones->guardar([
                'grupo_seccion_id' => $vigente->id, 'version_id' => $actual->id, 'dia_id' => $datos['dia_id'],
                'bloques_horarios' => [$datos['bloque_id']], 'profesores_ids' => $docentes->pluck('profesor_id')->unique()->all(),
            ], $request->user()->id, $request->ip());
        });

        return $this->volver($request, $version, $carreraId, 'Módulo agregado. Puedes asignarle un espacio desde el grupo.');
    }

    public function quitar(Request $request, Models\Horario $horario, EdicionPanelCarrera $edicion, AsignacionModulosHorario $asignaciones)
    {
        $this->autorizar($request, 'asignar horarios');
        $carreraId = $this->carreraId($request);
        $this->verificarHorario($horario, $carreraId);
        $version = $horario->versionPlanificacion;
        $edicion->conGrupo($version, $horario->grupoSeccion, $carreraId,
            fn () => $asignaciones->eliminar($horario, $request->user()->id, $request->ip()));

        return $this->volver($request, $version, $carreraId, 'Módulo retirado.');
    }

    public function espacio(Request $request, Models\Horario $horario, EdicionPanelCarrera $edicion)
    {
        $this->autorizar($request, 'asignar horarios');
        $datos = $request->validate(['espacio' => ['required', 'string', 'regex:/^(auto|(?:sala|laboratorio|movil):[1-9][0-9]*)$/']]);
        $carreraId = $this->carreraId($request);
        $this->verificarHorario($horario, $carreraId);
        $version = $horario->versionPlanificacion;
        $edicion->conGrupo($version, $horario->grupoSeccion, $carreraId,
            fn ($grupo, $actual, $periodo) => $edicion->espacio($horario, $grupo, $actual, $periodo, $datos['espacio'], $request->user()));

        return $this->volver($request, $version, $carreraId, 'Espacio asignado al módulo.');
    }

    public function exportar(Request $request, DatosPanelCarrera $datos, ExportacionPanelCarrera $excel)
    {
        $this->autorizar($request);
        ['version' => $version, 'carrera' => $carrera] = $this->contexto($request);
        abort_unless($version && $carrera, 404);

        return $excel->descargar($version, $carrera, $this->panel($datos, $version, $carrera));
    }

    private function panel(DatosPanelCarrera $datos, Models\VersionPlanificacion $version, Models\Carrera $carrera): array
    {
        $panel = $datos->consultar($version, $carrera);
        $grupos = collect($panel['secciones'])->flatMap(fn ($seccion) => $seccion['grupos'])->pluck('id');
        $confirmados = DB::table('confirmaciones_grupo_programacion')->where('version_planificacion_id', $version->id)
            ->whereIn('grupo_seccion_id', $grupos)->pluck('profesor_id', 'grupo_seccion_id');
        $horariosPorGrupo = collect($panel['horarios'])->groupBy('grupo_id');
        $plantillas = Models\GrupoSeccion::whereKey($grupos->all())->with([
            'profesores' => fn ($q) => $q->where('estado', true)->orderByDesc('es_principal')->orderBy('id'),
            'profesores.profesor', 'actividadSeccion.seccion.profesores' => fn ($q) => $q->where('estado', true)->orderByDesc('es_principal')->orderBy('id'),
            'actividadSeccion.seccion.profesores.profesor',
        ])->get()->keyBy('id');
        $panel['confirmaciones'] = [];
        $confirmadas = $completas = 0;
        foreach ($panel['secciones'] as &$seccion) {
            foreach ($seccion['grupos'] as &$grupo) {
                $plantilla = $plantillas->get($grupo['id']);
                $principal = $plantilla?->profesores->first() ?? $plantilla?->actividadSeccion?->seccion?->profesores->first();
                $grupo['principal_id'] = $principal?->profesor?->estado ? (int) $principal->profesor_id : null;
                if (! $principal) {
                    $directos = $horariosPorGrupo->get($grupo['id'], collect())
                        ->map(fn ($h) => $h['docentes'][0] ?? null)->unique('id');
                    if ($directos->count() === 1 && ($directos->first()['activo'] ?? false)) $grupo['principal_id'] = $directos->first()['id'];
                }
                $confirmado = $confirmados->get($grupo['id']);
                $grupo['confirmado'] = $version->es_actual && $version->estado === 'Borrador'
                    ? $grupo['principal_id'] !== null && (int) $confirmado === $grupo['principal_id']
                    : $confirmado !== null;
                $panel['confirmaciones'][$grupo['id']] = $grupo['confirmado'];
            }
            unset($grupo);
            $seccion['confirmada'] = ! empty($seccion['grupos']) && collect($seccion['grupos'])->every(fn ($g) => $g['confirmado']);
            $seccion['cerrada'] = $seccion['confirmada'] && $seccion['con_docente'] && $seccion['carga_completa'];
            $confirmadas += (int) $seccion['confirmada'];
            $completas += (int) $seccion['cerrada'];
        }
        unset($seccion);
        $panel['indicadores']['confirmadas'] = $confirmadas;
        $panel['indicadores']['completas'] = $completas;

        return $panel;
    }

    private function contexto(Request $request): array
    {
        $datos = $request->validate([
            'periodo_id' => ['nullable', 'integer', 'min:1'], 'version_id' => ['nullable', 'integer', 'min:1'],
            'carrera_id' => ['nullable', 'integer', 'min:1'], 'pestana' => ['nullable', Rule::in(PanelCarrera::PESTANAS)],
        ]);
        $periodos = Models\PeriodoAcademico::with('universidad')->orderByDesc('anio')->orderByDesc('numero_semestre')->latest('id')->get();
        $periodo = isset($datos['periodo_id']) ? $periodos->firstWhere('id', $datos['periodo_id'])
            : ($periodos->firstWhere('estado', true) ?? $periodos->first());
        abort_if(isset($datos['periodo_id']) && ! $periodo, 404);
        $versiones = Models\VersionPlanificacion::where('periodo_academico_id', $periodo?->id)->where('tipo', 'semestral')->latest('id')->get();
        $version = isset($datos['version_id']) ? $versiones->firstWhere('id', $datos['version_id']) : null;
        abort_if($request->routeIs('programacion.panel-carrera.exportar') && isset($datos['version_id']) && ! $version, 404);
        $version ??= $versiones->firstWhere('es_actual', true) ?? $versiones->first();
        if ($version && $periodo) $version->setRelation('periodoAcademico', $periodo);
        $carreras = Models\Carrera::where('universidad_id', $periodo?->universidad_id)
            ->whereHas('secciones', fn ($q) => $q->where('periodo_academico_id', $periodo?->id))->orderBy('nombre')->get();
        $carrera = isset($datos['carrera_id']) ? $carreras->firstWhere('id', $datos['carrera_id']) : null;
        abort_if($request->routeIs('programacion.panel-carrera.exportar') && isset($datos['carrera_id']) && ! $carrera, 404);
        $carrera ??= $carreras->first();
        $pestana = $datos['pestana'] ?? 'horario';
        $filtros = array_filter(['periodo_id' => $periodo?->id, 'version_id' => $version?->id, 'carrera_id' => $carrera?->id]);

        return compact('periodos', 'periodo', 'versiones', 'version', 'carreras', 'carrera', 'pestana', 'filtros');
    }

    private function espaciosDisponibles(Models\Carrera $carrera): array
    {
        $sedes = $carrera->sedes()->pluck('sedes.id')->push($carrera->sede_id)->filter()->unique()->values();
        $salas = Models\Sala::where('universidad_id', $carrera->universidad_id)->whereIn('sede_id', $sedes)
            ->where('estado', true)->where('disponible', true)->with('sede')->orderBy('codigo')->get();
        $laboratorios = Models\Laboratorio::whereIn('sala_id', $salas->modelKeys())->where('estado', true)->where('disponible', true)->orderBy('nombre')->get();
        $moviles = Models\LaboratorioMovil::where('universidad_id', $carrera->universidad_id)->whereIn('sede_id', $sedes)
            ->where('estado', true)->where('disponible', true)->with('sede')->orderBy('nombre')->get();

        return ['auto' => 'Buscar espacio libre compatible']
            + $salas->mapWithKeys(fn ($s) => ['sala:'.$s->id => $s->codigo.' · '.$s->sede?->nombre.' · '.$s->capacidad.' plazas'])->all()
            + $laboratorios->mapWithKeys(fn ($l) => ['laboratorio:'.$l->id => $l->nombre.' · '.$salas->find($l->sala_id)?->sede?->nombre.' · '.$l->capacidad.' plazas'])->all()
            + $moviles->mapWithKeys(fn ($m) => ['movil:'.$m->id => $m->nombre.' · Móvil · '.$m->sede?->nombre.' · '.$m->capacidad.' plazas'])->all();
    }

    private function carreraId(Request $request): int
    {
        return (int) $request->validate(['carrera_id' => ['required', 'integer', 'min:1']])['carrera_id'];
    }

    private function versionPost(Request $request): Models\VersionPlanificacion
    {
        return Models\VersionPlanificacion::findOrFail($request->validate(['version_id' => ['required', 'integer', 'min:1']])['version_id']);
    }

    private function verificarHorario(Models\Horario $horario, int $carreraId): void
    {
        $version = $horario->versionPlanificacion;
        $seccion = $horario->actividadSeccion?->seccion;
        abort_unless($horario->estado && $horario->grupoSeccion && $seccion && $version?->tipo === 'semestral'
            && (int) $horario->grupoSeccion->actividad_seccion_id === (int) $horario->actividad_seccion_id
            && (int) $seccion->periodo_academico_id === (int) $version->periodo_academico_id
            && $seccion->carreras()->whereKey($carreraId)->where('carreras.universidad_id', $version->periodoAcademico->universidad_id)->exists(), 404);
    }

    private function editable(Models\VersionPlanificacion $version): bool
    {
        return $version->es_actual && $version->estado === 'Borrador' && $version->periodoAcademico->estado && $version->periodoAcademico->universidad?->estado;
    }

    private function volver(Request $request, Models\VersionPlanificacion $version, int $carreraId, string $mensaje)
    {
        $pestana = $request->input('pestana');

        return redirect()->route('programacion.panel-carrera', [
            'periodo_id' => $version->periodo_academico_id, 'version_id' => $version->id, 'carrera_id' => $carreraId,
            'pestana' => in_array($pestana, PanelCarrera::PESTANAS, true) ? $pestana : 'horario',
        ])->with('success', $mensaje);
    }

    private function autorizar(Request $request, ?string $permiso = null): void
    {
        abort_unless(PanelCarrera::visible($request->user()) && (! $permiso || $request->user()->can($permiso)), 403);
    }
}

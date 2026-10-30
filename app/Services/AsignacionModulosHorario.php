<?php

namespace App\Services;

use App\Models;
use App\Support\IntervalosHorario;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AsignacionModulosHorario
{
    public function guardar(array $datos, int $usuarioId, ?string $ip): array
    {
        $referencia = Models\GrupoSeccion::with('actividadSeccion.seccion')->findOrFail($datos['grupo_seccion_id']);
        $periodoId = $referencia->actividadSeccion->seccion->periodo_academico_id;
        return DB::transaction(function () use ($datos, $usuarioId, $ip, $periodoId) {
            // Iniciar con una lectura bloqueante evita fijar una vista anterior a otra asignación concurrente.
            $periodo = Models\PeriodoAcademico::lockForUpdate()->findOrFail($periodoId);
            $version = $this->versionEditable($periodo, (int) $datos['version_id']);
            // El bloqueo docente también se usa al modificar su propuesta o aplicar un reemplazo.
            $profesoresIds = collect($datos['profesores_ids'])->map(fn ($id) => (int) $id)->sort()->values();
            $profesores = Models\Profesor::whereIn('id', $profesoresIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $grupo = Models\GrupoSeccion::lockForUpdate()->findOrFail($datos['grupo_seccion_id']);
            $actividad = Models\ActividadSeccion::lockForUpdate()->findOrFail($grupo->actividad_seccion_id);
            $seccion = Models\Seccion::lockForUpdate()->findOrFail($actividad->seccion_id);
            if (! $grupo->estado_activo || ! $actividad->estado || ! $seccion->estado_activo
                || (int) $seccion->periodo_academico_id !== (int) $periodo->id) {
                $this->rechazar('horario', 'El grupo, la actividad o la sección ya no están activos en este período. Actualiza la página.');
            }
            $asignaciones = Models\GrupoProfesor::where('grupo_seccion_id', $grupo->id)->where('estado', true)
                ->whereIn('profesor_id', $profesoresIds)->orderBy('id')->lockForUpdate()->get()->unique('profesor_id');
            if ($asignaciones->count() !== $profesoresIds->count() || $profesores->count() !== $profesoresIds->count()
                || $profesores->contains(fn ($profesor) => ! $profesor->estado || (int) $profesor->universidad_id !== (int) $periodo->universidad_id)) {
                $this->rechazar('profesores_ids', 'La asignación de profesores del grupo cambió o contiene docentes inactivos o de otra institución. Revisa los docentes antes de guardar.');
            }
            $dia = Models\Dia::lockForUpdate()->findOrFail($datos['dia_id']);
            $bloques = Models\BloqueHorario::whereIn('id', $datos['bloques_horarios'])->orderBy('id')->lockForUpdate()->get();
            if (! $dia->estado || $bloques->count() !== count($datos['bloques_horarios']) || $bloques->contains(fn ($bloque) => ! $bloque->estado)) {
                $this->rechazar('bloques_horarios', 'Selecciona un día y bloques horarios que estén activos. Actualiza la página.');
            }
            $rangos = [];
            foreach ($bloques as $bloque) {
                $rango = IntervalosHorario::deBloque($bloque);
                if (! $rango) $this->rechazar('bloques_horarios', 'El módulo '.$bloque->numero.' tiene un intervalo inválido. Revisa sus horas antes de asignarlo.');
                foreach ($rangos as $anterior) if (IntervalosHorario::seSuperponen($rango, $anterior)) {
                    $this->rechazar('bloques_horarios', 'Los módulos seleccionados se superponen. Elige intervalos que no coincidan entre sí.');
                }
                $rangos[$bloque->id] = $rango;
            }
            $asignados = Models\Horario::where('version_planificacion_id', $version->id)->where('grupo_seccion_id', $grupo->id)->where('estado', true)->count();
            $maximo = (int) $actividad->cantidad_horas;
            if ($maximo <= 0 || $asignados + $bloques->count() > $maximo) {
                $this->rechazar('horario', 'La actividad permite '.$maximo.' módulos. Tiene '.$asignados.' asignados y puedes agregar '.max(0, $maximo - $asignados).'. Revisa la carga antes de guardar.');
            }
            $existentes = Models\Horario::where('version_planificacion_id', $version->id)->where('dia_id', $dia->id)->where('estado', true)
                ->with(['bloqueHorario', 'actividadSeccion.seccion', 'profesores' => fn ($q) => $q->where('estado', true),
                    'grupoSeccion.profesores' => fn ($q) => $q->where('estado', true), 'actividadSeccion.seccion.profesores' => fn ($q) => $q->where('estado', true)])
                ->orderBy('id')->get();
            foreach ($existentes as $existente) {
                $docentes = $existente->profesores;
                if ($docentes->isEmpty()) $docentes = $existente->grupoSeccion?->profesores ?? collect();
                if ($docentes->isEmpty()) $docentes = $existente->actividadSeccion?->seccion?->profesores ?? collect();
                $compartidos = $profesoresIds->intersect($docentes->pluck('profesor_id'));
                $mismaSeccion = (int) $existente->actividadSeccion?->seccion_id === (int) $seccion->id;
                if (! $mismaSeccion && $compartidos->isEmpty()) continue;
                $rango = IntervalosHorario::deBloque($existente->bloqueHorario);
                if (! $rango) $this->rechazar('horario', 'El horario #'.$existente->id.' de esta sección o docente tiene horas inválidas. Revísalo antes de agregar módulos en este día.');
                foreach ($rangos as $objetivo) if (IntervalosHorario::seSuperponen($rango, $objetivo)) {
                    $afectado = $mismaSeccion ? 'La sección' : 'El docente '.trim($profesores->get($compartidos->first())->nombre.' '.$profesores->get($compartidos->first())->apellido);
                    $this->rechazar('horario', $afectado.' ya tiene el horario #'.$existente->id.' el '.$dia->nombre.' entre '.substr($existente->bloqueHorario->hora_inicio, 0, 8).' y '.substr($existente->bloqueHorario->hora_fin, 0, 8).'. Los intervalos no pueden superponerse.');
                }
            }
            $declaradas = Models\DisponibilidadProfesor::where('periodo_academico_id', $periodo->id)->whereIn('profesor_id', $profesoresIds)
                ->where('estado', true)->with('bloqueHorario')->get()->groupBy('profesor_id');
            $sinDeclaracion = [];
            foreach ($profesores as $profesor) {
                $disponibilidad = $declaradas->get($profesor->id, collect());
                if ($disponibilidad->isEmpty()) { $sinDeclaracion[] = $profesor->id; continue; }
                foreach ($rangos as $objetivo) {
                    $resultado = IntervalosHorario::coberturaDisponibilidad($objetivo, $disponibilidad->where('dia_id', $dia->id));
                    if ($resultado !== 'cubierto') {
                        $this->rechazar('horario', trim($profesor->nombre.' '.$profesor->apellido).': '.($resultado === 'datos'
                            ? 'la disponibilidad de este día contiene datos incompletos o no reconocidos; revísala antes de asignar.'
                            : 'los módulos elegidos quedan fuera de su disponibilidad registrada o coinciden con un bloque no disponible.'));
                    }
                }
            }
            $horarios = [];
            foreach ($bloques as $bloque) {
                $horario = Models\Horario::create(['actividad_seccion_id' => $actividad->id, 'grupo_seccion_id' => $grupo->id,
                    'version_planificacion_id' => $version->id, 'dia_id' => $dia->id, 'bloque_horario_id' => $bloque->id,
                    'observaciones' => $datos['observaciones'] ?? null, 'estado' => true]);
                foreach ($asignaciones as $asignacion) Models\HorarioProfesor::create(['horario_id' => $horario->id, 'profesor_id' => $asignacion->profesor_id,
                    'tipo_participacion' => $asignacion->tipo_participacion, 'es_principal' => $asignacion->es_principal, 'observaciones' => $asignacion->observaciones, 'estado' => true]);
                $horarios[] = $horario->id;
            }
            $this->auditar('Asignar módulos', $horarios[0], $usuarioId, $ip, ['periodo_id' => $periodo->id, 'version_id' => $version->id,
                'grupo_id' => $grupo->id, 'dia_id' => $dia->id, 'horarios_ids' => $horarios, 'profesores_ids' => $profesoresIds->all(),
                'profesores_sin_declaracion' => $sinDeclaracion, 'bloques' => $bloques->map->only(['id', 'numero', 'hora_inicio', 'hora_fin'])->all()]);
            return ['periodo_id' => $periodo->id, 'seccion_id' => $seccion->id, 'cantidad' => count($horarios)];
        }, 3);
    }

    public function eliminar(Models\Horario $horario, int $usuarioId, ?string $ip): array
    {
        $periodoId = $horario->versionPlanificacion->periodo_academico_id;
        return DB::transaction(function () use ($horario, $usuarioId, $ip, $periodoId) {
            $periodo = Models\PeriodoAcademico::lockForUpdate()->findOrFail($periodoId);
            $version = $this->versionEditable($periodo, (int) $horario->version_planificacion_id);
            $actual = Models\Horario::lockForUpdate()->findOrFail($horario->id);
            $grupo = Models\GrupoSeccion::lockForUpdate()->findOrFail($actual->grupo_seccion_id);
            $seccion = $grupo->actividadSeccion->seccion;
            if ((int) $actual->version_planificacion_id !== (int) $version->id || (int) $seccion->periodo_academico_id !== (int) $periodo->id) {
                $this->rechazar('horario', 'El horario cambió de versión o período. Actualiza la página antes de eliminarlo.');
            }
            $antes = $actual->load('profesores', 'asignacionesSalas', 'asignacionesLaboratorios', 'asignacionesLaboratoriosMoviles')->toArray();
            $actual->delete();
            $this->auditar('Eliminar módulo', $actual->id, $usuarioId, $ip, ['periodo_id' => $periodo->id, 'version_id' => $version->id, 'antes' => $antes]);
            return ['periodo_id' => $periodo->id, 'seccion_id' => $seccion->id];
        }, 3);
    }

    private function versionEditable(Models\PeriodoAcademico $periodo, int $versionId): Models\VersionPlanificacion
    {
        $version = Models\VersionPlanificacion::where('periodo_academico_id', $periodo->id)->where('tipo', 'semestral')->where('es_actual', true)
            ->orderByDesc('id')->lockForUpdate()->first();
        if (! $periodo->estado || ! $version || (int) $version->id !== $versionId || $version->estado !== 'Borrador') {
            $this->rechazar('version_id', 'Solo puedes cambiar horarios de la versión semestral actual en borrador de un período activo. Actualiza la página para revisar la versión vigente.');
        }
        return $version;
    }

    private function auditar(string $accion, int $horarioId, int $usuarioId, ?string $ip, array $datos): void
    {
        DB::table('historial_acciones')->insert(['usuario_id' => $usuarioId, 'accion' => $accion, 'modulo' => 'Asignación de horarios',
            'tabla_afectada' => 'horarios', 'registro_id' => $horarioId, 'descripcion' => 'Modificación manual de módulos en la programación semestral.',
            'datos' => json_encode($datos, JSON_THROW_ON_ERROR), 'direccion_ip' => $ip, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function rechazar(string $campo, string $mensaje): never
    {
        throw ValidationException::withMessages([$campo => $mensaje]);
    }
}

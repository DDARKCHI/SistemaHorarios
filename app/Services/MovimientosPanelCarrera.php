<?php

namespace App\Services;

use App\Models;
use App\Support\IntervalosHorario;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MovimientosPanelCarrera
{
    public function evaluar(Models\Horario $horario): array
    {
        $horario = $this->cargar($horario);
        $this->validarOrigen($horario);
        $contexto = $this->contexto($horario);
        $dias = Models\Dia::where('estado', true)->orderBy('numero')->get();
        $bloques = Models\BloqueHorario::where('estado', true)->orderBy('numero')->get();
        $resultado = [];
        foreach ($dias as $dia) {
            foreach ($bloques as $bloque) {
                $resultado[$dia->id.':'.$bloque->id] = $this->evaluarDestino($horario, $dia, $bloque, $contexto);
            }
        }

        return $resultado;
    }

    public function mover(Models\Horario $horario, int $diaId, int $bloqueId, int $diaOrigen, int $bloqueOrigen, int $usuarioId, bool $confirmar = false, ?int $carreraId = null): void
    {
        $versionId = (int) $horario->version_planificacion_id;
        $periodoId = (int) $horario->versionPlanificacion->periodo_academico_id;
        DB::transaction(function () use ($horario, $versionId, $periodoId, $diaId, $bloqueId, $diaOrigen, $bloqueOrigen, $usuarioId, $confirmar, $carreraId) {
            $periodo = Models\PeriodoAcademico::lockForUpdate()->findOrFail($periodoId);
            $version = Models\VersionPlanificacion::lockForUpdate()->findOrFail($versionId);
            // Sigue el orden de bloqueo de publicación y solicitudes de cambio.
            app(ActivacionVersion::class)->bloquearDatos($periodo, $version);
            $vigente = $this->cargar($horario);
            $this->validarOrigen($vigente);
            if ($carreraId !== null && ! $vigente->actividadSeccion->seccion->carreras->contains('id', $carreraId)) {
                $this->rechazar('El bloque ya no pertenece a la carrera seleccionada. Actualiza el panel.');
            }
            if ((int) $vigente->version_planificacion_id !== $versionId || (int) $version->periodo_academico_id !== $periodoId
                || (int) $vigente->dia_id !== $diaOrigen || (int) $vigente->bloque_horario_id !== $bloqueOrigen) {
                $this->rechazar('El bloque cambió mientras lo editabas. Actualiza el horario y vuelve a intentarlo.');
            }

            $dia = Models\Dia::find($diaId);
            $bloque = Models\BloqueHorario::find($bloqueId);
            if (! $dia?->estado || ! $bloque?->estado) {
                $this->rechazar('Elige un día y un módulo activos del calendario.');
            }
            if ($diaId === $diaOrigen && $bloqueId === $bloqueOrigen) return;

            $resultado = $this->evaluarDestino($vigente, $dia, $bloque, $this->contexto($vigente, $diaId));
            if ($resultado['bloqueado']) $this->rechazar(implode(' ', $resultado['motivos']));
            if ($resultado['nivel'] === 'rojo' && ! $confirmar) {
                throw ValidationException::withMessages(['confirmar' => $resultado['motivos']]);
            }

            $vigente->update(['dia_id' => $diaId, 'bloque_horario_id' => $bloqueId]);
            DB::table('historial_acciones')->insert([
                'usuario_id' => $usuarioId,
                'accion' => 'Mover bloque en panel de carrera',
                'modulo' => 'Programación semestral',
                'tabla_afectada' => 'horarios',
                'registro_id' => $vigente->id,
                'descripcion' => 'Cambio de día o módulo en el borrador desde el panel de carrera.',
                'datos' => json_encode([
                    'version_id' => $versionId,
                    'antes' => ['dia_id' => $diaOrigen, 'bloque_horario_id' => $bloqueOrigen],
                    'despues' => ['dia_id' => $diaId, 'bloque_horario_id' => $bloqueId],
                    'revision' => $resultado,
                ], JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }, 3);
    }

    private function cargar(Models\Horario $referencia): Models\Horario
    {
        return Models\Horario::with($this->relaciones() + [
            'versionPlanificacion.periodoAcademico.universidad',
            'actividadSeccion.grupos' => fn ($q) => $q->where('estado_activo', true),
            'actividadSeccion.requerimientos' => fn ($q) => $q->where('estado', true)->where('obligatorio', true),
            'actividadSeccion.requerimientos.tipoSala',
            'actividadSeccion.requerimientos.equipamiento',
            'profesores.profesor', 'grupoSeccion.profesores.profesor', 'actividadSeccion.seccion.profesores.profesor',
            'asignacionesSalas.sala.tipoSala',
            'asignacionesLaboratorios.laboratorio.sala.tipoSala',
            'asignacionesLaboratoriosMoviles.laboratorioMovil',
        ])->findOrFail($referencia->id);
    }

    private function validarOrigen(Models\Horario $horario): void
    {
        $version = $horario->versionPlanificacion;
        $periodo = $version->periodoAcademico;
        $actividad = $horario->actividadSeccion;
        $seccion = $actividad?->seccion;
        $grupo = $horario->grupoSeccion;
        if ($version->tipo !== 'semestral' || $version->estado !== 'Borrador' || ! $version->es_actual || ! $periodo->estado || ! $periodo->universidad?->estado) {
            $this->rechazar('Solo puedes mover bloques en el borrador vigente de un período activo.');
        }
        if (! $horario->estado || ! $grupo?->estado_activo || ! $actividad?->estado || ! $seccion?->estado_activo
            || (int) $grupo->actividad_seccion_id !== (int) $actividad->id
            || (int) $seccion->periodo_academico_id !== (int) $periodo->id) {
            $this->rechazar('El horario, el grupo y la actividad deben estar activos y pertenecer al mismo período.');
        }
        if (Models\SolicitudCambioHorario::where('horario_id', $horario->id)->where('estado_activo', true)
            ->whereIn('estado', ['Pendiente de respuesta', 'Respondida'])->where('fecha_limite', '>', now())->exists()) {
            $this->rechazar('Este bloque tiene una solicitud de cambio abierta. Resuélvela antes de moverlo desde el panel.');
        }
    }

    private function contexto(Models\Horario $horario, ?int $diaId = null): array
    {
        $periodo = $horario->versionPlanificacion->periodoAcademico;
        $docentes = app(AlternativasCambioHorario::class)->docentes($horario);
        $disponibilidad = Models\DisponibilidadProfesor::where('periodo_academico_id', $periodo->id)->where('estado', true)
            ->whereIn('profesor_id', $docentes->pluck('profesor_id'))->with('bloqueHorario')->get()->groupBy('profesor_id');
        $otros = Models\Horario::where('version_planificacion_id', $horario->version_planificacion_id)->whereKeyNot($horario->id)
            ->where('estado', true)->when($diaId, fn ($q) => $q->where('dia_id', $diaId))->with($this->relaciones())->get()->groupBy('dia_id');
        $duplicados = Models\Horario::where('version_planificacion_id', $horario->version_planificacion_id)->whereKeyNot($horario->id)
            ->where('actividad_seccion_id', $horario->actividad_seccion_id)->where('grupo_seccion_id', $horario->grupo_seccion_id)
            ->when($diaId, fn ($q) => $q->where('dia_id', $diaId))->get(['dia_id', 'bloque_horario_id'])
            ->mapWithKeys(fn ($h) => [$h->dia_id.':'.$h->bloque_horario_id => true]);
        $inventario = Models\Equipamiento::where('universidad_id', $periodo->universidad_id)->with('ubicaciones')->get()->keyBy('id');
        $reglas = Models\PrioridadRecurso::where('estado', true)
            ->whereHas('asignatura', fn ($q) => $q->where('universidad_id', $periodo->universidad_id))->with('tipoSala')->get();
        $prioridades = new PrioridadUsoRecursos($reglas, $inventario);
        $recursos = new RevisionRecursosProgramacion((int) $periodo->universidad_id, new RevisionEquipamientoProgramacion($inventario));
        $revision = app(RevisionAsignacionesProgramacion::class)->revisar($horario, $docentes);
        $avisos = array_filter([$revision['asignaciones']['mensaje'] ?? null, $revision['capacidad']['mensaje'] ?? null, $recursos->revisar($horario)['mensaje'] ?? null]);
        if ($docentes->contains(fn ($d) => ! $d->profesor?->estado || (int) $d->profesor->universidad_id !== (int) $periodo->universidad_id)) {
            $avisos[] = 'Revisa los docentes asignados a este módulo y su institución.';
        }
        $esLaboratorio = $horario->asignacionesLaboratorios->isNotEmpty() || $horario->asignacionesLaboratoriosMoviles->isNotEmpty()
            || Models\RequerimientoActividad::tipoNormalizado($horario->actividadSeccion->tipo) === 'Laboratorio'
            || $horario->actividadSeccion->requerimientos->contains(fn ($r) => in_array(Models\RequerimientoActividad::tipoNormalizado($r->tipo_requerimiento), ['Laboratorio', 'Laboratorio móvil'], true));
        $espacios = $this->espacios($horario);

        return compact('docentes', 'disponibilidad', 'otros', 'duplicados', 'avisos', 'esLaboratorio', 'espacios', 'prioridades');
    }

    private function evaluarDestino(Models\Horario $horario, Models\Dia $dia, Models\BloqueHorario $bloque, array $contexto): array
    {
        if ((int) $horario->dia_id === (int) $dia->id && (int) $horario->bloque_horario_id === (int) $bloque->id) {
            return ['nivel' => 'verde', 'motivos' => ['Ubicación actual del bloque.'], 'bloqueado' => true];
        }
        if ($contexto['duplicados']->has($dia->id.':'.$bloque->id)) {
            return $this->destinoBloqueado('Ya existe un bloque del mismo grupo en este día y módulo.');
        }
        $destino = IntervalosHorario::deBloque($bloque);
        $origen = IntervalosHorario::deBloque($horario->bloqueHorario);
        if (! $destino || ! $origen) return $this->destinoBloqueado('Revisa las horas de inicio y término del módulo.');
        if ($destino[1] - $destino[0] !== $origen[1] - $origen[0]) {
            return $this->destinoBloqueado('El destino debe tener la misma duración que el bloque original.');
        }

        $cruces = [];
        $avisos = $contexto['avisos'];
        if ($bloque->es_almuerzo || IntervalosHorario::seSuperponen($destino, [13 * 3600, 14 * 3600])) $avisos[] = 'El destino coincide con el horario de almuerzo.';
        foreach ($contexto['docentes'] as $docente) {
            $nombre = $this->nombreDocente($docente);
            $declarada = $contexto['disponibilidad']->get($docente->profesor_id, collect());
            if ($declarada->isEmpty()) {
                $avisos[] = "$nombre aún no tiene disponibilidad registrada.";
            } elseif (IntervalosHorario::coberturaDisponibilidad($destino, $declarada->where('dia_id', $dia->id)) !== 'cubierto') {
                $avisos[] = "Este bloque queda fuera de los módulos disponibles de $nombre.";
            }
        }
        if ($contexto['esLaboratorio'] && $horario->asignacionesLaboratorios->isEmpty() && $horario->asignacionesLaboratoriosMoviles->isEmpty()) {
            $cruces[] = 'Falta asignar un laboratorio a esta clase.';
        }
        foreach ($contexto['otros']->get($dia->id, collect()) as $otro) {
            $intervalo = IntervalosHorario::deBloque($otro->bloqueHorario);
            if ($intervalo && ! IntervalosHorario::seSuperponen($destino, $intervalo)) continue;
            $docentesOcupados = app(AlternativasCambioHorario::class)->docentes($otro)->pluck('profesor_id');
            $comunes = $contexto['docentes']->whereIn('profesor_id', $docentesOcupados);
            $espacios = array_intersect_key($contexto['espacios'], $this->espacios($otro));
            $cohorte = self::comparteEstudiantes($horario, $otro);
            if (! $intervalo) {
                if ($cohorte || $espacios || $comunes->isNotEmpty()) $avisos[] = 'Otra clase con recursos compartidos tiene un intervalo por revisar.';
                continue;
            }
            $ramo = $otro->actividadSeccion?->seccion?->asignatura?->codigo ?: 'otra clase';
            if ($cohorte) $cruces[] = "Cruce de estudiantes con $ramo.";
            foreach ($comunes as $docente) $cruces[] = $this->nombreDocente($docente)." ya tiene clase en $ramo.";
            foreach ($espacios as $clave => $nombre) {
                $mensaje = "$nombre ya está ocupado por $ramo.";
                if ($contexto['esLaboratorio'] || ! str_starts_with($clave, 'sala:')) $cruces[] = $mensaje;
                else $avisos[] = $mensaje;
                [$tipo, $id] = explode(':', $clave, 2);
                $recurso = ['tipo' => $tipo, 'id' => (int) $id, 'nombre' => $nombre];
                $nivelActual = $contexto['prioridades']->nivel($horario, $recurso);
                $nivelOcupado = $contexto['prioridades']->nivel($otro, $recurso);
                if ($nivelActual !== $nivelOcupado) {
                    $tienePrioridad = ($nivelActual ?? PHP_INT_MAX) < ($nivelOcupado ?? PHP_INT_MAX);
                    $curso = $tienePrioridad ? $horario->actividadSeccion->seccion->asignatura->codigo : $ramo;
                    $nivel = $tienePrioridad ? $nivelActual : $nivelOcupado;
                    $avisos[] = "$curso tiene prioridad de nivel $nivel para $nombre.";
                }
            }
        }

        return ['nivel' => $cruces ? 'rojo' : ($avisos ? 'ambar' : 'verde'),
            'motivos' => array_values(array_unique([...$cruces, ...$avisos])), 'bloqueado' => false];
    }

    public static function comparteEstudiantes(Models\Horario $horario, Models\Horario $otro): bool
    {
        $seccion = $horario->actividadSeccion?->seccion;
        $otraSeccion = $otro->actividadSeccion?->seccion;
        if (! $seccion || ! $otraSeccion) return false;
        if ((int) $seccion->id === (int) $otraSeccion->id) {
            // Dos grupos paralelos de la misma actividad pueden dictarse a la vez.
            return (int) $horario->actividad_seccion_id !== (int) $otro->actividad_seccion_id
                || (int) $horario->grupo_seccion_id === (int) $otro->grupo_seccion_id;
        }

        return $seccion->semestre !== null && $seccion->semestre > 0 && $seccion->semestre === $otraSeccion->semestre
            && (int) $seccion->asignatura_id !== (int) $otraSeccion->asignatura_id
            && $seccion->carreras->pluck('id')->intersect($otraSeccion->carreras->pluck('id'))->isNotEmpty()
            && self::puedenCompartirSede($horario, $otro);
    }

    private static function puedenCompartirSede(Models\Horario $horario, Models\Horario $otro): bool
    {
        $sedes = fn (Models\Horario $h) => $h->asignacionesSalas->pluck('sala.sede_id')
            ->concat($h->asignacionesLaboratorios->pluck('laboratorio.sala.sede_id'))->filter();
        $primera = $sedes($horario);
        $segunda = $sedes($otro);

        return $primera->isEmpty() || $segunda->isEmpty() || $primera->intersect($segunda)->isNotEmpty();
    }

    private function espacios(Models\Horario $horario): array
    {
        $espacios = [];
        foreach ($horario->asignacionesSalas as $asignacion) {
            $espacios['sala:'.$asignacion->sala_id] = 'Sala '.($asignacion->sala?->codigo ?? '#'.$asignacion->sala_id);
        }
        foreach ($horario->asignacionesLaboratorios as $asignacion) {
            $laboratorio = $asignacion->laboratorio;
            $espacios['laboratorio:'.$asignacion->laboratorio_id] = $laboratorio?->nombre ?: 'Laboratorio #'.$asignacion->laboratorio_id;
            if ($laboratorio?->sala_id) $espacios['sala:'.$laboratorio->sala_id] = 'Sala '.($laboratorio->sala?->codigo ?? '#'.$laboratorio->sala_id);
        }
        foreach ($horario->asignacionesLaboratoriosMoviles as $asignacion) {
            $espacios['movil:'.$asignacion->laboratorio_movil_id] = $asignacion->laboratorioMovil?->nombre ?: 'Laboratorio móvil #'.$asignacion->laboratorio_movil_id;
        }

        return $espacios;
    }

    private function nombreDocente($asignacion): string
    {
        $docente = $asignacion->profesor;
        return trim(($docente?->nombre ?? '').' '.($docente?->apellido ?? '')) ?: 'Docente #'.$asignacion->profesor_id;
    }

    private function relaciones(): array
    {
        return [
            'bloqueHorario', 'actividadSeccion.seccion.asignatura', 'actividadSeccion.seccion.carreras', 'grupoSeccion',
            'profesores' => fn ($q) => $q->where('estado', true),
            'grupoSeccion.profesores' => fn ($q) => $q->where('estado', true),
            'actividadSeccion.seccion.profesores' => fn ($q) => $q->where('estado', true),
            'asignacionesSalas' => fn ($q) => $q->where('estado', true), 'asignacionesSalas.sala',
            'asignacionesLaboratorios' => fn ($q) => $q->where('estado', true), 'asignacionesLaboratorios.laboratorio.sala',
            'asignacionesLaboratoriosMoviles' => fn ($q) => $q->where('estado', true), 'asignacionesLaboratoriosMoviles.laboratorioMovil',
        ];
    }

    private function destinoBloqueado(string $motivo): array
    {
        return ['nivel' => 'rojo', 'motivos' => [$motivo], 'bloqueado' => true];
    }

    private function rechazar(string $mensaje): never
    {
        throw ValidationException::withMessages(['movimiento' => $mensaje]);
    }
}

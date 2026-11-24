<?php

namespace App\Services;

use App\Models;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SolicitudesCambio
{
    public function crear(Models\Horario $horario, array $datos, int $usuarioId, ?string $ip): Models\SolicitudCambioHorario
    {
        return DB::transaction(function () use ($horario, $datos, $usuarioId, $ip) {
            $this->bloquearHorario($horario);
            $contexto = app(AlternativasCambioHorario::class)->preparar($horario, $datos['espacio'] ?? 'actual');
            if (! hash_equals($contexto['firma'], $datos['firma'])) $this->rechazar('La clase cambió desde la revisión. Actualiza las alternativas antes de crear la solicitud.');
            if (Models\SolicitudCambioHorario::where('horario_id', $horario->id)->where('estado_activo', true)->whereIn('estado', ['Pendiente de respuesta', 'Respondida'])->where('fecha_limite', '>', now())->exists()) $this->rechazar('Esta clase ya tiene una solicitud abierta. Revísala o cancélala antes de crear otra.');
            $opciones = [];
            foreach ($datos['opciones'] as $clave) {
                if (! isset($contexto['opciones'][$clave])) $this->rechazar('Una alternativa ya no está disponible. Actualiza la búsqueda.');
                $opciones[] = $contexto['opciones'][$clave];
            }
            $solicitud = Models\SolicitudCambioHorario::create(['horario_id' => $horario->id, 'profesor_id' => $contexto['docentes']->first()->profesor_id,
                'usuario_id' => $usuarioId, 'motivo' => $datos['motivo'], 'descripcion' => $datos['descripcion'] ?? null, 'estado' => 'Pendiente de respuesta',
                'fecha_solicitud' => now(), 'fecha_limite' => $datos['fecha_limite'].' 23:59:59', 'firma_origen' => $contexto['firma'],
                'origen' => ['resumen' => $contexto['resumen'], 'actual' => $contexto['actual'], 'version_id' => $contexto['version']->id, 'periodo_id' => $contexto['periodo']->id,
                    'sin_declaracion' => $contexto['sinDeclaracion'], 'espacio_destino' => $contexto['espacio'],
                    'espacio_propuesto' => $contexto['destino']['nombre']], 'estado_activo' => true]);
            foreach ($opciones as $indice => $o) $solicitud->opciones()->create(['dia_id' => $o['dia_id'], 'bloque_horario_id' => $o['bloque_horario_id'], 'prioridad' => $indice + 1,
                'datos' => $o, 'estado' => 'Propuesta']);
            foreach ($contexto['docentes'] as $d) $solicitud->respuestasDocentes()->create(['profesor_id' => $d->profesor_id, 'estado' => 'Pendiente']);
            $this->auditar($solicitud, 'Crear solicitud de cambio', $usuarioId, $ip, ['origen' => $solicitud->origen, 'opciones' => $opciones]);
            return $solicitud;
        }, 3);
    }

    public function responder(Models\RespuestaSolicitudCambio $referencia, array $datos, ?string $ip): void
    {
        DB::transaction(function () use ($referencia, $datos, $ip) {
            $solicitud = Models\SolicitudCambioHorario::lockForUpdate()->findOrFail($referencia->solicitud_cambio_horario_id);
            $respuesta = Models\RespuestaSolicitudCambio::lockForUpdate()->findOrFail($referencia->id);
            abort_unless($this->abierta($solicitud) && $solicitud->estado === 'Pendiente de respuesta' && $respuesta->estado === 'Pendiente' && $respuesta->profesor?->estado, 410, 'Esta solicitud ya no admite respuestas.');
            $opcion = null;
            if ($datos['decision'] === 'aceptar') {
                $opcion = $solicitud->opciones()->where('estado', 'Propuesta')->find($datos['opcion_id'] ?? null);
                if (! $opcion) $this->rechazar('Selecciona una alternativa de esta solicitud.');
            }
            $respuesta->update(['estado' => $opcion ? 'Aceptada' : 'Rechazada', 'opcion_id' => $opcion?->id, 'observaciones' => $datos['observaciones'] ?? null, 'respondido_en' => now()]);
            if (! $opcion) $solicitud->update(['estado' => 'Rechazada', 'estado_activo' => false, 'fecha_respuesta' => now(), 'respuesta' => 'Un docente rechazó las alternativas propuestas.']);
            elseif (! $solicitud->respuestasDocentes()->where('estado', 'Pendiente')->exists()) $solicitud->update(['estado' => 'Respondida', 'fecha_respuesta' => now()]);
            $this->auditar($solicitud, 'Responder solicitud de cambio', null, $ip, ['docente_id' => $respuesta->profesor_id, 'opcion_id' => $opcion?->id, 'respuesta' => $respuesta->estado]);
        }, 3);
    }

    public function revisar(Models\SolicitudCambioHorario $referencia, array $datos, int $usuarioId, ?string $ip): void
    {
        DB::transaction(function () use ($referencia, $datos, $usuarioId, $ip) {
            $horario = Models\Horario::findOrFail($referencia->horario_id);
            $this->bloquearHorario($horario);
            $solicitud = Models\SolicitudCambioHorario::lockForUpdate()->findOrFail($referencia->id);
            if (! $solicitud->estado_activo || ! in_array($solicitud->estado, ['Pendiente de respuesta', 'Respondida'], true)) $this->rechazar('La solicitud ya fue cerrada.');
            if ($datos['decision'] !== 'aplicar') {
                $solicitud->update(['estado' => $datos['decision'] === 'cancelar' ? 'Cancelada' : 'Rechazada', 'estado_activo' => false, 'revisado_por' => $usuarioId,
                    'revisado_en' => now(), 'motivo_revision' => $datos['motivo']]);
                $this->auditar($solicitud, 'Cerrar solicitud de cambio', $usuarioId, $ip, ['estado' => $solicitud->estado, 'motivo' => $datos['motivo']]);
                return;
            }
            if (! $this->abierta($solicitud) || $solicitud->estado !== 'Respondida') $this->rechazar('Faltan respuestas o la solicitud está vencida.');
            $contexto = app(AlternativasCambioHorario::class)->preparar($horario, $solicitud->origen['espacio_destino'] ?? 'actual');
            if (! is_string($solicitud->firma_origen) || ! hash_equals($solicitud->firma_origen, $contexto['firma'])) $this->rechazar('La clase o sus asignaciones cambiaron. Cancela esta solicitud y crea otra sobre los datos actuales.');
            $respuestas = $solicitud->respuestasDocentes()->lockForUpdate()->get();
            $participantes = $contexto['docentes']->pluck('profesor_id')->map(fn ($id) => (int) $id)->sort()->values()->all();
            if ($participantes !== $respuestas->pluck('profesor_id')->map(fn ($id) => (int) $id)->sort()->values()->all()
                || $respuestas->contains(fn ($r) => $r->estado !== 'Aceptada' || ! $r->opcion_id) || $respuestas->pluck('opcion_id')->unique()->count() !== 1) $this->rechazar('Todos los docentes de la clase deben aceptar la misma alternativa.');
            $opcion = $solicitud->opciones()->lockForUpdate()->findOrFail($respuestas->first()->opcion_id);
            $clave = $opcion->dia_id.':'.$opcion->bloque_horario_id;
            if (! isset($contexto['opciones'][$clave]) || $contexto['opciones'][$clave] != $opcion->datos) $this->rechazar('La alternativa dejó de estar libre, cambió su intervalo o ya no cumple las disponibilidades. No se aplicó el cambio.');
            $antes = $contexto['horario']->getAttributes();
            $espaciosAntes = ['salas' => $contexto['horario']->asignacionesSalas->map(fn ($a) => $a->getAttributes())->all(),
                'laboratorios' => $contexto['horario']->asignacionesLaboratorios->map(fn ($a) => $a->getAttributes())->all()];
            if ($contexto['espacio'] !== 'actual') {
                $contexto['horario']->asignacionesSalas()->where('estado', true)->update(['estado' => false]);
                $contexto['horario']->asignacionesLaboratorios()->where('estado', true)->update(['estado' => false]);
                foreach (['asignacionesSalas' => 'sala_id', 'asignacionesLaboratorios' => 'laboratorio_id'] as $relacion => $campo) {
                    foreach ($contexto['candidato']->$relacion as $asignacion) $contexto['horario']->$relacion()->updateOrCreate([$campo => $asignacion->$campo],
                        ['capacidad_asignada' => $asignacion->capacidad_asignada, 'es_principal' => $asignacion->es_principal,
                            'observaciones' => $asignacion->observaciones, 'estado' => true]);
                }
            }
            // Fijar docentes inferidos para que el movimiento conserve exactamente los participantes que respondieron.
            if ($contexto['horario']->profesores->isEmpty()) foreach ($contexto['docentes'] as $d) Models\HorarioProfesor::updateOrCreate(['horario_id' => $horario->id, 'profesor_id' => $d->profesor_id],
                ['tipo_participacion' => $d->tipo_participacion, 'es_principal' => $d->es_principal, 'observaciones' => $d->observaciones, 'estado' => true]);
            $contexto['horario']->update(['dia_id' => $opcion->dia_id, 'bloque_horario_id' => $opcion->bloque_horario_id]);
            $solicitud->update(['estado' => 'Aplicada', 'estado_activo' => false, 'revisado_por' => $usuarioId, 'revisado_en' => now(), 'motivo_revision' => $datos['motivo'] ?? null]);
            $opcion->update(['estado' => 'Aplicada']);
            $this->auditar($solicitud, 'Aplicar cambio de horario', $usuarioId, $ip, ['antes' => $antes, 'despues' => $contexto['horario']->getAttributes(),
                'espacios_anteriores' => $espaciosAntes, 'destino' => $contexto['destino'], 'opcion_id' => $opcion->id, 'sin_declaracion' => $contexto['sinDeclaracion']]);
        }, 3);
    }

    public function abierta(Models\SolicitudCambioHorario $solicitud): bool
    {
        return $solicitud->estado_activo && $solicitud->fecha_limite?->isFuture() && in_array($solicitud->estado, ['Pendiente de respuesta', 'Respondida'], true)
            && $solicitud->horario?->estado && $solicitud->horario?->versionPlanificacion?->es_actual
            && $solicitud->horario?->versionPlanificacion?->estado === 'Borrador' && $solicitud->horario?->versionPlanificacion?->periodoAcademico?->estado;
    }

    private function bloquearHorario(Models\Horario $horario): void
    {
        $version = $horario->versionPlanificacion;
        $periodo = Models\PeriodoAcademico::lockForUpdate()->findOrFail($version->periodo_academico_id);
        $version = Models\VersionPlanificacion::lockForUpdate()->findOrFail($version->id);
        app(ActivacionVersion::class)->bloquearDatos($periodo, $version);
    }

    private function auditar(Models\SolicitudCambioHorario $s, string $accion, ?int $usuarioId, ?string $ip, array $datos): void
    {
        DB::table('historial_acciones')->insert(['usuario_id' => $usuarioId, 'accion' => $accion, 'modulo' => 'Solicitudes de cambio', 'tabla_afectada' => 'solicitudes_cambio_horario',
            'registro_id' => $s->id, 'descripcion' => 'Solicitud de cambio del horario #'.$s->horario_id.'.', 'datos' => json_encode($datos, JSON_THROW_ON_ERROR), 'direccion_ip' => $ip, 'created_at' => now(), 'updated_at' => now()]);
    }
    private function rechazar(string $mensaje): never { throw ValidationException::withMessages(['cambio' => $mensaje]); }
}

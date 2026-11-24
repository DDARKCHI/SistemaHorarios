<?php

namespace App\Services;

use App\Jobs\EnviarRecordatorioDisponibilidad;
use App\Models\RecordatorioDisponibilidad;
use App\Models\SolicitudDisponibilidad;
use App\Notifications\RecordatorioDisponibilidadNotification;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

class RecordatoriosDisponibilidad
{
    public function __construct(private EnviosAccesosDocentes $envios, private CuentasDocentes $cuentas) {}

    public function programar(bool $simular = false): int
    {
        if (! $simular && ! $this->envios->correoDisponible()) return 0;

        $hoy = today();
        $programados = 0;
        foreach (RecordatorioDisponibilidad::DIAS_ANTES as $dias) {
            SolicitudDisponibilidad::query()->where('estado', 'Pendiente')
                ->whereDate('fecha_limite', $hoy->copy()->addDays($dias))
                ->whereDoesntHave('respuestas')
                ->whereHas('enviosAcceso', fn ($envios) => $envios->where('estado', 'enviado')->where('enviado_en', '<', $hoy))
                ->whereDoesntHave('recordatorios', fn ($avisos) => $avisos->where('dias_antes', $dias))
                ->eachById(function (SolicitudDisponibilidad $solicitud) use ($hoy, $dias, $simular, &$programados) {
                    $programado = $simular
                        ? $this->destino($solicitud, $hoy, $dias) !== null
                        : $this->encolar($solicitud->id, $hoy, $dias);
                    if ($programado) $programados++;
                });
        }

        return $programados;
    }

    private function encolar(int $solicitudId, CarbonInterface $hoy, int $dias): bool
    {
        return DB::transaction(function () use ($solicitudId, $hoy, $dias) {
            $solicitud = SolicitudDisponibilidad::lockForUpdate()->find($solicitudId);
            if (! $solicitud || $solicitud->recordatorios()->where('dias_antes', $dias)->exists()) return false;

            $destino = $this->destino($solicitud, $hoy, $dias);
            if (! $destino) return false;

            $recordatorio = $solicitud->recordatorios()->create([
                'envio_acceso_docente_id' => $destino['invitacion']->id,
                'dias_antes' => $dias, 'enviar_el' => $hoy->toDateString(), 'estado' => 'pendiente',
            ]);
            $tarea = (new EnviarRecordatorioDisponibilidad($recordatorio->id))->onConnection('database')->onQueue('accesos-docentes');
            // La tarea y el hito se confirman juntos en la cola de la base de datos.
            Queue::connection('database')->push($tarea->beforeCommit(), '', $tarea->queue);

            return true;
        }, 3);
    }

    public function enviar(int $recordatorioId): void
    {
        $reclamado = RecordatorioDisponibilidad::whereKey($recordatorioId)->where('estado', 'pendiente')
            ->update(['estado' => 'en_proceso']);
        if (! $reclamado) return;

        DB::transaction(function () use ($recordatorioId) {
            $recordatorio = RecordatorioDisponibilidad::findOrFail($recordatorioId);
            $solicitud = SolicitudDisponibilidad::lockForUpdate()->findOrFail($recordatorio->solicitud_disponibilidad_id);
            $hoy = today();
            $destino = $recordatorio->enviar_el->isSameDay($hoy)
                ? $this->destino($solicitud, $hoy, $recordatorio->dias_antes)
                : null;

            if (! $destino || (int) $destino['invitacion']->id !== (int) $recordatorio->envio_acceso_docente_id) {
                $recordatorio->update([
                    'estado' => 'omitido', 'error' => 'La solicitud, su plazo o el destinatario ya no corresponden al aviso.',
                ]);

                return;
            }
            if (! $this->envios->correoDisponible()) {
                $recordatorio->update(['estado' => 'fallido', 'error' => 'El correo saliente no está configurado.']);

                return;
            }

            $cuenta = $destino['cuenta'];
            Notification::route('mail', $destino['invitacion']->correo)->notify(new RecordatorioDisponibilidadNotification(
                $solicitud,
                $cuenta->name,
                $destino['rut'],
                $cuenta->debe_cambiar_password,
                $recordatorio->dias_antes
            ));
            $recordatorio->update(['estado' => 'enviado', 'enviado_en' => now(), 'error' => null]);
        });
    }

    private function destino(SolicitudDisponibilidad $solicitud, CarbonInterface $hoy, int $dias): ?array
    {
        if ($solicitud->estado !== 'Pendiente'
            || ! $solicitud->fecha_limite?->isSameDay($hoy->copy()->addDays($dias))
            || $solicitud->respuestas()->exists()) return null;

        $solicitud->loadMissing('profesor', 'periodoAcademico.universidad');
        $profesor = $solicitud->profesor;
        $periodo = $solicitud->periodoAcademico;
        if (! $profesor?->estado || ! $periodo?->estado || ! $periodo->universidad?->estado
            || (int) $periodo->universidad_id !== (int) $profesor->universidad_id) return null;

        $invitacion = $solicitud->enviosAcceso()->with('usuario')
            ->where('estado', 'enviado')->whereNotNull('enviado_en')
            ->where('profesor_id', $profesor->id)->where('periodo_academico_id', $periodo->id)
            ->latest('enviado_en')->latest('id')->first();
        if (! $invitacion || $invitacion->enviado_en->gte($hoy)) return null;

        $cuenta = $invitacion->usuario;
        if (! $cuenta || (int) $cuenta->profesor_id !== (int) $profesor->id) return null;

        $rut = CuentasDocentes::rutParaAcceso($profesor->rut);
        $correo = $this->cuentas->correoProfesor($profesor) ?? mb_strtolower(trim($cuenta->email ?? ''));
        if ($rut === null || ! CuentasDocentes::buscarUsuario($rut)?->is($cuenta)
            || ! filter_var($correo, FILTER_VALIDATE_EMAIL)
            || $correo !== mb_strtolower(trim($invitacion->correo ?? ''))) return null;

        return compact('invitacion', 'cuenta', 'rut');
    }
}

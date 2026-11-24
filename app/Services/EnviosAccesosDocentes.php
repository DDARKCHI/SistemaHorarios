<?php

namespace App\Services;

use App\Jobs\EnviarAccesoDocente;
use App\Models\EnvioAccesoDocente;
use App\Models\PeriodoAcademico;
use App\Models\Profesor;
use App\Models\SolicitudDisponibilidad;
use App\Models\User;
use App\Notifications\AccesoDocenteNotification;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class EnviosAccesosDocentes
{
    public function __construct(private CuentasDocentes $cuentas) {}

    public function correoDisponible(): bool
    {
        if (! filter_var(config('mail.from.address'), FILTER_VALIDATE_EMAIL)) return false;

        return $this->transporteReal((string) config('mail.default'), []);
    }

    public function enviar(
        PeriodoAcademico $periodo,
        array $profesorIds,
        int $actor,
        ?string $ip = null,
        ?string $lote = null,
        ?string $fechaLimite = null
    ): array {
        return $this->procesar($periodo, $profesorIds, $actor, $ip, $lote, $fechaLimite, false);
    }

    public function encolar(
        PeriodoAcademico $periodo,
        array $profesorIds,
        int $actor,
        ?string $ip = null,
        ?string $lote = null,
        ?string $fechaLimite = null
    ): array {
        return $this->procesar($periodo, $profesorIds, $actor, $ip, $lote, $fechaLimite, true);
    }

    private function procesar(
        PeriodoAcademico $periodo,
        array $profesorIds,
        int $actor,
        ?string $ip,
        ?string $lote,
        ?string $fechaLimite,
        bool $encolar
    ): array {
        $usuario = User::findOrFail($actor);
        abort_unless($usuario->can('asignar profesores'), 403);
        if (! $periodo->estado || ! $periodo->universidad->estado) {
            $this->rechazar('Selecciona un período y una institución activos.');
        }
        if (! DB::table('dias')->where('estado', true)->exists()
            || ! DB::table('bloques_horarios')->where('estado', true)->exists()) {
            $this->rechazar('Configura los días y módulos activos antes de solicitar disponibilidad.');
        }
        if (! $profesorIds || count($profesorIds) > 2000
            || count(array_filter($profesorIds, fn ($id) => ! is_scalar($id) || ! ctype_digit((string) $id))) > 0) {
            $this->rechazar('Selecciona entre 1 y 2.000 docentes.');
        }
        $profesorIds = array_values(array_unique(array_map('intval', $profesorIds)));
        $lote ??= (string) Str::uuid();
        if (! Str::isUuid($lote)) $this->rechazar('Actualiza el formulario antes de enviar.');
        try {
            $limite = $fechaLimite === null ? today()->addDays(7) : Carbon::createFromFormat('!Y-m-d', $fechaLimite);
        } catch (\Throwable) {
            $this->rechazar('Indica una fecha límite válida.');
        }
        if (! $limite || ($fechaLimite !== null && $limite->format('Y-m-d') !== $fechaLimite)
            || $limite->lt(today()) || $limite->gt(today()->addYear())) {
            $this->rechazar('La fecha límite debe estar entre hoy y un año.');
        }

        $resultado = ['enviados' => 0, 'fallidos' => 0, 'pendientes' => 0, 'resultados' => []];
        foreach ($profesorIds as $profesorId) {
            [$envio, $enviar] = $this->preparar($periodo, $profesorId, $actor, $lote, $limite, $encolar);
            if ($encolar && $envio?->estado === 'pendiente' && $envio->error === null) {
                EnviarAccesoDocente::dispatch($periodo->id, $profesorId, $actor, $ip, $lote, $limite->toDateString())
                    ->onConnection('database')->onQueue('accesos-docentes')->afterCommit();
            } elseif ($enviar) {
                try {
                    $cuenta = $envio->usuario;
                    Notification::route('mail', $envio->correo)->notify(new AccesoDocenteNotification(
                        $cuenta,
                        $periodo,
                        $cuenta->debe_cambiar_password ? $cuenta->clave_temporal : null,
                        $envio->solicitudDisponibilidad->fecha_limite->format('d/m/Y'),
                        CuentasDocentes::rutParaAcceso($envio->profesor->rut)
                    ));
                    $envio->update(['estado' => 'enviado', 'enviado_en' => now(), 'error' => null]);
                } catch (\Throwable) {
                    // Los mensajes del transporte pueden contener credenciales o contenido del correo.
                    $envio->update([
                        'estado' => 'fallido', 'enviado_en' => null,
                        'error' => 'No fue posible enviar el correo. Revisa la configuración de salida y vuelve a intentarlo.',
                    ]);
                }
            }
            $estado = $envio?->estado ?? 'fallido';
            $contador = match ($estado) { 'enviado' => 'enviados', 'fallido' => 'fallidos', default => 'pendientes' };
            $resultado[$contador]++;
            $resultado['resultados'][] = [
                'profesor_id' => $profesorId, 'envio_id' => $envio?->id, 'estado' => $estado,
                'mensaje' => $envio?->error ?? match ($estado) {
                    'enviado' => 'Correo enviado.',
                    'en_proceso' => 'Este envío ya se está procesando.',
                    'pendiente' => 'En cola para envío.',
                    default => 'El docente no está activo o no pertenece a la institución del período.',
                },
            ];
        }
        DB::table('historial_acciones')->insert([
            'usuario_id' => $actor, 'accion' => $encolar ? 'Encolar accesos docentes' : 'Enviar accesos docentes',
            'modulo' => 'Disponibilidad docente', 'tabla_afectada' => 'envios_accesos_docentes',
            'registro_id' => null, 'descripcion' => 'Envío de invitaciones para registrar disponibilidad.',
            'datos' => json_encode([
                'lote' => $lote, 'periodo_id' => $periodo->id, 'seleccionados' => $profesorIds,
                'enviados' => $resultado['enviados'], 'fallidos' => $resultado['fallidos'], 'pendientes' => $resultado['pendientes'],
            ], JSON_THROW_ON_ERROR),
            'direccion_ip' => $ip, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return $resultado;
    }

    private function preparar(PeriodoAcademico $periodo, int $profesorId, int $actor, string $lote, Carbon $limite, bool $encolar): array
    {
        return DB::transaction(function () use ($periodo, $profesorId, $actor, $lote, $limite, $encolar) {
            $periodo = PeriodoAcademico::lockForUpdate()->findOrFail($periodo->id);
            $profesor = Profesor::lockForUpdate()->find($profesorId);
            $envio = EnvioAccesoDocente::where('lote', $lote)->where('profesor_id', $profesorId)->lockForUpdate()->first();
            if ($envio && ((int) $envio->periodo_academico_id !== (int) $periodo->id || (int) $envio->enviado_por !== $actor)) {
                $this->rechazar('Este envío pertenece a otro período. Actualiza el formulario.');
            }
            if ($envio && ($envio->estado === 'enviado'
                || ($envio->estado === 'en_proceso' && $envio->updated_at->gt(now()->subMinutes(10))))) return [$envio, false];
            if (! $profesor || ! $profesor->estado || (int) $profesor->universidad_id !== (int) $periodo->universidad_id) {
                $envio?->update(['estado' => 'fallido', 'error' => 'El docente ya no está activo en la institución del período.']);

                return [$envio, false];
            }
            $envio ??= new EnvioAccesoDocente([
                'profesor_id' => $profesor->id, 'periodo_academico_id' => $periodo->id,
                'enviado_por' => $actor, 'lote' => $lote,
            ]);
            $rutAcceso = CuentasDocentes::rutParaAcceso($profesor->rut);
            $cuenta = $rutAcceso === null ? null : $this->cuentas->asegurarCuenta($profesor);
            $correo = $this->cuentas->correoProfesor($profesor);
            if ($correo === null && filter_var($cuenta?->email, FILTER_VALIDATE_EMAIL)) {
                $correo = mb_strtolower(trim($cuenta->email));
            }
            if ($envio->exists && $envio->correo && $envio->correo !== $correo) {
                $envio->update(['estado' => 'fallido', 'error' => 'El correo del docente cambió. Selecciónalo otra vez para usar su dirección actual.']);

                return [$envio, false];
            }
            $envio->fill(['user_id' => $cuenta?->id, 'correo' => $correo]);
            if ($rutAcceso === null || ! $cuenta || $correo === null
                || ! CuentasDocentes::buscarUsuario($rutAcceso)?->is($cuenta)) {
                $envio->fill([
                    'estado' => 'fallido',
                    'error' => match (true) {
                        $rutAcceso === null => 'Completa el RUT en la ficha del docente antes de enviar el acceso.',
                        $correo === null => 'El docente necesita un correo válido antes de enviar el acceso.',
                        default => 'No se pudo vincular el RUT a una cuenta única. Revisa las cuentas existentes.',
                    },
                ])->save();

                return [$envio, false];
            }
            if (! $periodo->estado || ! $periodo->universidad->estado) {
                $this->rechazar('El período o la institución ya no están activos.');
            }
            $solicitud = $envio->solicitud_disponibilidad_id
                ? SolicitudDisponibilidad::lockForUpdate()->find($envio->solicitud_disponibilidad_id) : null;
            if ($solicitud && ($solicitud->estado === 'Cancelada'
                || $solicitud->fecha_limite?->copy()->endOfDay()->isPast())) {
                $envio->update(['estado' => 'fallido', 'error' => 'La solicitud se canceló o venció. Prepara un nuevo envío si corresponde.']);

                return [$envio, false];
            }
            $solicitud ??= SolicitudDisponibilidad::where('profesor_id', $profesor->id)
                ->where('periodo_academico_id', $periodo->id)
                ->where(fn ($query) => $query->where('estado', 'En revisión')
                    ->orWhere(fn ($pendiente) => $pendiente->where('estado', 'Pendiente')->whereDate('fecha_limite', '>=', today())))
                ->latest('id')->lockForUpdate()->first();
            $solicitud ??= SolicitudDisponibilidad::create([
                'profesor_id' => $profesor->id, 'periodo_academico_id' => $periodo->id,
                'fecha_solicitud' => today(), 'fecha_limite' => $limite->toDateString(),
                'estado' => 'Pendiente', 'observaciones' => 'Disponibilidad solicitada mediante acceso personal a la plataforma.',
            ]);
            $envio->solicitud_disponibilidad_id = $solicitud->id;
            $disponible = $this->correoDisponible();
            $envio->estado = $disponible && ! $encolar ? 'en_proceso' : 'pendiente';
            $envio->error = ! $disponible
                ? 'El correo saliente no está configurado para entregar mensajes. El acceso quedó pendiente de envío.'
                : null;
            $envio->enviado_en = null;
            $envio->save();
            $envio->setRelation('profesor', $profesor);

            return [$envio, $envio->estado === 'en_proceso'];
        }, 3);
    }

    private function transporteReal(string $nombre, array $visitados): bool
    {
        if ($nombre === '' || in_array($nombre, $visitados, true)) return false;
        $config = config('mail.mailers.'.$nombre, []);
        $transporte = $config['transport'] ?? null;
        if (in_array($transporte, ['log', 'array', null], true)) return false;
        if (in_array($transporte, ['failover', 'roundrobin'], true)) {
            $mailers = $config['mailers'] ?? [];
            if (! $mailers) return false;
            foreach ($mailers as $mailer) {
                if (! $this->transporteReal($mailer, [...$visitados, $nombre])) return false;
            }

            return true;
        }
        if ($transporte === 'smtp') return filled($config['host'] ?? null) || filled($config['url'] ?? null);

        return in_array($transporte, ['sendmail', 'ses', 'ses-v2', 'mailgun', 'postmark', 'resend'], true);
    }

    private function rechazar(string $mensaje): never
    {
        throw ValidationException::withMessages(['accesos' => $mensaje]);
    }
}

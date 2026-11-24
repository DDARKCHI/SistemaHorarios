<?php

namespace Tests\Feature;

use App\Jobs\EnviarRecordatorioDisponibilidad;
use App\Models\PeriodoAcademico;
use App\Models\Profesor;
use App\Models\RecordatorioDisponibilidad;
use App\Models\SolicitudDisponibilidad;
use App\Models\Universidad;
use App\Notifications\RecordatorioDisponibilidadNotification;
use App\Services\CuentasDocentes;
use App\Services\RecordatoriosDisponibilidad;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\IsolatedDatabaseTestCase;

class RecordatoriosDisponibilidadTest extends IsolatedDatabaseTestCase
{
    private PeriodoAcademico $periodo;
    private int $rut = 16000000;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-16 09:00:00', config('app.timezone')));
        $this->periodo = PeriodoAcademico::create([
            'universidad_id' => Universidad::firstOrFail()->id,
            'nombre' => '2026-2', 'anio' => 2026, 'numero_semestre' => 2, 'estado' => true,
        ]);
        config([
            'mail.default' => 'smtp', 'mail.mailers.smtp.host' => 'correo.example.test',
            'mail.from.address' => 'prosec@example.test',
        ]);
    }

    private function solicitud(int $dias = 3, bool $invitada = true): SolicitudDisponibilidad
    {
        $profesor = Profesor::create([
            'universidad_id' => $this->periodo->universidad_id, 'rut' => (string) ++$this->rut,
            'nombre' => 'Docente', 'apellido' => 'Prueba', 'estado' => true,
            'correo_institucional' => $this->rut.'@example.test',
        ]);
        $cuenta = app(CuentasDocentes::class)->asegurarCuenta($profesor);
        $solicitud = SolicitudDisponibilidad::create([
            'profesor_id' => $profesor->id, 'periodo_academico_id' => $this->periodo->id,
            'fecha_solicitud' => today()->subWeek(), 'fecha_limite' => today()->addDays($dias), 'estado' => 'Pendiente',
        ]);
        if ($invitada) {
            $solicitud->enviosAcceso()->create([
                'profesor_id' => $profesor->id, 'user_id' => $cuenta->id, 'periodo_academico_id' => $this->periodo->id,
                'lote' => (string) Str::uuid(), 'estado' => 'enviado', 'correo' => $profesor->correo_institucional,
                'enviado_en' => now()->subDays(2),
            ]);
        }

        return $solicitud;
    }

    public function test_la_simulacion_lista_elegibles_sin_crear_filas_ni_trabajos(): void
    {
        Notification::fake();
        Queue::fake();
        $this->solicitud();
        $this->solicitud(1);
        $this->solicitud(2);
        $this->solicitud(invitada: false);

        $this->artisan('prosec:recordar-disponibilidad', ['--simular' => true])
            ->expectsOutput('2 recordatorio(s) elegible(s). Simulación sin envíos.')->assertSuccessful();

        $this->assertDatabaseCount('recordatorios_disponibilidad', 0);
        Queue::assertNothingPushed();
        Notification::assertNothingSent();
    }

    public function test_envia_una_vez_en_cada_hito_aunque_el_docente_no_haya_ingresado(): void
    {
        Queue::fake();
        Notification::fake();
        $solicitud = $this->solicitud();
        $motor = app(RecordatoriosDisponibilidad::class);
        $usuario = $solicitud->enviosAcceso()->sole()->usuario;

        $this->assertNull($usuario->primer_ingreso_en);
        $this->assertSame(1, $motor->programar());
        $this->assertSame(0, $motor->programar());
        $primero = $solicitud->recordatorios()->sole();
        Queue::assertPushed(EnviarRecordatorioDisponibilidad::class, fn ($tarea) => $tarea->queue === 'accesos-docentes'
            && $tarea->connection === 'database' && ! str_contains(serialize($tarea), $usuario->clave_temporal));

        $tarea = new EnviarRecordatorioDisponibilidad($primero->id);
        $tarea->handle($motor);
        $tarea->handle($motor);
        Notification::assertSentOnDemand(RecordatorioDisponibilidadNotification::class, fn ($aviso, $canales, $destino) =>
            $destino->routeNotificationFor('mail') === $usuario->email);
        Notification::assertCount(1);
        $this->assertSame('enviado', $primero->fresh()->estado);
        $this->assertNotNull($primero->fresh()->enviado_en);

        $this->travel(1)->days();
        $this->assertSame(0, $motor->programar());
        $this->travel(1)->days();
        $this->assertSame(1, $motor->programar());
        $segundo = $solicitud->recordatorios()->where('dias_antes', 1)->sole();
        (new EnviarRecordatorioDisponibilidad($segundo->id))->handle($motor);

        Notification::assertCount(2);
        $this->assertSame('enviado', $segundo->fresh()->estado);
        $this->assertSame([3, 1], $solicitud->recordatorios()->orderBy('id')->pluck('dias_antes')->all());
    }

    public function test_omite_solicitudes_sin_destino_vigente_o_con_respuesta(): void
    {
        Queue::fake();
        Notification::fake();
        $this->solicitud()->profesor->update(['correo_institucional' => 'nuevo@example.test']);
        $this->solicitud()->update(['estado' => 'En revisión']);
        $this->solicitud()->update(['estado' => 'Cancelada']);
        $this->solicitud()->enviosAcceso()->sole()->update(['enviado_en' => now()]);
        $this->solicitud()->profesor->update(['rut' => 'DOC-PRUEBA']);
        $this->solicitud()->profesor->update(['estado' => false]);
        $respondida = $this->solicitud();
        $respondida->respuestas()->create([
            'profesor_id' => $respondida->profesor_id, 'respuesta' => 'Pendiente de revisión',
            'fecha_respuesta' => now(), 'bloques' => ['disponibles' => []],
        ]);

        $this->assertSame(0, app(RecordatoriosDisponibilidad::class)->programar());
        Queue::assertNothingPushed();
        Notification::assertNothingSent();
    }

    public function test_el_trabajo_revalida_respuesta_plazo_y_ultimo_envio_antes_de_enviar(): void
    {
        Queue::fake();
        Notification::fake();
        $respondida = $this->solicitud(1);
        $vencida = $this->solicitud(1);
        $reenviada = $this->solicitud(1);
        $motor = app(RecordatoriosDisponibilidad::class);
        $this->assertSame(3, $motor->programar());
        $respondida->update(['estado' => 'En revisión']);
        $nuevoEnvio = $reenviada->enviosAcceso()->sole()->replicate();
        $nuevoEnvio->fill(['lote' => (string) Str::uuid(), 'enviado_en' => now()])->save();

        foreach ([$respondida, $reenviada] as $solicitud) {
            (new EnviarRecordatorioDisponibilidad($solicitud->recordatorios()->sole()->id))->handle($motor);
        }
        $this->travel(1)->days();
        (new EnviarRecordatorioDisponibilidad($vencida->recordatorios()->sole()->id))->handle($motor);

        $this->assertSame(3, RecordatorioDisponibilidad::where('estado', 'omitido')->count());
        Notification::assertNothingSent();
    }

    public function test_la_cola_y_el_registro_se_confirman_en_la_base_aislada(): void
    {
        Notification::fake();
        $this->solicitud();
        $this->artisan('prosec:recordar-disponibilidad')
            ->expectsOutput('1 recordatorio(s) programado(s).')->assertSuccessful();

        $this->assertDatabaseCount('recordatorios_disponibilidad', 1);
        $this->assertDatabaseCount('jobs', 1);
        $this->assertSame('accesos-docentes', DB::table('jobs')->sole()->queue);
        Notification::assertNothingSent();
    }

    public function test_registra_error_de_transporte_sin_reenviar_ni_guardar_datos_sensibles(): void
    {
        Queue::fake();
        Notification::shouldReceive('send')->once()->andThrow(new \RuntimeException('credencial del transporte'));
        $solicitud = $this->solicitud();
        $motor = app(RecordatoriosDisponibilidad::class);
        $this->assertSame(1, $motor->programar());
        $aviso = $solicitud->recordatorios()->sole();
        $tarea = new EnviarRecordatorioDisponibilidad($aviso->id);
        $tarea->handle($motor);
        $tarea->handle($motor);

        $aviso->refresh();
        $this->assertSame('fallido', $aviso->estado);
        $this->assertStringNotContainsString('credencial', $aviso->error);
        $this->assertNull($aviso->enviado_en);
        $this->assertSame(0, $motor->programar());
    }

    public function test_el_recordatorio_usa_la_marca_y_no_incluye_la_clave_provisoria(): void
    {
        config(['mail.default' => 'array']);
        $solicitud = $this->solicitud(1);
        $cuenta = $solicitud->enviosAcceso()->sole()->usuario;
        Notification::route('mail', $cuenta->email)->notify(new RecordatorioDisponibilidadNotification(
            $solicitud, $cuenta->name, $cuenta->rut, $cuenta->debe_cambiar_password, 1,
        ));

        $correo = Mail::mailer('array')->getSymfonyTransport()->messages()->sole()->getOriginalMessage();
        $html = $correo->getHtmlBody();
        $this->assertSame('PROSEC - UCSC', $correo->getFrom()[0]->getName());
        $this->assertSame([], $correo->getAttachments());
        $this->assertStringContainsString('vence mañana', $html);
        $this->assertStringContainsString('contraseña temporal de la invitación inicial', $html);
        $this->assertStringContainsString(route('mi.disponibilidad.show', $solicitud), $html);
        $this->assertStringNotContainsString($cuenta->clave_temporal, $html);
    }
}

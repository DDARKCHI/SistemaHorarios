<?php

namespace Tests\Feature;

use App\Jobs\EnviarAccesoDocente;
use App\Models;
use App\Notifications\AccesoDocenteNotification;
use App\Services\CuentasDocentes;
use App\Services\EnviosAccesosDocentes;
use App\Services\ProgramacionCargaService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\IsolatedDatabaseTestCase;

class CuentasYEnviosDocentesTest extends IsolatedDatabaseTestCase
{
    private Models\User $actor;
    private Models\PeriodoAcademico $periodo;
    private Models\Profesor $profesor;

    protected function setUp(): void
    {
        parent::setUp();
        $universidad = Models\Universidad::firstOrFail();
        $this->actor = Models\User::create([
            'name' => 'Coordinación', 'rut' => '99000000', 'email' => 'coordinacion@example.test', 'password' => 'prueba',
        ]);
        $this->actor->givePermissionTo(Permission::findOrCreate('asignar profesores', 'web'));
        $this->periodo = Models\PeriodoAcademico::create([
            'universidad_id' => $universidad->id, 'nombre' => '2026-2', 'anio' => 2026, 'numero_semestre' => 2, 'estado' => true,
        ]);
        $this->profesor = Models\Profesor::create([
            'universidad_id' => $universidad->id, 'rut' => '12.345.678-9', 'nombre' => 'Ana',
            'apellido' => 'Prueba', 'correo_institucional' => 'ana@example.test', 'estado' => true,
        ]);
    }

    private function smtp(): void
    {
        config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => 'smtp.example.test', 'mail.from.address' => 'prosec@example.test']);
    }

    public function test_crea_cuenta_unica_con_clave_temporal_cifrada_y_perfil_docente(): void
    {
        $servicio = app(CuentasDocentes::class);
        $cuenta = $servicio->asegurarCuenta($this->profesor);
        $segunda = $servicio->asegurarCuenta($this->profesor);

        $this->assertSame($cuenta->id, $segunda->id);
        $this->assertSame('123456789', $cuenta->rut);
        $this->assertTrue($cuenta->debe_cambiar_password);
        $this->assertTrue(Hash::check($cuenta->clave_temporal, $cuenta->password));
        $this->assertNotSame($cuenta->clave_temporal, DB::table('users')->where('id', $cuenta->id)->value('clave_temporal'));
        $this->assertArrayNotHasKey('clave_temporal', $cuenta->toArray());
        $this->assertArrayNotHasKey('password', $cuenta->toArray());
        $this->assertTrue($cuenta->hasRole('Docente'));
        $this->assertTrue($cuenta->can('enviar propuesta horario'));
        $this->assertTrue($cuenta->can('ver programacion semestral'));
        $this->assertFalse($cuenta->can('administrar usuarios'));
        $this->assertFalse($cuenta->can('asignar profesores'));
    }

    public function test_vincula_cuenta_existente_sin_reemplazar_clave_ni_roles(): void
    {
        $usuario = Models\User::create([
            'name' => 'Nombre anterior', 'rut' => '123456789',
            'email' => 'ana@example.test', 'password' => 'PersonalSegura123',
        ]);
        $usuario->givePermissionTo(Permission::findOrCreate('ver panel inicio', 'web'));
        $hash = $usuario->password;

        $vinculada = app(CuentasDocentes::class)->asegurarCuenta($this->profesor);

        $this->assertSame($usuario->id, $vinculada->id);
        $this->assertSame($hash, $vinculada->password);
        $this->assertFalse($vinculada->debe_cambiar_password);
        $this->assertNull($vinculada->clave_temporal);
        $this->assertTrue($vinculada->can('ver panel inicio'));
        $this->assertFalse($vinculada->hasRole('Docente'));
    }

    public function test_no_apropia_cuenta_por_correo_de_otro_rut(): void
    {
        $usuario = Models\User::create(['name' => 'Otra persona', 'rut' => '22222222', 'email' => 'ana@example.test', 'password' => 'Personal123']);
        $this->assertNull(app(CuentasDocentes::class)->asegurarCuenta($this->profesor));
        $this->assertNull($usuario->fresh()->profesor_id);
        $this->assertDatabaseCount('users', 2);
    }

    public function test_docente_sin_rut_recibe_usuario_interno_y_sin_correo_puede_completarlo_despues(): void
    {
        $this->profesor->update(['rut' => null]);
        $cuenta = app(CuentasDocentes::class)->asegurarCuenta($this->profesor);
        $this->assertSame('DOC'.$this->profesor->id, $cuenta->rut);
        $otro = Models\Profesor::create([
            'universidad_id' => $this->periodo->universidad_id, 'rut' => '98765432',
            'nombre' => 'Pedro', 'apellido' => 'Sin correo', 'estado' => true,
        ]);
        $sinCorreo = app(CuentasDocentes::class)->asegurarCuenta($otro);
        $this->assertNotNull($sinCorreo);
        $this->assertNull($sinCorreo->email);
    }

    public function test_no_crea_cuenta_sin_identidad_ni_para_docente_inactivo(): void
    {
        $this->profesor->update(['rut' => null, 'correo_institucional' => null]);
        $this->assertNull(app(CuentasDocentes::class)->asegurarCuenta($this->profesor));
        $this->profesor->update(['rut' => '12345678', 'estado' => false]);
        $this->assertNull(app(CuentasDocentes::class)->asegurarCuenta($this->profesor));
    }

    public function test_envia_acceso_y_crea_solicitud_sin_repetir_correo_con_mismo_lote(): void
    {
        Notification::fake();
        $this->smtp();
        $lote = (string) Str::uuid();
        $servicio = app(EnviosAccesosDocentes::class);

        $resultado = $servicio->enviar($this->periodo, [$this->profesor->id], $this->actor->id, null, $lote);
        $repetido = $servicio->enviar($this->periodo, [$this->profesor->id], $this->actor->id, null, $lote);

        $this->assertSame(1, $resultado['enviados']);
        $this->assertSame(1, $repetido['enviados']);
        $this->assertDatabaseCount('solicitudes_disponibilidad', 1);
        $this->assertDatabaseCount('envios_accesos_docentes', 1);
        Notification::assertSentOnDemand(AccesoDocenteNotification::class, fn ($n, $channels, $notifiable) =>
            $notifiable->routes['mail'] === 'ana@example.test');
        Notification::assertCount(1);
        $envio = Models\EnvioAccesoDocente::sole();
        $this->assertSame('enviado', $envio->estado);
        $this->assertNotNull($envio->enviado_en);
        $this->assertNotNull($envio->solicitud_disponibilidad_id);
    }

    public function test_transporte_de_prueba_queda_pendiente_y_no_envia_ni_encola(): void
    {
        Notification::fake();
        Queue::fake();
        config(['mail.default' => 'log']);
        $resultado = app(EnviosAccesosDocentes::class)->encolar($this->periodo, [$this->profesor->id], $this->actor->id);

        $this->assertSame(1, $resultado['pendientes']);
        $this->assertSame('pendiente', Models\EnvioAccesoDocente::sole()->estado);
        Notification::assertNothingSent();
        Queue::assertNothingPushed();
    }

    public function test_encola_por_destinatario_sin_incluir_clave_en_trabajo(): void
    {
        Queue::fake();
        Notification::fake();
        $this->smtp();

        $resultado = app(EnviosAccesosDocentes::class)->encolar($this->periodo, [$this->profesor->id], $this->actor->id);

        $this->assertSame(1, $resultado['pendientes']);
        $cuenta = Models\User::where('profesor_id', $this->profesor->id)->sole();
        Queue::assertPushed(EnviarAccesoDocente::class, function ($job) use ($cuenta) {
            $this->assertSame('database', $job->connection);
            $this->assertSame('accesos-docentes', $job->queue);
            $this->assertStringNotContainsString($cuenta->clave_temporal, serialize($job));

            return true;
        });
        Notification::assertNothingSent();
    }

    public function test_job_procesa_solo_su_destinatario_y_actualiza_seguimiento(): void
    {
        Notification::fake();
        $this->smtp();
        $lote = (string) Str::uuid();
        $job = new EnviarAccesoDocente($this->periodo->id, $this->profesor->id, $this->actor->id, null, $lote, today()->addDays(7)->toDateString());

        $job->handle(app(EnviosAccesosDocentes::class));

        $this->assertSame('enviado', Models\EnvioAccesoDocente::sole()->estado);
        Notification::assertCount(1);
    }

    public function test_error_smtp_se_registra_sin_credenciales_y_se_puede_reintentar(): void
    {
        $this->smtp();
        Notification::shouldReceive('send')->once()->andThrow(new \RuntimeException('secreto-smtp-clave'));
        $lote = (string) Str::uuid();
        $servicio = app(EnviosAccesosDocentes::class);

        $resultado = $servicio->enviar($this->periodo, [$this->profesor->id], $this->actor->id, null, $lote);

        $this->assertSame(1, $resultado['fallidos']);
        $envio = Models\EnvioAccesoDocente::sole();
        $this->assertStringNotContainsString('secreto', $envio->error);
        $this->assertNull($envio->enviado_en);
        Notification::fake();
        $reintento = $servicio->enviar($this->periodo, [$this->profesor->id], $this->actor->id, null, $lote);
        $this->assertSame(1, $reintento['enviados']);
        $this->assertDatabaseCount('solicitudes_disponibilidad', 1);
    }

    public function test_sin_correo_falla_solo_ese_destinatario(): void
    {
        Notification::fake();
        $this->smtp();
        $otro = Models\Profesor::create([
            'universidad_id' => $this->periodo->universidad_id, 'rut' => '98765432',
            'nombre' => 'Sin correo', 'apellido' => 'Prueba', 'estado' => true,
        ]);

        $resultado = app(EnviosAccesosDocentes::class)->enviar($this->periodo, [$otro->id, $this->profesor->id], $this->actor->id);

        $this->assertSame(1, $resultado['fallidos']);
        $this->assertSame(1, $resultado['enviados']);
        Notification::assertCount(1);
    }

    public function test_envio_no_recrea_credenciales_personales_y_mail_incluye_cambio_obligatorio_solo_para_temporal(): void
    {
        $this->smtp();
        $cuenta = app(CuentasDocentes::class)->asegurarCuenta($this->profesor);
        $nueva = (new AccesoDocenteNotification($cuenta, $this->periodo, $cuenta->clave_temporal, '30/11/2026', '123456789'))->toMail($cuenta);
        $this->assertSame('emails.acceso-docente', $nueva->view);
        $this->assertSame($cuenta->clave_temporal, $nueva->viewData['claveTemporal']);
        $cuenta->forceFill(['password' => 'PersonalNueva123', 'debe_cambiar_password' => false, 'clave_temporal' => null])->save();
        $hash = $cuenta->password;
        Notification::fake();

        app(EnviosAccesosDocentes::class)->enviar($this->periodo, [$this->profesor->id], $this->actor->id);

        $this->assertSame($hash, $cuenta->fresh()->password);
        Notification::assertSentOnDemand(AccesoDocenteNotification::class, fn ($notificacion) =>
            $notificacion->toMail($cuenta)->viewData['claveTemporal'] === null);
    }

    public function test_el_correo_muestra_el_rut_y_la_marca_sin_adjuntar_los_logos(): void
    {
        config([
            'mail.default' => 'array', 'mail.from.address' => 'prosec@example.test',
            'mail.asset_url' => 'https://prosec.ucsc.cl',
        ]);
        $cuenta = app(CuentasDocentes::class)->asegurarCuenta($this->profesor);
        Notification::route('mail', 'ana@example.test')->notify(new AccesoDocenteNotification(
            $cuenta, $this->periodo, $cuenta->clave_temporal, '30/11/2026', '123456789'
        ));
        $mensaje = Mail::mailer('array')->getSymfonyTransport()->messages()->sole()->getOriginalMessage();
        $html = $mensaje->getHtmlBody();

        $this->assertSame('PROSEC - UCSC', $mensaje->getFrom()[0]->getName());
        $this->assertSame([], $mensaje->getAttachments());
        $this->assertStringContainsString('RUT de acceso sin puntos ni guion', $html);
        $this->assertStringContainsString('>123456789</p>', $html);
        $this->assertStringContainsString('<strong>disponibilidad horaria</strong>', $html);
        $this->assertStringContainsString('https://prosec.ucsc.cl/identidad/correo/principal.png', $html);
        $this->assertStringContainsString($cuenta->clave_temporal, $html);
    }

    public function test_envia_ruts_y_claves_distintas_y_permite_ingresar_con_el_rut_completado(): void
    {
        Notification::fake();
        $this->smtp();
        $otro = Models\Profesor::create([
            'universidad_id' => $this->periodo->universidad_id, 'nombre' => 'Pedro',
            'apellido' => 'Prueba', 'correo_institucional' => 'pedro@example.test', 'estado' => true,
        ]);
        $cuentaAnterior = app(CuentasDocentes::class)->asegurarCuenta($otro);
        $otro->update(['rut' => '10.222.333-k']);
        $resultado = app(EnviosAccesosDocentes::class)->enviar(
            $this->periodo, [$this->profesor->id, $otro->id], $this->actor->id
        );
        $cuentaNueva = Models\User::where('profesor_id', $this->profesor->id)->sole();

        $this->assertSame(2, $resultado['enviados']);
        $this->assertNotSame($cuentaAnterior->clave_temporal, $cuentaNueva->clave_temporal);
        Notification::assertSentOnDemand(AccesoDocenteNotification::class, function ($notificacion, $canales, $destino) use ($cuentaAnterior) {
            $datos = $notificacion->toMail($destino)->viewData;

            return $destino->routes['mail'] === 'pedro@example.test' && $datos['rutAcceso'] === '10222333K'
                && $datos['claveTemporal'] === $cuentaAnterior->clave_temporal;
        });
        $this->post(route('login.procesar'), ['rut' => '10222333K', 'password' => $cuentaAnterior->clave_temporal])
            ->assertRedirect(route('cuenta.password.edit'));
        $this->assertAuthenticatedAs($cuentaAnterior);
    }

    public function test_un_identificador_alfanumerico_no_se_envia_como_rut(): void
    {
        Notification::fake();
        Queue::fake();
        $this->smtp();
        $this->profesor->update(['rut' => 'PRUEBA123']);

        $resultado = app(EnviosAccesosDocentes::class)->encolar($this->periodo, [$this->profesor->id], $this->actor->id);

        $this->assertSame(1, $resultado['fallidos']);
        $this->assertStringContainsString('Completa el RUT', Models\EnvioAccesoDocente::sole()->error);
        $this->assertDatabaseCount('solicitudes_disponibilidad', 0);
        Queue::assertNothingPushed();
        Notification::assertNothingSent();
    }

    public function test_la_importacion_crea_cuenta_docente_sin_enviar_correos_ni_cambiar_clave_al_reimportar(): void
    {
        Notification::fake();
        $fila = [
            'ID_CURSO' => 'PRUEBA-ACCESO', 'COD_RAMO' => 'ACC101', 'RAMO_NOMBRE' => 'Acceso de prueba',
            'SECC' => '1', 'SEMESTRE' => '2027-1', 'SEDE' => 'Sede de prueba', 'COD_SEDE' => 'ACC',
            'PROF_RESP' => '22334455', 'NOM_PROF_RESP' => 'Docente Importado',
            'CORREO_INST' => 'importado@example.test', 'UA' => 'Instituto',
            'NOM_CARR' => 'Carrera de prueba', 'COD_CARR' => 'CPR', 'CUPO' => '20',
            'INSCRITOS' => '20', 'NIVEL_UA' => '1', 'MOD_CATEDRA' => '1',
            'HORARIO' => 'ca: LU.1/G:1 (ACC-01)',
        ];
        $motor = app(ProgramacionCargaService::class);

        $motor->cargar([$fila], $this->actor->id, 'accesos-prueba.csv');
        $profesor = Models\Profesor::where('rut', '22334455')->sole();
        $cuenta = Models\User::where('profesor_id', $profesor->id)->sole();
        $password = $cuenta->password;
        $this->assertTrue($cuenta->debe_cambiar_password);
        $this->assertTrue(Hash::check($cuenta->clave_temporal, $cuenta->password));
        $fila['HORARIO'] = 'ca: LU.2/G:1 (ACC-01)';

        $motor->cargar([$fila], $this->actor->id, 'accesos-prueba-actualizada.csv');

        $this->assertSame($password, $cuenta->fresh()->password);
        $this->assertSame(1, Models\User::where('profesor_id', $profesor->id)->count());
        Notification::assertNothingSent();
    }

    public function test_un_job_fallido_se_refleja_en_el_historial_sin_detalles_sensibles(): void
    {
        $this->smtp();
        Queue::fake();
        $lote = (string) Str::uuid();
        app(EnviosAccesosDocentes::class)->encolar($this->periodo, [$this->profesor->id], $this->actor->id, null, $lote);
        $job = new EnviarAccesoDocente($this->periodo->id, $this->profesor->id, $this->actor->id, null, $lote, today()->addDays(7)->toDateString());

        $job->failed(new \RuntimeException('secreto-de-transporte'));

        $envio = Models\EnvioAccesoDocente::sole();
        $this->assertSame('fallido', $envio->estado);
        $this->assertStringNotContainsString('secreto', $envio->error);
    }
}

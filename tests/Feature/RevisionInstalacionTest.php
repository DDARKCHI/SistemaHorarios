<?php

namespace Tests\Feature;

use App\Services\RevisionInstalacion;
use Illuminate\Support\Facades\DB;
use Tests\IsolatedDatabaseTestCase;

class RevisionInstalacionTest extends IsolatedDatabaseTestCase
{
    private string $muestra;

    protected function setUp(): void
    {
        parent::setUp();
        $this->muestra = sys_get_temp_dir().'/prosec-instalacion-'.bin2hex(random_bytes(8));
        mkdir($this->muestra.'/build/assets', 0777, true);
        $this->app->usePublicPath($this->muestra);
        $this->app->useStoragePath($this->muestra.'/storage');
        foreach (['framework/cache/data', 'framework/sessions', 'framework/views', 'logs'] as $carpeta) mkdir($this->muestra.'/storage/'.$carpeta, 0777, true);
        file_put_contents($this->muestra.'/build/assets/app.css', 'body{}');
        file_put_contents($this->muestra.'/build/assets/app.js', 'void 0;');
        $this->manifiesto();
        config(['app.key' => 'base64:'.base64_encode(str_repeat('x', 32)), 'app.debug' => false, 'app.url' => 'https://prosec.example.test',
            'session.secure' => true, 'session.http_only' => true, 'session.same_site' => 'lax', 'session.encrypt' => true, 'mail.default' => 'smtp']);
        $this->beforeApplicationDestroyed(function () {
            $ruta = realpath($this->muestra);
            $temporal = realpath(sys_get_temp_dir());
            if ($ruta && $temporal && str_starts_with($ruta, $temporal.DIRECTORY_SEPARATOR) && str_starts_with(basename($ruta), 'prosec-instalacion-')) (new \Illuminate\Filesystem\Filesystem)->deleteDirectory($ruta);
        });
    }

    private function manifiesto(?string $css = null): void
    {
        file_put_contents($this->muestra.'/build/manifest.json', json_encode(['resources/css/app.css' => ['file' => $css ?? 'assets/app.css'], 'resources/js/app.js' => ['file' => 'assets/app.js']]));
    }
    private function revisar(bool $entrega = false): array { return collect(app(RevisionInstalacion::class)->revisar($entrega)['comprobaciones'])->keyBy('id')->all(); }

    public function test_consulta_requisitos_sin_cambiar_base_ni_generar_clave(): void
    {
        $antes = DB::table('migrations')->get()->toJson(); $clave = config('app.key');
        $r = $this->revisar();
        foreach (['php', 'clave', 'recursos', 'conexion', 'migraciones', 'secretos_publicos'] as $id) $this->assertSame('correcto', $r[$id]['estado']);
        $this->assertSame($antes, DB::table('migrations')->get()->toJson());
        $this->assertSame($clave, config('app.key'));
        $this->assertSame([], glob($this->muestra.'/storage/framework/views/.prosec-verificacion-*'));
    }
    public function test_falta_de_migracion_se_detecta_sin_aplicarla(): void
    {
        $fila = DB::table('migrations')->orderByDesc('id')->first(); DB::table('migrations')->where('id', $fila->id)->delete();
        $this->assertSame('error', $this->revisar()['migraciones']['estado']);
        $this->assertDatabaseMissing('migrations', ['migration' => $fila->migration]);
    }
    public function test_manifiesto_corrupto_archivo_ausente_o_ruta_ajena_se_rechaza(): void
    {
        file_put_contents($this->muestra.'/build/manifest.json', '{');
        $this->assertSame('error', $this->revisar()['recursos']['estado']);
        $this->manifiesto('../app.css'); $this->assertSame('error', $this->revisar()['recursos']['estado']);
        $this->manifiesto('assets/no-existe.css'); $this->assertSame('error', $this->revisar()['recursos']['estado']);
        $this->manifiesto('assets/app.js'); $this->assertSame('error', $this->revisar()['recursos']['estado']);
    }
    public function test_servidor_desarrollo_es_aviso_local_y_bloqueo_de_entrega(): void
    {
        file_put_contents($this->muestra.'/hot', 'http://localhost:5173');
        $this->assertSame('aviso', $this->revisar()['servidor_desarrollo']['estado']);
        $this->assertSame('error', $this->revisar(true)['servidor_desarrollo']['estado']);
    }
    public function test_entrega_detecta_depuracion_url_cookies_y_correo_sin_modificarlos(): void
    {
        config(['app.debug' => true, 'app.url' => 'http://localhost', 'session.secure' => false, 'session.encrypt' => false, 'mail.default' => 'log']);
        $r = $this->revisar(true);
        foreach (['entorno', 'depuracion', 'url', 'sesiones'] as $id) $this->assertSame('error', $r[$id]['estado']);
        foreach (['sesiones_cifradas', 'correo', 'motor_datos'] as $id) $this->assertSame('aviso', $r[$id]['estado']);
        $this->assertTrue(config('app.debug')); $this->assertSame('log', config('mail.default'));
    }
    public function test_archivo_privado_en_public_se_detecta_sin_leer_su_contenido(): void
    {
        file_put_contents($this->muestra.'/.env', 'SECRETO_FICTICIO=no-mostrar');
        $r = app(RevisionInstalacion::class)->revisar();
        $this->assertSame('error', collect($r['comprobaciones'])->keyBy('id')['secretos_publicos']['estado']);
        $this->assertStringNotContainsString('SECRETO_FICTICIO', json_encode($r));
    }
    public function test_conexion_erronea_no_expone_contrasena_ni_vuelve_a_intentar_en_entrega(): void
    {
        config(['database.default' => 'conexion-inexistente-secreto-ficticio']);
        $r = app(RevisionInstalacion::class)->revisar(true);
        $this->assertSame('error', collect($r['comprobaciones'])->keyBy('id')['conexion']['estado']);
        $this->assertStringNotContainsString('secreto-ficticio', json_encode($r));
        config(['database.default' => 'sqlite']);
    }
    public function test_comando_json_devuelve_error_sin_exponer_la_clave(): void
    {
        config(['app.key' => 'clave-ficticia-invalida']);
        $this->assertSame(1, \Illuminate\Support\Facades\Artisan::call('prosec:verificar', ['--json' => true]));
        $salida = \Illuminate\Support\Facades\Artisan::output();
        $r = json_decode($salida, true, 512, JSON_THROW_ON_ERROR);
        $this->assertGreaterThan(0, $r['errores']);
        $this->assertStringNotContainsString('clave-ficticia-invalida', $salida);
    }
    public function test_comando_local_exitoso_no_declara_el_sistema_listo_para_entrega(): void
    {
        $r = app(RevisionInstalacion::class)->revisar();
        $this->assertSame(0, $r['errores'], json_encode($r));
        $this->assertSame(0, \Illuminate\Support\Facades\Artisan::call('prosec:verificar'));
        $this->assertStringContainsString('no sustituye las pruebas funcionales', \Illuminate\Support\Facades\Artisan::output());
    }
}

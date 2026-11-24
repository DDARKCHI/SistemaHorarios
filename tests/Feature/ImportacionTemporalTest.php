<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\VersionPlanificacion;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Maatwebsite\Excel\Facades\Excel;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Spatie\Permission\Models\Permission;
use Tests\IsolatedDatabaseTestCase;

class ImportacionTemporalTest extends IsolatedDatabaseTestCase
{
    private User $usuario;

    protected function setUp(): void
    {
        parent::setUp();

        // Aislar tambien archivos y temporales del lector Excel fuera del proyecto.
        $almacenamiento = sys_get_temp_dir().'/sistema-horarios-importacion-tests-'.getmypid();
        $this->app->useStoragePath($almacenamiento);
        config([
            'filesystems.default' => 'public',
            'view.compiled' => $almacenamiento.'/framework/views',
            'excel.temporary_files.local_path' => $almacenamiento.'/excel',
        ]);
        if (! is_dir(config('view.compiled'))) {
            mkdir(config('view.compiled'), 0777, true);
        }
        Storage::fake('local');
        Storage::fake('public');
        $this->usuario = $this->crearUsuario();
        $this->actingAs($this->usuario);
    }

    public function test_analizar_un_csv_real_registra_su_ruta_usuario_y_nombre_en_almacenamiento_privado(): void
    {
        $ruta = $this->analizar('programacion-original.csv');

        $pendiente = $this->pendientes()[hash('sha256', $ruta)];
        $this->assertSame($ruta, $pendiente['ruta']);
        $this->assertSame((string) $this->usuario->id, $pendiente['usuario_id']);
        $this->assertSame('programacion-original.csv', $pendiente['nombre_archivo']);
        $this->assertStringStartsWith('importaciones/pendientes/', $ruta);
        Storage::disk('local')->assertExists($ruta);
        $this->assertSame([], Storage::disk('public')->allFiles());
        $this->assertDatabaseCount('versiones_planificacion', 0);
    }

    public function test_un_archivo_con_estructura_invalida_no_crea_un_pendiente(): void
    {
        $archivo = UploadedFile::fake()->createWithContent('invalido.csv', "otra_columna\nvalor\n")
            ->mimeType('text/csv');

        $this->post(route('programacion.importar.analizar'), ['archivo' => $archivo])
            ->assertOk()->assertViewHas('resultado', fn ($resultado) => ! $resultado['valido']);

        $this->assertSame([], $this->pendientes());
        $this->assertSame([], Storage::disk('local')->allFiles());
        $this->assertDatabaseCount('versiones_planificacion', 0);
    }

    public function test_el_analisis_conserva_los_limites_de_tipo_y_tamano_del_archivo(): void
    {
        foreach ([
            UploadedFile::fake()->create('documento.pdf', 1, 'application/pdf'),
            UploadedFile::fake()->create('demasiado-grande.csv', 20481, 'text/csv'),
        ] as $archivo) {
            $this->from(route('programacion.importar'))
                ->post(route('programacion.importar.analizar'), ['archivo' => $archivo])
                ->assertRedirect(route('programacion.importar'))
                ->assertSessionHasErrors('archivo');
        }

        $this->assertSame([], $this->pendientes());
        $this->assertSame([], Storage::disk('local')->allFiles());
        $this->assertDatabaseCount('versiones_planificacion', 0);
    }

    #[DataProvider('acciones')]
    public function test_una_ruta_no_analizada_no_se_puede_leer_ni_eliminar(string $accion): void
    {
        $ruta = 'documentos/informe-ajeno.csv';
        Storage::disk('local')->put($ruta, 'Documento ficticio ajeno');
        Excel::shouldReceive('toArray')->never();

        $this->enviar($accion, $ruta)->assertRedirect(route('programacion.importar'))
            ->assertSessionHas('error');

        $this->assertSame('Documento ficticio ajeno', Storage::disk('local')->get($ruta));
        $this->assertSame([], $this->pendientes());
        $this->assertDatabaseCount('versiones_planificacion', 0);
    }

    #[DataProvider('acciones')]
    public function test_otra_sesion_del_mismo_usuario_no_puede_usar_un_pendiente(string $accion): void
    {
        $ruta = $this->analizar();
        $this->withSession(['importaciones_pendientes' => []]);
        Excel::shouldReceive('toArray')->never();

        $this->enviar($accion, $ruta)->assertRedirect(route('programacion.importar'))
            ->assertSessionHas('error');

        Storage::disk('local')->assertExists($ruta);
        $this->assertSame([], $this->pendientes());
        $this->assertDatabaseCount('versiones_planificacion', 0);
    }

    #[DataProvider('acciones')]
    public function test_otro_usuario_no_puede_usar_el_pendiente_aunque_conserve_la_sesion(string $accion): void
    {
        $ruta = $this->analizar();
        $pendientes = $this->pendientes();
        $this->actingAs($this->crearUsuario());
        Excel::shouldReceive('toArray')->never();

        $this->enviar($accion, $ruta)->assertRedirect(route('programacion.importar'))
            ->assertSessionHas('error');

        Storage::disk('local')->assertExists($ruta);
        $this->assertSame($pendientes, $this->pendientes());
        $this->assertDatabaseCount('versiones_planificacion', 0);
    }

    #[DataProvider('rutasInvalidas')]
    public function test_una_ruta_mal_formada_se_rechaza_sin_tocar_el_pendiente(string $accion, mixed $ruta): void
    {
        $rutaValida = $this->analizar();
        $pendientes = $this->pendientes();
        Excel::shouldReceive('toArray')->never();

        $this->enviar($accion, $ruta)->assertRedirect(route('programacion.importar'))
            ->assertSessionHasErrors('ruta_temporal');

        Storage::disk('local')->assertExists($rutaValida);
        $this->assertSame($pendientes, $this->pendientes());
        $this->assertDatabaseCount('versiones_planificacion', 0);
    }

    public function test_cancelar_un_pendiente_conserva_el_de_la_otra_pestana(): void
    {
        $primeraRuta = $this->analizar('primera.csv');
        $segundaRuta = $this->analizar('segunda.csv');
        $this->assertCount(2, $this->pendientes());

        $this->enviar('cancelar', $primeraRuta)->assertRedirect(route('programacion.importar'));

        Storage::disk('local')->assertMissing($primeraRuta);
        Storage::disk('local')->assertExists($segundaRuta);
        $this->assertArrayNotHasKey(hash('sha256', $primeraRuta), $this->pendientes());
        $this->assertArrayHasKey(hash('sha256', $segundaRuta), $this->pendientes());
        $this->assertDatabaseCount('versiones_planificacion', 0);

        $this->enviar('confirmar', $segundaRuta)->assertRedirect(route('programacion.importar'))
            ->assertSessionHas('success');

        $this->assertDatabaseCount('versiones_planificacion', 1);
        $this->assertSame([], $this->pendientes());
    }

    public function test_confirmar_carga_datos_reales_usa_el_nombre_original_y_consumir_impide_repetir(): void
    {
        $ruta = $this->analizar('programacion-original.csv');

        $this->enviar('confirmar', $ruta, ['nombre_archivo' => 'nombre-adulterado.csv'])
            ->assertRedirect(route('programacion.importar'))->assertSessionHas('success');

        $version = VersionPlanificacion::sole();
        $this->assertSame('Programación importada - programacion-original.csv', $version->nombre);
        $this->assertSame($this->usuario->id, $version->usuario_id);
        $this->assertDatabaseHas('periodos_academicos', ['nombre' => '2026-2']);
        $this->assertDatabaseHas('asignaturas', ['codigo' => 'INF101', 'nombre' => 'Introduccion a informatica']);
        $this->assertDatabaseCount('secciones', 1);
        Storage::disk('local')->assertMissing($ruta);
        $this->assertSame([], $this->pendientes());

        $this->enviar('confirmar', $ruta)->assertRedirect(route('programacion.importar'))
            ->assertSessionHas('error');

        $this->assertDatabaseCount('versiones_planificacion', 1);
        $this->assertSame($version->getRawOriginal(), $version->fresh()->getRawOriginal());
    }

    public function test_no_se_puede_confirmar_un_pendiente_cancelado(): void
    {
        $ruta = $this->analizar();
        $this->enviar('cancelar', $ruta)->assertRedirect(route('programacion.importar'));

        $this->enviar('confirmar', $ruta)->assertRedirect(route('programacion.importar'))
            ->assertSessionHas('error');

        Storage::disk('local')->assertMissing($ruta);
        $this->assertSame([], $this->pendientes());
        $this->assertDatabaseCount('versiones_planificacion', 0);
    }

    #[DataProvider('acciones')]
    public function test_el_archivo_ausente_retira_solo_su_pendiente(string $accion): void
    {
        $ruta = $this->analizar();
        $otraRuta = $this->analizar('otra-pestana.csv');
        Storage::disk('local')->delete($ruta);
        Excel::shouldReceive('toArray')->never();

        $this->enviar($accion, $ruta)->assertRedirect(route('programacion.importar'));

        $this->assertArrayNotHasKey(hash('sha256', $ruta), $this->pendientes());
        $this->assertArrayHasKey(hash('sha256', $otraRuta), $this->pendientes());
        Storage::disk('local')->assertExists($otraRuta);
        $this->assertDatabaseCount('versiones_planificacion', 0);
    }

    public function test_si_el_archivo_pierde_su_estructura_no_se_carga_y_se_consumen_sus_datos_temporales(): void
    {
        $ruta = $this->analizar();
        Storage::disk('local')->put($ruta, "columna_desconocida\ncontenido\n");

        $this->enviar('confirmar', $ruta)->assertRedirect(route('programacion.importar'))
            ->assertSessionHas('error');

        Storage::disk('local')->assertMissing($ruta);
        $this->assertSame([], $this->pendientes());
        $this->assertDatabaseCount('versiones_planificacion', 0);
    }

    public function test_un_error_del_lector_conserva_el_pendiente_para_reintentar(): void
    {
        $ruta = $this->analizar();
        $pendientes = $this->pendientes();
        $lectorReal = Excel::getFacadeRoot();
        Excel::shouldReceive('toArray')->once()->andThrow(new RuntimeException('Fallo de lectura simulado'));

        $this->enviar('confirmar', $ruta)->assertRedirect(route('programacion.importar'))
            ->assertSessionHas('error');

        Storage::disk('local')->assertExists($ruta);
        $this->assertSame($pendientes, $this->pendientes());
        $this->assertDatabaseCount('versiones_planificacion', 0);
        Excel::swap($lectorReal);

        $this->enviar('confirmar', $ruta)->assertRedirect(route('programacion.importar'))
            ->assertSessionHas('success');

        $this->assertDatabaseCount('versiones_planificacion', 1);
        Storage::disk('local')->assertMissing($ruta);
        $this->assertSame([], $this->pendientes());
    }

    #[DataProvider('acciones')]
    public function test_un_invitado_no_puede_operar_sobre_archivos(string $accion): void
    {
        $ruta = $this->analizar();
        auth()->forgetGuards();
        Excel::shouldReceive('toArray')->never();

        $this->enviar($accion, $ruta)->assertRedirect(route('login'));

        Storage::disk('local')->assertExists($ruta);
        $this->assertDatabaseCount('versiones_planificacion', 0);
    }

    public static function acciones(): array
    {
        return [['confirmar'], ['cancelar']];
    }

    public static function rutasInvalidas(): array
    {
        $casos = [];
        foreach (['confirmar', 'cancelar'] as $accion) {
            foreach ([null, ['archivo.csv'], str_repeat('a', 256)] as $ruta) {
                $casos[] = [$accion, $ruta];
            }
        }

        return $casos;
    }

    private function crearUsuario(): User
    {
        $numero = User::count() + 1;
        $usuario = User::create([
            'name' => 'Importador de prueba '.$numero, 'rut' => (string) (77000000 + $numero),
            'email' => 'importador'.$numero.'@example.test', 'password' => 'clave-de-prueba',
        ]);
        $usuario->givePermissionTo(Permission::findOrCreate('ver programacion anterior', 'web'), Permission::findOrCreate('importar programacion anterior', 'web'));

        return $usuario;
    }

    private function analizar(string $nombre = 'programacion.csv'): string
    {
        $datos = [
            'id_curso' => '123456', 'cod_ramo' => 'INF101', 'ramo_nombre' => 'Introduccion a informatica',
            'secc' => '1', 'semestre' => '2026-2', 'sede' => 'Concepcion',
            'prof_resp' => '22222222', 'nom_prof_resp' => 'Docente De Prueba',
            'ua' => 'INSTITUTO', 'nom_carr' => 'Informatica', 'cupo' => '20',
            'modalidad' => 'Presencial', 'categoria' => 'Obligatoria', 'tipo_dictacion' => 'Semestral',
            'cod_sede' => 'CON', 'estado_curso' => 'Abierto', 'id_ramo' => '101',
        ];
        $csv = fopen('php://temp', 'r+');
        fputcsv($csv, array_keys($datos));
        fputcsv($csv, array_values($datos));
        rewind($csv);
        $contenido = stream_get_contents($csv);
        fclose($csv);
        $archivo = UploadedFile::fake()->createWithContent($nombre, $contenido)->mimeType('text/csv');

        $response = $this->post(route('programacion.importar.analizar'), ['archivo' => $archivo])
            ->assertOk()->assertViewHas('resultado', fn ($resultado) => $resultado['valido']);

        return $response->viewData('resultado')['ruta_temporal'];
    }

    private function enviar(string $accion, mixed $ruta, array $adicionales = []): TestResponse
    {
        return $this->from(route('programacion.importar'))
            ->post(route('programacion.importar.'.$accion), ['ruta_temporal' => $ruta] + $adicionales);
    }

    private function pendientes(): array
    {
        return session('importaciones_pendientes', []);
    }
}

<?php

namespace Tests\Feature;

use App\Models\{ConfiguracionInstitucion, Universidad, User};
use App\Notifications\{PasswordCambiadaNotification, ResetPasswordPersonalizado};
use App\Services\{GestionIdentidadVisual, IdentidadVisual};
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{DB, Mail, Notification, Storage};
use Spatie\Permission\Models\Permission;
use Tests\IsolatedDatabaseTestCase;

class IdentidadVisualTest extends IsolatedDatabaseTestCase
{
    private User $usuario;
    private Universidad $institucion;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->institucion = Universidad::firstOrFail();
        $this->usuario = User::create(['name' => 'Administración ficticia', 'rut' => '19876543', 'email' => 'marca@example.test', 'password' => 'ClaveFicticia123']);
        foreach (['ver configuracion', 'administrar usuarios', 'ver panel inicio', 'ver programacion semestral'] as $p) $this->usuario->givePermissionTo(Permission::findOrCreate($p, 'web'));
        $this->actingAs($this->usuario);
    }

    private function datos(array $cambios = []): array
    {
        return $cambios + ['nombre_sistema' => 'Campus Azul', 'lema_sistema' => 'Planificación académica', 'color_principal' => '#1752A2', 'color_secundario' => '#172554', 'color_fondo' => '#EFF6FF', 'firma' => app(GestionIdentidadVisual::class)->firma(), 'confirmar' => '1'];
    }

    private function guardar(array $cambios = [], ?Universidad $institucion = null)
    {
        return $this->from(route('configuracion.identidad'))->post(route('configuracion.identidad.update', $institucion ?? $this->institucion), $this->datos($cambios));
    }

    public function test_identidad_inicial_conserva_prosec_y_sus_recursos(): void
    {
        $this->get(route('configuracion.identidad'))->assertOk()->assertSee('Vista previa')->assertSee('Guardar y aplicar identidad');
        $this->get(route('login'))->assertOk()->assertSee('Iniciar sesión | PROSEC')->assertSee('/identidad/logos/prosec-logo.svg?v=')->assertSee('favicon.ico')->assertDontSee('id="tema-institucional"', false);
        $this->assertFalse(app(IdentidadVisual::class)->datos()['personalizada']);
    }

    public function test_conserva_en_cache_solo_los_logos_con_la_version_vigente(): void
    {
        foreach (IdentidadVisual::LOGOS_BASE as $archivo) {
            $fecha = filemtime(public_path('images/'.$archivo));
            $respuesta = $this->get(route('identidad.logo-base', ['archivo' => $archivo, 'v' => $fecha]));
            $respuesta->assertOk()->assertHeader('Content-Type', 'image/svg+xml');
            $this->assertTrue($respuesta->headers->hasCacheControlDirective('immutable'));

            $anterior = $this->get(route('identidad.logo-base', ['archivo' => $archivo, 'v' => $fecha - 1]));
            $anterior->assertOk();
            $this->assertFalse($anterior->headers->hasCacheControlDirective('immutable'));
        }

        $this->get(route('identidad.logo-base', 'favicon.svg'))->assertRedirect(route('login'));
    }

    public function test_aplica_nombre_y_colores_sin_cambiar_datos_academicos_ni_cuentas(): void
    {
        $antes = DB::table('users')->get()->toJson();
        $this->guardar(['nombre_sistema' => '  Campus   Azul  '])->assertSessionHasNoErrors()->assertRedirect();
        $c = ConfiguracionInstitucion::sole();
        $this->assertTrue($c->identidad_activa); $this->assertSame('Campus Azul', $c->nombre_sistema);
        $this->assertSame($antes, DB::table('users')->get()->toJson());
        $this->assertDatabaseCount('historial_acciones', 1);
        $this->get(route('login'))->assertOk()->assertSee('Iniciar sesión | Campus Azul')->assertSee('--color-institucional: #1752A2;', false)->assertSee('Planificación académica')->assertDontSee('/identidad/logos/main-logo.svg');
        $this->get(route('configuracion.index'))->assertOk()->assertSee('Configuración institucional | Campus Azul')->assertSee('Personalizar identidad');
        $this->get(route('administracion.docentes.index'))->assertOk()->assertSee('Docentes | Campus Azul');
    }

    public function test_no_cambia_colores_por_actualizar_solo_datos_institucionales(): void
    {
        $this->guardar()->assertSessionHasNoErrors();
        $this->put(route('configuracion.institucion.update', $this->institucion), ['nombre' => 'Institución nueva', 'sigla' => 'IN'])->assertSessionHasNoErrors();
        $this->assertSame('#1752A2', ConfiguracionInstitucion::sole()->color_principal);
        $this->get(route('login'))->assertOk()->assertSee('Campus Azul')->assertSee('Institución nueva');
    }

    public function test_cambiar_solo_la_paleta_conserva_los_logos_y_favicon_originales_de_la_web(): void
    {
        $campos = ['principal_url', 'compacto_url', 'institucion_url', 'favicon_url', 'apple_url'];
        $identidad = app(IdentidadVisual::class);
        $antes = collect($identidad->datos())->only($campos)->all();

        $this->guardar([
            'nombre_sistema' => IdentidadVisual::PREDETERMINADA['nombre'],
            'lema_sistema' => IdentidadVisual::PREDETERMINADA['lema'],
            'color_principal' => '#1752A2',
        ])->assertSessionHasNoErrors();

        $this->get(route('login'))->assertOk()
            ->assertSee('--color-institucional: #1752A2;', false)
            ->assertSee('/identidad/logos/prosec-logo.svg?v=')
            ->assertSee('/identidad/logos/main-logo.svg?v=')
            ->assertSee('favicon.ico');
        $this->assertSame($antes, collect($identidad->datos())->only($campos)->all());
        $this->assertTrue($identidad->datos()['favicon_base']);
    }

    public function test_perfil_lectura_consulta_sin_aplicar_ni_restaurar(): void
    {
        $this->usuario->revokePermissionTo('administrar usuarios');
        $this->get(route('configuracion.identidad'))->assertOk()->assertDontSee('Guardar y aplicar identidad')->assertSee('La edición requiere permisos de administración.');
        $this->guardar()->assertRedirect();
        $this->post(route('configuracion.identidad.restaurar'), ['firma' => app(GestionIdentidadVisual::class)->firma(), 'confirmar_restauracion' => '1'])->assertRedirect();
        $this->assertDatabaseCount('configuracion_institucion', 0); $this->assertDatabaseCount('historial_acciones', 0);
    }

    public function test_administracion_sin_permiso_configuracion_tampoco_aplica(): void
    {
        $this->usuario->revokePermissionTo('ver configuracion');
        $this->get(route('configuracion.identidad'))->assertRedirect(); $this->guardar()->assertRedirect();
        $this->assertDatabaseCount('configuracion_institucion', 0);
    }

    public function test_valida_nombre_colores_fondo_contraste_y_confirmacion(): void
    {
        foreach ([['nombre_sistema' => ' '], ['nombre_sistema' => '<script>'], ['nombre_sistema' => str_repeat('a', 81)], ['color_principal' => 'red'], ['color_principal' => '#000;}</style>'], ['color_principal' => ['#fff']], ['color_secundario' => '#FFFFFF'], ['color_fondo' => '#000000'], ['confirmar' => '0'], ['lema_sistema' => str_repeat('a', 151)], ['firma' => 'incorrecta']] as $cambios) {
            $this->guardar($cambios)->assertSessionHasErrors();
            $this->assertDatabaseCount('configuracion_institucion', 0);
        }
    }

    public function test_color_principal_claro_usa_texto_oscuro_y_enlaces_legibles(): void
    {
        $this->guardar(['color_principal' => '#FACC15'])->assertSessionHasNoErrors();
        $this->get(route('login'))->assertOk()->assertSee('--color-sobre-institucional: #000000;', false);
        $d = app(IdentidadVisual::class)->datos();
        $this->assertGreaterThanOrEqual(4.5, IdentidadVisual::contraste($d['principal'], $d['sobre_principal']));
        $this->assertGreaterThanOrEqual(4.5, IdentidadVisual::contraste($d['enlace'], $d['fondo']));
        foreach (['#777777', '#888888', '#FFFFFF', '#000000', '#D12421', '#AB7777'] as $c) $this->assertGreaterThanOrEqual(4.5, IdentidadVisual::contraste($c, IdentidadVisual::textoSobre($c)));
    }

    public function test_carga_los_cuatro_recursos_con_nombres_aleatorios_y_contenido_png(): void
    {
        $files = []; foreach (GestionIdentidadVisual::IMAGENES as $campo) $files[$campo] = UploadedFile::fake()->image($campo.'.jpg', 160, 160);
        $this->guardar($files)->assertSessionHasNoErrors();
        $c = ConfiguracionInstitucion::sole();
        foreach (GestionIdentidadVisual::IMAGENES as $campo) {
            $this->assertMatchesRegularExpression('/^identidad\/[a-f0-9]{32}\.png$/', $c->$campo);
            Storage::disk('local')->assertExists($c->$campo);
            $this->assertSame(IMAGETYPE_PNG, getimagesizefromstring(Storage::disk('local')->get($c->$campo))[2]);
            $this->get(route('identidad.archivo', basename($c->$campo)))->assertOk()->assertHeader('Content-Type', 'image/png')->assertHeader('X-Content-Type-Options', 'nosniff');
        }
        $this->get(route('login'))->assertOk()->assertSee(route('identidad.archivo', basename($c->logo)))->assertSee(route('identidad.archivo', basename($c->favicon)));
        $this->assertCount(4, Storage::disk('local')->allFiles('identidad'));
    }

    public function test_recursos_de_marca_se_pueden_ver_sin_sesion(): void
    {
        $this->guardar(['favicon' => UploadedFile::fake()->image('icono.png', 64, 64)])->assertSessionHasNoErrors();
        $ruta = ConfiguracionInstitucion::sole()->favicon;
        auth()->logout();
        $this->get(route('identidad.archivo', basename($ruta)))->assertOk();
        $this->get(route('identidad.marca', 'principal'))->assertOk()->assertHeader('Content-Type', 'image/svg+xml')->assertSee('Campus Azul');
        $this->get(route('identidad.marca', 'favicon'))->assertOk()->assertSee('rx="26"', false);
        $this->get(route('identidad.icono'))->assertOk()->assertHeader('Content-Type', 'image/png');
    }

    public function test_rechaza_svg_archivos_falsos_favicon_rectangular_y_tamano_excesivo(): void
    {
        foreach ([
            ['logo' => UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>')],
            ['logo' => UploadedFile::fake()->createWithContent('logo.png', '<?php echo "no";')],
            ['favicon' => UploadedFile::fake()->image('favicon.png', 100, 50)],
            ['logo' => UploadedFile::fake()->image('grande.png', 3001, 32)],
            ['logo' => UploadedFile::fake()->image('pesado.png', 100, 100)->size(2049)],
        ] as $cambios) {
            $this->guardar($cambios)->assertSessionHasErrors(); $this->assertDatabaseCount('configuracion_institucion', 0);
            $this->assertCount(0, Storage::disk('local')->allFiles());
        }
    }

    public function test_recodificar_elimina_contenido_anadido_a_una_imagen(): void
    {
        $archivo = UploadedFile::fake()->image('logo.png', 100, 100);
        file_put_contents($archivo->getRealPath(), '<?php echo "codigo-no-publicable"; ?>', FILE_APPEND);
        $this->guardar(['logo' => $archivo])->assertSessionHasNoErrors();
        $this->assertStringNotContainsString('codigo-no-publicable', Storage::disk('local')->get(ConfiguracionInstitucion::sole()->logo));
    }

    public function test_png_transparente_conserva_la_transparencia(): void
    {
        $imagen = imagecreatetruecolor(64, 64); imagealphablending($imagen, false); imagesavealpha($imagen, true);
        imagefill($imagen, 0, 0, imagecolorallocatealpha($imagen, 0, 0, 0, 127));
        ob_start(); imagepng($imagen); $png = ob_get_clean(); imagedestroy($imagen);
        $this->guardar(['logo' => UploadedFile::fake()->createWithContent('transparente.png', $png)])->assertSessionHasNoErrors();
        $resultado = imagecreatefromstring(Storage::disk('local')->get(ConfiguracionInstitucion::sole()->logo));
        $this->assertSame(127, imagecolorsforindex($resultado, imagecolorat($resultado, 0, 0))['alpha']);
        imagedestroy($resultado);
    }

    public function test_otra_institucion_sin_personalizacion_no_muestra_el_logo_ucsc(): void
    {
        $this->institucion->update(['nombre' => 'Instituto Andino', 'sigla' => 'IA']);
        $this->get(route('login'))->assertOk()->assertSee('Instituto Andino')->assertSee('/identidad/marca/institucion')->assertDontSee('/identidad/logos/main-logo.svg');
        $this->assertSame(IMAGETYPE_PNG, getimagesizefromstring(app(IdentidadVisual::class)->png('institucion'))[2]);
    }

    public function test_reemplazar_y_quitar_imagen_conserva_archivo_anterior(): void
    {
        $this->guardar(['logo' => UploadedFile::fake()->image('uno.png', 100, 100)])->assertSessionHasNoErrors();
        $anterior = ConfiguracionInstitucion::sole()->logo;
        $this->guardar(['logo' => UploadedFile::fake()->image('dos.png', 100, 100)])->assertSessionHasNoErrors();
        $nuevo = ConfiguracionInstitucion::sole()->logo;
        $this->assertNotSame($anterior, $nuevo); Storage::disk('local')->assertExists($anterior);
        $this->guardar(['quitar_logo' => '1'])->assertSessionHasNoErrors();
        $this->assertNull(ConfiguracionInstitucion::sole()->logo); Storage::disk('local')->assertExists($nuevo);
        $this->get(route('login'))->assertOk()->assertSee('/identidad/marca/principal');
    }

    public function test_no_permite_quitar_y_cargar_la_misma_imagen(): void
    {
        $this->guardar(['quitar_logo' => '1', 'logo' => UploadedFile::fake()->image('uno.png', 100, 100)])->assertSessionHasErrors('logo');
        $this->assertDatabaseCount('configuracion_institucion', 0);
    }

    public function test_no_acepta_rutas_de_archivo_aportadas_en_vez_de_imagenes(): void
    {
        $this->guardar(['logo' => '../../.env'])->assertSessionHasErrors('logo');
        $this->get('/identidad/archivos/.env')->assertRedirect(route('login'));
        $this->get(route('identidad.archivo', str_repeat('a', 32).'.png'))->assertNotFound();
        $this->assertNull(app(IdentidadVisual::class)->archivoValido('../../.env'));
    }

    public function test_firma_desactualizada_impide_aplicar_y_no_deja_archivos(): void
    {
        $datos = $this->datos(['logo' => UploadedFile::fake()->image('logo.png', 100, 100)]);
        $this->institucion->update(['nombre' => 'Nombre cambiado']);
        $this->post(route('configuracion.identidad.update', $this->institucion), $datos)->assertSessionHasErrors('firma');
        $this->assertDatabaseCount('configuracion_institucion', 0); $this->assertCount(0, Storage::disk('local')->allFiles());
    }

    public function test_cambiar_institucion_activa_solo_una_identidad(): void
    {
        $this->guardar()->assertSessionHasNoErrors();
        $otra = Universidad::create(['nombre' => 'Instituto de ejemplo', 'sigla' => 'IE', 'estado' => true]);
        $this->guardar(['nombre_sistema' => 'Campus Verde', 'color_principal' => '#166534'], $otra)->assertSessionHasNoErrors();
        $this->assertSame(1, ConfiguracionInstitucion::where('identidad_activa', true)->count());
        $this->get(route('login'))->assertOk()->assertSee('Campus Verde')->assertSee('Instituto de ejemplo')->assertDontSee('Campus Azul');
        $this->get(route('configuracion.identidad', ['universidad_id' => $this->institucion->id]))->assertOk()->assertSee('Campus Azul')->assertSee('Campus Verde');
        $this->assertSame(2, Universidad::count());
    }

    public function test_institucion_inactiva_no_puede_ser_identidad_activa(): void
    {
        $this->institucion->update(['estado' => false]);
        $this->guardar()->assertNotFound();
        $this->get(route('configuracion.identidad'))->assertOk()->assertSee('Registra una institución activa');
        $this->assertDatabaseCount('configuracion_institucion', 0);
    }

    public function test_restaurar_requiere_confirmacion_y_conserva_configuracion_e_imagenes(): void
    {
        $this->guardar(['logo' => UploadedFile::fake()->image('logo.png', 100, 100)])->assertSessionHasNoErrors();
        $ruta = ConfiguracionInstitucion::sole()->logo;
        $datos = ['firma' => app(GestionIdentidadVisual::class)->firma()];
        $this->post(route('configuracion.identidad.restaurar'), $datos)->assertSessionHasErrors('confirmar_restauracion');
        $this->post(route('configuracion.identidad.restaurar'), $datos + ['confirmar_restauracion' => '1'])->assertSessionHasNoErrors();
        $this->get(route('login'))->assertOk()->assertSee('Iniciar sesión | PROSEC')->assertSee('favicon.ico');
        $this->assertFalse(ConfiguracionInstitucion::sole()->identidad_activa); Storage::disk('local')->assertExists($ruta);
        $this->assertSame('Campus Azul', ConfiguracionInstitucion::sole()->nombre_sistema);
        $this->assertDatabaseHas('historial_acciones', ['accion' => 'Restaurar identidad PROSEC']);
    }

    public function test_fallo_de_auditoria_revierte_identidad_y_retira_solo_las_imagenes_nuevas(): void
    {
        $this->guardar(['logo' => UploadedFile::fake()->image('anterior.png', 100, 100)])->assertSessionHasNoErrors();
        $antes = ConfiguracionInstitucion::sole()->getAttributes();
        DB::unprepared("CREATE TRIGGER falla_marca BEFORE INSERT ON historial_acciones BEGIN SELECT RAISE(ABORT, 'fallo simulado'); END");
        $this->withoutExceptionHandling();
        try { $this->guardar(['nombre_sistema' => 'Otro nombre', 'logo' => UploadedFile::fake()->image('nuevo.png', 100, 100)]); $this->fail(); }
        catch (\Illuminate\Database\QueryException) { $this->assertSame($antes, ConfiguracionInstitucion::sole()->getAttributes()); }
        $this->assertCount(1, Storage::disk('local')->allFiles('identidad'));
        Storage::disk('local')->assertExists($antes['logo']);
    }

    public function test_aplicar_mismos_valores_no_duplica_configuracion_ni_historial(): void
    {
        $this->guardar()->assertSessionHasNoErrors(); $this->guardar()->assertSessionHasNoErrors();
        $this->assertDatabaseCount('configuracion_institucion', 1); $this->assertDatabaseCount('historial_acciones', 1);
    }

    public function test_subtitulo_vacio_se_conserva_al_reabrir(): void
    {
        $this->guardar(['lema_sistema' => ''])->assertSessionHasNoErrors();
        $this->get(route('login'))->assertOk()->assertDontSee('Programación Semestral de Cursos');
        $this->get(route('configuracion.identidad'))->assertOk()->assertDontSee('value="Programación Semestral de Cursos"', false);
    }

    public function test_nombre_con_comillas_se_escapa_en_svg_y_en_html(): void
    {
        $this->guardar(['nombre_sistema' => 'Campus "Azul" & Norte'])->assertSessionHasNoErrors();
        $this->get(route('identidad.marca', 'principal'))->assertOk()->assertSee('Campus &quot;Azul&quot; &amp; Norte', false);
        $this->get(route('login'))->assertOk()->assertSee('Campus &quot;Azul&quot; &amp; Norte', false);
    }

    public function test_correo_usa_identidad_configurada_sin_adjuntos(): void
    {
        $this->guardar()->assertSessionHasNoErrors();
        config(['mail.asset_url' => 'https://horarios.example.edu']);
        $notificaciones = [
            'Recuperación de contraseña | Campus Azul' => new ResetPasswordPersonalizado('token-ficticio'),
            'Contraseña actualizada | Campus Azul' => new PasswordCambiadaNotification,
        ];

        $transporte = Mail::mailer('array')->getSymfonyTransport();
        foreach ($notificaciones as $asunto => $notificacion) {
            Notification::sendNow($this->usuario, $notificacion);
            $envio = $transporte->messages()->last();
            $mensaje = $envio->getOriginalMessage();
            $this->assertSame($asunto, $mensaje->getSubject());
            $this->assertSame('Campus Azul - '.$this->institucion->sigla, $mensaje->getFrom()[0]->getName());
            $this->assertSame(config('mail.from.address'), $mensaje->getFrom()[0]->getAddress());
            $html = $mensaje->getHtmlBody();
            $this->assertStringContainsString('Campus Azul', $html);
            $this->assertStringContainsString('#1752A2', $html);
            $this->assertStringContainsString('https://horarios.example.edu/identidad/correo/principal.png?v=', $html);
            $this->assertStringContainsString('https://horarios.example.edu/identidad/correo/institucion.png?v=', $html);
            $this->assertStringNotContainsString('PROSEC', $html);
            $this->assertStringNotContainsString('cid:', $html);
            $this->assertSame([], $mensaje->getAttachments());
            $this->assertStringNotContainsString('Content-Disposition:', $envio->toString());
        }
        $this->assertCount(2, $transporte->messages());
    }

    public function test_correo_local_muestra_nombre_institucional_sin_adjuntos(): void
    {
        config(['mail.asset_url' => null, 'app.url' => 'http://127.0.0.1:8000']);
        $mail = (new PasswordCambiadaNotification)->toMail($this->usuario);
        $html = view($mail->view, $mail->viewData)->render();
        $this->assertSame('PROSEC - UCSC', $mail->from[1]);
        $this->assertStringContainsString('PRO<span style="color:#D12421;">SEC</span>', $html);
        $this->assertStringContainsString('>Programación Semestral de Cursos</span>', $html);
        $this->assertStringContainsString('>UCSC</strong>', $html);
        $this->assertStringNotContainsString('<img', $html);
    }

    public function test_logos_de_correo_son_png_optimizados_accesibles_sin_sesion(): void
    {
        config(['mail.asset_url' => null, 'app.url' => 'https://horarios.example.edu/prosec']);
        $this->app['auth']->forgetGuards();
        $this->assertGuest();
        $identidad = app(IdentidadVisual::class);
        foreach (IdentidadVisual::ANCHOS_LOGOS_CORREO as $tipo => $ancho) {
            $url = $identidad->logoCorreoUrl($tipo);
            $ruta = route('identidad.logo-correo', ['tipo' => $tipo, 'v' => $identidad->logoCorreoVersion($tipo)], false);
            $this->assertSame('https://horarios.example.edu/prosec'.$ruta, $url);
            $respuesta = $this->get($ruta)->assertOk()->assertHeader('Content-Type', 'image/png');
            $this->assertTrue($respuesta->headers->hasCacheControlDirective('immutable'));
            $this->assertSame([], $respuesta->headers->getCookies());
            $datos = getimagesizefromstring($respuesta->getContent());
            $this->assertSame($ancho, $datos[0]);
            $this->assertSame(IMAGETYPE_PNG, $datos[2]);
            $this->assertLessThan(65_000, strlen($respuesta->getContent()));
            $this->withHeaders(['If-None-Match' => $respuesta->headers->get('ETag')])->get($ruta)->assertStatus(304);
        }
    }

    public function test_imagenes_para_pdf_y_correo_tienen_formato_y_proporciones_controladas(): void
    {
        $this->guardar(['logo' => UploadedFile::fake()->image('alto.png', 32, 1000)])->assertSessionHasNoErrors();
        $this->get(route('login'))->assertOk();
        foreach (['principal', 'institucion', 'favicon'] as $tipo) {
            $datos = getimagesizefromstring(app(IdentidadVisual::class)->png($tipo));
            $this->assertSame(IMAGETYPE_PNG, $datos[2]); $this->assertSame($tipo === 'favicon' ? 180 : 900, $datos[0]);
            $this->assertSame($tipo === 'favicon' ? 180 : 220, $datos[1]);
        }
    }

    public function test_campos_malformados_recuperan_el_formulario(): void
    {
        $this->guardar(['nombre_sistema' => ['texto'], 'color_principal' => ['azul']])->assertSessionHasErrors();
        $this->get(route('configuracion.identidad'))->assertOk();
    }
}

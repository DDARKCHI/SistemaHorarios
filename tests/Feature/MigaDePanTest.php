<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\MigaDePan;
use DOMDocument;
use DOMXPath;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Permission;
use Tests\IsolatedDatabaseTestCase;

class MigaDePanTest extends IsolatedDatabaseTestCase
{
    public function test_los_formularios_de_laboratorio_regresan_al_catalogo_del_mismo_tipo(): void
    {
        $usuario = $this->usuario('gestionar salas y recursos');
        foreach (['fijo', 'movil'] as $tipo) {
            foreach (['create' => ['/laboratorios/'.$tipo.'/crear', 'Crear laboratorio'],
                'edit' => ['/laboratorios/'.$tipo.'/1/editar', 'Editar laboratorio']] as [$url, $etiqueta]) {
                $request = Request::create($url);
                $ruta = Route::getRoutes()->match($request);
                $request->setRouteResolver(fn () => $ruta);
                $migas = MigaDePan::para($request, $usuario);
                $this->assertSame(['Laboratorios', $etiqueta], array_column($migas, 'label'));
                $this->assertSame(route('laboratorios.index', ['tipo' => $tipo]), $migas[0]['url']);
            }
        }
    }

    public function test_inicio_solo_tiene_una_pagina_actual_sin_enlace(): void
    {
        $usuario = $this->usuario('ver panel inicio');

        $this->assertSame([
            ['label' => 'Inicio', 'url' => null, 'current' => true],
        ], MigaDePan::para($this->solicitud('inicio'), $usuario));
    }

    public function test_los_indices_tienen_etiqueta_correcta_y_no_se_enlazan_a_si_mismos(): void
    {
        $usuario = $this->usuario('ver panel inicio');

        foreach ([
            'programacion.semestral' => 'Programación semestral',
            'programacion.index' => 'Programación anterior',
            'administracion.index' => 'Administración',
            'programacion.intercambio' => 'Intercambio docente',
            'salas.index' => 'Salas y recursos',
            'configuracion.index' => 'Configuración',
        ] as $ruta => $etiqueta) {
            $miga = MigaDePan::para($this->solicitud($ruta), $usuario);
            $actual = $miga[array_key_last($miga)];

            $this->assertSame($etiqueta, $actual['label'], $ruta);
            $this->assertTrue($actual['current'], $ruta);
            $this->assertNull($actual['url'], $ruta);
            $this->assertCount(1, array_filter($miga, fn ($elemento) => $elemento['current']), $ruta);
            $this->assertNotContains(route($ruta), array_column($miga, 'url'), $ruta);
        }
    }

    public function test_asignaciones_y_propuesta_regresan_a_la_programacion_semestral(): void
    {
        $usuario = $this->usuario('ver programacion semestral');

        foreach ([
            'programacion.asignacion.profesores' => 'Asignación de profesores',
            'programacion.asignacion.horarios' => 'Asignación de horarios',
            'programacion.propuesta' => 'Propuesta de horario',
        ] as $ruta => $etiqueta) {
            $miga = MigaDePan::para($this->solicitud($ruta), $usuario);

            $this->assertSame(['Programación semestral', $etiqueta], array_column($miga, 'label'));
            $this->assertSame(route('programacion.semestral'), $miga[0]['url']);
            $this->assertFalse($miga[0]['current']);
            $this->assertSame(['label' => $etiqueta, 'url' => null, 'current' => true], $miga[1]);
        }
    }

    public function test_importacion_regresa_a_la_programacion_anterior(): void
    {
        $usuario = $this->usuario('ver programacion anterior');
        $miga = MigaDePan::para($this->solicitud('programacion.importar'), $usuario);

        $this->assertSame(['Programación anterior', 'Cargar programación anterior'], array_column($miga, 'label'));
        $this->assertSame(route('programacion.index'), $miga[0]['url']);
        $this->assertNull($miga[1]['url']);
        $this->assertTrue($miga[1]['current']);
    }

    public function test_crear_y_editar_usuario_regresan_a_administracion(): void
    {
        $usuario = $this->usuario('administrar usuarios');

        foreach (['usuarios.create' => 'Crear usuario', 'usuarios.edit' => 'Editar usuario'] as $ruta => $etiqueta) {
            $miga = MigaDePan::para($this->solicitud($ruta), $usuario);

            $this->assertSame(['Administración', $etiqueta], array_column($miga, 'label'));
            $this->assertSame(route('administracion.index'), $miga[0]['url']);
            $this->assertSame(['label' => $etiqueta, 'url' => null, 'current' => true], $miga[1]);
        }
    }

    public function test_el_analisis_post_del_excel_mantiene_la_miga_de_importacion_con_enlaces_solo_get(): void
    {
        $usuario = $this->usuario('ver panel inicio', 'ver programacion anterior');
        $ruta = Route::getRoutes()->getByName('programacion.importar.analizar');
        $this->assertNotNull($ruta);
        $request = Request::create('/programacion/importar/analizar', 'POST', [
            'ruta_temporal' => 'importaciones/archivo-de-prueba.xlsx',
            'nombre_archivo' => 'prueba.xlsx', 'periodo_id' => '999', 'version_id' => '999',
        ]);
        $request->setRouteResolver(fn () => $ruta);

        $miga = MigaDePan::para($request, $usuario);

        $this->assertSame(['Inicio', 'Programación anterior', 'Cargar programación anterior'], array_column($miga, 'label'));
        $this->assertSame(route('inicio'), $miga[0]['url']);
        $this->assertSame(route('programacion.index'), $miga[1]['url']);
        $this->assertSame(['label' => 'Cargar programación anterior', 'url' => null, 'current' => true], $miga[2]);
        $this->assertNotContains(route('programacion.importar.analizar'), array_column($miga, 'url'));
    }

    public function test_inicio_aparece_como_ancestro_solo_cuando_el_usuario_tiene_permiso(): void
    {
        $solicitud = $this->solicitud('programacion.asignacion.horarios');
        $conInicio = $this->usuario('ver panel inicio', 'ver programacion semestral');
        $sinInicio = $this->usuario('ver programacion semestral');
        $migaConInicio = MigaDePan::para($solicitud, $conInicio);
        $migaSinInicio = MigaDePan::para($solicitud, $sinInicio);

        $this->assertSame('Inicio', $migaConInicio[0]['label']);
        $this->assertSame(route('inicio'), $migaConInicio[0]['url']);
        $this->assertNotContains('Inicio', array_column($migaSinInicio, 'label'));
    }

    public function test_el_padre_sin_permiso_se_muestra_como_texto_y_no_como_enlace(): void
    {
        $usuario = $this->usuario('asignar horarios');
        $miga = MigaDePan::para($this->solicitud('programacion.asignacion.horarios'), $usuario);

        $this->assertSame('Programación semestral', $miga[0]['label']);
        $this->assertNull($miga[0]['url']);
        $this->assertFalse($miga[0]['current']);
        $this->assertTrue($miga[1]['current']);
        $this->assertNotContains('Inicio', array_column($miga, 'label'));
    }

    public function test_sin_usuario_no_se_generan_enlaces_a_pantallas_privadas(): void
    {
        $miga = MigaDePan::para($this->solicitud('programacion.asignacion.horarios'), null);

        $this->assertNotEmpty($miga);
        $this->assertSame([], array_values(array_filter(array_column($miga, 'url'))));
        $this->assertCount(1, array_filter($miga, fn ($elemento) => $elemento['current']));
    }

    public function test_los_padres_academicos_conservan_solo_periodo_y_version_positivos(): void
    {
        $usuario = $this->usuario('ver programacion semestral', 'ver programacion anterior');
        $consulta = [
            'periodo_id' => '7', 'version_id' => '22', 'carrera_id' => '99',
            'asignatura_id' => '31', 'token' => 'dato-de-prueba', 'return_to' => 'https://example.test/externo',
        ];

        foreach (['programacion.asignacion.profesores', 'programacion.importar'] as $ruta) {
            $miga = MigaDePan::para($this->solicitud($ruta, $consulta), $usuario);
            parse_str(parse_url($miga[0]['url'], PHP_URL_QUERY) ?? '', $parametros);

            $this->assertSame(['periodo_id' => '7', 'version_id' => '22'], $parametros);
            $this->assertSame(parse_url(route('inicio'), PHP_URL_HOST), parse_url($miga[0]['url'], PHP_URL_HOST));
        }
    }

    public function test_descarta_filtros_invalidos_y_parametros_compuestos(): void
    {
        $usuario = $this->usuario('ver programacion semestral');

        foreach ([
            ['periodo_id' => '0', 'version_id' => '-2'],
            ['periodo_id' => 'abc', 'version_id' => 'https://example.test'],
            ['periodo_id' => ['7'], 'version_id' => ['22']],
            ['periodo_id' => null, 'version_id' => ''],
        ] as $consulta) {
            $miga = MigaDePan::para($this->solicitud('programacion.asignacion.horarios', $consulta), $usuario);

            $this->assertSame(route('programacion.semestral'), $miga[0]['url']);
        }

        $miga = MigaDePan::para($this->solicitud('programacion.asignacion.horarios', [
            'periodo_id' => '0', 'version_id' => '22',
        ]), $usuario);
        parse_str(parse_url($miga[0]['url'], PHP_URL_QUERY) ?? '', $parametros);
        $this->assertSame(['version_id' => '22'], $parametros);
    }

    public function test_inicio_y_administracion_no_arrastran_los_filtros_academicos(): void
    {
        $usuario = $this->usuario('ver panel inicio', 'administrar usuarios');
        $miga = MigaDePan::para($this->solicitud('usuarios.create', [
            'periodo_id' => '7', 'version_id' => '22', 'carrera_id' => '99',
        ]), $usuario);

        $this->assertSame(route('inicio'), $miga[0]['url']);
        $this->assertSame(route('administracion.index'), $miga[1]['url']);
        $this->assertNull($miga[2]['url']);
    }

    public function test_navbar_expone_una_miga_semantica_con_una_sola_pagina_actual_sin_enlace(): void
    {
        $usuario = $this->usuario('ver panel inicio', 'administrar usuarios');
        $this->actingAs($usuario);
        $this->app->instance('request', $this->solicitud('usuarios.edit'));
        $html = view('components.navbar')->render();
        $dom = new DOMDocument;
        $erroresAnteriores = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">'.$html);
        libxml_clear_errors();
        libxml_use_internal_errors($erroresAnteriores);
        $xpath = new DOMXPath($dom);
        $navegaciones = $xpath->query('//nav[@aria-label="Miga de pan"]');

        $this->assertSame(1, $navegaciones->length);
        $nav = $navegaciones->item(0);
        $this->assertSame(3, $xpath->query('.//ol/li', $nav)->length);
        $actuales = $xpath->query('.//*[@aria-current="page"]', $nav);
        $this->assertSame(1, $actuales->length);
        $this->assertSame('Editar usuario', trim($actuales->item(0)->textContent));
        $this->assertSame(0, $xpath->query('.//*[@aria-current="page"]/ancestor::a', $nav)->length);
        $enlaces = $xpath->query('.//a', $nav);
        $this->assertSame(2, $enlaces->length);
        $this->assertSame(route('inicio'), $enlaces->item(0)->getAttribute('href'));
        $this->assertSame(route('administracion.index'), $enlaces->item(1)->getAttribute('href'));
    }

    private function usuario(string ...$permisos): User
    {
        $numero = User::count() + 1;
        $usuario = User::create([
            'name' => "Usuario de prueba $numero", 'rut' => (string) (11000000 + $numero),
            'email' => "miga$numero@example.test", 'password' => 'clave-de-prueba',
        ]);

        foreach ($permisos as $permiso) {
            $usuario->givePermissionTo(Permission::findOrCreate($permiso, 'web'));
        }

        return $usuario;
    }

    private function solicitud(string $nombreRuta, array $consulta = []): Request
    {
        $ruta = Route::getRoutes()->getByName($nombreRuta);
        $this->assertNotNull($ruta, $nombreRuta);
        $request = Request::create('/prueba-miga-de-pan', 'GET', $consulta);
        $request->setRouteResolver(fn () => $ruta);

        return $request;
    }
}

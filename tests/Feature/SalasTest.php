<?php

namespace Tests\Feature;

use App\Models\Sala;
use App\Models\Sede;
use App\Models\TipoSala;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Permission;
use Tests\IsolatedDatabaseTestCase;

class SalasTest extends IsolatedDatabaseTestCase
{
    private User $usuario;

    private int $universidadId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->universidadId = DB::table('universidades')->value('id');
        $this->usuario = User::create([
            'name' => 'Consulta de salas', 'rut' => '77777777',
            'email' => 'salas@example.test', 'password' => 'clave-de-prueba',
        ]);
        $this->usuario->givePermissionTo(Permission::findOrCreate('gestionar salas y recursos', 'web'));
        $this->actingAs($this->usuario);
    }

    public function test_un_catalogo_vacio_muestra_la_consulta_sin_registros_ficticios(): void
    {
        $this->consultar()->assertOk()->assertViewIs('salas.index')
            ->assertViewHas('salas', fn ($salas) => $salas->total() === 0)
            ->assertViewHas('resumen', ['total' => 0, 'activas' => 0, 'sedes' => 0])
            ->assertViewHas('hayFiltros', false)
            ->assertDontSee('Esta sección se encuentra en desarrollo.');
    }

    public function test_por_defecto_muestra_salas_activas_con_carga_de_relaciones_y_resumen_global(): void
    {
        $datos = $this->catalogo();
        $antes = $this->snapshot();

        $response = $this->consultar()->assertOk()
            ->assertViewHas('resumen', ['total' => 5, 'activas' => 4, 'sedes' => 2])
            ->assertViewHas('hayFiltros', false)
            ->assertSee('Sin capacidad informada');

        $this->assertSame($this->ids($datos, ['aula', 'auditorio', 'taller', 'sin_capacidad']), $this->salasIds($response));
        $this->assertSame('activas', $response->viewData('filtros')['estado']);
        foreach ($response->viewData('salas') as $sala) {
            $this->assertTrue($sala->relationLoaded('sede'));
            $this->assertTrue($sala->relationLoaded('tipoSala'));
        }
        $this->assertSame($antes, $this->snapshot());
    }

    public function test_combina_sede_tipo_capacidad_estado_y_uso_sin_que_la_busqueda_eluda_los_filtros(): void
    {
        $datos = $this->catalogo();
        $esperada = $this->sala($datos['sede'], $datos['tipo'], [
            'codigo' => 'NORTE-80', 'capacidad' => 80,
        ]);
        $this->sala($datos['sede'], $datos['tipo'], ['codigo' => 'NORTE-10', 'capacidad' => 10]);
        $this->sala($datos['otra_sede'], $datos['tipo'], ['codigo' => 'NORTE-90', 'capacidad' => 90]);
        $this->sala($datos['sede'], $datos['otro_tipo'], ['codigo' => 'NORTE-70', 'capacidad' => 70]);
        $this->sala($datos['sede'], $datos['tipo'], ['codigo' => 'NORTE-60', 'capacidad' => 60, 'disponible' => false]);
        $this->sala($datos['sede'], $datos['tipo'], ['codigo' => 'NORTE-50', 'capacidad' => 50, 'estado' => false]);

        $response = $this->consultar([
            'q' => 'NORTE', 'sede_id' => $datos['sede']->id, 'tipo_sala_id' => $datos['tipo']->id,
            'capacidad_minima' => 50, 'estado' => 'activas', 'uso' => 'habilitadas',
        ])->assertOk()->assertViewHas('hayFiltros', true);

        $this->assertSame([$esperada->id], $this->salasIds($response));
        $this->assertSame(11, $response->viewData('resumen')['total']);
    }

    #[DataProvider('camposDeBusqueda')]
    public function test_busca_en_codigo_nombre_y_edificio(string $campo): void
    {
        $datos = $this->catalogo();
        $esperada = $this->sala($datos['sede'], $datos['tipo'], [$campo => 'Referencia Singular']);

        $response = $this->consultar(['q' => 'Singular'])->assertOk();

        $this->assertSame([$esperada->id], $this->salasIds($response));
    }

    #[DataProvider('caracteresLiterales')]
    public function test_los_caracteres_de_like_se_buscan_como_texto_literal(string $caracter): void
    {
        $datos = $this->catalogo();
        $esperada = $this->sala($datos['sede'], $datos['tipo'], ['codigo' => 'REF'.$caracter.'LITERAL']);
        $this->sala($datos['sede'], $datos['tipo'], ['codigo' => 'REF-OTRA']);

        $response = $this->consultar(['q' => $caracter])->assertOk();

        $this->assertSame([$esperada->id], $this->salasIds($response));
    }

    public function test_un_termino_en_blanco_no_filtra_y_el_aforo_cero_no_descarta_salas(): void
    {
        $datos = $this->catalogo();

        $response = $this->consultar(['q' => '   ', 'capacidad_minima' => 0])->assertOk();

        $this->assertSame($this->ids($datos, ['aula', 'auditorio', 'taller', 'sin_capacidad']), $this->salasIds($response));
        $this->assertSame('', $response->viewData('filtros')['q']);
    }

    public function test_estado_del_registro_y_habilitacion_son_filtros_independientes(): void
    {
        $datos = $this->catalogo();

        $this->assertSame([$datos['auditorio']->id], $this->salasIds(
            $this->consultar(['uso' => 'deshabilitadas'])->assertOk()
        ));
        $this->assertSame([$datos['inactiva']->id], $this->salasIds(
            $this->consultar(['estado' => 'inactivas', 'uso' => 'habilitadas'])->assertOk()
        ));
        $this->assertSame($this->ids($datos, ['aula', 'inactiva', 'taller', 'sin_capacidad']), $this->salasIds(
            $this->consultar(['estado' => 'todas', 'uso' => 'habilitadas'])->assertOk()
        ));
    }

    public function test_las_sedes_y_tipos_inactivos_siguen_permitiendo_consultar_sus_salas(): void
    {
        $datos = $this->catalogo();

        $response = $this->consultar([
            'sede_id' => $datos['otra_sede']->id, 'tipo_sala_id' => $datos['otro_tipo']->id,
        ])->assertOk();

        $this->assertSame([$datos['taller']->id], $this->salasIds($response));
        $this->assertTrue($response->viewData('sedes')->contains('id', $datos['otra_sede']->id));
        $this->assertTrue($response->viewData('tiposSala')->contains('id', $datos['otro_tipo']->id));
        $response->assertSee($datos['otra_sede']->nombre)->assertSee($datos['otro_tipo']->nombre);
    }

    public function test_el_mismo_codigo_en_dos_sedes_no_se_fusiona(): void
    {
        $datos = $this->catalogo();
        $otra = $this->sala($datos['otra_sede'], $datos['tipo'], ['codigo' => $datos['aula']->codigo]);

        $response = $this->consultar(['q' => $datos['aula']->codigo])->assertOk();

        $this->assertSame([$datos['aula']->id, $otra->id], $this->salasIds($response));
    }

    public function test_un_filtro_sin_coincidencias_conserva_el_resumen_del_catalogo(): void
    {
        $this->catalogo();

        $this->consultar(['q' => 'inexistente'])->assertOk()
            ->assertViewHas('salas', fn ($salas) => $salas->total() === 0)
            ->assertViewHas('resumen', ['total' => 5, 'activas' => 4, 'sedes' => 2])
            ->assertViewHas('hayFiltros', true);
    }

    public function test_pagina_de_doce_salas_con_orden_estable_y_conserva_solo_los_filtros_permitidos(): void
    {
        [$sede, $tipo, $salas] = $this->catalogoPaginado();
        $filtros = ['q' => 'ROOM', 'sede_id' => $sede->id, 'tipo_sala_id' => $tipo->id,
            'capacidad_minima' => 20, 'estado' => 'todas', 'uso' => 'habilitadas'];
        $antes = $this->snapshot();

        $response = $this->consultar($filtros + ['intruso' => ['valor' => 'no']])->assertOk();

        $this->assertSame(array_slice($salas, 0, 12), $this->salasIds($response));
        $paginador = $response->viewData('salas');
        $this->assertSame(25, $paginador->total());
        $this->assertSame(12, $paginador->perPage());
        parse_str(parse_url($paginador->nextPageUrl(), PHP_URL_QUERY), $query);
        $this->assertEquals($filtros + ['page' => 2], $query);

        $segundaPagina = $this->get($paginador->nextPageUrl())->assertOk();
        $this->assertSame(array_slice($salas, 12, 12), $this->salasIds($segundaPagina));
        $this->assertSame($antes, $this->snapshot());
    }

    public function test_una_pagina_fuera_de_rango_regresa_a_la_ultima_con_los_filtros(): void
    {
        $this->catalogoPaginado();

        $response = $this->consultar(['q' => 'ROOM', 'estado' => 'todas', 'page' => 999,
            'intruso' => ['valor' => 'no']])->assertRedirect();

        parse_str(parse_url($response->headers->get('Location'), PHP_URL_QUERY), $query);
        $this->assertEquals(['q' => 'ROOM', 'estado' => 'todas', 'page' => 3], $query);
        $this->get($response->headers->get('Location'))->assertOk()
            ->assertViewHas('salas', fn ($salas) => $salas->currentPage() === 3 && $salas->count() === 1);
    }

    #[DataProvider('filtrosInvalidos')]
    public function test_rechaza_filtros_mal_formados_sin_fallar_ni_perder_los_demas(string $campo, mixed $valor): void
    {
        $this->catalogo();
        $antes = $this->snapshot();
        $parametros = ['q' => 'Conservar', 'estado' => 'todas'];
        $parametros[$campo] = $valor;

        $response = $this->consultar($parametros)->assertRedirect()->assertSessionHasErrors($campo);

        parse_str(parse_url($response->headers->get('Location'), PHP_URL_QUERY) ?? '', $query);
        $this->assertArrayNotHasKey($campo, $query);
        if ($campo !== 'q') {
            $this->assertSame('Conservar', $query['q']);
        }
        if ($campo !== 'estado') {
            $this->assertSame('todas', $query['estado']);
        }
        $this->get($response->headers->get('Location'))->assertOk();
        $this->assertSame($antes, $this->snapshot());
    }

    public function test_el_permiso_sigue_limitando_el_acceso_al_catalogo(): void
    {
        $this->usuario->revokePermissionTo('gestionar salas y recursos');
        $this->usuario->givePermissionTo(Permission::findOrCreate('ver panel inicio', 'web'));

        $this->consultar()->assertRedirect(route('inicio'));
    }

    public function test_el_invitado_es_enviado_al_inicio_de_sesion(): void
    {
        auth()->forgetGuards();

        $this->consultar()->assertRedirect(route('login'));
    }

    public static function camposDeBusqueda(): array
    {
        return [['codigo'], ['nombre'], ['edificio']];
    }

    public static function caracteresLiterales(): array
    {
        return [['%'], ['_'], ['!']];
    }

    public static function filtrosInvalidos(): array
    {
        return [
            ['q', ['texto']], ['q', str_repeat('a', 101)],
            ['sede_id', ['1']], ['sede_id', 0], ['sede_id', 999999],
            ['tipo_sala_id', ['1']], ['tipo_sala_id', 999999],
            ['capacidad_minima', ['20']], ['capacidad_minima', -1], ['capacidad_minima', 65536],
            ['estado', ['todas']], ['estado', 'otro'], ['uso', ['habilitadas']], ['uso', 'otro'],
            ['page', ['1']], ['page', 0],
        ];
    }

    private function consultar(array $filtros = []): TestResponse
    {
        return $this->get(route('salas.index', $filtros));
    }

    private function catalogo(): array
    {
        $sede = Sede::create(['universidad_id' => $this->universidadId, 'nombre' => 'Sede Central', 'estado' => true]);
        $otraSede = Sede::create(['universidad_id' => $this->universidadId, 'nombre' => 'Sede Norte', 'estado' => false]);
        $tipo = TipoSala::create(['nombre' => 'Aula', 'estado' => true]);
        $otroTipo = TipoSala::create(['nombre' => 'Taller', 'estado' => false]);

        return [
            'sede' => $sede, 'otra_sede' => $otraSede, 'tipo' => $tipo, 'otro_tipo' => $otroTipo,
            'aula' => $this->sala($sede, $tipo, ['codigo' => 'A-101', 'capacidad' => 40]),
            'auditorio' => $this->sala($sede, $tipo, ['codigo' => 'A-102', 'capacidad' => 80, 'disponible' => false]),
            'taller' => $this->sala($otraSede, $otroTipo, ['codigo' => 'LAB-01', 'capacidad' => 60]),
            'inactiva' => $this->sala($sede, $tipo, ['codigo' => 'ARCH-01', 'capacidad' => 15, 'estado' => false]),
            'sin_capacidad' => $this->sala($sede, $tipo, ['codigo' => 'SIN-AFORO', 'capacidad' => 0]),
        ];
    }

    private function catalogoPaginado(): array
    {
        $sede = Sede::create(['universidad_id' => $this->universidadId, 'nombre' => 'Sede de prueba']);
        $tipo = TipoSala::create(['nombre' => 'Aula']);
        $salas = [];
        // Insertar al revés obliga a que la consulta ordene por código y no por creación.
        for ($numero = 25; $numero >= 1; $numero--) {
            $salas[$numero] = $this->sala($sede, $tipo, ['codigo' => sprintf('ROOM-%02d', $numero)])->id;
        }
        ksort($salas);

        return [$sede, $tipo, array_values($salas)];
    }

    private function sala(Sede $sede, TipoSala $tipo, array $datos = []): Sala
    {
        return Sala::create($datos + [
            'universidad_id' => $this->universidadId, 'sede_id' => $sede->id, 'tipo_sala_id' => $tipo->id,
            'codigo' => 'SALA-'.(Sala::count() + 1), 'nombre' => 'Sala de prueba',
            'capacidad' => 30, 'disponible' => true, 'estado' => true,
        ]);
    }

    private function salasIds(TestResponse $response): array
    {
        return $response->viewData('salas')->getCollection()->pluck('id')->all();
    }

    private function ids(array $catalogo, array $claves): array
    {
        return collect($claves)->map(fn ($clave) => $catalogo[$clave])->sortBy('codigo')->pluck('id')->all();
    }

    private function snapshot(): array
    {
        return collect(['salas', 'sedes', 'tipos_sala'])->mapWithKeys(
            fn ($tabla) => [$tabla => DB::table($tabla)->orderBy('id')->get()->map(fn ($fila) => (array) $fila)->all()]
        )->all();
    }
}

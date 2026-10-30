<?php

namespace Tests\Feature;

use App\Models\Equipamiento;
use App\Models\Universidad;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Permission;
use Tests\IsolatedDatabaseTestCase;

class EquipamientoTest extends IsolatedDatabaseTestCase
{
    private User $usuario;

    private Universidad $institucion;

    protected function setUp(): void
    {
        parent::setUp();
        $this->institucion = Universidad::firstOrFail();
        $this->usuario = User::create(['name' => 'Gestión de inventario', 'rut' => '77777774', 'email' => 'inventario@example.test', 'password' => 'clave-de-prueba']);
        $this->usuario->givePermissionTo(Permission::findOrCreate('gestionar salas y recursos', 'web'));
        $this->actingAs($this->usuario);
    }

    public function test_inventario_vacio_y_formulario_muestran_datos_reales_sin_generar_registros(): void
    {
        $this->get(route('recursos.index'))->assertOk()->assertViewIs('recursos.index')
            ->assertSee('No hay equipamiento registrado')->assertViewHas('resumen', ['total' => 0, 'activos' => 0, 'unidades' => 0]);
        $this->get(route('recursos.create'))->assertOk()->assertViewIs('recursos.formulario')->assertSee('Registrar equipamiento')
            ->assertViewHas('instituciones', fn ($filas) => $filas->count() === 1)
            ->assertViewHas('tipos', fn ($filas) => $filas->isEmpty());
        $this->assertDatabaseCount('equipamientos', 0);
        $this->assertDatabaseCount('historial_acciones', 0);
    }

    public function test_registra_un_tipo_nuevo_sin_aceptar_identificadores_o_campos_ajenos_y_audita_al_autor(): void
    {
        $response = $this->post(route('recursos.store'), $this->datos(['nombre' => ' Proyectores portátiles ', 'tipo' => ' Áudio y vídeo ',
            'id' => 999999, 'sala_id' => 999999, 'usuario_id' => 999999]))->assertRedirect()->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'Equipamiento registrado correctamente.');
        $equipo = Equipamiento::sole();
        $this->assertSame('Proyectores portátiles', $equipo->nombre);
        $this->assertSame('Áudio y vídeo', $equipo->tipo);
        $this->assertSame(5, $equipo->cantidad);
        $this->assertNotSame(999999, $equipo->id);
        $this->assertSame($this->institucion->id, $equipo->universidad_id);
        $log = DB::table('historial_acciones')->sole();
        $this->assertSame($this->usuario->id, $log->usuario_id);
        $this->assertSame('equipamientos', $log->tabla_afectada);
        $this->assertSame('Crear equipamiento', $log->accion);
        $datos = json_decode($log->datos, true);
        $this->assertNull($datos['antes']);
        $this->assertSame(5, $datos['despues']['cantidad']);
        $this->assertArrayNotHasKey('sala_id', $datos['despues']);
        $this->get($response->headers->get('Location'))->assertOk()->assertSee('Proyectores portátiles')->assertSee('Equipamiento registrado correctamente.');
    }

    public function test_edita_cantidad_y_desactiva_sin_borrar_el_registro_ni_otros_recursos(): void
    {
        $equipo = Equipamiento::create($this->datos());
        $otro = Equipamiento::create($this->datos(['nombre' => 'Otro recurso', 'cantidad' => 10]));
        $antes = $otro->fresh()->getAttributes();
        $response = $this->put(route('recursos.update', $equipo), $this->datos(['cantidad' => 0, 'estado' => 0, 'disponible' => 0]))
            ->assertSessionHasNoErrors()->assertSessionHas('success', 'Equipamiento actualizado correctamente.');
        $this->assertSame(0, $equipo->fresh()->cantidad);
        $this->assertFalse($equipo->fresh()->estado);
        $this->assertFalse($equipo->fresh()->disponible);
        $this->assertSame($antes, $otro->fresh()->getAttributes());
        $this->assertDatabaseCount('equipamientos', 2);
        $datos = json_decode(DB::table('historial_acciones')->sole()->datos, true);
        $this->assertSame(5, $datos['antes']['cantidad']);
        $this->assertSame(0, $datos['despues']['cantidad']);
        $this->get($response->headers->get('Location'))->assertOk()->assertSee('Registro inactivo')->assertSee('Sin unidades registradas');
    }

    public function test_los_limites_de_cantidad_y_descripcion_se_conservan_sin_truncar(): void
    {
        $descripcion = str_repeat('á', 255);
        $this->post(route('recursos.store'), $this->datos(['cantidad' => 65535, 'descripcion' => $descripcion]))->assertSessionHasNoErrors();
        $equipo = Equipamiento::sole();
        $this->assertSame(65535, $equipo->cantidad);
        $this->assertSame($descripcion, $equipo->descripcion);
        $this->get(route('recursos.edit', $equipo))->assertOk()->assertSee($descripcion)->assertSee('value="65535"', false);
    }

    public function test_instituciones_inactivas_se_consultan_y_conservan_pero_no_reciben_registros_nuevos(): void
    {
        $equipo = Equipamiento::create($this->datos());
        $this->institucion->update(['estado' => false]);
        $this->get(route('recursos.create'))->assertOk()->assertViewHas('instituciones', fn ($filas) => $filas->isEmpty());
        $this->post(route('recursos.store'), $this->datos())->assertSessionHasErrors('universidad_id');
        $this->get(route('recursos.edit', $equipo))->assertOk()->assertSee('(inactiva)')
            ->assertViewHas('instituciones', fn ($filas) => $filas->contains('id', $this->institucion->id));
        $this->put(route('recursos.update', $equipo), $this->datos(['cantidad' => 8]))->assertSessionHasNoErrors();
        $this->get(route('recursos.index', ['universidad_id' => $this->institucion->id]))->assertOk()->assertSee('Institución inactiva');
        $otra = Universidad::create(['nombre' => 'Otra institución', 'sigla' => 'OTRA', 'estado' => false]);
        $this->put(route('recursos.update', $equipo), $this->datos(['universidad_id' => $otra->id]))->assertSessionHasErrors('universidad_id');
        $this->assertSame($this->institucion->id, $equipo->fresh()->universidad_id);
        $this->assertDatabaseCount('equipamientos', 1);
    }

    public function test_filtros_combinados_no_confunden_instituciones_y_las_cantidades_del_resumen_no_son_el_numero_de_registros(): void
    {
        $esperado = Equipamiento::create($this->datos(['nombre' => 'Proyector buscado', 'cantidad' => 9]));
        Equipamiento::create($this->datos(['nombre' => 'Proyector buscado', 'tipo' => 'Computación', 'cantidad' => 8]));
        Equipamiento::create($this->datos(['nombre' => 'Proyector buscado', 'cantidad' => 20, 'disponible' => 0]));
        Equipamiento::create($this->datos(['nombre' => 'Proyector buscado', 'cantidad' => 30, 'estado' => 0]));
        $otra = Universidad::create(['nombre' => 'Otra institución', 'sigla' => 'OTRA']);
        Equipamiento::create($this->datos(['universidad_id' => $otra->id, 'nombre' => 'Proyector buscado', 'cantidad' => 10]));
        $response = $this->get(route('recursos.index', ['q' => 'proyector', 'universidad_id' => $this->institucion->id,
            'tipo' => 'Audiovisual', 'cantidad_minima' => 9, 'estado' => 'activos', 'uso' => 'habilitados']))->assertOk()
            ->assertViewHas('resumen', ['total' => 5, 'activos' => 4, 'unidades' => 47]);
        $this->assertSame([$esperado->id], $response->viewData('equipamientos')->pluck('id')->all());
        $this->assertTrue($response->viewData('equipamientos')->first()->relationLoaded('universidad'));
        $this->assertDatabaseCount('historial_acciones', 0);
    }

    public function test_busca_sin_tildes_por_nombre_tipo_y_descripcion_y_los_comodines_son_literales(): void
    {
        $equipo = Equipamiento::create($this->datos(['nombre' => 'Proyéctor %_!', 'tipo' => 'Áudio y vídeo', 'descripcion' => 'Uso en computación']));
        Equipamiento::create($this->datos(['nombre' => 'Otro recurso', 'descripcion' => 'Distinto']));
        foreach (['proyector', 'audio video', 'computacion', '%_!'] as $q) {
            $response = $this->get(route('recursos.index', ['q' => $q]))->assertOk();
            $this->assertSame([$equipo->id], $response->viewData('equipamientos')->pluck('id')->all());
        }
        $this->get(route('recursos.index', ['q' => 'inexistente%']))->assertOk()->assertViewHas('equipamientos', fn ($filas) => $filas->isEmpty());
        $this->get(route('recursos.index', ['q' => '   ', 'cantidad_minima' => 0]))->assertOk()->assertViewHas('equipamientos', fn ($filas) => $filas->total() === 2);
    }

    public function test_un_stock_cero_no_se_muestra_como_cantidad_desconocida_y_estado_y_uso_son_independientes(): void
    {
        $sinStock = Equipamiento::create($this->datos(['cantidad' => 0]));
        $inactivo = Equipamiento::create($this->datos(['nombre' => 'Registro retirado', 'estado' => 0]));
        $deshabilitado = Equipamiento::create($this->datos(['nombre' => 'Registro deshabilitado', 'disponible' => 0]));
        $this->get(route('recursos.index'))->assertOk()->assertSee('0 unidades')->assertSee('Sin unidades registradas')
            ->assertViewHas('resumen', ['total' => 3, 'activos' => 2, 'unidades' => 5]);
        foreach ([['estado' => 'inactivos', 'uso' => 'habilitados', 'id' => $inactivo->id],
            ['uso' => 'deshabilitados', 'id' => $deshabilitado->id]] as $caso) {
            $id = $caso['id'];
            unset($caso['id']);
            $this->get(route('recursos.index', $caso))->assertOk()->assertViewHas('equipamientos', fn ($filas) => $filas->pluck('id')->all() === [$id]);
        }
        $this->assertTrue($sinStock->fresh()->estado);
    }

    public function test_los_registros_con_el_mismo_nombre_no_se_fusionan_ni_se_suman_al_editar(): void
    {
        $primero = Equipamiento::create($this->datos());
        $segundo = Equipamiento::create($this->datos(['cantidad' => 7, 'descripcion' => 'Otro lote registrado']));
        $this->put(route('recursos.update', $primero), $this->datos(['cantidad' => 10]))->assertSessionHasNoErrors();
        $this->assertSame(7, $segundo->fresh()->cantidad);
        $this->assertDatabaseCount('equipamientos', 2);
    }

    public function test_pagina_de_doce_registros_con_orden_estable_y_conserva_solo_los_filtros_validos(): void
    {
        for ($i = 13; $i >= 1; $i--) {
            Equipamiento::create($this->datos(['nombre' => sprintf('Equipo %02d', $i)]));
        }
        $response = $this->get(route('recursos.index', ['q' => 'Equipo', 'tipo' => 'Audiovisual', 'intruso' => 'descartar']))->assertOk();
        $filas = $response->viewData('equipamientos');
        $this->assertSame(13, $filas->total());
        $this->assertCount(12, $filas);
        $this->assertSame('Equipo 01', $filas->first()->nombre);
        $this->assertStringNotContainsString('intruso', $filas->nextPageUrl());
        $this->get($filas->nextPageUrl())->assertOk()->assertViewHas('equipamientos', fn ($filas) => $filas->count() === 1 && $filas->first()->nombre === 'Equipo 13');
        $response = $this->get(route('recursos.index', ['q' => 'Equipo', 'page' => 999]))->assertRedirect();
        $this->get($response->headers->get('Location'))->assertOk()->assertViewHas('equipamientos', fn ($filas) => $filas->currentPage() === 2);
    }

    #[DataProvider('datosInvalidos')]
    public function test_rechaza_datos_invalidos_y_el_formulario_se_puede_corregir_sin_guardado_parcial(string $campo, mixed $valor): void
    {
        $equipo = Equipamiento::create($this->datos());
        $antes = $equipo->fresh()->getAttributes();
        $this->from(route('recursos.edit', $equipo))->put(route('recursos.update', $equipo), $this->datos([$campo => $valor]))
            ->assertRedirect(route('recursos.edit', $equipo))->assertSessionHasErrors($campo);
        $this->get(route('recursos.edit', $equipo))->assertOk()->assertSee('Revisa los datos del equipamiento.');
        $this->assertSame($antes, $equipo->fresh()->getAttributes());
        $this->assertDatabaseCount('historial_acciones', 0);
    }

    #[DataProvider('filtrosInvalidos')]
    public function test_descarta_filtros_invalidos_sin_provocar_errores_ni_escrituras(string $campo, mixed $valor): void
    {
        $equipo = Equipamiento::create($this->datos());
        $response = $this->get(route('recursos.index', array_replace(['q' => 'Conservar'], [$campo => $valor])))
            ->assertRedirect()->assertSessionHasErrors($campo);
        parse_str(parse_url($response->headers->get('Location'), PHP_URL_QUERY) ?? '', $query);
        $this->assertArrayNotHasKey($campo, $query);
        if ($campo !== 'q') {
            $this->assertSame('Conservar', $query['q']);
        }
        $this->get($response->headers->get('Location'))->assertOk();
        $this->assertDatabaseCount('equipamientos', 1);
        $this->assertSame(5, $equipo->fresh()->cantidad);
        $this->assertDatabaseCount('historial_acciones', 0);
    }

    public function test_no_registra_actualizaciones_sin_cambios_y_escapa_el_texto_del_inventario(): void
    {
        $equipo = Equipamiento::create($this->datos(['nombre' => '<script>alert(1)</script>']));
        $this->put(route('recursos.update', $equipo), $this->datos(['nombre' => $equipo->nombre]))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('historial_acciones', 0);
        $this->get(route('recursos.index'))->assertOk()->assertDontSee('<script>alert(1)</script>', false)->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
    }

    public function test_fallar_el_historial_revierte_tanto_creaciones_como_ediciones(): void
    {
        $equipo = Equipamiento::create($this->datos());
        DB::statement("CREATE TRIGGER impedir_historial BEFORE INSERT ON historial_acciones BEGIN SELECT RAISE(ABORT, 'auditoria no disponible'); END");
        $this->withoutExceptionHandling();
        foreach (['POST', 'PUT'] as $metodo) {
            try {
                $this->call($metodo, $metodo === 'POST' ? route('recursos.store') : route('recursos.update', $equipo), $this->datos(['cantidad' => 20]));
                $this->fail('La auditoría debía fallar.');
            } catch (QueryException $error) {
                $this->assertStringContainsString('auditoria no disponible', $error->getMessage());
            }
            $this->assertDatabaseCount('equipamientos', 1);
            $this->assertSame(5, $equipo->fresh()->cantidad);
        }
    }

    public function test_las_rutas_exigen_sesion_y_permiso_y_los_ids_inexistentes_no_crean_registros(): void
    {
        $equipo = Equipamiento::create($this->datos());
        $this->get(route('recursos.edit', 999999))->assertNotFound();
        $this->put(route('recursos.update', 999999), $this->datos())->assertNotFound();
        $this->usuario->revokePermissionTo('gestionar salas y recursos');
        $this->usuario->givePermissionTo(Permission::findOrCreate('ver panel inicio', 'web'));
        foreach ([false, true] as $invitado) {
            if ($invitado) {
                Auth::logout();
            }
            $destino = route($invitado ? 'login' : 'inicio');
            $this->get(route('recursos.index'))->assertRedirect($destino);
            $this->get(route('recursos.create'))->assertRedirect($destino);
            $this->get(route('recursos.edit', $equipo))->assertRedirect($destino);
            $this->post(route('recursos.store'), $this->datos())->assertRedirect($destino);
            $this->put(route('recursos.update', $equipo), $this->datos(['cantidad' => 90]))->assertRedirect($destino);
        }
        $this->assertSame(5, $equipo->fresh()->cantidad);
        $this->assertDatabaseCount('equipamientos', 1);
        $this->assertDatabaseCount('historial_acciones', 0);
    }

    public static function datosInvalidos(): array
    {
        return [['universidad_id', 999999], ['universidad_id', [1]], ['nombre', '   '], ['nombre', ['texto']], ['nombre', str_repeat('A', 101)],
            ['tipo', '   '], ['tipo', ['texto']], ['tipo', str_repeat('A', 101)], ['descripcion', str_repeat('A', 256)], ['descripcion', ['texto']],
            ['cantidad', -1], ['cantidad', 65536], ['cantidad', 2.5], ['cantidad', null], ['cantidad', ['5']],
            ['estado', 'otro'], ['disponible', ['1']]];
    }

    public static function filtrosInvalidos(): array
    {
        return [['q', ['texto']], ['q', str_repeat('A', 101)], ['universidad_id', 999999], ['universidad_id', [1]],
            ['tipo', ['texto']], ['tipo', 'tipo inexistente'], ['cantidad_minima', -1], ['cantidad_minima', 65536],
            ['estado', ['activos']], ['uso', 'otro'], ['page', 0]];
    }

    private function datos(array $cambios = []): array
    {
        return array_replace(['universidad_id' => $this->institucion->id, 'nombre' => 'Proyector portátil', 'tipo' => 'Audiovisual',
            'descripcion' => null, 'cantidad' => 5, 'estado' => 1, 'disponible' => 1], $cambios);
    }
}

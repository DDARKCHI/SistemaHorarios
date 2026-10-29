<?php

namespace Tests\Feature;

use App\Models\Laboratorio;
use App\Models\LaboratorioMovil;
use App\Models\Sala;
use App\Models\Sede;
use App\Models\TipoSala;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Permission;
use Tests\IsolatedDatabaseTestCase;

class LaboratoriosTest extends IsolatedDatabaseTestCase
{
    private User $usuario;

    private Sede $sede;

    private Sala $sala;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sede = Sede::create(['universidad_id' => DB::table('universidades')->value('id'), 'nombre' => 'Sede central']);
        $tipo = TipoSala::create(['nombre' => 'Sala de docencia']);
        $this->sala = Sala::create(['universidad_id' => $this->sede->universidad_id, 'sede_id' => $this->sede->id,
            'tipo_sala_id' => $tipo->id, 'codigo' => 'FISICA-01', 'capacidad' => 40, 'estado' => true, 'disponible' => true]);
        $this->usuario = User::create(['name' => 'Gestión de laboratorios', 'rut' => '77777772', 'email' => 'laboratorios@example.test', 'password' => 'clave-de-prueba']);
        $this->usuario->givePermissionTo(Permission::findOrCreate('gestionar salas y recursos', 'web'));
        $this->actingAs($this->usuario);
    }

    public function test_catalogo_vacio_y_formularios_no_inventan_datos_ni_escriben_en_la_base(): void
    {
        $this->get(route('laboratorios.index'))->assertOk()->assertViewIs('laboratorios.index')
            ->assertViewHas('resumen', ['fijos' => 0, 'moviles' => 0, 'activos' => 0])
            ->assertViewHas('laboratorios', fn ($filas) => $filas->isEmpty());
        foreach (['fijo', 'movil'] as $tipo) {
            $this->get(route('laboratorios.create', ['tipo' => $tipo]))->assertOk()->assertViewIs('laboratorios.formulario')
                ->assertViewHas('ubicaciones', fn ($filas) => $filas->count() === 1)->assertSee('Sede central');
        }
        $this->assertDatabaseCount('laboratorios', 0);
        $this->assertDatabaseCount('laboratorios_moviles', 0);
        $this->assertDatabaseCount('historial_acciones', 0);
    }

    #[DataProvider('tipos')]
    public function test_crea_edita_y_desactiva_con_auditoria_y_tipo_inmutable(string $tipo): void
    {
        $modelo = $tipo === 'fijo' ? Laboratorio::class : LaboratorioMovil::class;
        $respuesta = $this->post(route('laboratorios.store', ['tipo' => $tipo]), $this->datos($tipo, [
            'nombre' => ' Laboratorio nuevo ', 'universidad_id' => 999999, 'tipo' => 'otro', 'id' => 999999,
        ]))->assertRedirect()->assertSessionHasNoErrors()->assertSessionHas('success', 'Laboratorio creado correctamente.');
        $lab = $modelo::sole();
        $this->assertSame('Laboratorio nuevo', $lab->nombre);
        $this->assertSame(30, $lab->capacidad);
        $this->assertNotSame(999999, $lab->id);
        if ($tipo === 'movil') {
            $this->assertSame($this->sede->universidad_id, $lab->universidad_id);
        } else {
            $this->assertSame($this->sala->id, $lab->sala_id);
        }
        $this->get($respuesta->headers->get('Location'))->assertOk()->assertSee('Laboratorio nuevo');
        $this->get(route('laboratorios.edit', ['tipo' => $tipo, 'id' => $lab->id]))->assertOk()->assertSee('Laboratorio nuevo')->assertSee('Guardar cambios');
        $this->put(route('laboratorios.update', ['tipo' => $tipo, 'id' => $lab->id]), $this->datos($tipo, ['capacidad' => 0, 'estado' => 0, 'disponible' => 0]))
            ->assertSessionHasNoErrors()->assertSessionHas('success', 'Laboratorio actualizado correctamente.');
        $this->assertSame(0, $lab->fresh()->capacidad);
        $this->assertFalse($lab->fresh()->estado);
        $this->assertFalse($lab->fresh()->disponible);
        $logs = DB::table('historial_acciones')->orderBy('id')->get();
        $this->assertCount(2, $logs);
        $this->assertSame($this->usuario->id, $logs[0]->usuario_id);
        $this->assertSame($lab->getTable(), $logs[0]->tabla_afectada);
        $datos = json_decode($logs[1]->datos, true);
        $this->assertSame($tipo, $datos['tipo']);
        $this->assertSame(30, $datos['antes']['capacidad']);
        $this->assertSame(0, $datos['despues']['capacidad']);
    }

    public function test_codigo_fijo_es_opcional_y_unico_por_sala_y_el_movil_es_unico_por_sede(): void
    {
        $this->post(route('laboratorios.store', ['tipo' => 'fijo']), $this->datos('fijo', ['codigo' => null]))->assertSessionHasNoErrors();
        $this->post(route('laboratorios.store', ['tipo' => 'fijo']), $this->datos('fijo', ['codigo' => null]))->assertSessionHasNoErrors();
        $this->post(route('laboratorios.store', ['tipo' => 'fijo']), $this->datos('fijo'))->assertSessionHasNoErrors();
        $this->post(route('laboratorios.store', ['tipo' => 'fijo']), $this->datos('fijo'))->assertSessionHasErrors('codigo');
        $otraSala = $this->otraSala();
        $this->post(route('laboratorios.store', ['tipo' => 'fijo']), $this->datos('fijo', ['sala_id' => $otraSala->id]))->assertSessionHasNoErrors();
        $this->post(route('laboratorios.store', ['tipo' => 'movil']), $this->datos('movil'))->assertSessionHasNoErrors();
        $this->post(route('laboratorios.store', ['tipo' => 'movil']), $this->datos('movil'))->assertSessionHasErrors('codigo');
        $otraSede = Sede::create(['universidad_id' => $this->sede->universidad_id, 'nombre' => 'Otra sede']);
        $this->post(route('laboratorios.store', ['tipo' => 'movil']), $this->datos('movil', ['sede_id' => $otraSede->id]))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('laboratorios', 4);
        $this->assertDatabaseCount('laboratorios_moviles', 2);
    }

    public function test_omitir_codigo_no_permite_un_duplicado_al_cambiar_de_sala(): void
    {
        $lab = Laboratorio::create($this->datos('fijo'));
        $otraSala = $this->otraSala();
        Laboratorio::create($this->datos('fijo', ['sala_id' => $otraSala->id]));
        $datos = $this->datos('fijo', ['sala_id' => $otraSala->id]);
        unset($datos['codigo']);
        $this->put(route('laboratorios.update', ['tipo' => 'fijo', 'id' => $lab->id]), $datos)->assertSessionHasErrors('codigo');
        $this->assertSame($this->sala->id, $lab->fresh()->sala_id);
    }

    public function test_capacidad_fija_respeta_aforo_conocido_y_admite_capacidad_sin_informar(): void
    {
        $this->post(route('laboratorios.store', ['tipo' => 'fijo']), $this->datos('fijo', ['capacidad' => 41]))->assertSessionHasErrors('capacidad');
        $this->assertDatabaseCount('laboratorios', 0);
        $this->post(route('laboratorios.store', ['tipo' => 'fijo']), $this->datos('fijo', ['capacidad' => 40]))->assertSessionHasNoErrors();
        $lab = Laboratorio::sole();
        $this->put(route('salas.update', $this->sala), ['sede_id' => $this->sede->id, 'tipo_sala_id' => $this->sala->tipo_sala_id,
            'codigo' => $this->sala->codigo, 'capacidad' => 20, 'estado' => 1, 'disponible' => 1])->assertSessionHasErrors('capacidad');
        $this->assertSame(40, $this->sala->fresh()->capacidad);
        $this->put(route('laboratorios.update', ['tipo' => 'fijo', 'id' => $lab->id]), $this->datos('fijo', ['capacidad' => 0]))->assertSessionHasNoErrors();
        $this->sala->update(['capacidad' => 0]);
        $this->put(route('laboratorios.update', ['tipo' => 'fijo', 'id' => $lab->id]), $this->datos('fijo', ['capacidad' => 90]))->assertSessionHasNoErrors();
    }

    public function test_ubicaciones_inactivas_se_pueden_conservar_pero_no_asignar_a_recursos_nuevos(): void
    {
        $fijo = Laboratorio::create($this->datos('fijo'));
        $movil = LaboratorioMovil::create($this->datos('movil') + ['universidad_id' => $this->sede->universidad_id]);
        $this->sala->update(['disponible' => false]);
        $this->sede->update(['estado' => false]);
        foreach (['fijo' => $fijo, 'movil' => $movil] as $tipo => $lab) {
            $campo = $tipo === 'fijo' ? 'sala_id' : 'sede_id';
            $this->get(route('laboratorios.create', ['tipo' => $tipo]))->assertOk()->assertViewHas('ubicaciones', fn ($filas) => $filas->isEmpty());
            $this->get(route('laboratorios.edit', ['tipo' => $tipo, 'id' => $lab->id]))->assertOk()->assertViewHas('ubicaciones', fn ($filas) => $filas->count() === 1);
            $this->post(route('laboratorios.store', ['tipo' => $tipo]), $this->datos($tipo, ['codigo' => 'NUEVO']))->assertSessionHasErrors($campo);
            $this->put(route('laboratorios.update', ['tipo' => $tipo, 'id' => $lab->id]), $this->datos($tipo, ['nombre' => 'Nombre corregido']))->assertSessionHasNoErrors();
            $this->get(route('laboratorios.index', ['tipo' => $tipo]))->assertOk()->assertSee('Sede inactiva');
        }
        $this->assertDatabaseCount('laboratorios', 1);
        $this->assertDatabaseCount('laboratorios_moviles', 1);
    }

    #[DataProvider('datosInvalidos')]
    public function test_datos_invalidos_regresan_al_formulario_sin_escrituras(string $tipo, string $campo, mixed $valor): void
    {
        $url = route('laboratorios.create', ['tipo' => $tipo]);
        $this->from($url)->post(route('laboratorios.store', ['tipo' => $tipo]), $this->datos($tipo, [$campo => $valor]))
            ->assertRedirect($url)->assertSessionHasErrors($campo);
        $this->get($url)->assertOk()->assertSee('Revisa los datos del laboratorio.');
        $this->assertDatabaseCount('laboratorios', 0);
        $this->assertDatabaseCount('laboratorios_moviles', 0);
        $this->assertDatabaseCount('historial_acciones', 0);
    }

    public function test_filtros_combinados_buscan_literales_sin_eludir_sede_tipo_o_estado(): void
    {
        $lab = Laboratorio::create($this->datos('fijo', ['codigo' => 'LAB%_!', 'capacidad' => 35]));
        $otraSala = $this->otraSala();
        $otraSede = Sede::create(['universidad_id' => $this->sede->universidad_id, 'nombre' => 'Otra sede']);
        $otraSala->update(['sede_id' => $otraSede->id]);
        Laboratorio::create($this->datos('fijo', ['codigo' => 'LAB%_!', 'sala_id' => $otraSala->id]));
        Laboratorio::create($this->datos('fijo', ['codigo' => 'LAB-OTRO', 'estado' => 0]));
        LaboratorioMovil::create($this->datos('movil', ['codigo' => 'LAB%_!']) + ['universidad_id' => $this->sede->universidad_id]);
        $response = $this->get(route('laboratorios.index', ['tipo' => 'fijo', 'q' => '%_!', 'sede_id' => $this->sede->id,
            'capacidad_minima' => 35, 'estado' => 'activos', 'uso' => 'habilitados']))->assertOk()
            ->assertViewHas('resumen', ['fijos' => 3, 'moviles' => 1, 'activos' => 3]);
        $this->assertSame([$lab->id], $response->viewData('laboratorios')->pluck('id')->all());
        $porSala = $this->get(route('laboratorios.index', ['q' => 'FISICA-01']))->assertOk();
        $this->assertSame([$lab->id], $porSala->viewData('laboratorios')->pluck('id')->all());
        $this->assertDatabaseCount('historial_acciones', 0);
    }

    public function test_paginacion_conserva_solo_filtros_validos_y_corrige_paginas_fuera_de_rango(): void
    {
        for ($i = 13; $i >= 1; $i--) {
            LaboratorioMovil::create($this->datos('movil', ['codigo' => sprintf('MOV-%02d', $i)]) + ['universidad_id' => $this->sede->universidad_id]);
        }
        $response = $this->get(route('laboratorios.index', ['tipo' => 'movil', 'q' => 'MOV-', 'intruso' => 'descartar']))->assertOk();
        $paginador = $response->viewData('laboratorios');
        $this->assertSame(13, $paginador->total());
        $this->assertCount(12, $paginador);
        $this->assertSame('MOV-01', $paginador->first()->codigo);
        $this->assertStringNotContainsString('intruso', $paginador->nextPageUrl());
        $this->get($paginador->nextPageUrl())->assertOk()->assertViewHas('laboratorios', fn ($filas) => $filas->count() === 1 && $filas->first()->codigo === 'MOV-13');
        $redirect = $this->get(route('laboratorios.index', ['tipo' => 'movil', 'q' => 'MOV-', 'page' => 999]))->assertRedirect();
        $this->get($redirect->headers->get('Location'))->assertOk()->assertViewHas('laboratorios', fn ($filas) => $filas->currentPage() === 2);
    }

    #[DataProvider('filtrosInvalidos')]
    public function test_filtros_mal_formados_se_descartan_sin_perder_el_contexto(string $campo, mixed $valor): void
    {
        $response = $this->get(route('laboratorios.index', array_replace(['tipo' => 'movil', 'q' => 'Conservar'], [$campo => $valor])))
            ->assertRedirect()->assertSessionHasErrors($campo);
        parse_str(parse_url($response->headers->get('Location'), PHP_URL_QUERY) ?? '', $query);
        $this->assertArrayNotHasKey($campo, $query);
        if ($campo !== 'q') {
            $this->assertSame('Conservar', $query['q']);
        }
        $this->get($response->headers->get('Location'))->assertOk();
    }

    #[DataProvider('tipos')]
    public function test_guardar_sin_cambios_no_duplica_auditoria_y_los_ids_inexistentes_no_crean_registros(string $tipo): void
    {
        $modelo = $tipo === 'fijo' ? Laboratorio::class : LaboratorioMovil::class;
        $lab = $modelo::create($this->datos($tipo) + ($tipo === 'movil' ? ['universidad_id' => $this->sede->universidad_id] : []));
        $this->put(route('laboratorios.update', ['tipo' => $tipo, 'id' => $lab->id]), $this->datos($tipo))->assertSessionHasNoErrors();
        $this->get(route('laboratorios.edit', ['tipo' => $tipo, 'id' => 999999]))->assertNotFound();
        $this->put(route('laboratorios.update', ['tipo' => $tipo, 'id' => 999999]), $this->datos($tipo))->assertNotFound();
        $this->assertDatabaseCount('historial_acciones', 0);
    }

    #[DataProvider('tipos')]
    public function test_el_fallo_de_auditoria_revierte_la_creacion(string $tipo): void
    {
        DB::statement("CREATE TRIGGER impedir_historial BEFORE INSERT ON historial_acciones BEGIN SELECT RAISE(ABORT, 'auditoria no disponible'); END");
        $this->withoutExceptionHandling();
        try {
            $this->post(route('laboratorios.store', ['tipo' => $tipo]), $this->datos($tipo));
            $this->fail('La auditoría debía fallar.');
        } catch (QueryException $error) {
            $this->assertStringContainsString('auditoria no disponible', $error->getMessage());
        }
        $this->assertDatabaseCount('laboratorios', 0);
        $this->assertDatabaseCount('laboratorios_moviles', 0);
    }

    public function test_las_rutas_exigen_sesion_y_permiso_tanto_para_lecturas_como_escrituras(): void
    {
        $this->usuario->revokePermissionTo('gestionar salas y recursos');
        $this->usuario->givePermissionTo(Permission::findOrCreate('ver panel inicio', 'web'));
        foreach ([false, true] as $invitado) {
            if ($invitado) {
                Auth::logout();
            }
            $destino = route($invitado ? 'login' : 'inicio');
            $this->get(route('laboratorios.index'))->assertRedirect($destino);
            foreach (['fijo', 'movil'] as $tipo) {
                $this->get(route('laboratorios.create', ['tipo' => $tipo]))->assertRedirect($destino);
                $this->get(route('laboratorios.edit', ['tipo' => $tipo, 'id' => 1]))->assertRedirect($destino);
                $this->post(route('laboratorios.store', ['tipo' => $tipo]), $this->datos($tipo))->assertRedirect($destino);
                $this->put(route('laboratorios.update', ['tipo' => $tipo, 'id' => 1]), $this->datos($tipo))->assertRedirect($destino);
            }
        }
        $this->assertDatabaseCount('laboratorios', 0);
        $this->assertDatabaseCount('laboratorios_moviles', 0);
    }

    public static function tipos(): array
    {
        return [['fijo'], ['movil']];
    }

    public static function datosInvalidos(): array
    {
        $casos = [];
        foreach (['fijo', 'movil'] as $tipo) {
            foreach ([['codigo', ['compuesto']], ['codigo', str_repeat('A', 31)], ['nombre', '  '], ['nombre', str_repeat('A', 101)],
                ['capacidad', -1], ['capacidad', 65536], ['capacidad', 2.5], ['capacidad', ['30']],
                ['descripcion', str_repeat('A', 5001)], ['estado', 'otro'], ['disponible', ['1']],
                [$tipo === 'fijo' ? 'sala_id' : 'sede_id', 999999]] as [$campo, $valor]) {
                $casos[] = [$tipo, $campo, $valor];
            }
        }
        $casos[] = ['movil', 'codigo', null];

        return $casos;
    }

    public static function filtrosInvalidos(): array
    {
        return [['tipo', ['fijo']], ['tipo', 'otro'], ['q', ['texto']], ['sede_id', 999999],
            ['capacidad_minima', -1], ['estado', ['activos']], ['uso', 'otro'], ['page', 0]];
    }

    private function datos(string $tipo, array $cambios = []): array
    {
        return array_replace([$tipo === 'fijo' ? 'sala_id' : 'sede_id' => $tipo === 'fijo' ? $this->sala->id : $this->sede->id,
            'codigo' => $tipo === 'fijo' ? 'LAB-01' : 'MOV-01', 'nombre' => 'Laboratorio de prueba',
            'capacidad' => 30, 'descripcion' => null, 'estado' => 1, 'disponible' => 1], $cambios);
    }

    private function otraSala(): Sala
    {
        return Sala::create(['universidad_id' => $this->sede->universidad_id, 'sede_id' => $this->sede->id,
            'tipo_sala_id' => $this->sala->tipo_sala_id, 'codigo' => 'FISICA-02', 'capacidad' => 50]);
    }
}

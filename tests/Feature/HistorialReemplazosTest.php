<?php

namespace Tests\Feature;

use App\Models\ActividadSeccion;
use App\Models\Asignatura;
use App\Models\BloqueHorario;
use App\Models\Dia;
use App\Models\GrupoSeccion;
use App\Models\Horario;
use App\Models\PeriodoAcademico;
use App\Models\Profesor;
use App\Models\ProfesorVersion;
use App\Models\Seccion;
use App\Models\User;
use App\Models\VersionPlanificacion;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Permission;
use Tests\IsolatedDatabaseTestCase;

class HistorialReemplazosTest extends IsolatedDatabaseTestCase
{
    private User $usuario;

    private PeriodoAcademico $periodo;

    private VersionPlanificacion $version;

    private Profesor $origen;

    private Profesor $candidato;

    private GrupoSeccion $grupo;

    private Horario $horario;

    protected function setUp(): void
    {
        parent::setUp();
        $universidad = DB::table('universidades')->value('id');
        $this->usuario = User::create(['name' => 'Gestión académica', 'rut' => '77777773', 'email' => 'historial@example.test', 'password' => 'prueba']);
        $this->usuario->givePermissionTo(Permission::findOrCreate('ver intercambio docente', 'web'));
        $this->actingAs($this->usuario);
        $this->periodo = PeriodoAcademico::create(['universidad_id' => $universidad, 'nombre' => '2026-II', 'anio' => 2026, 'numero_semestre' => 2, 'estado' => false]);
        $this->version = VersionPlanificacion::create(['periodo_academico_id' => $this->periodo->id, 'nombre' => 'Versión histórica',
            'tipo' => 'semestral', 'version' => '0.1', 'estado' => 'Aprobada', 'es_actual' => false]);
        $this->origen = Profesor::create(['universidad_id' => $universidad, 'nombre' => 'Andrés', 'apellido' => 'Fernández', 'rut' => '11111111-1']);
        $this->candidato = Profesor::create(['universidad_id' => $universidad, 'nombre' => 'Ángela', 'apellido' => 'LÓPEZ', 'rut' => '22222222K']);
        $asignatura = Asignatura::create(['universidad_id' => $universidad, 'codigo' => 'MAT1', 'nombre' => 'Matemáticas']);
        $seccion = Seccion::create(['asignatura_id' => $asignatura->id, 'periodo_academico_id' => $this->periodo->id, 'codigo' => '1']);
        $actividad = ActividadSeccion::create(['seccion_id' => $seccion->id, 'tipo' => 'Cátedra']);
        $this->grupo = GrupoSeccion::create(['actividad_seccion_id' => $actividad->id, 'codigo' => 'G:1']);
        $this->horario = Horario::create(['version_planificacion_id' => $this->version->id, 'actividad_seccion_id' => $actividad->id,
            'grupo_seccion_id' => $this->grupo->id, 'dia_id' => Dia::firstOrFail()->id, 'bloque_horario_id' => BloqueHorario::firstOrFail()->id]);
    }

    public function test_historial_vacio_y_solo_incluye_operaciones_de_reemplazo_docente(): void
    {
        $this->get(route('programacion.intercambio.historial'))->assertOk()->assertViewHas('registros', fn ($filas) => $filas->isEmpty());
        $id = $this->registro();
        $otro = $this->registro([], ['accion' => 'Crear laboratorio', 'modulo' => 'Laboratorios', 'tabla_afectada' => 'laboratorios']);
        $this->registro([], ['modulo' => 'Otro módulo']);
        $this->registro([], ['tabla_afectada' => 'users']);
        $antes = $this->snapshot();
        $response = $this->get(route('programacion.intercambio.historial'))->assertOk()
            ->assertSee('Andrés Fernández')->assertSee('Ángela LÓPEZ')->assertSee('Versión histórica');
        $this->assertSame([$id], $response->viewData('registros')->pluck('id')->all());
        $this->get($this->detalle($otro))->assertNotFound();
        $this->get($this->detalle(999999))->assertNotFound();
        $this->assertSame($antes, $this->snapshot());
    }

    public function test_registro_anterior_explica_sus_limites_y_no_presenta_ausencia_de_datos_como_disponibilidad_declarada(): void
    {
        $id = $this->registro();
        ProfesorVersion::create(['version_planificacion_id' => $this->version->id, 'profesor_id' => $this->origen->id,
            'nombre' => 'Nombre de versión', 'apellido' => 'Conservado', 'rut' => '11111111-1']);
        $response = $this->get($this->detalle($id))->assertOk()->assertSee('información parcial')
            ->assertSee('Nombre de versión Conservado')->assertSee('Referencias actuales de los módulos')
            ->assertSee('El registro anterior no conserva el tipo de comprobación de disponibilidad.');
        $registro = $response->viewData('registro');
        $this->assertFalse($registro['detalle_registrado']);
        $this->assertSame('referencias_actuales', $registro['fuente_modulos']);
        $this->assertSame(1, $registro['cantidad_modulos']);
        $this->assertSame('08:10', $registro['modulos'][0]['hora_inicio']);
        $manual = $this->registro(['disponibilidad_confirmada' => true]);
        $this->get($this->detalle($manual))->assertOk()->assertSee('El registro conserva la confirmación de consulta al docente.');
    }

    public function test_el_detalle_guardado_sobrevive_a_cambios_y_bajas_de_las_referencias_y_no_se_mezcla_con_datos_actuales(): void
    {
        $id = $this->registro(['detalle' => $this->captura()]);
        $this->origen->update(['nombre' => 'Nombre posterior', 'apellido' => 'Apellido posterior']);
        $this->candidato->update(['nombre' => 'Otro nombre', 'rut' => '99999999-9']);
        $this->usuario->update(['name' => 'Usuario renombrado']);
        $this->grupo->actividadSeccion->seccion->asignatura->update(['nombre' => 'Ramo renombrado']);
        $this->horario->delete();
        $response = $this->get($this->detalle($id))->assertOk()->assertSee('Nombre original')->assertSee('Ángela histórica')
            ->assertSee('Matemáticas registradas')->assertSee('Operador histórico')->assertSee('09:10–10:00')
            ->assertDontSee('Ramo renombrado')->assertDontSee('Apellido posterior');
        $registro = $response->viewData('registro');
        $this->assertTrue($registro['detalle_registrado']);
        $this->assertSame('registrado', $registro['fuente_modulos']);
        $this->assertNull($registro['origen_rut'] ?: null);
        $this->assertSame('Nombre original', $registro['origen']);
        $this->assertSame(1, $registro['cantidad_modulos']);
        $this->version->delete();
        $this->periodo->delete();
        $this->origen->delete();
        $this->candidato->delete();
        $this->get($this->detalle($id))->assertOk()->assertSee('Período registrado')->assertSee('Semestral registrada')->assertSee('Nombre original');
    }

    #[DataProvider('busquedas')]
    public function test_busca_docentes_rut_ramos_y_responsable_sin_tildes_ni_comodines(string $busqueda): void
    {
        $id = $this->registro();
        $response = $this->get(route('programacion.intercambio.historial', ['q' => $busqueda]))->assertOk();
        $this->assertSame([$id], $response->viewData('registros')->pluck('id')->all());
    }

    public static function busquedas(): array
    {
        return [['angela lopez'], ['andres fernandez'], ['22.222.222-k'], ['matematicas'], ['gestion academica']];
    }

    public function test_la_busqueda_usa_los_nombres_guardados_y_los_caracteres_especiales_son_literales(): void
    {
        $detalle = $this->captura();
        $detalle['candidato']['nombre'] = 'Ángela %_!';
        $id = $this->registro(['detalle' => $detalle]);
        $this->candidato->update(['nombre' => 'Nombre actual diferente']);
        foreach (['angela historica', '%_!'] as $busqueda) {
            $response = $this->get(route('programacion.intercambio.historial', ['q' => $busqueda]))->assertOk();
            $this->assertSame([$id], $response->viewData('registros')->pluck('id')->all());
        }
        $this->get(route('programacion.intercambio.historial', ['q' => 'Nombre actual diferente']))->assertOk()
            ->assertViewHas('registros', fn ($filas) => $filas->isEmpty());
        $this->get(route('programacion.intercambio.historial', ['q' => 'inexistente%']))->assertOk()
            ->assertViewHas('registros', fn ($filas) => $filas->isEmpty());
    }

    public function test_combina_periodo_version_usuario_y_fechas_incluyendo_el_dia_final_completo(): void
    {
        $esperado = $this->registro([], ['created_at' => '2026-10-29 23:59:59']);
        $this->registro([], ['created_at' => '2026-10-30 00:00:00']);
        $this->registro([], ['created_at' => '2026-10-28 23:59:59']);
        $otra = VersionPlanificacion::create(['periodo_academico_id' => $this->periodo->id, 'nombre' => 'Otra versión', 'tipo' => 'semestral']);
        $this->registro(['version_id' => $otra->id]);
        $this->registro([], ['usuario_id' => null]);
        $response = $this->get(route('programacion.intercambio.historial', ['periodo_id' => $this->periodo->id,
            'version_id' => $this->version->id, 'usuario_id' => $this->usuario->id, 'desde' => '2026-10-29', 'hasta' => '2026-10-29']))->assertOk();
        $this->assertSame([$esperado], $response->viewData('registros')->pluck('id')->all());
        $this->get(route('programacion.intercambio.historial', ['hasta' => '2026-10-29']))->assertOk();
    }

    public function test_version_de_otro_periodo_se_rechaza_y_permite_consultar_versiones_historicas(): void
    {
        $otro = PeriodoAcademico::create(['universidad_id' => $this->periodo->universidad_id, 'nombre' => '2025-II', 'anio' => 2025, 'numero_semestre' => 2]);
        $version = VersionPlanificacion::create(['periodo_academico_id' => $otro->id, 'nombre' => 'Otra planificación', 'tipo' => 'semestral']);
        $response = $this->get(route('programacion.intercambio.historial', ['periodo_id' => $this->periodo->id, 'version_id' => $version->id]))
            ->assertRedirect()->assertSessionHasErrors('version_id');
        $this->get($response->headers->get('Location'))->assertOk()->assertViewHas('versiones', fn ($filas) => $filas->contains('id', $this->version->id) && ! $filas->contains('id', $version->id));
    }

    public function test_pagina_en_orden_estable_y_conserva_solo_filtros_permitidos(): void
    {
        $ids = [];
        for ($i = 0; $i < 13; $i++) {
            $ids[] = $this->registro();
        }
        $response = $this->get(route('programacion.intercambio.historial', ['q' => 'angela', 'intruso' => 'descartar']))->assertOk();
        $filas = $response->viewData('registros');
        $this->assertSame(13, $filas->total());
        $this->assertCount(12, $filas);
        $this->assertSame(array_slice(array_reverse($ids), 0, 12), $filas->pluck('id')->all());
        $this->assertStringNotContainsString('intruso', $filas->nextPageUrl());
        $this->get($filas->nextPageUrl())->assertOk()->assertViewHas('registros', fn ($filas) => $filas->pluck('id')->all() === [$ids[0]]);
        $response = $this->get(route('programacion.intercambio.historial', ['q' => 'angela', 'page' => 999]))->assertRedirect();
        $this->get($response->headers->get('Location'))->assertOk()->assertViewHas('registros', fn ($filas) => $filas->currentPage() === 2);
    }

    public function test_datos_anteriores_mal_formados_o_referencias_eliminadas_no_rompen_el_historial(): void
    {
        $ids = [$this->registro([], ['datos' => '{json inválido']), $this->registro([], ['datos' => null]),
            $this->registro(['horarios_ids' => ['mal', [], -1, true], 'detalle' => ['version_esquema' => []]])];
        $sinFicha = $this->registro(['origen_id' => 999999, 'candidato_id' => 999998, 'horarios_ids' => [$this->horario->id, $this->horario->id]]);
        $this->horario->delete();
        foreach ([...$ids, $sinFicha] as $id) {
            $this->get($this->detalle($id))->assertOk();
        }
        $this->get($this->detalle($sinFicha))->assertOk()->assertSee('Docente ya no disponible')->assertSee('Horario sin información');
        $this->get(route('programacion.intercambio.historial'))->assertOk()->assertViewHas('registros', fn ($filas) => $filas->total() === 4);
        $this->get(route('programacion.intercambio.historial', ['q' => 'angela']))->assertOk();
    }

    public function test_una_captura_incompleta_no_se_anuncia_como_detalle_completo_ni_inventa_modulos(): void
    {
        $detalle = $this->captura();
        $detalle['modulos'] = [];
        $id = $this->registro(['detalle' => $detalle]);
        $response = $this->get($this->detalle($id))->assertOk()->assertSee('información parcial')->assertSee('Horario sin información');
        $this->assertFalse($response->viewData('registro')['detalle_registrado']);
        $this->assertCount(1, $response->viewData('registro')['modulos']);
        $this->assertSame($this->horario->id, $response->viewData('registro')['modulos'][0]['id']);
    }

    #[DataProvider('filtrosInvalidos')]
    public function test_filtros_invalidos_regresan_a_una_consulta_valida_sin_escribir(string $campo, mixed $valor): void
    {
        $this->registro();
        $antes = $this->snapshot();
        $response = $this->get(route('programacion.intercambio.historial', array_replace(['q' => 'Conservar'], [$campo => $valor])))
            ->assertRedirect()->assertSessionHasErrors($campo);
        parse_str(parse_url($response->headers->get('Location'), PHP_URL_QUERY) ?? '', $query);
        $this->assertArrayNotHasKey($campo, $query);
        $this->get($response->headers->get('Location'))->assertOk();
        $this->assertSame($antes, $this->snapshot());
    }

    public static function filtrosInvalidos(): array
    {
        return [['q', ['texto']], ['q', str_repeat('A', 81)], ['periodo_id', [1]], ['periodo_id', 999999],
            ['version_id', 999999], ['usuario_id', 999999], ['desde', ['2026-10-29']], ['desde', '2026-02-30'],
            ['hasta', 'no-fecha'], ['page', 0], ['page', [1]]];
    }

    public function test_fecha_final_anterior_a_inicial_se_rechaza_y_las_salidas_se_escapan(): void
    {
        $detalle = $this->captura();
        $detalle['candidato']['nombre'] = '<script>alert(1)</script>';
        $id = $this->registro(['detalle' => $detalle]);
        $response = $this->get(route('programacion.intercambio.historial', ['desde' => '2026-10-29', 'hasta' => '2026-10-28']))
            ->assertRedirect()->assertSessionHasErrors('hasta');
        $this->get($response->headers->get('Location'))->assertOk();
        $this->get($this->detalle($id))->assertOk()->assertDontSee('<script>alert(1)</script>', false)->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
    }

    public function test_consultar_no_exige_permiso_de_aplicar_y_rechaza_usuarios_sin_permiso_de_consulta(): void
    {
        $id = $this->registro();
        $this->get($this->detalle($id))->assertOk();
        $this->usuario->revokePermissionTo('ver intercambio docente');
        $this->usuario->givePermissionTo(Permission::findOrCreate('ver panel inicio', 'web'));
        $this->get(route('programacion.intercambio.historial'))->assertRedirect(route('inicio'));
        $this->get($this->detalle($id))->assertRedirect(route('inicio'));
        Auth::logout();
        $this->get(route('programacion.intercambio.historial'))->assertRedirect(route('login'));
        $this->get($this->detalle($id))->assertRedirect(route('login'));
    }

    private function registro(array $datos = [], array $cambios = []): int
    {
        return DB::table('historial_acciones')->insertGetId(array_replace(['usuario_id' => $this->usuario->id,
            'accion' => 'Reemplazo docente', 'modulo' => 'Intercambio docente', 'tabla_afectada' => 'grupos_seccion', 'registro_id' => $this->grupo->id,
            'datos' => json_encode(array_replace(['periodo_id' => $this->periodo->id, 'version_id' => $this->version->id,
                'origen_id' => $this->origen->id, 'candidato_id' => $this->candidato->id, 'horarios_ids' => [$this->horario->id], 'disponibilidad_confirmada' => false], $datos), JSON_THROW_ON_ERROR),
            'created_at' => '2026-10-29 10:30:00', 'updated_at' => '2026-10-29 10:30:00'], $cambios));
    }

    private function captura(): array
    {
        return ['version_esquema' => 1, 'periodo' => ['nombre' => 'Período registrado'], 'version' => ['nombre' => 'Semestral registrada', 'version' => '0.1'],
            'origen' => ['nombre' => 'Nombre original', 'apellido' => null, 'rut' => null],
            'candidato' => ['nombre' => 'Ángela', 'apellido' => 'histórica', 'rut' => '22222222K'], 'responsable' => 'Operador histórico',
            'clase' => ['asignatura_codigo' => 'MAT1', 'asignatura_nombre' => 'Matemáticas registradas', 'seccion' => '1', 'actividad' => 'Cátedra', 'grupo' => 'G:1'],
            'disponibilidad' => 'declarada', 'modulos' => [['id' => $this->horario->id, 'dia' => 'Lunes', 'bloque' => 2, 'hora_inicio' => '09:10:00', 'hora_fin' => '10:00:00']]];
    }

    private function detalle(int $id): string
    {
        return route('programacion.intercambio.historial.detalle', ['registro' => $id]);
    }

    private function snapshot(): array
    {
        return collect(['historial_acciones', 'horarios', 'horarios_profesores', 'profesores', 'profesores_versiones'])
            ->mapWithKeys(fn ($tabla) => [$tabla => DB::table($tabla)->orderBy('id')->get()->toJson()])->all();
    }
}

<?php

namespace Tests\Feature;

use App\Models\ActividadSeccion;
use App\Models\AsignacionLaboratorio;
use App\Models\AsignacionLaboratorioMovil;
use App\Models\AsignacionSala;
use App\Models\Asignatura;
use App\Models\BloqueHorario;
use App\Models\Dia;
use App\Models\GrupoSeccion;
use App\Models\Horario;
use App\Models\HorarioProfesor;
use App\Models\Laboratorio;
use App\Models\LaboratorioMovil;
use App\Models\PeriodoAcademico;
use App\Models\Profesor;
use App\Models\ProfesorVersion;
use App\Models\Sala;
use App\Models\Seccion;
use App\Models\SeccionProfesor;
use App\Models\Sede;
use App\Models\TipoSala;
use App\Models\Universidad;
use App\Models\User;
use App\Models\VersionPlanificacion;
use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Permission;
use Tests\IsolatedDatabaseTestCase;

class HorarioEspacioTest extends IsolatedDatabaseTestCase
{
    private User $usuario;

    private int $universidadId;

    private int $secuencia = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->universidadId = DB::table('universidades')->value('id');
        $this->usuario = User::create([
            'name' => 'Consulta de espacios', 'rut' => '88777777',
            'email' => 'horario-espacios@example.test', 'password' => 'clave-de-prueba',
        ]);
        $this->usuario->givePermissionTo(Permission::findOrCreate('gestionar salas y recursos', 'web'));
        $this->actingAs($this->usuario);
    }

    public function test_una_instalacion_vacia_muestra_la_consulta_sin_inventar_espacios_ni_versiones(): void
    {
        $this->consultar()->assertOk()->assertViewIs('salas.horario')
            ->assertViewHas('espacios', fn ($espacios) => $espacios->isEmpty())
            ->assertViewHas('espacioSeleccionado', null)
            ->assertViewHas('periodoSeleccionado', null)
            ->assertViewHas('versionSeleccionada', null)
            ->assertViewHas('sesiones', fn ($sesiones) => $sesiones->isEmpty());
    }

    public function test_consulta_el_horario_movil_por_asignaciones_activas_y_version_sin_confundir_ids_de_otros_recursos(): void
    {
        $datos = $this->programacion();
        $movil = LaboratorioMovil::create(['universidad_id' => $this->universidadId, 'sede_id' => $datos['sala']->sede_id,
            'codigo' => 'MOVIL-01', 'nombre' => 'Laboratorio transportable', 'capacidad' => 30, 'estado' => true, 'disponible' => true]);
        foreach (['directa', 'laboratorio', 'inactiva', 'horario_importacion', 'horario_otro_periodo'] as $clave) {
            AsignacionLaboratorioMovil::create(['horario_id' => $datos[$clave]->id, 'laboratorio_movil_id' => $movil->id, 'estado' => true]);
        }
        AsignacionLaboratorioMovil::create(['horario_id' => $datos['otro_laboratorio']->id, 'laboratorio_movil_id' => $movil->id, 'estado' => false]);
        $antes = $this->snapshot();
        $response = $this->consultar(['espacio' => 'movil:'.$movil->id])->assertOk()
            ->assertSee('Laboratorio móvil consultado')->assertSee('Laboratorio transportable')->assertSee('Recurso móvil')
            ->assertViewHas('espacioSeleccionado', fn ($espacio) => $espacio['tipo'] === 'movil' && $espacio['sala_id'] === null)
            ->assertViewHas('versionSeleccionada', fn ($version) => $version->is($datos['version']));
        $this->assertSame($this->ids($datos, ['directa', 'laboratorio']), $this->sesionesIds($response));
        $this->assertSame(['MOVIL-01'], $response->viewData('sesiones')->firstWhere('id', $datos['directa']->id)['recursos']['Lab. móvil']);
        $this->assertSame($antes, $this->snapshot());
        $movil->update(['estado' => false, 'disponible' => false]);
        $response = $this->consultar(['espacio' => 'movil:'.$movil->id])->assertOk()->assertSee('Uso deshabilitado en catálogo');
        $this->assertSame($this->ids($datos, ['directa', 'laboratorio']), $this->sesionesIds($response));
    }

    public function test_editar_laboratorios_conserva_asignaciones_y_no_permite_mover_recursos_con_historial(): void
    {
        $datos = $this->programacion();
        $fijo = $datos['lab'];
        $movil = LaboratorioMovil::create(['universidad_id' => $this->universidadId, 'sede_id' => $datos['sala']->sede_id,
            'codigo' => 'MOVIL-01', 'nombre' => 'Laboratorio móvil', 'capacidad' => 30, 'estado' => true, 'disponible' => true]);
        AsignacionLaboratorioMovil::create(['horario_id' => $datos['directa']->id, 'laboratorio_movil_id' => $movil->id, 'estado' => true]);
        $antes = collect(['horarios', 'asignaciones_salas', 'asignaciones_laboratorios', 'asignaciones_laboratorios_moviles'])
            ->mapWithKeys(fn ($tabla) => [$tabla => DB::table($tabla)->orderBy('id')->get()->map(fn ($fila) => (array) $fila)->all()])->all();
        $fijoDatos = ['sala_id' => $fijo->sala_id, 'codigo' => $fijo->codigo, 'nombre' => 'Laboratorio actualizado', 'capacidad' => 20, 'estado' => 0, 'disponible' => 0];
        $movilDatos = ['sede_id' => $movil->sede_id, 'codigo' => $movil->codigo, 'nombre' => 'Móvil actualizado', 'capacidad' => 0, 'estado' => 0, 'disponible' => 0];
        $this->put(route('laboratorios.update', ['tipo' => 'fijo', 'id' => $fijo->id]), array_replace($fijoDatos, ['sala_id' => $datos['otra_sala']->id]))
            ->assertSessionHasErrors('sala_id');
        $this->put(route('laboratorios.update', ['tipo' => 'movil', 'id' => $movil->id]), array_replace($movilDatos, ['sede_id' => $datos['otra_sala']->sede_id]))
            ->assertSessionHasErrors('sede_id');
        $this->put(route('laboratorios.update', ['tipo' => 'fijo', 'id' => $fijo->id]), $fijoDatos)->assertSessionHasNoErrors();
        $this->put(route('laboratorios.update', ['tipo' => 'movil', 'id' => $movil->id]), $movilDatos)->assertSessionHasNoErrors();
        $this->assertSame('Laboratorio actualizado', $fijo->fresh()->nombre);
        $this->assertSame('Móvil actualizado', $movil->fresh()->nombre);
        foreach ($antes as $tabla => $filas) {
            $this->assertSame($filas, DB::table($tabla)->orderBy('id')->get()->map(fn ($fila) => (array) $fila)->all());
        }
        $this->assertDatabaseCount('historial_acciones', 2);
    }

    public function test_al_entrar_sin_espacio_no_carga_la_programacion_completa(): void
    {
        $datos = $this->programacion();
        DB::enableQueryLog();
        DB::flushQueryLog();

        $this->consultar([
            'periodo_id' => $datos['periodo']->id, 'version_id' => $datos['version']->id,
        ])->assertOk()->assertViewHas('sesiones', fn ($sesiones) => $sesiones->isEmpty());

        $lecturas = collect(DB::getQueryLog())->filter(
            fn ($consulta) => preg_match('/\bfrom\s+["`]?horarios["`]?(?:\s|$)/i', $consulta['query'])
        );
        $this->assertCount(0, $lecturas, 'Elegir el contexto sin un espacio no debe cargar los horarios.');
    }

    public function test_el_contexto_predeterminado_usa_el_periodo_activo_de_la_institucion_y_su_semestral_actual(): void
    {
        $datos = $this->programacion();
        $this->periodo(['nombre' => '2026-1', 'numero_semestre' => 1]);
        $this->periodo(['nombre' => '2025-2 activo', 'anio' => 2025]);
        $this->periodo(['nombre' => '2030-2 inactivo', 'anio' => 2030, 'estado' => false]);
        $otraUniversidad = Universidad::create(['nombre' => 'Otra universidad', 'sigla' => 'OTRA']);
        $this->periodo(['universidad_id' => $otraUniversidad->id, 'nombre' => '2040-2', 'anio' => 2040]);

        $response = $this->paraSala($datos['sala'])->assertOk()
            ->assertViewHas('periodoSeleccionado', fn ($periodo) => $periodo->is($datos['periodo']))
            ->assertViewHas('versionSeleccionada', fn ($version) => $version->is($datos['version']));

        $this->assertSame('sala:'.$datos['sala']->id, $response->viewData('espacioSeleccionado')['clave']);
        $this->assertSame([$datos['periodo']->universidad_id], $response->viewData('periodos')->pluck('universidad_id')->unique()->values()->all());
        $this->assertTrue($response->viewData('versiones')->contains('id', $datos['importacion']->id));
    }

    public function test_los_empates_de_periodo_y_version_se_resuelven_por_el_identificador_mas_reciente(): void
    {
        $datos = $this->programacion();
        $periodo = $this->periodo(['nombre' => 'Segundo período activo equivalente']);
        $this->version($periodo, ['nombre' => 'Primera semestral actual']);
        $esperada = $this->version($periodo, ['nombre' => 'Última semestral actual']);
        $this->version($periodo, ['tipo' => 'anterior', 'nombre' => 'Importación posterior']);

        $this->paraSala($datos['sala'])->assertOk()
            ->assertViewHas('periodoSeleccionado', fn ($actual) => $actual->is($periodo))
            ->assertViewHas('versionSeleccionada', fn ($actual) => $actual->is($esperada))
            ->assertViewHas('sesiones', fn ($sesiones) => $sesiones->isEmpty());
    }

    public function test_sin_semestral_actual_no_reutiliza_una_importacion_o_un_borrador_historico(): void
    {
        $datos = $this->programacion();
        $datos['version']->update(['es_actual' => false]);

        $this->paraSala($datos['sala'])->assertOk()
            ->assertViewHas('periodoSeleccionado', fn ($periodo) => $periodo->is($datos['periodo']))
            ->assertViewHas('versionSeleccionada', null)
            ->assertViewHas('sesiones', fn ($sesiones) => $sesiones->isEmpty());
    }

    public function test_sin_periodo_activo_el_historico_solo_se_consulta_por_seleccion_explicita(): void
    {
        $datos = $this->programacion();
        $datos['periodo']->update(['estado' => false]);

        $this->paraSala($datos['sala'])->assertOk()
            ->assertViewHas('periodoSeleccionado', null)
            ->assertViewHas('versionSeleccionada', null)
            ->assertViewHas('sesiones', fn ($sesiones) => $sesiones->isEmpty());

        $response = $this->paraSala($datos['sala'], [
            'periodo_id' => $datos['otro_periodo']->id,
            'version_id' => $datos['otra_version']->id,
        ])->assertOk();

        $this->assertSame([$datos['horario_otro_periodo']->id], $this->sesionesIds($response));
    }

    public function test_la_sala_reune_asignaciones_directas_y_laboratorios_sin_duplicar_una_clase(): void
    {
        $datos = $this->programacion();
        $antes = $this->snapshot();

        $response = $this->paraSala($datos['sala'])->assertOk();

        $this->assertSame($this->ids($datos, ['directa', 'laboratorio', 'doble', 'otro_laboratorio']), $this->sesionesIds($response));
        $this->assertSame(['bloques' => 4, 'asignaturas' => 2, 'coincidencias' => 0], $response->viewData('resumen'));
        $this->assertSame($antes, $this->snapshot(), 'La consulta no debe alterar clases, recursos ni históricos.');
    }

    public function test_el_laboratorio_no_recibe_clases_directas_de_su_sala_ni_de_otro_laboratorio(): void
    {
        $datos = $this->programacion();

        $response = $this->consultar(['espacio' => 'laboratorio:'.$datos['lab']->id])->assertOk();

        $this->assertSame($this->ids($datos, ['laboratorio', 'doble']), $this->sesionesIds($response));
        $this->assertSame('laboratorio', $response->viewData('espacioSeleccionado')['tipo']);
        $this->assertSame($datos['sala']->id, $response->viewData('espacioSeleccionado')['sala_id']);
        $this->assertSame($datos['sala']->codigo, $response->viewData('espacioSeleccionado')['sala_codigo']);
    }

    public function test_un_recurso_deshabilitado_actualmente_no_oculta_sus_clases_registradas(): void
    {
        $datos = $this->programacion();
        $datos['sala']->update(['estado' => false, 'disponible' => false]);
        $datos['lab']->update(['estado' => false, 'disponible' => false]);

        $this->assertSame($this->ids($datos, ['directa', 'laboratorio', 'doble', 'otro_laboratorio']), $this->sesionesIds(
            $this->paraSala($datos['sala'])->assertOk()
        ));
        $this->assertSame($this->ids($datos, ['laboratorio', 'doble']), $this->sesionesIds(
            $this->consultar(['espacio' => 'laboratorio:'.$datos['lab']->id])->assertOk()
        ));
    }

    public function test_los_codigos_iguales_de_otras_sedes_no_comparten_clases(): void
    {
        $datos = $this->programacion();

        $response = $this->paraSala($datos['otra_sala'])->assertOk();

        $this->assertSame([$datos['otra_sala_horario']->id], $this->sesionesIds($response));
        $this->assertSame($datos['otra_sala']->sede->nombre, $response->viewData('espacioSeleccionado')['sede']);
    }

    public function test_los_docentes_proceden_del_modulo_y_del_snapshot_de_la_version_consultada(): void
    {
        $datos = $this->programacion();
        $responsable = $this->profesor('Nombre actual', 'Apellido actual');
        $colaborador = $this->profesor('Colaborador', 'Vigente');
        $retirado = $this->profesor('Participación', 'Retirada');
        $soloSeccion = $this->profesor('Docente solo', 'De sección');
        HorarioProfesor::create(['horario_id' => $datos['directa']->id, 'profesor_id' => $responsable->id, 'estado' => true]);
        HorarioProfesor::create(['horario_id' => $datos['directa']->id, 'profesor_id' => $colaborador->id, 'estado' => true]);
        HorarioProfesor::create(['horario_id' => $datos['directa']->id, 'profesor_id' => $retirado->id, 'estado' => false]);
        SeccionProfesor::create(['seccion_id' => $datos['grupo']->actividadSeccion->seccion_id, 'profesor_id' => $soloSeccion->id]);
        ProfesorVersion::create([
            'version_planificacion_id' => $datos['version']->id, 'profesor_id' => $responsable->id,
            'nombre' => 'Nombre de la versión', 'apellido' => 'Apellido histórico',
        ]);
        ProfesorVersion::create([
            'version_planificacion_id' => $datos['importacion']->id, 'profesor_id' => $colaborador->id,
            'nombre' => 'Nombre de otra', 'apellido' => 'Versión',
        ]);

        $response = $this->paraSala($datos['sala'])->assertOk();
        $sesion = $response->viewData('sesiones')->firstWhere('id', $datos['directa']->id);

        $this->assertEqualsCanonicalizing(['Nombre de la versión Apellido histórico', 'Colaborador Vigente'], $sesion['profesores']);
        $this->assertCount(2, $sesion['profesores']);
    }

    public function test_conserva_domingo_y_bloques_inactivos_si_tienen_clases_en_la_version(): void
    {
        $datos = $this->programacion();
        $domingo = Dia::create(['nombre' => 'Domingo', 'abreviatura' => 'Dom', 'numero' => 7, 'estado' => false]);
        $bloque = BloqueHorario::create([
            'numero' => 99, 'hora_inicio' => '23:10:00', 'hora_fin' => '23:59:00', 'estado' => false,
        ]);
        $horario = $this->horario($datos['version'], $datos['grupo'], 1, 1, [
            'dia_id' => $domingo->id, 'bloque_horario_id' => $bloque->id,
        ]);
        $this->asignarSala($horario, $datos['sala']);

        $response = $this->paraSala($datos['sala'])->assertOk();

        $this->assertContains($horario->id, $this->sesionesIds($response));
        $this->assertTrue($response->viewData('dias')->contains('id', $domingo->id));
        $this->assertTrue($response->viewData('bloques')->contains('id', $bloque->id));
        $this->assertCount(1, $response->viewData('celdas')->get($domingo->id.'-'.$bloque->id));
    }

    public function test_varias_clases_en_un_bloque_permanecen_visibles_y_se_cuentan_como_coincidencia(): void
    {
        $datos = $this->programacion();
        $grupo = $this->grupo($datos['periodo'], 'MAT1');
        $coincidente = $this->horario($datos['version'], $grupo);
        $this->asignarSala($coincidente, $datos['sala']);

        $response = $this->paraSala($datos['sala'])->assertOk();
        $clave = $datos['directa']->dia_id.'-'.$datos['directa']->bloque_horario_id;

        $this->assertEqualsCanonicalizing([$datos['directa']->id, $coincidente->id], $response->viewData('celdas')->get($clave)->pluck('id')->all());
        $this->assertSame(['bloques' => 4, 'asignaturas' => 2, 'coincidencias' => 1], $response->viewData('resumen'));
    }

    public function test_elegir_dia_y_vista_conserva_las_sesiones_de_toda_la_semana_y_el_contexto(): void
    {
        $datos = $this->programacion();
        $sabado = Dia::where('numero', 6)->firstOrFail();

        $response = $this->paraSala($datos['sala'], [
            'dia_id' => $sabado->id, 'vista' => 'dia', 'intruso' => ['dato' => 'descartar'],
        ])->assertOk()->assertViewHas('diaSeleccionado', fn ($dia) => $dia->is($sabado))
            ->assertViewHas('vista', 'dia');

        $this->assertSame($this->ids($datos, ['directa', 'laboratorio', 'doble', 'otro_laboratorio']), $this->sesionesIds($response));
        $parametros = $response->viewData('parametros');
        $this->assertSame('sala:'.$datos['sala']->id, $parametros['espacio']);
        $this->assertEquals($datos['periodo']->id, $parametros['periodo_id']);
        $this->assertEquals($datos['version']->id, $parametros['version_id']);
        $this->assertEquals($sabado->id, $parametros['dia_id']);
        $this->assertArrayNotHasKey('intruso', $parametros);
    }

    public function test_la_importacion_se_consulta_solo_cuando_se_selecciona_su_version_exacta(): void
    {
        $datos = $this->programacion();

        $response = $this->paraSala($datos['sala'], [
            'periodo_id' => $datos['periodo']->id, 'version_id' => $datos['importacion']->id,
            'vista' => 'semana',
        ])->assertOk()->assertViewHas('versionSeleccionada', fn ($version) => $version->is($datos['importacion']))
            ->assertViewHas('vista', 'semana');

        $this->assertSame([$datos['horario_importacion']->id], $this->sesionesIds($response));
    }

    public function test_una_version_de_otro_periodo_se_rechaza_sin_mezclar_sus_clases(): void
    {
        $datos = $this->programacion();
        $antes = $this->snapshot();

        $response = $this->paraSala($datos['sala'], [
            'periodo_id' => $datos['periodo']->id, 'version_id' => $datos['otra_version']->id,
        ])->assertRedirect()->assertSessionHasErrors('version_id');

        $parametros = $this->parametrosDeRedireccion($response);
        $this->assertArrayNotHasKey('version_id', $parametros);
        $this->assertSame('sala:'.$datos['sala']->id, $parametros['espacio']);
        $this->assertSame($antes, $this->snapshot());
    }

    public function test_el_periodo_de_otra_institucion_se_rechaza_y_limpia_su_version(): void
    {
        $datos = $this->programacion();
        $otraUniversidad = Universidad::create(['nombre' => 'Universidad distinta', 'sigla' => 'DIST']);
        $periodo = $this->periodo(['universidad_id' => $otraUniversidad->id, 'nombre' => 'Otro contexto']);
        $version = $this->version($periodo);

        $response = $this->paraSala($datos['sala'], [
            'periodo_id' => $periodo->id, 'version_id' => $version->id,
        ])->assertRedirect()->assertSessionHasErrors('periodo_id');

        $parametros = $this->parametrosDeRedireccion($response);
        $this->assertArrayNotHasKey('periodo_id', $parametros);
        $this->assertArrayNotHasKey('version_id', $parametros);
        $this->assertSame('sala:'.$datos['sala']->id, $parametros['espacio']);
    }

    #[DataProvider('parametrosInvalidos')]
    public function test_los_parametros_invalidos_vuelven_a_una_url_limpia_sin_error_quinientos(string $campo, mixed $valor): void
    {
        $datos = $this->programacion();
        $antes = $this->snapshot();
        $parametros = [
            'espacio' => 'sala:'.$datos['sala']->id, 'periodo_id' => $datos['periodo']->id,
            'version_id' => $datos['version']->id, 'intruso' => ['dato' => 'no conservar'],
        ];
        $parametros[$campo] = $valor;

        $response = $this->consultar($parametros)->assertRedirect()->assertSessionHasErrors($campo);
        $destino = $this->parametrosDeRedireccion($response);

        $this->assertArrayNotHasKey($campo, $destino);
        $this->assertArrayNotHasKey('intruso', $destino);
        $this->assertSame($antes, $this->snapshot());
        $this->get($response->headers->get('Location'))->assertOk();
    }

    public function test_sin_permiso_no_puede_consultar_las_clases_de_un_espacio(): void
    {
        $datos = $this->programacion();
        $this->usuario->givePermissionTo(Permission::findOrCreate('ver panel inicio', 'web'));
        $this->usuario->revokePermissionTo('gestionar salas y recursos');
        $this->actingAs($this->usuario->fresh());

        $this->paraSala($datos['sala'])->assertRedirect(route('inicio'));
    }

    public function test_un_invitado_debe_iniciar_sesion_antes_de_consultar_un_espacio(): void
    {
        $datos = $this->programacion();
        auth()->forgetGuards();

        $this->paraSala($datos['sala'])->assertRedirect(route('login'));
    }

    public function test_el_catalogo_ofrece_el_enlace_al_horario_de_la_sala(): void
    {
        $datos = $this->programacion();
        $url = route('salas.horario', ['espacio' => 'sala:'.$datos['sala']->id]);

        $this->get(route('salas.index'))->assertOk()->assertSee($url);
    }

    public function test_el_laboratorio_enlaza_al_horario_completo_de_su_sala_con_el_mismo_contexto(): void
    {
        $datos = $this->programacion();
        $dia = Dia::where('numero', 1)->firstOrFail();
        $antes = $this->snapshot();
        $response = $this->consultar([
            'espacio' => 'laboratorio:'.$datos['lab']->id,
            'periodo_id' => $datos['otro_periodo']->id,
            'version_id' => $datos['otra_version']->id,
            'dia_id' => $dia->id, 'vista' => 'dia', 'intruso' => 'descartar',
        ])->assertOk();
        $enlace = $this->xpath($response)->query('//a[starts-with(normalize-space(.), "Ver sala ")]')->item(0);
        $this->assertNotNull($enlace, 'El laboratorio debe permitir consultar el uso de su sala contenedora.');
        parse_str(parse_url($enlace->getAttribute('href'), PHP_URL_QUERY) ?? '', $parametros);
        $this->assertEquals([
            'espacio' => 'sala:'.$datos['sala']->id,
            'periodo_id' => $datos['otro_periodo']->id,
            'version_id' => $datos['otra_version']->id,
            'dia_id' => $dia->id, 'vista' => 'dia',
        ], $parametros);

        $sala = $this->get($enlace->getAttribute('href'))->assertOk()
            ->assertViewHas('versionSeleccionada', fn ($version) => $version->is($datos['otra_version']));

        $this->assertSame([$datos['horario_otro_periodo']->id], $this->sesionesIds($sala));
        $this->assertSame($antes, $this->snapshot());
    }

    public function test_el_enlace_al_dia_siguiente_conserva_el_espacio_y_la_version_y_muestra_sus_modulos(): void
    {
        $datos = $this->programacion();
        $lunes = Dia::where('numero', 1)->firstOrFail();
        $martes = Dia::where('numero', 2)->firstOrFail();
        $response = $this->paraSala($datos['sala'], [
            'periodo_id' => $datos['periodo']->id, 'version_id' => $datos['version']->id,
            'dia_id' => $lunes->id, 'vista' => 'dia', 'intruso' => ['dato' => 'descartar'],
        ])->assertOk();
        $enlace = $this->xpath($response)->query('//a[@aria-label="Día siguiente"]')->item(0);
        $this->assertNotNull($enlace);
        parse_str(parse_url($enlace->getAttribute('href'), PHP_URL_QUERY) ?? '', $parametros);
        $this->assertEquals([
            'espacio' => 'sala:'.$datos['sala']->id,
            'periodo_id' => $datos['periodo']->id, 'version_id' => $datos['version']->id,
            'dia_id' => $martes->id, 'vista' => 'dia',
        ], $parametros);

        $siguiente = $this->get($enlace->getAttribute('href'))->assertOk()
            ->assertViewHas('diaSeleccionado', fn ($dia) => $dia->is($martes));
        $tarjetas = $this->xpath($siguiente)->query('//*[@id="dia-espacio"]//article[@data-clase-id]');

        $this->assertCount(1, $tarjetas);
        $this->assertSame((string) $datos['laboratorio']->id, $tarjetas->item(0)->getAttribute('data-clase-id'));
        $this->assertSame($this->ids($datos, ['directa', 'laboratorio', 'doble', 'otro_laboratorio']), $this->sesionesIds($siguiente));
    }

    public function test_una_sala_sin_asignaciones_no_hereda_clases_de_otros_espacios(): void
    {
        $datos = $this->programacion();
        $sinClases = Sala::create([
            'universidad_id' => $this->universidadId, 'sede_id' => $datos['sala']->sede_id,
            'tipo_sala_id' => $datos['sala']->tipo_sala_id, 'codigo' => 'SIN-CLASES',
        ]);
        $antes = $this->snapshot();

        $this->paraSala($sinClases)->assertOk()
            ->assertViewHas('versionSeleccionada', fn ($version) => $version->is($datos['version']))
            ->assertViewHas('sesiones', fn ($sesiones) => $sesiones->isEmpty())
            ->assertViewHas('resumen', ['bloques' => 0, 'asignaturas' => 0, 'coincidencias' => 0])
            ->assertSee('Sin clases registradas en esta versión');

        $this->assertSame($antes, $this->snapshot());
    }

    public static function parametrosInvalidos(): array
    {
        return [
            'espacio compuesto' => ['espacio', ['sala:1']],
            'tipo desconocido' => ['espacio', 'recurso:1'],
            'identificador nulo' => ['espacio', 'sala:0'],
            'sala inexistente' => ['espacio', 'sala:999999'],
            'laboratorio inexistente' => ['espacio', 'laboratorio:999999'],
            'móvil inexistente' => ['espacio', 'movil:999999'],
            'móvil con identificador nulo' => ['espacio', 'movil:0'],
            'espacio demasiado largo' => ['espacio', str_repeat('s', 65)],
            'período compuesto' => ['periodo_id', [1]],
            'período negativo' => ['periodo_id', -1],
            'período inexistente' => ['periodo_id', 999999],
            'versión compuesta' => ['version_id', [1]],
            'versión inexistente' => ['version_id', 999999],
            'día compuesto' => ['dia_id', [1]],
            'día inexistente' => ['dia_id', 999999],
            'vista compuesta' => ['vista', ['dia']],
            'vista desconocida' => ['vista', 'mensual'],
        ];
    }

    public function test_las_tarjetas_muestran_los_recursos_activos_del_modulo_sin_heredar_los_del_espacio(): void
    {
        $datos = $this->programacion();
        $datos['otra_sala']->update(['codigo' => 'SALA-RETIRADA']);
        $this->asignarSala($datos['directa'], $datos['otra_sala'], false);
        $this->asignarLab($datos['directa'], $datos['otro_lab'], false);

        $sesiones = $this->paraSala($datos['sala'])->assertOk()->viewData('sesiones');

        $this->assertSame(['Sala' => ['A-101'], 'Lab.' => [], 'Lab. móvil' => []], $sesiones->firstWhere('id', $datos['directa']->id)['recursos']);
        $this->assertSame(['Sala' => [], 'Lab.' => ['LAB-1'], 'Lab. móvil' => []], $sesiones->firstWhere('id', $datos['laboratorio']->id)['recursos']);
        $this->assertSame(['Sala' => ['A-101'], 'Lab.' => ['LAB-1'], 'Lab. móvil' => []], $sesiones->firstWhere('id', $datos['doble']->id)['recursos']);
    }

    private function programacion(): array
    {
        $periodo = $this->periodo();
        $otroPeriodo = $this->periodo(['nombre' => '2025-2', 'anio' => 2025, 'estado' => false]);
        $version = $this->version($periodo);
        $importacion = $this->version($periodo, ['nombre' => 'Importación anterior', 'tipo' => 'anterior', 'version' => '2.0']);
        $historica = $this->version($periodo, ['nombre' => 'Semestral histórica', 'es_actual' => false, 'version' => '0.0']);
        $otraVersion = $this->version($otroPeriodo);
        $tipo = TipoSala::create(['nombre' => 'Sala de docencia']);
        $sede = Sede::create(['universidad_id' => $this->universidadId, 'nombre' => 'Sede central']);
        $otraSede = Sede::create(['universidad_id' => $this->universidadId, 'nombre' => 'Sede norte']);
        $sala = Sala::create([
            'universidad_id' => $this->universidadId, 'sede_id' => $sede->id, 'tipo_sala_id' => $tipo->id,
            'codigo' => 'A-101', 'nombre' => 'Sala central', 'capacidad' => 40, 'estado' => true,
        ]);
        $otraSala = Sala::create([
            'universidad_id' => $this->universidadId, 'sede_id' => $otraSede->id, 'tipo_sala_id' => $tipo->id,
            'codigo' => 'A-101', 'nombre' => 'Sala norte', 'capacidad' => 30, 'estado' => true,
        ]);
        $lab = Laboratorio::create(['sala_id' => $sala->id, 'codigo' => 'LAB-1', 'nombre' => 'Laboratorio uno']);
        $otroLab = Laboratorio::create(['sala_id' => $sala->id, 'codigo' => 'LAB-2', 'nombre' => 'Laboratorio dos']);
        $grupo = $this->grupo($periodo, 'MAT1');
        $otroGrupo = $this->grupo($periodo, 'FIS1');
        $grupoAnterior = $this->grupo($otroPeriodo, 'MAT1');
        $directa = $this->horario($version, $grupo);
        $this->asignarSala($directa, $sala);
        $laboratorio = $this->horario($version, $grupo, 2, 2);
        $this->asignarLab($laboratorio, $lab);
        $doble = $this->horario($version, $otroGrupo, 1, 3);
        $this->asignarSala($doble, $sala);
        $this->asignarLab($doble, $lab);
        $otroLaboratorio = $this->horario($version, $otroGrupo, 3, 4);
        $this->asignarLab($otroLaboratorio, $otroLab);
        $otraSalaHorario = $this->horario($version, $otroGrupo, 4, 5);
        $this->asignarSala($otraSalaHorario, $otraSala);
        $inactiva = $this->horario($version, $grupo, 5, 6, ['estado' => false]);
        $this->asignarSala($inactiva, $sala);
        $pivotInactivo = $this->horario($version, $grupo, 5, 7);
        $this->asignarSala($pivotInactivo, $sala, false);
        $labInactivo = $this->horario($version, $otroGrupo, 5, 8);
        $this->asignarLab($labInactivo, $lab, false);
        $horarioImportacion = $this->horario($importacion, $grupo);
        $this->asignarSala($horarioImportacion, $sala);
        $horarioHistorico = $this->horario($historica, $grupo);
        $this->asignarSala($horarioHistorico, $sala);
        $horarioOtroPeriodo = $this->horario($otraVersion, $grupoAnterior);
        $this->asignarSala($horarioOtroPeriodo, $sala);
        $periodoIncompatible = $this->horario($version, $grupoAnterior, 1, 2);
        $this->asignarSala($periodoIncompatible, $sala);

        return [
            'periodo' => $periodo, 'otro_periodo' => $otroPeriodo, 'version' => $version,
            'importacion' => $importacion, 'historica' => $historica, 'otra_version' => $otraVersion,
            'sala' => $sala, 'otra_sala' => $otraSala, 'lab' => $lab, 'otro_lab' => $otroLab,
            'grupo' => $grupo, 'directa' => $directa, 'laboratorio' => $laboratorio, 'inactiva' => $inactiva,
            'doble' => $doble, 'otro_laboratorio' => $otroLaboratorio, 'otra_sala_horario' => $otraSalaHorario,
            'horario_importacion' => $horarioImportacion, 'horario_otro_periodo' => $horarioOtroPeriodo,
        ];
    }

    private function periodo(array $cambios = []): PeriodoAcademico
    {
        return PeriodoAcademico::create(array_replace([
            'universidad_id' => $this->universidadId, 'nombre' => '2026-2',
            'anio' => 2026, 'numero_semestre' => 2, 'estado' => true,
        ], $cambios));
    }

    private function version(PeriodoAcademico $periodo, array $cambios = []): VersionPlanificacion
    {
        return VersionPlanificacion::create(array_replace([
            'periodo_academico_id' => $periodo->id, 'nombre' => 'Semestral de prueba',
            'tipo' => 'semestral', 'version' => '0.1', 'estado' => 'Borrador', 'es_actual' => true,
        ], $cambios));
    }

    private function grupo(PeriodoAcademico $periodo, string $codigo): GrupoSeccion
    {
        $asignatura = Asignatura::firstOrCreate([
            'universidad_id' => $this->universidadId, 'codigo' => $codigo,
        ], ['nombre' => 'Asignatura '.$codigo]);
        $seccion = Seccion::create([
            'periodo_academico_id' => $periodo->id, 'asignatura_id' => $asignatura->id,
            'codigo' => (string) ++$this->secuencia,
        ]);
        $actividad = ActividadSeccion::create(['seccion_id' => $seccion->id, 'tipo' => 'Cátedra']);

        return GrupoSeccion::create(['actividad_seccion_id' => $actividad->id, 'codigo' => 'G:1']);
    }

    private function horario(VersionPlanificacion $version, GrupoSeccion $grupo, int $dia = 1, int $bloque = 1, array $cambios = []): Horario
    {
        return Horario::create(array_replace([
            'version_planificacion_id' => $version->id, 'actividad_seccion_id' => $grupo->actividad_seccion_id,
            'grupo_seccion_id' => $grupo->id, 'dia_id' => Dia::where('numero', $dia)->value('id'),
            'bloque_horario_id' => BloqueHorario::where('numero', $bloque)->value('id'), 'estado' => true,
        ], $cambios));
    }

    private function asignarSala(Horario $horario, Sala $sala, bool $estado = true): void
    {
        AsignacionSala::create(['horario_id' => $horario->id, 'sala_id' => $sala->id, 'estado' => $estado]);
    }

    private function asignarLab(Horario $horario, Laboratorio $laboratorio, bool $estado = true): void
    {
        AsignacionLaboratorio::create(['horario_id' => $horario->id, 'laboratorio_id' => $laboratorio->id, 'estado' => $estado]);
    }

    private function profesor(string $nombre, string $apellido): Profesor
    {
        return Profesor::create(['universidad_id' => $this->universidadId, 'nombre' => $nombre, 'apellido' => $apellido]);
    }

    private function paraSala(Sala $sala, array $parametros = []): TestResponse
    {
        return $this->consultar(['espacio' => 'sala:'.$sala->id] + $parametros);
    }

    private function consultar(array $parametros = []): TestResponse
    {
        return $this->get(route('salas.horario', $parametros));
    }

    private function sesionesIds(TestResponse $response): array
    {
        return $response->viewData('sesiones')->pluck('id')->sort()->values()->all();
    }

    private function ids(array $datos, array $claves): array
    {
        return collect($claves)->map(fn ($clave) => $datos[$clave]->id)->sort()->values()->all();
    }

    private function parametrosDeRedireccion(TestResponse $response): array
    {
        $url = $response->headers->get('Location');
        $this->assertSame('/salas/horario', parse_url($url, PHP_URL_PATH));
        parse_str(parse_url($url, PHP_URL_QUERY) ?? '', $parametros);

        return $parametros;
    }

    private function xpath(TestResponse $response): DOMXPath
    {
        $anterior = libxml_use_internal_errors(true);
        $documento = new DOMDocument;
        $documento->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
        libxml_clear_errors();
        libxml_use_internal_errors($anterior);

        return new DOMXPath($documento);
    }

    private function snapshot(): array
    {
        return collect([
            'periodos_academicos', 'versiones_planificacion', 'salas', 'laboratorios', 'secciones',
            'actividades_seccion', 'grupos_seccion', 'horarios', 'asignaciones_salas',
            'asignaciones_laboratorios', 'horarios_profesores', 'profesores_versiones',
            'laboratorios_moviles', 'asignaciones_laboratorios_moviles',
        ])->mapWithKeys(fn ($tabla) => [$tabla => DB::table($tabla)->orderBy('id')->get()->map(fn ($fila) => (array) $fila)->all()])->all();
    }
}

<?php

namespace Tests\Feature;

use App\Models\ActividadSeccion;
use App\Models\Asignatura;
use App\Models\Carrera;
use App\Models\GrupoSeccion;
use App\Models\Horario;
use App\Models\HorarioProfesor;
use App\Models\PeriodoAcademico;
use App\Models\Profesor;
use App\Models\Sala;
use App\Models\Seccion;
use App\Models\Sede;
use App\Models\TipoSala;
use App\Models\User;
use App\Models\VersionPlanificacion;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Tests\IsolatedDatabaseTestCase;

class InicioTest extends IsolatedDatabaseTestCase
{
    private User $usuario;

    private int $universidadId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->universidadId = DB::table('universidades')->value('id');
        $this->usuario = User::create([
            'name' => 'Usuario del panel', 'rut' => '11111111',
            'email' => 'panel@example.test', 'password' => 'clave-de-prueba',
        ]);
        $this->darPermisos('ver panel inicio');
        $this->actingAs($this->usuario);
    }

    public function test_una_instalacion_sin_datos_muestra_un_resumen_vacio_sin_validaciones_ficticias(): void
    {
        $this->get(route('inicio'))
            ->assertOk()
            ->assertViewIs('inicio')
            ->assertViewHas('periodoActivo', fn ($periodo) => $periodo === null)
            ->assertViewHas('versionSemestral', fn ($version) => $version === null)
            ->assertViewHas('totales', ['carreras' => 0, 'profesores' => 0, 'salas' => 0, 'secciones' => 0])
            ->assertViewHas('resumen', ['modulos' => 0, 'grupos' => 0, 'secciones' => 0])
            ->assertViewHas('accesos', [])
            ->assertDontSee('Estado de validación');
    }

    public function test_cuenta_catalogos_activos_y_secciones_activas_del_periodo_sin_duplicar_cursos_compartidos(): void
    {
        $periodo = $this->crearPeriodo();
        $otroPeriodo = $this->crearPeriodo(['anio' => 2025, 'nombre' => '2025-2']);
        $sede = Sede::create(['universidad_id' => $this->universidadId, 'nombre' => 'Sede de prueba']);
        $carrera = Carrera::create([
            'universidad_id' => $this->universidadId, 'sede_id' => $sede->id,
            'nombre' => 'Carrera activa', 'estado' => true,
        ]);
        $otraCarrera = Carrera::create([
            'universidad_id' => $this->universidadId, 'sede_id' => $sede->id,
            'nombre' => 'Carrera inactiva', 'estado' => false,
        ]);
        Profesor::create(['universidad_id' => $this->universidadId, 'nombre' => 'Docente activo', 'apellido' => 'Prueba', 'estado' => true]);
        Profesor::create(['universidad_id' => $this->universidadId, 'nombre' => 'Docente inactivo', 'apellido' => 'Prueba', 'estado' => false]);
        $tipo = TipoSala::create(['nombre' => 'Sala de prueba']);
        foreach ([['A-1', true, false], ['A-2', false, true]] as [$codigo, $estado, $disponible]) {
            Sala::create([
                'universidad_id' => $this->universidadId, 'sede_id' => $sede->id,
                'tipo_sala_id' => $tipo->id, 'codigo' => $codigo,
                'estado' => $estado, 'disponible' => $disponible,
            ]);
        }
        $seccion = $this->crearSeccion($periodo);
        $seccion->carreras()->attach([$carrera->id, $otraCarrera->id]);
        $this->crearSeccion($periodo, ['codigo' => '2', 'estado_activo' => false]);
        $this->crearSeccion($otroPeriodo);

        $this->get(route('inicio'))
            ->assertOk()
            ->assertViewHas('totales', ['carreras' => 1, 'profesores' => 1, 'salas' => 1, 'secciones' => 1]);

        $periodo->update(['estado' => false]);
        $otroPeriodo->update(['estado' => false]);
        $this->get(route('inicio'))
            ->assertOk()
            ->assertViewHas('periodoActivo', fn ($actual) => $actual === null)
            ->assertViewHas('totales', ['carreras' => 1, 'profesores' => 1, 'salas' => 1, 'secciones' => 0]);
    }

    public function test_elige_el_periodo_activo_por_anio_y_semestre_sin_confundir_el_ultimo_id(): void
    {
        $this->crearPeriodo();
        $this->crearPeriodo(['nombre' => '2026-1', 'numero_semestre' => 1]);
        $this->crearPeriodo(['nombre' => '2025-2', 'anio' => 2025]);
        $esperado = $this->crearPeriodo(['nombre' => '2026-2 vigente']);
        $this->crearPeriodo(['nombre' => '2028-2', 'anio' => 2028, 'estado' => false]);

        $this->get(route('inicio'))
            ->assertOk()
            ->assertViewHas('periodoActivo', fn ($periodo) => $periodo->is($esperado));
    }

    public function test_usa_solo_la_version_semestral_actual_del_periodo_elegido_y_conserva_su_contexto_en_el_enlace(): void
    {
        $this->darPermisos('ver programacion semestral');
        $periodo = $this->crearPeriodo();
        $otroPeriodo = $this->crearPeriodo(['anio' => 2025, 'nombre' => '2025-2']);
        $esperada = $this->crearVersion($periodo);
        $this->crearVersion($periodo, ['nombre' => 'Semestral histórica', 'version' => '0.9', 'es_actual' => false]);
        $this->crearVersion($periodo, ['nombre' => 'Importación actual', 'tipo' => 'anterior', 'version' => '2.0']);
        $this->crearVersion($otroPeriodo, ['nombre' => 'Semestral de otro período', 'version' => '3.0']);

        $response = $this->get(route('inicio'))
            ->assertOk()
            ->assertViewHas('versionSemestral', fn ($version) => $version->is($esperada))
            ->assertViewHas('resumen', ['modulos' => 0, 'grupos' => 0, 'secciones' => 0])
            ->assertSee('Esta versión todavía no tiene módulos programados.')
            ->assertSee('Semestral · Borrador');

        $url = $response->viewData('urlProgramacion');
        $this->assertIsString($url);
        $this->assertSame('/programacion/semestral', parse_url($url, PHP_URL_PATH));
        parse_str(parse_url($url, PHP_URL_QUERY) ?? '', $parametros);
        $this->assertSame((string) $periodo->id, $parametros['periodo_id'] ?? null);
        $this->assertSame((string) $esperada->id, $parametros['version_id'] ?? null);
        $response->assertSee($url);
    }

    public function test_sin_semestral_actual_no_reutiliza_una_importacion_ni_una_version_de_otro_periodo(): void
    {
        $periodo = $this->crearPeriodo();
        $otroPeriodo = $this->crearPeriodo(['anio' => 2025, 'nombre' => '2025-2']);
        $this->crearVersion($periodo, ['tipo' => 'anterior']);
        $this->crearVersion($periodo, ['version' => '0.2', 'es_actual' => false]);
        $this->crearVersion($otroPeriodo);

        $this->get(route('inicio'))
            ->assertOk()
            ->assertViewHas('periodoActivo', fn ($actual) => $actual->is($periodo))
            ->assertViewHas('versionSemestral', fn ($version) => $version === null)
            ->assertViewHas('resumen', ['modulos' => 0, 'grupos' => 0, 'secciones' => 0]);
    }

    public function test_el_resumen_agrega_modulos_grupos_y_secciones_sin_cargar_historicos_ni_multiplicar_docentes(): void
    {
        $periodo = $this->crearPeriodo();
        $version = $this->crearVersion($periodo);
        $historica = $this->crearVersion($periodo, ['version' => '0.2', 'es_actual' => false]);
        $anterior = $this->crearVersion($periodo, ['tipo' => 'anterior', 'version' => '1.0']);
        $seccion = $this->crearSeccion($periodo);
        $otraSeccion = $this->crearSeccion($periodo, ['codigo' => '2']);
        $grupo = $this->crearGrupo($seccion);
        $otroGrupo = GrupoSeccion::create(['actividad_seccion_id' => $grupo->actividad_seccion_id, 'codigo' => 'G:2']);
        $tercerGrupo = $this->crearGrupo($otraSeccion);
        $primero = $this->crearHorario($version, $grupo, 1);
        $this->crearHorario($version, $grupo, 2);
        $this->crearHorario($version, $otroGrupo, 3);
        $this->crearHorario($version, $tercerGrupo, 4);
        $this->crearHorario($version, $tercerGrupo, 5, ['estado' => false]);
        $this->crearHorario($historica, $grupo, 6);
        $this->crearHorario($anterior, $grupo, 7);
        foreach (['Responsable', 'Colaborador'] as $nombre) {
            $profesor = Profesor::create(['universidad_id' => $this->universidadId, 'nombre' => $nombre, 'apellido' => 'Prueba']);
            HorarioProfesor::create(['horario_id' => $primero->id, 'profesor_id' => $profesor->id]);
        }
        DB::enableQueryLog();

        $this->get(route('inicio'))
            ->assertOk()
            ->assertViewHas('resumen', ['modulos' => 4, 'grupos' => 3, 'secciones' => 2]);

        $cargasCompletas = collect(DB::getQueryLog())->filter(
            fn ($query) => preg_match('/^select \* from ["`]horarios["`]/i', $query['query'])
        );
        $this->assertCount(0, $cargasCompletas);
    }

    public function test_los_accesos_y_el_enlace_a_programacion_respetan_los_permisos_del_usuario(): void
    {
        $periodo = $this->crearPeriodo();
        $version = $this->crearVersion($periodo);
        $this->darPermisos('ver programacion semestral', 'asignar profesores');
        $response = $this->get(route('inicio'))->assertOk();
        $accesos = $response->viewData('accesos');
        $this->assertIsArray($accesos);
        $rutasPermitidas = ['/programacion/semestral', '/programacion/asignacion-profesores'];
        $rutasEncontradas = [];
        foreach ($accesos as $acceso) {
            $this->assertNotEmpty($acceso['titulo']);
            $ruta = parse_url($acceso['url'], PHP_URL_PATH);
            $this->assertContains($ruta, $rutasPermitidas);
            $rutasEncontradas[] = $ruta;
            if ($ruta === '/programacion/asignacion-profesores') {
                parse_str(parse_url($acceso['url'], PHP_URL_QUERY) ?? '', $parametros);
                $this->assertSame((string) $periodo->id, $parametros['periodo_id'] ?? null);
            }
        }
        $this->assertContains('/programacion/asignacion-profesores', $rutasEncontradas);

        foreach (['ver programacion semestral', 'asignar profesores'] as $permiso) {
            $this->usuario->revokePermissionTo($permiso);
        }
        $this->actingAs($this->usuario->fresh());
        $sinPermisos = $this->get(route('inicio'))->assertOk()->assertViewHas('accesos', []);
        $urlProgramacion = route('programacion.semestral', ['periodo_id' => $periodo->id, 'version_id' => $version->id]);
        $sinPermisos->assertDontSee('href="'.e($urlProgramacion).'"', false);
        $sinPermisos->assertDontSee('href="'.e(route('administracion.index')).'"', false);
    }

    public function test_la_ruta_de_inicio_sigue_reservada_a_su_permiso(): void
    {
        $this->darPermisos('ver programacion semestral');
        $this->usuario->revokePermissionTo('ver panel inicio');
        $this->actingAs($this->usuario->fresh());

        $this->get(route('inicio'))->assertRedirect(route('programacion.semestral'));
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('rolesDisponibilidad')]
    public function test_la_disponibilidad_personal_no_aparece_en_el_panel_ni_en_los_menus_del_administrador(array $roles, bool $visible): void
    {
        $this->darPermisos('enviar propuesta horario', 'ver programacion semestral');
        foreach ($roles as $rol) {
            $this->usuario->assignRole(\Spatie\Permission\Models\Role::findOrCreate($rol, 'web'));
        }
        $this->actingAs($this->usuario->fresh());

        $response = $this->get(route('inicio'))->assertOk();
        $this->assertSame($visible, collect($response->viewData('accesos'))->contains('titulo', 'Mi disponibilidad'));
        $this->assertSame($visible, str_contains($response->getContent(), 'href="'.e(route('programacion.propuesta')).'"'));
    }

    public static function rolesDisponibilidad(): array
    {
        return [
            'administrador' => [['Administrador'], false],
            'profesor' => [['Profesor'], true],
            'administrador con segundo rol docente' => [['Administrador', 'Profesor'], false],
        ];
    }

    private function darPermisos(string ...$permisos): void
    {
        foreach ($permisos as $permiso) {
            $this->usuario->givePermissionTo(Permission::findOrCreate($permiso, 'web'));
        }
    }

    private function crearPeriodo(array $cambios = []): PeriodoAcademico
    {
        return PeriodoAcademico::create(array_replace([
            'universidad_id' => $this->universidadId, 'nombre' => '2026-2',
            'anio' => 2026, 'numero_semestre' => 2, 'estado' => true,
        ], $cambios));
    }

    private function crearVersion(PeriodoAcademico $periodo, array $cambios = []): VersionPlanificacion
    {
        return VersionPlanificacion::create(array_replace([
            'periodo_academico_id' => $periodo->id, 'nombre' => 'Semestral de prueba',
            'tipo' => 'semestral', 'version' => '0.1', 'estado' => 'Borrador', 'es_actual' => true,
        ], $cambios));
    }

    private function crearSeccion(PeriodoAcademico $periodo, array $cambios = []): Seccion
    {
        $asignatura = Asignatura::firstOrCreate(
            ['universidad_id' => $this->universidadId, 'codigo' => 'INICIO101'],
            ['nombre' => 'Asignatura del panel']
        );

        return Seccion::create(array_replace([
            'periodo_academico_id' => $periodo->id, 'asignatura_id' => $asignatura->id,
            'codigo' => '1', 'estado_activo' => true,
        ], $cambios));
    }

    private function crearGrupo(Seccion $seccion): GrupoSeccion
    {
        $actividad = ActividadSeccion::create(['seccion_id' => $seccion->id, 'tipo' => 'Cátedra', 'cantidad_horas' => 3]);

        return GrupoSeccion::create(['actividad_seccion_id' => $actividad->id, 'codigo' => 'G:1']);
    }

    private function crearHorario(VersionPlanificacion $version, GrupoSeccion $grupo, int $numeroBloque, array $cambios = []): Horario
    {
        return Horario::create(array_replace([
            'version_planificacion_id' => $version->id, 'actividad_seccion_id' => $grupo->actividad_seccion_id,
            'grupo_seccion_id' => $grupo->id, 'dia_id' => DB::table('dias')->where('numero', 1)->value('id'),
            'bloque_horario_id' => DB::table('bloques_horarios')->where('numero', $numeroBloque)->value('id'),
            'estado' => true,
        ], $cambios));
    }
}

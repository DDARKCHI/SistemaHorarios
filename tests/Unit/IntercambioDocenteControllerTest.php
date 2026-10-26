<?php

namespace Tests\Unit;

use App\Http\Controllers\IntercambioDocenteController;
use Illuminate\Container\Container;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\TestCase;

class IntercambioDocenteControllerTest extends TestCase
{
    private Capsule $database;

    private mixed $previousFacadeApplication;

    private mixed $previousContainer;

    private mixed $previousResolver;

    private mixed $previousDispatcher;

    private array $viewData = [];

    protected function setUp(): void
    {
        parent::setUp();

        // No cargar .env: todas las consultas se ejecutan en memoria.
        $this->previousFacadeApplication = Facade::getFacadeApplication();
        $this->previousContainer = Container::getInstance();
        $this->previousResolver = Model::getConnectionResolver();
        $this->previousDispatcher = Model::getEventDispatcher();
        $container = new Container;
        Container::setInstance($container);
        $this->database = new Capsule($container);
        $this->database->addConnection([
            'driver' => 'sqlite',
            'database' => ':memory:',
            'foreign_key_constraints' => true,
        ]);
        $container->instance('db', $this->database->getDatabaseManager());
        $container->bind('db.schema', fn () => $this->database->getConnection()->getSchemaBuilder());
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($container);
        Model::setConnectionResolver($this->database->getDatabaseManager());
        Model::unsetEventDispatcher();

        $views = $this->createMock(Factory::class);
        $views->expects($this->atLeastOnce())->method('make')
            ->willReturnCallback(function ($name, $data) {
                $this->assertSame('programacion.intercambio', $name);
                $this->viewData = $data;

                return $this->createStub(View::class);
            });
        $container->instance(Factory::class, $views);

        $this->createSchema();
    }

    protected function tearDown(): void
    {
        $this->database->getConnection()->disconnect();
        if ($this->previousResolver) {
            Model::setConnectionResolver($this->previousResolver);
        } else {
            Model::unsetConnectionResolver();
        }
        if ($this->previousDispatcher) {
            Model::setEventDispatcher($this->previousDispatcher);
        } else {
            Model::unsetEventDispatcher();
        }
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($this->previousFacadeApplication);
        Container::setInstance($this->previousContainer);

        parent::tearDown();
    }

    public function test_prefiere_la_semestral_actual_a_una_importacion_mas_reciente(): void
    {
        $this->period(3, 2026, 2, true);
        $this->version(22, 3, 'semestral');
        $this->version(33, 3, 'anterior');
        $this->version(34, 3, 'semestral', false);

        $this->consult();

        $this->assertSame(22, $this->viewData['versionActual']->id);
    }

    public function test_selecciona_el_periodo_activo_por_anio_y_semestre(): void
    {
        $this->period(10, 2026, 2, true);
        $this->period(11, 2026, 1, true);
        $this->period(12, 2025, 2, true);
        $this->period(13, 2027, 1, false);
        $this->version(22, 10, 'semestral');
        $this->version(23, 11, 'semestral');
        $this->version(24, 12, 'semestral');
        $this->version(25, 13, 'semestral');

        $this->consult();

        $this->assertSame(22, $this->viewData['versionActual']->id);
    }

    public function test_no_reutiliza_otro_periodo_si_el_actual_no_tiene_borrador_vigente(): void
    {
        $this->period(1, 2026, 1, true);
        $this->period(3, 2026, 2, true);
        $this->version(20, 1, 'semestral');
        $this->version(22, 3, 'semestral', false);
        $this->version(33, 3, 'anterior');

        $this->consult();

        $this->assertNull($this->viewData['versionActual']);
        $this->assertCount(0, $this->viewData['resultados']);
    }

    public function test_sin_periodo_activo_no_consulta_versiones_historicas(): void
    {
        $this->period(1, 2026, 1, false);
        $this->version(22, 1, 'semestral');

        $this->consult();

        $this->assertNull($this->viewData['versionActual']);
        $this->assertCount(0, $this->viewData['resultados']);
    }

    public function test_carga_y_profesores_pertenecen_al_periodo_y_version_semestral_actual(): void
    {
        $this->academicFixture();

        $this->consult(1);

        $this->assertSame(22, $this->viewData['versionActual']->id);
        $results = $this->viewData['resultados'];
        $this->assertCount(1, $results);
        $this->assertSame(1, $results[0]['profesor']->id);
        $this->assertSame([100], $results[0]['horarios']->pluck('horario.id')->all());
        $this->assertSame([100], $results[0]['horarios_carga']->pluck('id')->all());
        $this->assertSame(1, $results[0]['cantidad_horarios']);
        $this->assertSame(1, $results[0]['cantidad_asignaturas']);
    }

    public function test_sin_borrador_no_muestra_profesores_aunque_la_asignatura_exista(): void
    {
        $this->academicFixture();
        DB::table('versiones_planificacion')->where('id', 22)->update(['es_actual' => false]);

        $this->consult(1);

        $this->assertSame(1, $this->viewData['asignatura']->id);
        $this->assertNull($this->viewData['versionActual']);
        $this->assertCount(0, $this->viewData['resultados']);
    }

    public function test_reune_todas_las_secciones_del_mismo_docente_sin_atribuirle_modulos_del_codocente(): void
    {
        $this->multiSectionFixture();
        $antes = $this->snapshot();

        $this->consult(1);

        $results = $this->viewData['resultados']->keyBy('profesor.id');
        $this->assertSame([1, 3], $results->keys()->sort()->values()->all());
        $docente = $results[1];
        $this->assertSame([100, 103], $docente['horarios']->pluck('horario.id')->sort()->values()->all());
        $this->assertSame([2, 3], $docente['horarios']->pluck('grupo.id')->sort()->values()->all());
        $this->assertSame([1, 2], $docente['sedes']->pluck('id')->sort()->values()->all());
        $this->assertSame([100, 103, 105], $docente['horarios_carga']->pluck('id')->sort()->values()->all());
        $this->assertSame([1, 2, 3], $docente['sedes_carga']->pluck('id')->sort()->values()->all());
        $this->assertSame(3, $docente['cantidad_horarios']);
        $this->assertSame(2, $docente['cantidad_asignaturas']);
        $this->assertSame([1, 2], $docente['asignaturas_carga']->pluck('id')->sort()->values()->all());
        $this->assertSame([100, 103, 105], $docente['horarios_ocupados']->pluck('horario_id')->sort()->values()->all());
        $porAsignatura = $docente['carga_por_asignatura']->keyBy('asignatura.id');
        $this->assertSame(2, $porAsignatura[1]['cantidad_horarios']);
        $this->assertSame(1, $porAsignatura[2]['cantidad_horarios']);

        $codocente = $results[3];
        $this->assertSame([103, 104], $codocente['horarios']->pluck('horario.id')->sort()->values()->all());
        $this->assertSame([103, 104], $codocente['horarios_carga']->pluck('id')->sort()->values()->all());
        $this->assertSame([2], $codocente['sedes']->pluck('id')->all());
        $this->assertSame(2, $codocente['cantidad_horarios']);
        $this->assertSame(1, $codocente['cantidad_asignaturas']);
        $this->assertSame($antes, $this->snapshot(), 'Consultar el intercambio no debe alterar asignaciones ni históricos.');
    }

    public function test_la_carga_se_consulta_por_lotes_aunque_haya_varios_docentes_y_secciones(): void
    {
        $this->multiSectionFixture();
        DB::connection()->enableQueryLog();
        DB::connection()->flushQueryLog();
        $this->consult(1);
        $this->assertCount(2, $this->viewData['resultados']);
        $consultasIniciales = $this->cantidadConsultas();

        for ($id = 4; $id <= 9; $id++) {
            DB::table('profesores')->insert(['id' => $id, 'nombre' => 'Docente '.$id]);
            DB::table('secciones_profesores')->insert([
                ['seccion_id' => 2, 'profesor_id' => $id],
                ['seccion_id' => 3, 'profesor_id' => $id],
            ]);
            DB::table('horarios_profesores')->insert([
                ['horario_id' => 100, 'profesor_id' => $id],
                ['horario_id' => 103, 'profesor_id' => $id],
            ]);
        }
        DB::connection()->flushQueryLog();

        $this->consult(1);

        $consultas = $this->cantidadConsultas();
        $this->assertCount(8, $this->viewData['resultados']);
        $this->assertSame($consultasIniciales, $consultas, 'La cantidad de consultas no debe crecer al pasar de dos a ocho docentes: '.$consultasIniciales.' a '.$consultas.'.');
    }

    public function test_un_docente_vinculado_sin_modulos_sigue_apareciendo_con_carga_vacia(): void
    {
        $this->academicFixture();
        DB::table('profesores')->insert(['id' => 3, 'nombre' => 'Sin módulos']);
        DB::table('secciones_profesores')->insert([
            'seccion_id' => 2, 'profesor_id' => 3,
            'tipo_participacion' => 'Ayudante', 'es_principal' => false,
        ]);
        $antes = $this->snapshot();

        $this->consult(1);

        $results = $this->viewData['resultados']->keyBy('profesor.id');
        $this->assertSame([1, 3], $results->keys()->sort()->values()->all());
        $docente = $results[3];
        foreach (['horarios', 'horarios_carga', 'horarios_ocupados', 'sedes', 'sedes_carga', 'asignaturas_carga', 'carga_por_asignatura'] as $campo) {
            $this->assertCount(0, $docente[$campo], $campo.' debe permanecer vacío para quien no tiene módulos.');
        }
        $this->assertSame(0, $docente['cantidad_horarios']);
        $this->assertSame(0, $docente['cantidad_asignaturas']);
        $this->assertSame('Ayudante', $docente['tipo_participacion']);
        $this->assertFalse($docente['es_principal']);
        $this->assertSame($antes, $this->snapshot());
    }

    public function test_una_asignatura_inexistente_devuelve_resultados_vacios(): void
    {
        $this->period(3, 2026, 2, true);
        $this->version(22, 3, 'semestral');

        $this->consult(999);

        $this->assertNull($this->viewData['asignatura']);
        $this->assertCount(0, $this->viewData['resultados']);
    }

    public function test_una_instalacion_sin_datos_devuelve_la_vista_vacia(): void
    {
        $this->consult();

        $this->assertNull($this->viewData['versionActual']);
        $this->assertCount(0, $this->viewData['asignaturas']);
        $this->assertCount(0, $this->viewData['resultados']);
    }

    private function consult(?int $asignaturaId = null): void
    {
        $parameters = $asignaturaId ? ['asignatura_id' => $asignaturaId] : [];
        (new IntercambioDocenteController)->index(Request::create('/programacion/intercambio', 'GET', $parameters));
    }

    private function period(int $id, int $year, int $semester, bool $active): void
    {
        DB::table('periodos_academicos')->insert([
            'id' => $id, 'anio' => $year, 'numero_semestre' => $semester, 'estado' => $active,
        ]);
    }

    private function version(int $id, int $periodId, string $type, bool $current = true): void
    {
        DB::table('versiones_planificacion')->insert([
            'id' => $id, 'periodo_academico_id' => $periodId, 'tipo' => $type,
            'version' => '0.1', 'es_actual' => $current,
        ]);
    }

    private function academicFixture(): void
    {
        $this->period(1, 2026, 1, false);
        $this->period(3, 2026, 2, true);
        $this->version(20, 1, 'semestral');
        $this->version(22, 3, 'semestral');
        $this->version(33, 3, 'anterior');
        DB::table('asignaturas')->insert(['id' => 1, 'codigo' => 'MAT1', 'nombre' => 'Matemáticas', 'estado' => true]);
        // La sección histórica va primero para detectar resultados contaminados por otro periodo.
        DB::table('secciones')->insert([
            ['id' => 1, 'asignatura_id' => 1, 'periodo_academico_id' => 1],
            ['id' => 2, 'asignatura_id' => 1, 'periodo_academico_id' => 3],
        ]);
        DB::table('profesores')->insert([['id' => 1, 'nombre' => 'Actual'], ['id' => 2, 'nombre' => 'Histórico']]);
        DB::table('secciones_profesores')->insert([
            ['seccion_id' => 1, 'profesor_id' => 2],
            ['seccion_id' => 2, 'profesor_id' => 1],
        ]);
        DB::table('actividades_seccion')->insert([['id' => 1, 'seccion_id' => 1], ['id' => 2, 'seccion_id' => 2]]);
        DB::table('grupos_seccion')->insert([
            ['id' => 1, 'actividad_seccion_id' => 1], ['id' => 2, 'actividad_seccion_id' => 2],
        ]);
        DB::table('dias')->insert(['id' => 1, 'nombre' => 'Lunes']);
        DB::table('bloques_horarios')->insert(['id' => 1, 'numero' => 1, 'hora_inicio' => '08:00:00', 'hora_fin' => '08:50:00']);
        DB::table('horarios')->insert([
            ['id' => 100, 'actividad_seccion_id' => 2, 'grupo_seccion_id' => 2, 'version_planificacion_id' => 22, 'dia_id' => 1, 'bloque_horario_id' => 1],
            ['id' => 101, 'actividad_seccion_id' => 2, 'grupo_seccion_id' => 2, 'version_planificacion_id' => 33, 'dia_id' => 1, 'bloque_horario_id' => 1],
            ['id' => 102, 'actividad_seccion_id' => 1, 'grupo_seccion_id' => 1, 'version_planificacion_id' => 20, 'dia_id' => 1, 'bloque_horario_id' => 1],
        ]);
        DB::table('horarios_profesores')->insert([
            ['horario_id' => 100, 'profesor_id' => 1],
            ['horario_id' => 101, 'profesor_id' => 1],
            ['horario_id' => 102, 'profesor_id' => 2],
        ]);
    }

    private function multiSectionFixture(): void
    {
        $this->academicFixture();
        DB::table('asignaturas')->insert(['id' => 2, 'codigo' => 'INF1', 'nombre' => 'Informática', 'estado' => true]);
        DB::table('secciones')->insert([
            ['id' => 3, 'asignatura_id' => 1, 'periodo_academico_id' => 3],
            ['id' => 4, 'asignatura_id' => 2, 'periodo_academico_id' => 3],
        ]);
        DB::table('profesores')->insert(['id' => 3, 'nombre' => 'Codocente']);
        DB::table('secciones_profesores')->insert([
            ['seccion_id' => 3, 'profesor_id' => 1],
            ['seccion_id' => 3, 'profesor_id' => 3],
            ['seccion_id' => 4, 'profesor_id' => 1],
        ]);
        DB::table('actividades_seccion')->insert([['id' => 3, 'seccion_id' => 3], ['id' => 4, 'seccion_id' => 4]]);
        DB::table('grupos_seccion')->insert([
            ['id' => 3, 'actividad_seccion_id' => 3], ['id' => 4, 'actividad_seccion_id' => 4],
        ]);
        DB::table('bloques_horarios')->insert([
            ['id' => 2, 'numero' => 2, 'hora_inicio' => '09:00:00', 'hora_fin' => '09:50:00'],
            ['id' => 3, 'numero' => 3, 'hora_inicio' => '10:00:00', 'hora_fin' => '10:50:00'],
            ['id' => 4, 'numero' => 4, 'hora_inicio' => '11:00:00', 'hora_fin' => '11:50:00'],
        ]);
        DB::table('horarios')->insert([
            ['id' => 103, 'actividad_seccion_id' => 3, 'grupo_seccion_id' => 3, 'version_planificacion_id' => 22, 'dia_id' => 1, 'bloque_horario_id' => 2],
            ['id' => 104, 'actividad_seccion_id' => 3, 'grupo_seccion_id' => 3, 'version_planificacion_id' => 22, 'dia_id' => 1, 'bloque_horario_id' => 3],
            ['id' => 105, 'actividad_seccion_id' => 4, 'grupo_seccion_id' => 4, 'version_planificacion_id' => 22, 'dia_id' => 1, 'bloque_horario_id' => 4],
        ]);
        DB::table('horarios_profesores')->insert([
            ['horario_id' => 103, 'profesor_id' => 1],
            ['horario_id' => 103, 'profesor_id' => 1],
            ['horario_id' => 103, 'profesor_id' => 3],
            ['horario_id' => 104, 'profesor_id' => 3],
            ['horario_id' => 105, 'profesor_id' => 1],
            ['horario_id' => 102, 'profesor_id' => 1],
        ]);
        DB::table('sedes')->insert([
            ['id' => 1, 'nombre' => 'Central'], ['id' => 2, 'nombre' => 'Norte'], ['id' => 3, 'nombre' => 'Sur'],
        ]);
        DB::table('salas')->insert([
            ['id' => 1, 'sede_id' => 1], ['id' => 2, 'sede_id' => 2], ['id' => 3, 'sede_id' => 3],
        ]);
        DB::table('asignaciones_salas')->insert([
            ['horario_id' => 100, 'sala_id' => 1],
            ['horario_id' => 103, 'sala_id' => 2],
            ['horario_id' => 103, 'sala_id' => 2],
            ['horario_id' => 104, 'sala_id' => 2],
            ['horario_id' => 105, 'sala_id' => 3],
        ]);
    }

    private function snapshot(): array
    {
        return collect(['asignaturas', 'secciones', 'profesores', 'secciones_profesores',
            'actividades_seccion', 'grupos_seccion', 'horarios', 'horarios_profesores',
            'sedes', 'salas', 'asignaciones_salas'])
            ->mapWithKeys(fn ($tabla) => [$tabla => DB::table($tabla)->orderBy('id')->get()->map(fn ($fila) => (array) $fila)->all()])
            ->all();
    }

    private function cantidadConsultas(): int
    {
        return collect(DB::connection()->getQueryLog())->filter(
            fn ($consulta) => str_starts_with(strtolower(ltrim($consulta['query'])), 'select')
        )->count();
    }

    private function createSchema(): void
    {
        Schema::create('periodos_academicos', function (Blueprint $table) {
            $table->id();
            $table->integer('anio');
            $table->integer('numero_semestre');
            $table->boolean('estado');
        });
        Schema::create('versiones_planificacion', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('periodo_academico_id');
            $table->string('tipo');
            $table->string('version');
            $table->boolean('es_actual');
        });
        Schema::create('asignaturas', function (Blueprint $table) {
            $table->id();
            $table->string('codigo');
            $table->string('nombre');
            $table->boolean('estado');
        });
        Schema::create('secciones', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('asignatura_id');
            $table->unsignedBigInteger('periodo_academico_id');
        });
        Schema::create('profesores', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
        });
        Schema::create('secciones_profesores', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('seccion_id');
            $table->unsignedBigInteger('profesor_id');
            $table->string('tipo_participacion')->default('Titular');
            $table->boolean('es_principal')->default(true);
        });
        Schema::create('actividades_seccion', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('seccion_id');
        });
        Schema::create('grupos_seccion', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('actividad_seccion_id');
        });
        Schema::create('horarios', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('actividad_seccion_id');
            $table->unsignedBigInteger('grupo_seccion_id');
            $table->unsignedBigInteger('version_planificacion_id');
            $table->unsignedBigInteger('dia_id');
            $table->unsignedBigInteger('bloque_horario_id');
        });
        Schema::create('horarios_profesores', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('horario_id');
            $table->unsignedBigInteger('profesor_id');
        });
        Schema::create('dias', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
        });
        Schema::create('bloques_horarios', function (Blueprint $table) {
            $table->id();
            $table->integer('numero');
            $table->time('hora_inicio');
            $table->time('hora_fin');
        });
        Schema::create('asignaciones_salas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('horario_id');
            $table->unsignedBigInteger('sala_id');
        });
        Schema::create('salas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sede_id');
        });
        Schema::create('sedes', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
        });
    }
}

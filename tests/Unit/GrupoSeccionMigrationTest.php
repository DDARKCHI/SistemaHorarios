<?php

namespace Tests\Unit;

use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class GrupoSeccionMigrationTest extends TestCase
{
    private Capsule $database;

    private mixed $previousFacadeApplication;

    protected function setUp(): void
    {
        parent::setUp();

        // No cargar .env ni la aplicación: estas pruebas solo conocen SQLite en memoria.
        $this->previousFacadeApplication = Facade::getFacadeApplication();
        $container = new Container;
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

        Schema::create('secciones', function (Blueprint $table) {
            $table->id();
        });
        Schema::create('actividades_seccion', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seccion_id')->constrained('secciones');
        });
        $migration = require $this->migrationPath('2026_09_08_104106_crea_grupos_seccion.php');
        $migration->up();
        Schema::create('horarios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grupo_seccion_id')->constrained('grupos_seccion');
            $table->foreignId('actividad_seccion_id')->constrained('actividades_seccion');
        });
        Schema::create('grupos_profesores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grupo_seccion_id')->constrained('grupos_seccion');
            $table->unsignedBigInteger('profesor_id');
        });
        DB::table('secciones')->insert([['id' => 10], ['id' => 20]]);
    }

    protected function tearDown(): void
    {
        $this->database->getConnection()->disconnect();
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($this->previousFacadeApplication);

        parent::tearDown();
    }

    public function test_adapta_una_instalacion_vacia(): void
    {
        $this->migrate();

        $this->assertFalse(Schema::hasColumn('grupos_seccion', 'seccion_id'));
        $this->assertTrue(Schema::hasColumn('grupos_seccion', 'actividad_seccion_id'));
        $this->assertTrue(Schema::hasIndex('grupos_seccion', 'grupo_actividad_unique', 'unique'));
        $this->assertFalse(Schema::hasIndex('grupos_seccion', 'grupo_seccion_unique'));
        $this->assertSame(['actividad_seccion_id'], Schema::getForeignKeys('grupos_seccion')[0]['columns']);
        $column = collect(Schema::getColumns('grupos_seccion'))->firstWhere('name', 'actividad_seccion_id');
        $this->assertFalse($column['nullable']);
    }

    public function test_conserva_ids_datos_horarios_y_profesores_al_usar_la_actividad_del_horario(): void
    {
        $this->activities(10, 101, 102);
        $this->group();
        DB::table('horarios')->insert(['id' => 50, 'grupo_seccion_id' => 7, 'actividad_seccion_id' => 102]);
        DB::table('grupos_profesores')->insert(['id' => 60, 'grupo_seccion_id' => 7, 'profesor_id' => 300]);
        $before = (array) DB::table('grupos_seccion')->find(7);
        unset($before['seccion_id']);

        $this->migrate();

        $after = (array) DB::table('grupos_seccion')->find(7);
        $this->assertSame(102, $after['actividad_seccion_id']);
        unset($after['actividad_seccion_id']);
        $this->assertSame($before, $after);
        $this->assertSame(7, DB::table('horarios')->find(50)->grupo_seccion_id);
        $this->assertSame(7, DB::table('grupos_profesores')->find(60)->grupo_seccion_id);
        $this->assertSame([], DB::select('PRAGMA foreign_key_check'));
    }

    public function test_usa_la_unica_actividad_de_la_seccion_si_no_hay_horarios(): void
    {
        $this->activities(10, 101);
        $this->group();

        $this->migrate();

        $this->assertSame(101, DB::table('grupos_seccion')->find(7)->actividad_seccion_id);
    }

    public function test_no_adivina_entre_varias_actividades_sin_horarios(): void
    {
        $this->activities(10, 101, 102);
        $this->group();

        $this->assertRejectedWithoutChanges('única actividad');
    }

    public function test_rechaza_un_grupo_sin_actividad(): void
    {
        $this->group();

        $this->assertRejectedWithoutChanges('única actividad');
    }

    public function test_rechaza_horarios_de_distintas_actividades_para_el_mismo_grupo(): void
    {
        $this->activities(10, 101, 102);
        $this->group();
        DB::table('horarios')->insert([
            ['grupo_seccion_id' => 7, 'actividad_seccion_id' => 101],
            ['grupo_seccion_id' => 7, 'actividad_seccion_id' => 102],
        ]);

        $this->assertRejectedWithoutChanges('única actividad');
    }

    public function test_rechaza_una_actividad_de_otra_seccion(): void
    {
        $this->activities(20, 201);
        $this->group();
        DB::table('horarios')->insert(['grupo_seccion_id' => 7, 'actividad_seccion_id' => 201]);

        $this->assertRejectedWithoutChanges('no pertenece a su sección');
    }

    public function test_no_modifica_una_base_que_ya_tiene_la_estructura_actual(): void
    {
        $this->migrate();
        $this->activities(10, 101);
        DB::table('grupos_seccion')->insert(['id' => 7, 'actividad_seccion_id' => 101, 'codigo' => 'G:1']);
        $before = (array) DB::table('grupos_seccion')->find(7);
        DB::enableQueryLog();

        $this->migrate();

        $writes = collect(DB::getQueryLog())->filter(fn (array $query) => preg_match('/^\s*(insert|update|delete|alter|create|drop)\b/i', $query['query']));
        $this->assertCount(0, $writes);
        $this->assertSame($before, (array) DB::table('grupos_seccion')->find(7));
    }

    public function test_permite_el_mismo_codigo_en_actividades_distintas(): void
    {
        $this->migrate();
        $this->activities(10, 101, 102);
        DB::table('grupos_seccion')->insert([
            ['actividad_seccion_id' => 101, 'codigo' => 'G:1'],
            ['actividad_seccion_id' => 102, 'codigo' => 'G:1'],
        ]);

        $this->assertSame(2, DB::table('grupos_seccion')->count());
        $this->expectException(QueryException::class);
        DB::table('grupos_seccion')->insert(['actividad_seccion_id' => 101, 'codigo' => 'G:1']);
    }

    public function test_rechaza_una_actividad_inexistente(): void
    {
        $this->migrate();

        $this->expectException(QueryException::class);
        DB::table('grupos_seccion')->insert(['actividad_seccion_id' => 999, 'codigo' => 'G:1']);
    }

    public function test_puede_retomar_una_conversion_parcial_compatible(): void
    {
        $this->activities(10, 101);
        $this->group();
        Schema::table('grupos_seccion', function (Blueprint $table) {
            $table->unsignedBigInteger('actividad_seccion_id')->nullable();
        });
        DB::table('grupos_seccion')->where('id', 7)->update(['actividad_seccion_id' => 101]);

        $this->migrate();

        $this->assertFalse(Schema::hasColumn('grupos_seccion', 'seccion_id'));
        $this->assertSame(101, DB::table('grupos_seccion')->find(7)->actividad_seccion_id);
    }

    public function test_no_sobrescribe_una_conversion_parcial_incompatible(): void
    {
        $this->activities(10, 101, 102);
        $this->group();
        DB::table('horarios')->insert(['grupo_seccion_id' => 7, 'actividad_seccion_id' => 101]);
        Schema::table('grupos_seccion', function (Blueprint $table) {
            $table->unsignedBigInteger('actividad_seccion_id')->nullable();
        });
        DB::table('grupos_seccion')->where('id', 7)->update(['actividad_seccion_id' => 102]);
        $before = (array) DB::table('grupos_seccion')->find(7);

        try {
            $this->migrate();
            $this->fail('La conversión incompatible debe detenerse.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('parcial incompatible', $exception->getMessage());
        }

        $this->assertSame($before, (array) DB::table('grupos_seccion')->find(7));
        $this->assertTrue(Schema::hasColumn('grupos_seccion', 'seccion_id'));
    }

    public function test_un_rollback_no_deshace_correcciones_preexistentes(): void
    {
        $this->migrate();

        try {
            $migration = require $this->migrationPath('2026_10_20_120000_relaciona_grupos_con_actividades.php');
            $migration->down();
            $this->fail('La reversión requiere revisar un respaldo.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('no admite rollback automático', $exception->getMessage());
        }

        $this->assertTrue(Schema::hasColumn('grupos_seccion', 'actividad_seccion_id'));
    }

    private function migrate(): void
    {
        $migration = require $this->migrationPath('2026_10_20_120000_relaciona_grupos_con_actividades.php');
        $migration->up();
    }

    private function migrationPath(string $filename): string
    {
        return dirname(__DIR__, 2).'/database/migrations/'.$filename;
    }

    private function activities(int $seccionId, int ...$ids): void
    {
        foreach ($ids as $id) {
            DB::table('actividades_seccion')->insert(['id' => $id, 'seccion_id' => $seccionId]);
        }
    }

    private function group(): void
    {
        DB::table('grupos_seccion')->insert([
            'id' => 7,
            'seccion_id' => 10,
            'codigo' => 'G:1',
            'nombre' => 'Grupo de prueba',
            'numero' => 1,
            'cupos' => 40,
            'inscritos' => 27,
            'created_at' => '2026-09-01 10:00:00',
            'updated_at' => '2026-09-01 10:00:00',
        ]);
    }

    private function assertRejectedWithoutChanges(string $message): void
    {
        $before = (array) DB::table('grupos_seccion')->find(7);

        try {
            $this->migrate();
            $this->fail('La conversión ambigua debe detenerse.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString($message, $exception->getMessage());
        }

        $this->assertFalse(Schema::hasColumn('grupos_seccion', 'actividad_seccion_id'));
        $this->assertSame($before, (array) DB::table('grupos_seccion')->find(7));
    }
}

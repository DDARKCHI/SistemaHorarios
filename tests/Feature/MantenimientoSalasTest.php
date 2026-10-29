<?php

namespace Tests\Feature;

use App\Models\ActividadSeccion;
use App\Models\AsignacionSala;
use App\Models\Asignatura;
use App\Models\BloqueHorario;
use App\Models\Dia;
use App\Models\GrupoSeccion;
use App\Models\Horario;
use App\Models\Laboratorio;
use App\Models\PeriodoAcademico;
use App\Models\Sala;
use App\Models\Seccion;
use App\Models\Sede;
use App\Models\TipoSala;
use App\Models\User;
use App\Models\VersionPlanificacion;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Permission;
use Tests\IsolatedDatabaseTestCase;

class MantenimientoSalasTest extends IsolatedDatabaseTestCase
{
    private User $usuario;

    private Sede $sede;

    private TipoSala $tipo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sede = Sede::create(['universidad_id' => DB::table('universidades')->value('id'), 'nombre' => 'Sede central', 'estado' => true]);
        $this->tipo = TipoSala::create(['nombre' => 'Aula', 'estado' => true]);
        $this->usuario = User::create(['name' => 'Gestión de salas', 'rut' => '77777771', 'email' => 'gestion-salas@example.test', 'password' => 'clave-de-prueba']);
        $this->usuario->givePermissionTo(Permission::findOrCreate('gestionar salas y recursos', 'web'));
        $this->actingAs($this->usuario);
    }

    public function test_los_formularios_muestran_datos_y_navegacion_del_catalogo(): void
    {
        $this->get(route('salas.create'))->assertOk()->assertViewIs('salas.formulario')
            ->assertSee('Crear sala')->assertSee('Sede central')->assertSee('Aula')
            ->assertSee(route('salas.store'), false)->assertSee('Salas y recursos');
        $sala = $this->sala(['nombre' => 'Sala del segundo piso', 'capacidad' => 42]);
        $this->get(route('salas.edit', $sala))->assertOk()->assertSee('Editar sala')
            ->assertSee('Sala del segundo piso')->assertSee('value="42"', false)
            ->assertSee(route('salas.update', $sala), false)->assertSee('Guardar cambios');
        $this->get(route('salas.index'))->assertOk()->assertSee(route('salas.create'), false)
            ->assertSee(route('salas.edit', $sala), false);
        $this->assertDatabaseCount('historial_acciones', 0);
    }

    public function test_crea_la_sala_con_institucion_de_la_sede_y_registra_al_autor(): void
    {
        $respuesta = $this->post(route('salas.store'), $this->datos([
            'codigo' => ' NUEVA-01 ', 'nombre' => ' Sala nueva ', 'universidad_id' => 999999, 'id' => 888888,
        ]))->assertRedirect()->assertSessionHasNoErrors()->assertSessionHas('success', 'Sala creada correctamente.');

        $sala = Sala::sole();
        $this->assertSame('NUEVA-01', $sala->codigo);
        $this->assertSame('Sala nueva', $sala->nombre);
        $this->assertSame($this->sede->universidad_id, $sala->universidad_id);
        $this->assertNotSame(888888, $sala->id);
        $this->assertTrue($sala->estado);
        $this->assertTrue($sala->disponible);
        $log = DB::table('historial_acciones')->sole();
        $this->assertSame($this->usuario->id, $log->usuario_id);
        $this->assertSame('Crear sala', $log->accion);
        $this->assertSame($sala->id, $log->registro_id);
        $this->assertNull(json_decode($log->datos, true)['antes']);
        $this->get($respuesta->headers->get('Location'))->assertOk()->assertSee('NUEVA-01')->assertSee('Sala creada correctamente.');
    }

    public function test_impide_codigos_duplicados_en_una_sede_y_permite_el_mismo_en_otra(): void
    {
        $sala = $this->sala();
        $this->from(route('salas.create'))->post(route('salas.store'), $this->datos())
            ->assertRedirect(route('salas.create'))->assertSessionHasErrors('codigo')->assertSessionHasInput('codigo', $sala->codigo);
        $otra = Sede::create(['universidad_id' => $this->sede->universidad_id, 'nombre' => 'Sede norte']);
        $this->post(route('salas.store'), $this->datos(['sede_id' => $otra->id]))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('salas', 2);
        $this->put(route('salas.update', $sala), $this->datos(['sede_id' => $otra->id]))->assertSessionHasErrors('codigo');
        $this->assertSame($this->sede->id, $sala->fresh()->sede_id);
        $this->assertDatabaseCount('historial_acciones', 1);
    }

    #[DataProvider('datosInvalidos')]
    public function test_rechaza_datos_invalidos_sin_guardar_parcialmente(string $campo, mixed $valor): void
    {
        $sala = $this->sala();
        $this->from(route('salas.edit', $sala))->put(route('salas.update', $sala), $this->datos([$campo => $valor]))
            ->assertRedirect(route('salas.edit', $sala))->assertSessionHasErrors($campo);
        $this->get(route('salas.edit', $sala))->assertOk()->assertSee('Revisa los datos de la sala.');
        $this->assertSame(30, $sala->fresh()->capacidad);
        $this->assertDatabaseCount('salas', 1);
        $this->assertDatabaseCount('historial_acciones', 0);
    }

    public static function datosInvalidos(): array
    {
        return [
            ['codigo', '   '], ['codigo', ['compuesto']], ['codigo', str_repeat('A', 31)],
            ['nombre', str_repeat('A', 101)], ['capacidad', -1], ['capacidad', 65536],
            ['capacidad', 2.5], ['capacidad', null], ['capacidad', ['30']],
            ['sede_id', 999999], ['sede_id', [1]], ['tipo_sala_id', 999999],
            ['edificio', str_repeat('A', 101)], ['piso', str_repeat('A', 31)],
            ['descripcion', str_repeat('A', 5001)], ['estado', 'otro'], ['disponible', 'otro'],
        ];
    }

    public function test_no_asigna_nuevas_salas_a_catalogos_inactivos_pero_permite_conservar_los_actuales(): void
    {
        $sala = $this->sala();
        $this->sede->update(['estado' => false]);
        $this->tipo->update(['estado' => false]);
        $this->get(route('salas.create'))->assertOk()->assertViewHas('sedes', fn ($filas) => $filas->isEmpty())
            ->assertViewHas('tiposSala', fn ($filas) => $filas->isEmpty());
        $this->post(route('salas.store'), $this->datos(['codigo' => 'OTRA']))->assertSessionHasErrors(['sede_id', 'tipo_sala_id']);
        $this->get(route('salas.edit', $sala))->assertOk()->assertSee('Sede central (inactiva)')->assertSee('Aula (inactivo)');
        $this->put(route('salas.update', $sala), $this->datos(['capacidad' => 50]))->assertSessionHasNoErrors();
        $this->assertSame(50, $sala->fresh()->capacidad);
    }

    public function test_actualizar_capacidad_y_desactivar_conserva_horarios_y_laboratorios(): void
    {
        $sala = $this->sala();
        $laboratorio = Laboratorio::create(['sala_id' => $sala->id, 'nombre' => 'Laboratorio existente']);
        $periodo = PeriodoAcademico::create(['universidad_id' => $sala->universidad_id, 'nombre' => '2026-II', 'anio' => 2026, 'numero_semestre' => 2]);
        $version = VersionPlanificacion::create(['periodo_academico_id' => $periodo->id, 'nombre' => 'Borrador', 'tipo' => 'semestral']);
        $asignatura = Asignatura::create(['universidad_id' => $sala->universidad_id, 'codigo' => 'MAT1', 'nombre' => 'Matemáticas']);
        $seccion = Seccion::create(['periodo_academico_id' => $periodo->id, 'asignatura_id' => $asignatura->id, 'codigo' => '1']);
        $actividad = ActividadSeccion::create(['seccion_id' => $seccion->id, 'tipo' => 'Cátedra']);
        $grupo = GrupoSeccion::create(['actividad_seccion_id' => $actividad->id, 'codigo' => 'G:1']);
        $horario = Horario::create(['version_planificacion_id' => $version->id, 'actividad_seccion_id' => $actividad->id,
            'grupo_seccion_id' => $grupo->id, 'dia_id' => Dia::firstOrFail()->id, 'bloque_horario_id' => BloqueHorario::firstOrFail()->id]);
        $asignacion = AsignacionSala::create(['horario_id' => $horario->id, 'sala_id' => $sala->id, 'capacidad_asignada' => 25]);
        $antes = [$horario->fresh()->getAttributes(), $asignacion->fresh()->getAttributes(), $laboratorio->fresh()->getAttributes()];

        $otraSede = Sede::create(['universidad_id' => $sala->universidad_id, 'nombre' => 'Otra sede']);
        $this->put(route('salas.update', $sala), $this->datos(['sede_id' => $otraSede->id]))->assertSessionHasErrors('sede_id');
        $this->assertSame($this->sede->id, $sala->fresh()->sede_id);
        $this->assertDatabaseCount('historial_acciones', 0);

        $respuesta = $this->put(route('salas.update', $sala), $this->datos(['capacidad' => 0, 'estado' => 0, 'disponible' => 0]))
            ->assertSessionHasNoErrors()->assertSessionHas('success', 'Sala actualizada correctamente.');
        $this->assertSame(0, $sala->fresh()->capacidad);
        $this->assertFalse($sala->fresh()->estado);
        $this->assertFalse($sala->fresh()->disponible);
        $this->assertSame($antes, [$horario->fresh()->getAttributes(), $asignacion->fresh()->getAttributes(), $laboratorio->fresh()->getAttributes()]);
        $log = json_decode(DB::table('historial_acciones')->sole()->datos, true);
        $this->assertSame(30, $log['antes']['capacidad']);
        $this->assertSame(0, $log['despues']['capacidad']);
        $this->get($respuesta->headers->get('Location'))->assertOk()->assertSee('Registro inactivo')->assertSee('Sin capacidad informada');
    }

    public function test_guardar_sin_modificaciones_no_duplica_el_historial(): void
    {
        $sala = $this->sala();
        $this->put(route('salas.update', $sala), $this->datos())->assertSessionHasNoErrors();
        $this->assertDatabaseCount('historial_acciones', 0);
    }

    public function test_si_falla_el_historial_se_revierte_la_creacion(): void
    {
        DB::statement("CREATE TRIGGER impedir_historial BEFORE INSERT ON historial_acciones BEGIN SELECT RAISE(ABORT, 'auditoria no disponible'); END");
        $this->withoutExceptionHandling();
        try {
            $this->post(route('salas.store'), $this->datos());
            $this->fail('La auditoría debía fallar.');
        } catch (QueryException $error) {
            $this->assertStringContainsString('auditoria no disponible', $error->getMessage());
        }
        $this->assertDatabaseCount('salas', 0);
        $this->assertDatabaseCount('historial_acciones', 0);
    }

    public function test_las_operaciones_exigen_sesion_y_permiso_y_la_sala_debe_existir(): void
    {
        $sala = $this->sala();
        Auth::logout();
        $this->get(route('salas.create'))->assertRedirect(route('login'));
        $this->get(route('salas.edit', $sala))->assertRedirect(route('login'));
        $this->post(route('salas.store'), $this->datos())->assertRedirect(route('login'));
        $this->put(route('salas.update', $sala), $this->datos(['capacidad' => 60]))->assertRedirect(route('login'));
        $this->usuario->revokePermissionTo('gestionar salas y recursos');
        $this->usuario->givePermissionTo(Permission::findOrCreate('ver panel inicio', 'web'));
        $this->actingAs($this->usuario);
        $this->get(route('salas.create'))->assertRedirect(route('inicio'));
        $this->get(route('salas.edit', $sala))->assertRedirect(route('inicio'));
        $this->post(route('salas.store'), $this->datos(['codigo' => 'NO']))->assertRedirect(route('inicio'));
        $this->put(route('salas.update', $sala), $this->datos(['capacidad' => 60]))->assertRedirect(route('inicio'));
        $this->assertSame(30, $sala->fresh()->capacidad);
        $this->assertDatabaseCount('salas', 1);
        $this->assertDatabaseCount('historial_acciones', 0);
        $this->usuario->givePermissionTo('gestionar salas y recursos');
        $this->get(route('salas.edit', 999999))->assertNotFound();
        $this->put(route('salas.update', 999999), $this->datos())->assertNotFound();
    }

    private function datos(array $cambios = []): array
    {
        return array_replace(['sede_id' => $this->sede->id, 'tipo_sala_id' => $this->tipo->id,
            'codigo' => 'A-101', 'nombre' => 'Sala de prueba', 'capacidad' => 30,
            'edificio' => null, 'piso' => null, 'descripcion' => null, 'estado' => 1, 'disponible' => 1], $cambios);
    }

    private function sala(array $cambios = []): Sala
    {
        return Sala::create($this->datos($cambios) + ['universidad_id' => $this->sede->universidad_id]);
    }
}

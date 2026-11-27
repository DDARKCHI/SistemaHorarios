<?php

namespace Tests\Feature;

use App\Http\Controllers\DocenteController;
use App\Models;
use App\Services\AsignacionModulosHorario;
use App\Services\GestionOfertaPeriodo;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\IsolatedDatabaseTestCase;

class SincronizacionPanelCarreraTest extends IsolatedDatabaseTestCase
{
    private int $universidad;
    private Models\PeriodoAcademico $periodo;
    private Models\VersionPlanificacion $version;
    private Models\Carrera $carrera;
    private Models\Seccion $seccion;
    private Models\GrupoSeccion $grupo;
    private Models\Profesor $docente;
    private int $lunes;
    private int $martes;
    private int $primero;
    private int $segundo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->universidad = DB::table('universidades')->value('id');
        $this->periodo = Models\PeriodoAcademico::create([
            'universidad_id' => $this->universidad, 'nombre' => '2026-2', 'anio' => 2026, 'numero_semestre' => 2, 'estado' => true,
        ]);
        $this->version = $this->periodo->versionesPlanificacion()->create([
            'nombre' => 'Semestral', 'tipo' => 'semestral', 'version' => '0.2', 'estado' => 'Borrador', 'es_actual' => true,
        ]);
        $sede = Models\Sede::create(['universidad_id' => $this->universidad, 'nombre' => 'Talcahuano', 'estado' => true]);
        $this->carrera = Models\Carrera::create([
            'universidad_id' => $this->universidad, 'sede_id' => $sede->id, 'nombre' => 'Ingeniería en Informática', 'codigo' => 'INF', 'estado' => true,
        ]);
        $ramo = Models\Asignatura::create([
            'universidad_id' => $this->universidad, 'codigo' => 'BACK', 'nombre' => 'Desarrollo de aplicaciones', 'horas_catedra' => 2, 'estado' => true,
        ]);
        $this->seccion = Models\Seccion::create([
            'periodo_academico_id' => $this->periodo->id, 'asignatura_id' => $ramo->id, 'codigo' => '11',
            'semestre' => 2, 'cupos' => 30, 'inscritos' => 20, 'estado_activo' => true,
        ]);
        $this->seccion->carreras()->attach($this->carrera);
        $actividad = $this->seccion->actividades()->create(['tipo' => 'Cátedra', 'cantidad_horas' => 2, 'estado' => true]);
        $this->grupo = $actividad->grupos()->create(['codigo' => 'G:1', 'numero' => 1, 'inscritos' => 20, 'estado_activo' => true]);
        $this->docente = $this->crearDocente('Ana', 'Cabrera');
        $this->grupo->profesores()->create(['profesor_id' => $this->docente->id, 'es_principal' => true, 'estado' => true]);
        $this->lunes = Models\Dia::where('numero', 1)->value('id');
        $this->martes = Models\Dia::where('numero', 2)->value('id');
        $this->primero = Models\BloqueHorario::where('numero', 1)->value('id');
        $this->segundo = Models\BloqueHorario::where('numero', 2)->value('id');
    }

    public function test_jefatura_crea_asigna_espacio_mueve_y_quita_el_bloque_que_consulta_administracion(): void
    {
        $jefatura = $this->crearUsuario('Jefe de carrera', '17111111');
        $administracion = $this->crearUsuario('Administrador', '17222222');
        $this->actingAs($jefatura)->post(route('programacion.panel-carrera.agregar', $this->grupo), $this->filtros() + [
            'dia_id' => $this->lunes, 'bloque_id' => $this->primero,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $horario = $this->version->horarios()->whereBelongsTo($this->grupo)->sole();
        $tipo = Models\TipoSala::create(['nombre' => 'Aula', 'estado' => true]);
        $sala = Models\Sala::create([
            'universidad_id' => $this->universidad, 'sede_id' => $this->carrera->sede_id, 'tipo_sala_id' => $tipo->id,
            'codigo' => 'AULA-SYNC', 'capacidad' => 30, 'estado' => true, 'disponible' => true,
        ]);
        $this->post(route('programacion.panel-carrera.espacio', $horario), $this->filtros() + [
            'espacio' => 'sala:'.$sala->id,
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame($sala->id, $this->horarioPanel($horario)['espacios'][0]['id']);

        $movimiento = [
            'carrera_id' => $this->carrera->id, 'dia_origen' => $this->lunes, 'bloque_origen' => $this->primero,
            'dia_id' => $this->martes, 'bloque_id' => $this->segundo,
        ];
        $this->patchJson(route('programacion.panel-carrera.mover', $horario), $movimiento)->assertOk();

        $this->actingAs($administracion);
        $reflejado = $this->horariosSemanales()->sole();
        $this->assertTrue($horario->is($reflejado));
        $this->assertSame($this->martes, $reflejado->dia_id);
        $this->assertSame($this->segundo, $reflejado->bloque_horario_id);
        $this->assertTrue($sala->is($reflejado->asignacionesSalas->sole()->sala));

        $this->actingAs($jefatura)->patchJson(route('programacion.panel-carrera.mover', $horario), [
            'carrera_id' => $this->carrera->id, 'dia_origen' => $this->martes, 'bloque_origen' => $this->segundo,
            'dia_id' => $this->lunes, 'bloque_id' => $this->primero,
        ])->assertOk();

        $this->actingAs($administracion);
        $reflejado = $this->horariosSemanales()->sole();
        $this->assertSame($this->lunes, $reflejado->dia_id);
        $this->assertSame($this->primero, $reflejado->bloque_horario_id);
        $this->get(route('programacion.asignacion.horarios', $this->filtros() + ['seccion_id' => $this->seccion->id]))
            ->assertOk()->assertSee($sala->codigo);

        $this->actingAs($jefatura)->delete(route('programacion.panel-carrera.quitar', [$horario] + $this->filtros()))
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($administracion);
        $this->assertCount(0, $this->horariosSemanales());
    }

    public function test_asignacion_manual_y_cambio_de_docente_desde_administracion_se_reflejan_en_el_panel(): void
    {
        $administracion = $this->crearUsuario('Administrador', '17222222');
        $jefatura = $this->crearUsuario('Jefe de carrera', '17111111');
        $this->actingAs($administracion)->post(route('programacion.asignacion.horarios.guardar'), $this->filtros() + [
            'grupo_seccion_id' => $this->grupo->id, 'dia_id' => $this->martes, 'bloques_horarios' => [$this->segundo],
            'profesores_ids' => [$this->docente->id],
        ])->assertRedirect()->assertSessionHasNoErrors();
        $horario = $this->version->horarios()->sole();

        $this->actingAs($jefatura);
        $bloque = $this->horarioPanel($horario);
        $this->assertSame($this->martes, $bloque['dia_id']);
        $this->assertSame($this->segundo, $bloque['bloque_id']);
        $this->assertSame([$this->docente->id], array_column($bloque['docentes'], 'id'));

        $reemplazo = $this->crearDocente('Julia', 'Bravo');
        $this->actingAs($administracion)->put(route('programacion.asignacion.horarios.profesor', $horario), [
            'version_id' => $this->version->id, 'profesor_id' => $reemplazo->id, 'accion_docente' => 'reemplazar',
            'huella_profesores' => AsignacionModulosHorario::firmaDocentes($horario->profesores()->get()),
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->actingAs($jefatura);
        $bloque = $this->horarioPanel($horario);
        $this->assertSame([$reemplazo->id], array_column($bloque['docentes'], 'id'));
        $this->assertSame(['Julia Bravo'], array_column($bloque['docentes'], 'nombre'));

        $this->actingAs($administracion)->delete(route('programacion.asignacion.horarios.eliminar', $horario))
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($jefatura)->get(route('programacion.panel-carrera', $this->filtros()))
            ->assertOk()->assertViewHas('panel', fn (array $panel) => $panel['horarios'] === []
                && $panel['secciones'][0]['grupos'][0]['pendientes'] === 2);
    }

    public function test_matricula_editada_en_la_oferta_se_refleja_en_el_panel(): void
    {
        $administracion = $this->crearUsuario('Administrador', '17222222');
        $this->actingAs($administracion)->put(route('administracion.secciones.update', $this->seccion), [
            'revision' => app(GestionOfertaPeriodo::class)->firma($this->seccion->fresh()),
            'periodo_academico_id' => $this->periodo->id, 'asignatura_id' => $this->seccion->asignatura_id,
            'codigo' => $this->seccion->codigo, 'semestre' => 2, 'estado_activo' => 1, 'carreras' => [$this->carrera->id],
            'cupos' => 35, 'inscritos' => 24,
        ])->assertRedirectToRoute('administracion.secciones.show', $this->seccion)->assertSessionHasNoErrors();

        $this->get(route('programacion.panel-carrera', $this->filtros() + ['pestana' => 'ramos']))->assertOk()
            ->assertViewHas('panel', fn (array $panel) => $panel['secciones'][0]['id'] === $this->seccion->id
                && $panel['secciones'][0]['inscritos'] === 24 && (int) $panel['secciones'][0]['cupos'] === 35);
    }

    public function test_cambiar_responsable_desde_administracion_invalida_confirmacion_del_panel(): void
    {
        $jefatura = $this->crearUsuario('Jefe de carrera', '17111111');
        $administracion = $this->crearUsuario('Administrador', '17222222');
        $otro = $this->crearDocente('Julia', 'Bravo');
        $clave = ['version_planificacion_id' => $this->version->id, 'grupo_seccion_id' => $this->grupo->id];
        $this->actingAs($jefatura)->post(route('programacion.panel-carrera.confirmar', $this->grupo), $this->filtros() + [
            'profesor_id' => $this->docente->id, 'confirmado' => true,
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('confirmaciones_grupo_programacion', $clave + ['profesor_id' => $this->docente->id]);

        $datos = $this->filtros() + ['grupo_seccion_id' => $this->grupo->id, 'tipo_participacion' => 'PROFESOR RESPONSABLE', 'es_principal' => true];
        $this->actingAs($administracion)->post(route('programacion.asignacion.profesores.guardar'), $datos + ['profesor_id' => $this->docente->id])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('confirmaciones_grupo_programacion', $clave + ['profesor_id' => $this->docente->id]);

        foreach ([$otro, $this->docente] as $responsable) {
            $this->post(route('programacion.asignacion.profesores.guardar'), $datos + ['profesor_id' => $responsable->id])
                ->assertRedirect()->assertSessionHasNoErrors();
            $this->assertDatabaseMissing('confirmaciones_grupo_programacion', $clave);
        }
    }

    public function test_nombre_editado_en_catalogo_se_ve_en_ambas_vistas_del_borrador(): void
    {
        $horario = $this->crearHorario($this->version);
        $horario->profesores()->create(['profesor_id' => $this->docente->id, 'estado' => true]);
        Models\ProfesorVersion::create([
            'version_planificacion_id' => $this->version->id, 'profesor_id' => $this->docente->id,
            'nombre' => 'Ana', 'apellido' => 'Cabrera',
        ]);
        $this->actingAs($this->crearUsuario('Administrador', '17222222'))
            ->put(route('administracion.docentes.update', $this->docente), [
                'universidad_id' => $this->universidad, 'revision' => DocenteController::firma($this->docente->fresh()),
                'nombre' => 'Ana María', 'apellido' => 'Cabrera', 'estado' => true, 'correo_institucional' => 'ana@example.test',
            ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame('Ana María Cabrera', $this->horarioPanel($horario)['docentes'][0]['nombre']);
        $this->assertSame('Ana María', $this->horariosSemanales()->sole()->docentesEfectivos->first()->profesor->nombre);
        $this->assertDatabaseHas('profesores_versiones', [
            'version_planificacion_id' => $this->version->id, 'profesor_id' => $this->docente->id, 'nombre' => 'Ana',
        ]);
    }

    public function test_borrador_vigente_y_version_publicada_se_consultan_por_separado(): void
    {
        $publicada = $this->periodo->versionesPlanificacion()->create([
            'nombre' => 'Publicada', 'tipo' => 'semestral', 'version' => '0.1', 'estado' => 'Publicado', 'es_actual' => false,
        ]);
        $anterior = $this->crearHorario($publicada);
        $actual = $this->crearHorario($this->version);
        $movimiento = [
            'carrera_id' => $this->carrera->id, 'dia_origen' => $this->lunes, 'bloque_origen' => $this->primero,
            'dia_id' => $this->martes, 'bloque_id' => $this->segundo,
        ];
        $this->actingAs($this->crearUsuario('Administrador', '17222222'))
            ->patchJson(route('programacion.panel-carrera.mover', $actual), $movimiento)->assertOk();
        $this->patchJson(route('programacion.panel-carrera.mover', $anterior), $movimiento)
            ->assertUnprocessable()->assertJsonValidationErrors('movimiento');

        $this->assertSame($this->martes, $this->horariosSemanales()->sole()->dia_id);
        $this->assertSame($this->lunes, $this->horariosSemanales($publicada)->sole()->dia_id);
        $this->get(route('programacion.panel-carrera', $this->filtros($publicada)))->assertOk()
            ->assertViewHas('editable', false)
            ->assertViewHas('panel', fn (array $panel) => array_column($panel['horarios'], 'id') === [$anterior->id]
                && $panel['horarios'][0]['dia_id'] === $this->lunes);
    }

    private function crearUsuario(string $rol, string $rut): Models\User
    {
        $usuario = Models\User::create(['name' => $rol, 'rut' => $rut, 'email' => $rut.'@example.test', 'password' => 'prueba']);
        $usuario->assignRole(Role::findOrCreate($rol, 'web'));
        foreach (['ver programacion semestral', 'asignar horarios', 'asignar profesores', 'administrar usuarios'] as $permiso) {
            $usuario->givePermissionTo(Permission::findOrCreate($permiso, 'web'));
        }

        return $usuario;
    }

    private function crearDocente(string $nombre, string $apellido): Models\Profesor
    {
        return Models\Profesor::create([
            'nombre' => $nombre, 'apellido' => $apellido, 'universidad_id' => $this->universidad, 'estado' => true,
        ]);
    }

    private function crearHorario(Models\VersionPlanificacion $version): Models\Horario
    {
        return $this->grupo->horarios()->create([
            'actividad_seccion_id' => $this->grupo->actividad_seccion_id, 'version_planificacion_id' => $version->id,
            'dia_id' => $this->lunes, 'bloque_horario_id' => $this->primero, 'estado' => true,
        ]);
    }

    private function filtros(?Models\VersionPlanificacion $version = null): array
    {
        return ['periodo_id' => $this->periodo->id, 'version_id' => ($version ?? $this->version)->id, 'carrera_id' => $this->carrera->id];
    }

    private function horariosSemanales(?Models\VersionPlanificacion $version = null)
    {
        return $this->get(route('programacion.semestral', $this->filtros($version)))->assertOk()->viewData('horarios');
    }

    private function horarioPanel(Models\Horario $horario): array
    {
        $panel = $this->get(route('programacion.panel-carrera', $this->filtros()))->assertOk()->viewData('panel');

        return collect($panel['horarios'])->sole('id', $horario->id);
    }
}

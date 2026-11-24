<?php

namespace Tests\Feature;

use App\Models;
use App\Services\PreparacionGeneracion;
use Illuminate\Support\Facades\{DB, URL};
use Spatie\Permission\Models\Permission;
use Tests\IsolatedDatabaseTestCase;

class PreparacionGeneracionTest extends IsolatedDatabaseTestCase
{
    private Models\User $usuario;
    private Models\PeriodoAcademico $periodo;
    private Models\VersionPlanificacion $version;
    private Models\Profesor $profesor;
    private Models\GrupoSeccion $grupo;
    private Models\Horario $horario;

    protected function setUp(): void
    {
        parent::setUp();
        $uni = DB::table('universidades')->value('id');
        $this->usuario = Models\User::create(['name' => 'Planificación', 'rut' => '17777777', 'email' => 'preparacion@example.test', 'password' => 'prueba']);
        foreach (['generar programacion semestral', 'asignar profesores', 'ver programacion semestral'] as $permiso) $this->usuario->givePermissionTo(Permission::findOrCreate($permiso, 'web'));
        $this->actingAs($this->usuario);
        $this->periodo = Models\PeriodoAcademico::create(['universidad_id' => $uni, 'nombre' => '2026-II', 'anio' => 2026, 'numero_semestre' => 2, 'estado' => true]);
        $this->version = Models\VersionPlanificacion::create(['periodo_academico_id' => $this->periodo->id, 'nombre' => 'Semestral', 'tipo' => 'semestral', 'version' => '1.0', 'estado' => 'Borrador', 'es_actual' => true]);
        $asignatura = Models\Asignatura::create(['universidad_id' => $uni, 'codigo' => 'BACK-1', 'nombre' => 'Backend', 'estado' => true]);
        $seccion = Models\Seccion::create(['asignatura_id' => $asignatura->id, 'periodo_academico_id' => $this->periodo->id, 'codigo' => '11', 'cupos' => 20, 'inscritos' => 20, 'estado_activo' => true]);
        $actividad = Models\ActividadSeccion::create(['seccion_id' => $seccion->id, 'tipo' => 'Cátedra', 'cantidad_horas' => 1, 'cantidad_estudiantes' => 20, 'estado' => true]);
        $this->grupo = Models\GrupoSeccion::create(['actividad_seccion_id' => $actividad->id, 'codigo' => 'G:1', 'numero' => 1, 'cupos' => 20, 'inscritos' => 20, 'estado_activo' => true]);
        $this->profesor = Models\Profesor::create(['universidad_id' => $uni, 'nombre' => 'Ana', 'apellido' => 'Pérez', 'rut' => '12345678', 'estado' => true]);
        Models\GrupoProfesor::create(['grupo_seccion_id' => $this->grupo->id, 'profesor_id' => $this->profesor->id, 'estado' => true, 'es_principal' => true]);
        $this->horario = Models\Horario::create(['actividad_seccion_id' => $actividad->id, 'grupo_seccion_id' => $this->grupo->id, 'version_planificacion_id' => $this->version->id,
            'dia_id' => DB::table('dias')->where('numero', 1)->value('id'), 'bloque_horario_id' => DB::table('bloques_horarios')->where('numero', 1)->value('id'), 'estado' => true]);
        $sede = Models\Sede::create(['universidad_id' => $uni, 'nombre' => 'Talcahuano', 'estado' => true]);
        $tipo = Models\TipoSala::create(['nombre' => 'Cátedra', 'estado' => true, 'requiere_equipamiento' => false]);
        $sala = Models\Sala::create(['universidad_id' => $uni, 'sede_id' => $sede->id, 'tipo_sala_id' => $tipo->id, 'codigo' => 'TH-01', 'nombre' => 'TH-01', 'capacidad' => 30, 'estado' => true, 'disponible' => true]);
        Models\AsignacionSala::create(['horario_id' => $this->horario->id, 'sala_id' => $sala->id, 'estado' => true, 'es_principal' => true]);
    }

    private function aprobar(array $datos = []): Models\SolicitudDisponibilidad
    {
        $this->post(route('disponibilidad.store'), ['profesor_id' => $this->profesor->id, 'periodo_academico_id' => $this->periodo->id, 'fecha_limite' => today()->addDays(7)->toDateString()])->assertSessionHasNoErrors();
        $solicitud = Models\SolicitudDisponibilidad::latest('id')->firstOrFail();
        $url = URL::temporarySignedRoute('disponibilidad.publica', $solicitud->fecha_limite->endOfDay(), ['solicitud' => $solicitud->id]);
        $this->post($url, $datos ?: ['bloques' => [$this->horario->dia_id.':'.$this->horario->bloque_horario_id]])->assertSessionHasNoErrors();
        $this->post(route('disponibilidad.revisar', $solicitud), ['decision' => 'aprobar'])->assertSessionHasNoErrors();
        return $solicitud;
    }

    private function reporte(): array
    {
        return app(PreparacionGeneracion::class)->revisar($this->periodo->fresh(), $this->version);
    }

    public function test_diagnostico_global_no_modifica_horarios_y_acepta_disponibilidad_aprobada(): void
    {
        $this->aprobar(); $antes = $this->horario->fresh()->getAttributes();
        $r = $this->reporte();
        $this->assertSame(0, $r['bloqueos'], $r['observaciones']->pluck('detalle')->implode(' '));
        $this->assertSame(1, $r['aprobados']); $this->assertSame(1, $r['modulos']);
        $this->get(route('programacion.preparacion'))->assertOk()->assertSee('No se detectaron bloqueos')->assertSee('Esta revisión no genera horarios.');
        $this->assertSame($antes, $this->horario->fresh()->getAttributes());
        $this->assertDatabaseCount('versiones_planificacion', 1);
    }

    public function test_declaracion_sin_aprobacion_no_habilita_generacion(): void
    {
        Models\DisponibilidadProfesor::create(['profesor_id' => $this->profesor->id, 'periodo_academico_id' => $this->periodo->id, 'dia_id' => $this->horario->dia_id,
            'bloque_horario_id' => $this->horario->bloque_horario_id, 'tipo_disponibilidad' => 'Disponible', 'estado' => true]);
        $r = $this->reporte();
        $this->assertSame(0, $r['aprobados']);
        $this->assertTrue($r['observaciones']->contains('titulo', 'Disponibilidad aprobada pendiente'));
    }

    public function test_respuesta_pendiente_nueva_conserva_la_aprobacion_vigente(): void
    {
        $this->aprobar();
        $this->post(route('disponibilidad.store'), ['profesor_id' => $this->profesor->id, 'periodo_academico_id' => $this->periodo->id, 'fecha_limite' => today()->addDay()->toDateString()])->assertSessionHasNoErrors();
        $this->assertSame(1, $this->reporte()['aprobados']);
    }

    public function test_cambios_manual_o_de_calendario_exigen_nueva_aprobacion(): void
    {
        $this->aprobar();
        Models\DisponibilidadProfesor::where('profesor_id', $this->profesor->id)->where('tipo_disponibilidad', 'Disponible')->update(['tipo_disponibilidad' => 'No disponible']);
        $this->assertTrue($this->reporte()['observaciones']->contains('titulo', 'Disponibilidad modificada tras la aprobación'));
        Models\BloqueHorario::whereKey($this->horario->bloque_horario_id)->update(['hora_inicio' => '07:00:00']);
        $this->assertTrue($this->reporte()['observaciones']->contains('titulo', 'Disponibilidad aprobada pendiente'));
    }

    public function test_almuerzo_no_es_una_opcion_del_generador_aunque_no_tenga_marca(): void
    {
        $almuerzo = Models\BloqueHorario::create(['numero' => 100, 'hora_inicio' => '13:00:00', 'hora_fin' => '14:00:00', 'es_almuerzo' => false, 'estado' => true]);
        $this->aprobar(['bloques' => [$this->horario->dia_id.':'.$almuerzo->id]]);
        $r = $this->reporte();
        $this->assertSame(0, $r['aprobados']);
        $this->assertTrue($r['observaciones']->contains('titulo', 'Sin disponibilidad fuera del almuerzo'));
    }

    public function test_faltas_de_carga_docente_y_recursos_aparecen_como_bloqueos(): void
    {
        Models\GrupoProfesor::query()->delete();
        $this->grupo->actividadSeccion->update(['cantidad_horas' => 0]);
        Models\AsignacionSala::query()->delete();
        $r = $this->reporte();
        foreach (['Carga sin definir', 'Grupo sin docente', 'Asignaciones incompletas', 'Carga de la versión base'] as $titulo) $this->assertTrue($r['observaciones']->contains('titulo', $titulo), $titulo);
    }

    public function test_docente_directo_reemplaza_al_docente_inferido_del_grupo(): void
    {
        $this->aprobar();
        $otro = Models\Profesor::create(['universidad_id' => $this->profesor->universidad_id, 'nombre' => 'Luis', 'apellido' => 'Soto', 'rut' => '22222222', 'estado' => true]);
        Models\HorarioProfesor::create(['horario_id' => $this->horario->id, 'profesor_id' => $otro->id, 'estado' => true]);
        $r = $this->reporte(); $this->assertSame(1, $r['docentes']); $this->assertSame(0, $r['aprobados']);
        $this->assertStringContainsString('Soto', $r['observaciones']->firstWhere('titulo', 'Disponibilidad aprobada pendiente')['detalle']);
    }

    public function test_respeta_permiso_y_no_mezcla_periodos_y_versiones(): void
    {
        $otro = Models\PeriodoAcademico::create(['universidad_id' => $this->periodo->universidad_id, 'nombre' => '2027-I', 'anio' => 2027, 'numero_semestre' => 1]);
        $this->get(route('programacion.preparacion', ['periodo_id' => $otro->id, 'version_id' => $this->version->id]))->assertRedirect()->assertSessionHasErrors('version_id');
        $this->get(route('programacion.preparacion', ['periodo_id' => ['invalido']]))->assertRedirect()->assertSessionHasErrors('periodo_id');
        $this->usuario->revokePermissionTo('generar programacion semestral');
        $this->get(route('programacion.preparacion'))->assertRedirect();
    }

    public function test_periodo_inactivo_sin_version_y_sin_secciones_se_explican(): void
    {
        $this->periodo->update(['estado' => false]);
        Models\Seccion::query()->update(['estado_activo' => false]);
        $r = app(PreparacionGeneracion::class)->revisar($this->periodo->fresh(), null);
        foreach (['Período inactivo', 'Sin secciones activas', 'Falta una versión base'] as $titulo) $this->assertTrue($r['observaciones']->contains('titulo', $titulo));
    }

    public function test_los_topes_se_informan_como_avisos_y_no_se_ocultan(): void
    {
        $this->aprobar();
        $otroGrupo = $this->grupo->replicate(); $otroGrupo->codigo = 'G:2'; $otroGrupo->numero = 2; $otroGrupo->save();
        Models\GrupoProfesor::create(['grupo_seccion_id' => $otroGrupo->id, 'profesor_id' => $this->profesor->id, 'estado' => true]);
        $duplicado = $this->horario->replicate(); $duplicado->grupo_seccion_id = $otroGrupo->id; $duplicado->save();
        $asignacion = Models\AsignacionSala::firstOrFail()->replicate(); $asignacion->horario_id = $duplicado->id; $asignacion->save();
        $r = $this->reporte();
        $this->assertSame('aviso', $r['observaciones']->firstWhere('titulo', 'Topes en la versión base')['tipo']);
        $this->get(route('programacion.preparacion'))->assertOk()->assertSee('Topes en la versión base');
    }

    public function test_la_capacidad_y_el_estado_de_los_recursos_bloquean_reutilizacion(): void
    {
        Models\Sala::query()->update(['capacidad' => 1, 'disponible' => false]);
        $r = $this->reporte();
        $this->assertTrue($r['observaciones']->contains('titulo', 'Capacidad de espacios'));
        $this->assertTrue($r['observaciones']->contains('titulo', 'Compatibilidad de recursos'));
    }

    public function test_aprobacion_de_otro_periodo_no_se_utiliza(): void
    {
        $s = $this->aprobar();
        $otro = Models\PeriodoAcademico::create(['universidad_id' => $this->periodo->universidad_id, 'nombre' => '2027-I', 'anio' => 2027, 'numero_semestre' => 1]);
        $s->update(['periodo_academico_id' => $otro->id]);
        $this->assertSame(0, $this->reporte()['aprobados']);
    }
}

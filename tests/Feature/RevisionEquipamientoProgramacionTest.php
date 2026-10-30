<?php

namespace Tests\Feature;

use App\Models;
use App\Services\RevisionConflictosProgramacion;
use Illuminate\Support\Facades\DB;
use Tests\IsolatedDatabaseTestCase;

class RevisionEquipamientoProgramacionTest extends IsolatedDatabaseTestCase
{
    private Models\Horario $horario;
    private Models\Sala $sala;
    private Models\Equipamiento $equipo;
    private Models\RequerimientoActividad $regla;
    private Models\VersionPlanificacion $version;

    protected function setUp(): void
    {
        parent::setUp();
        $uni = DB::table('universidades')->value('id');
        $user = Models\User::create(['name' => 'Revisión de equipos', 'rut' => '78888888', 'email' => 'equipos@example.test', 'password' => 'prueba']);
        foreach (['gestionar conflictos', 'asignar horarios'] as $permiso) $user->givePermissionTo(\Spatie\Permission\Models\Permission::findOrCreate($permiso, 'web'));
        $this->actingAs($user);
        $periodo = Models\PeriodoAcademico::create(['universidad_id' => $uni, 'nombre' => '2026-2', 'anio' => 2026, 'numero_semestre' => 2]);
        $this->version = Models\VersionPlanificacion::create(['periodo_academico_id' => $periodo->id, 'tipo' => 'semestral', 'nombre' => 'Semestral', 'version' => '1.0']);
        $a = Models\Asignatura::create(['universidad_id' => $uni, 'codigo' => 'EQ-1', 'nombre' => 'Actividad con equipo']);
        $s = Models\Seccion::create(['asignatura_id' => $a->id, 'periodo_academico_id' => $periodo->id, 'codigo' => '11']);
        $act = Models\ActividadSeccion::create(['seccion_id' => $s->id, 'tipo' => 'Cátedra']);
        $grupo = Models\GrupoSeccion::create(['actividad_seccion_id' => $act->id, 'codigo' => 'G:1', 'inscritos' => 20]);
        $sede = Models\Sede::create(['universidad_id' => $uni, 'nombre' => 'Sede']);
        $tipo = Models\TipoSala::create(['nombre' => 'Aula']);
        $this->sala = Models\Sala::create(['universidad_id' => $uni, 'sede_id' => $sede->id, 'tipo_sala_id' => $tipo->id, 'codigo' => 'A-101', 'capacidad' => 30]);
        $this->horario = Models\Horario::create(['actividad_seccion_id' => $act->id, 'grupo_seccion_id' => $grupo->id, 'version_planificacion_id' => $this->version->id, 'dia_id' => 1, 'bloque_horario_id' => 1]);
        Models\AsignacionSala::create(['horario_id' => $this->horario->id, 'sala_id' => $this->sala->id]);
        $this->equipo = Models\Equipamiento::create(['universidad_id' => $uni, 'nombre' => 'Proyector', 'tipo' => 'Audiovisual', 'cantidad' => 3]);
        $this->regla = Models\RequerimientoActividad::create(['actividad_seccion_id' => $act->id, 'tipo_requerimiento' => 'Equipamiento', 'equipamiento_id' => $this->equipo->id, 'cantidad' => 2, 'obligatorio' => true]);
    }

    public function test_cuenta_solo_el_equipo_exacto_ubicado_en_los_espacios_del_modulo(): void
    {
        $this->assertSame(1, $this->reporte()['recursos']);
        $otro = $this->equipo->replicate(); $otro->save();
        Models\EquipamientoEspacio::create(['equipamiento_id' => $otro->id, 'sala_id' => $this->sala->id, 'cantidad' => 3]);
        $this->assertSame(1, $this->reporte()['recursos']);
        $ubicacion = $this->ubicar(1);
        $this->assertStringContainsString('se pueden cubrir 1', $this->fila()['mensaje']);
        $ubicacion->update(['cantidad' => 2]);
        $this->assertSame(0, $this->reporte()['recursos']);
    }

    public function test_no_suma_stock_sin_ubicar_o_de_otras_salas(): void
    {
        $otra = $this->sala->replicate(); $otra->codigo = 'A-102'; $otra->save();
        $this->ubicar(3, $otra->id);
        $this->assertStringContainsString('se pueden cubrir 0', $this->fila()['mensaje']);
    }

    public function test_la_sala_del_laboratorio_no_duplica_las_unidades(): void
    {
        $this->ubicar(1);
        $lab = Models\Laboratorio::create(['sala_id' => $this->sala->id, 'nombre' => 'Laboratorio', 'capacidad' => 30]);
        Models\AsignacionLaboratorio::create(['horario_id' => $this->horario->id, 'laboratorio_id' => $lab->id]);
        $this->assertStringContainsString('se pueden cubrir 1', $this->fila()['mensaje']);
    }

    public function test_no_reutiliza_unidades_entre_requerimientos_del_mismo_equipo(): void
    {
        $this->ubicar(3);
        $otra = $this->regla->replicate(); $otra->save();
        $this->assertStringContainsString('se pueden cubrir 1', $this->fila()['mensaje']);
        $otra->update(['cantidad' => 1]);
        $this->assertSame(0, $this->reporte()['recursos']);
    }

    public function test_no_certifica_equipos_inactivos_ni_distribucion_superior_al_inventario(): void
    {
        $this->ubicar(2); $this->equipo->update(['disponible' => false]);
        $this->assertStringContainsString('no se puede acreditar', $this->fila()['mensaje']);
        $this->equipo->update(['disponible' => true, 'cantidad' => 1]);
        $this->assertSame(1, $this->reporte()['recursos']);
    }

    public function test_no_cuenta_el_stock_de_un_espacio_inhabilitado(): void
    {
        $this->ubicar(3); $this->sala->update(['disponible' => false]);
        $this->assertStringContainsString('se pueden cubrir 0', $this->fila()['mensaje']);
    }

    public function test_el_equipo_viaja_con_el_laboratorio_movil_asignado(): void
    {
        $movil = Models\LaboratorioMovil::create(['universidad_id' => $this->sala->universidad_id, 'sede_id' => $this->sala->sede_id, 'codigo' => 'MOV-1', 'nombre' => 'Móvil', 'capacidad' => 30]);
        Models\AsignacionLaboratorioMovil::create(['horario_id' => $this->horario->id, 'laboratorio_movil_id' => $movil->id]);
        Models\RequerimientoActividad::create(['actividad_seccion_id' => $this->horario->actividad_seccion_id, 'tipo_requerimiento' => 'Sala', 'tipo_sala_id' => $this->sala->tipo_sala_id, 'obligatorio' => true]);
        Models\EquipamientoEspacio::create(['equipamiento_id' => $this->equipo->id, 'laboratorio_movil_id' => $movil->id, 'cantidad' => 2]);
        $this->assertSame(0, $this->reporte()['recursos']);
        Models\AsignacionLaboratorioMovil::where('horario_id', $this->horario->id)->update(['estado' => false]);
        $this->assertSame(1, $this->reporte()['recursos']);
    }

    public function test_los_requisitos_por_nombre_siguen_siendo_manuales_y_los_opcionales_no_se_exigen(): void
    {
        $this->regla->update(['equipamiento_id' => null, 'nombre_recurso' => 'Proyector']);
        $this->ubicar(3);
        $this->assertStringContainsString('no está vinculado', $this->fila()['mensaje']);
        $this->regla->update(['obligatorio' => false]);
        $this->assertSame(0, $this->reporte()['recursos']);
    }

    public function test_la_configuracion_vincula_el_equipo_y_rechaza_equipos_ajenos(): void
    {
        $url = route('programacion.requerimientos.store', ['actividad' => $this->horario->actividad_seccion_id]);
        $datos = ['tipo_requerimiento' => 'Equipamiento', 'equipamiento_id' => $this->equipo->id, 'cantidad' => 1, 'obligatorio' => 1, 'estado' => 1];
        $this->post($url, $datos)->assertRedirect();
        $nueva = Models\RequerimientoActividad::orderByDesc('id')->first();
        $this->assertSame('Proyector', $nueva->nombre_recurso);
        $otra = Models\Universidad::create(['nombre' => 'Otra', 'sigla' => 'OTRA']); $this->equipo->update(['universidad_id' => $otra->id]);
        $this->post($url, $datos)->assertSessionHasErrors('equipamiento_id');
    }

    public function test_los_filtros_y_la_consulta_no_modifican_el_inventario(): void
    {
        $this->ubicar(1);
        $antes = DB::table('equipamientos_espacios')->get()->toJson();
        $this->get(route('programacion.validacion', ['version_id' => $this->version->id, 'tipo' => 'equipamiento']))->assertOk()->assertViewHas('observaciones', fn ($p) => $p->total() === 1);
        $this->assertSame($antes, DB::table('equipamientos_espacios')->get()->toJson());
    }

    public function test_cambiar_un_requisito_de_sala_a_equipo_limpia_las_condiciones_del_espacio(): void
    {
        $this->regla->update(['tipo_requerimiento' => 'Sala', 'equipamiento_id' => null, 'tipo_sala_id' => $this->sala->tipo_sala_id, 'capacidad_minima' => 30]);
        $this->put(route('programacion.requerimientos.update', ['actividad' => $this->horario->actividad_seccion_id, 'requerimiento' => $this->regla->id]),
            ['tipo_requerimiento' => 'Equipamiento', 'equipamiento_id' => $this->equipo->id, 'cantidad' => 2, 'obligatorio' => 1, 'estado' => 1])->assertRedirect();
        $this->assertNull($this->regla->fresh()->capacidad_minima);
        $this->assertNull($this->regla->fresh()->tipo_sala_id);
        $this->assertSame($this->equipo->id, $this->regla->fresh()->equipamiento_id);
    }

    private function ubicar(int $cantidad, ?int $salaId = null): Models\EquipamientoEspacio { return Models\EquipamientoEspacio::create(['equipamiento_id' => $this->equipo->id, 'sala_id' => $salaId ?? $this->sala->id, 'cantidad' => $cantidad]); }
    private function reporte(): array { return app(RevisionConflictosProgramacion::class)->revisar($this->version); }
    private function fila(): array { return $this->reporte()['observaciones']->where('tipo', 'recursos')->sole(); }
}

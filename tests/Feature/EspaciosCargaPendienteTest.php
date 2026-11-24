<?php

namespace Tests\Feature;

use App\Models;
use App\Services\{DistribuidorCargaPendiente, EspaciosCargaPendiente, PrioridadUsoRecursos, RevisionEquipamientoProgramacion, RevisionRecursosProgramacion};
use Illuminate\Support\Facades\DB;
use Tests\IsolatedDatabaseTestCase;

class EspaciosCargaPendienteTest extends IsolatedDatabaseTestCase
{
    private Models\Sede $sede;
    private Models\TipoSala $tipo;
    private Models\GrupoSeccion $grupo;
    private Models\Profesor $profesor;

    protected function setUp(): void
    {
        parent::setUp();
        $universidad = DB::table('universidades')->value('id');
        $this->sede = Models\Sede::create(['universidad_id' => $universidad, 'nombre' => 'Talcahuano', 'estado' => true]);
        $this->tipo = Models\TipoSala::create(['nombre' => 'Aula', 'estado' => true, 'requiere_equipamiento' => false]);
        $periodo = Models\PeriodoAcademico::create(['universidad_id' => $universidad, 'nombre' => '2026-2', 'anio' => 2026, 'numero_semestre' => 2, 'estado' => true]);
        $ramo = Models\Asignatura::create(['universidad_id' => $universidad, 'nombre' => 'Backend', 'codigo' => 'BACK', 'estado' => true]);
        $seccion = Models\Seccion::create(['periodo_academico_id' => $periodo->id, 'asignatura_id' => $ramo->id, 'codigo' => '11', 'inscritos' => 80, 'cupos' => 80, 'estado_activo' => true]);
        $actividad = Models\ActividadSeccion::create(['seccion_id' => $seccion->id, 'tipo' => 'Cátedra', 'cantidad_horas' => 1, 'cantidad_estudiantes' => 80, 'estado' => true]);
        $this->grupo = Models\GrupoSeccion::create(['actividad_seccion_id' => $actividad->id, 'codigo' => '1', 'numero' => 1, 'inscritos' => 20, 'cupos' => 20, 'estado_activo' => true]);
        $this->profesor = Models\Profesor::create(['universidad_id' => $universidad, 'nombre' => 'Ana', 'apellido' => 'Pérez', 'estado' => true]);
        Models\GrupoProfesor::create(['grupo_seccion_id' => $this->grupo->id, 'profesor_id' => $this->profesor->id, 'es_principal' => true, 'estado' => true]);
    }

    private function sala(string $codigo, int $capacidad): Models\Sala
    {
        return Models\Sala::create(['universidad_id' => $this->sede->universidad_id, 'sede_id' => $this->sede->id, 'tipo_sala_id' => $this->tipo->id,
            'codigo' => $codigo, 'capacidad' => $capacidad, 'estado' => true, 'disponible' => true]);
    }

    private function candidatos(): array
    {
        $grupo = $this->grupo->fresh()->load('actividadSeccion.requerimientos.tipoSala', 'actividadSeccion.grupos', 'actividadSeccion.seccion.asignatura', 'profesores.profesor');
        $salas = Models\Sala::with('sede', 'tipoSala', 'laboratorios.sala.tipoSala')->orderBy('id')->get();
        $inventario = collect();
        $reglas = Models\PrioridadRecurso::with('tipoSala')->get();
        $revision = new RevisionRecursosProgramacion($this->sede->universidad_id, new RevisionEquipamientoProgramacion($inventario));
        $intentos = 0;

        return (new EspaciosCargaPendiente)->candidatos($grupo, $this->sede->id, $grupo->profesores, $salas, collect(),
            $revision, new PrioridadUsoRecursos($reglas, $inventario), $inventario, $intentos);
    }

    public function test_solo_ofrece_espacios_habilitados_con_capacidad_del_grupo(): void
    {
        $pequena = $this->sala('PEQUEÑA', 19);
        $sinAforo = $this->sala('SIN-AFORO', 0);
        $cerrada = $this->sala('CERRADA', 30);
        $cerrada->update(['disponible' => false]);
        $compatible = $this->sala('COMPATIBLE', 20);

        $candidatos = $this->candidatos();

        $this->assertCount(1, $candidatos);
        $paquete = reset($candidatos);
        $this->assertSame($compatible->id, $paquete['sala_id']);
        $this->assertSame(20, $paquete['cantidad']);
        $this->assertNotContains($pequena->id, array_column($candidatos, 'sala_id'));
        $this->assertNotContains($sinAforo->id, array_column($candidatos, 'sala_id'));
    }

    public function test_el_laboratorio_reserva_su_sala_fisica_y_comprueba_ambos_aforos(): void
    {
        $this->grupo->actividadSeccion->update(['tipo' => 'Laboratorio fijo']);
        $sala = $this->sala('LAB-1', 30);
        $laboratorio = Models\Laboratorio::create(['sala_id' => $sala->id, 'nombre' => 'Computación', 'capacidad' => 19, 'estado' => true, 'disponible' => true]);

        $this->assertSame([], $this->candidatos());
        $laboratorio->update(['capacidad' => 25]);
        $candidatos = $this->candidatos();
        $this->assertCount(1, $candidatos);
        $paquete = reset($candidatos);
        $this->assertSame(['sala:'.$sala->id, 'laboratorio:'.$laboratorio->id], $paquete['recursos']);

        $sala->update(['capacidad' => 19]);
        $this->assertSame([], $this->candidatos());
    }

    public function test_el_ajuste_de_capacidad_prefiere_la_sala_pequena_sin_excluir_la_mayor(): void
    {
        $grande = $this->sala('GRANDE', 100);
        $ajustada = $this->sala('AJUSTADA', 20);
        $candidatos = $this->candidatos();
        $this->assertCount(2, $candidatos);
        $this->assertLessThan($candidatos['s'.$grande->id.':l0:m0']['preferencia'], $candidatos['s'.$ajustada->id.':l0:m0']['preferencia']);
    }

    public function test_la_regla_de_laboratorio_lo_hace_preferible_sin_exigirlo_como_requerimiento(): void
    {
        $aula = $this->sala('AULA', 20);
        $salaLab = $this->sala('LAB', 25);
        $laboratorio = Models\Laboratorio::create(['sala_id' => $salaLab->id, 'nombre' => 'Computación', 'capacidad' => 25, 'estado' => true, 'disponible' => true]);
        Models\PrioridadRecurso::create(['nombre' => 'Backend usa laboratorio', 'asignatura_id' => $this->grupo->actividadSeccion->seccion->asignatura_id,
            'tipo_recurso' => 'laboratorio', 'clave_recurso' => 'laboratorio:todos', 'nivel' => 1, 'estado' => true]);
        $paquetes = $this->candidatos();
        $this->assertSame(256, $paquetes['s'.$aula->id.':l0:m0']['prioridad']);
        $this->assertSame(1, $paquetes['s'.$salaLab->id.':l'.$laboratorio->id.':m0']['prioridad']);
        $trabajo = ['id' => 'backend', 'grupo_id' => $this->grupo->id, 'orden' => 1, 'carga' => 1, 'prioridad' => 1,
            'recursos' => ['grupo:'.$this->grupo->id], 'opciones' => ['lunes']];
        $opciones = ['lunes' => ['dia_id' => 1, 'inicio' => 8 * 3600, 'fin' => 9 * 3600, 'orden' => 1]];

        $resultado = (new DistribuidorCargaPendiente)->distribuir([$trabajo], $opciones, [$this->grupo->id => $paquetes], []);

        $this->assertSame('completo', $resultado['estado']);
        $this->assertSame('s'.$salaLab->id.':l'.$laboratorio->id.':m0', $resultado['plan']['backend']['paquete']);
    }
}

<?php

namespace Tests\Feature;

use App\Models\ActividadSeccion;
use App\Models\AsignacionLaboratorio;
use App\Models\AsignacionLaboratorioMovil;
use App\Models\AsignacionSala;
use App\Models\Asignatura;
use App\Models\GrupoSeccion;
use App\Models\Horario;
use App\Models\HorarioProfesor;
use App\Models\Laboratorio;
use App\Models\LaboratorioMovil;
use App\Models\PeriodoAcademico;
use App\Models\Profesor;
use App\Models\Sala;
use App\Models\Seccion;
use App\Models\Sede;
use App\Models\TipoSala;
use App\Models\Universidad;
use App\Models\VersionPlanificacion;
use App\Services\IndicadoresProgramacion;
use Illuminate\Support\Facades\DB;
use Tests\IsolatedDatabaseTestCase;

class IndicadoresProgramacionTest extends IsolatedDatabaseTestCase
{
    private int $universidad;
    private PeriodoAcademico $periodo;
    private VersionPlanificacion $version;
    private ActividadSeccion $actividad;

    protected function setUp(): void
    {
        parent::setUp();
        $this->universidad = DB::table('universidades')->value('id');
        $this->periodo = PeriodoAcademico::create(['universidad_id' => $this->universidad, 'nombre' => '2026-2', 'anio' => 2026, 'numero_semestre' => 2, 'estado' => true]);
        $this->version = VersionPlanificacion::create(['periodo_academico_id' => $this->periodo->id, 'nombre' => 'Actual', 'tipo' => 'semestral', 'version' => '0.1', 'estado' => 'Borrador', 'es_actual' => true]);
        $asignatura = Asignatura::create(['universidad_id' => $this->universidad, 'codigo' => 'IND1', 'nombre' => 'Carga del período']);
        $seccion = Seccion::create(['periodo_academico_id' => $this->periodo->id, 'asignatura_id' => $asignatura->id, 'codigo' => '1', 'estado_activo' => true]);
        $this->actividad = ActividadSeccion::create(['seccion_id' => $seccion->id, 'tipo' => 'Cátedra', 'cantidad_horas' => 3, 'estado' => true]);
    }

    public function test_sin_periodo_o_version_no_presenta_porcentajes_ficticios(): void
    {
        foreach ([[null, null], [$this->periodo, null]] as [$periodo, $version]) {
            $datos = app(IndicadoresProgramacion::class)->calcular($periodo, $version);
            $this->assertCount(6, $datos['graficos']);
            foreach ($datos['graficos'] as $grafico) $this->assertNull($grafico['porcentaje']);
        }
    }

    public function test_los_excedentes_de_un_grupo_no_compensan_la_carga_faltante_de_otro(): void
    {
        $primero = $this->grupo('G:1');
        $this->grupo('G:2');
        foreach (range(1, 5) as $dia) $this->horario($primero, $dia);
        $inactivo = $this->grupo('G:3', ['estado_activo' => false]);
        $this->horario($inactivo, 1);
        $historica = VersionPlanificacion::create(['periodo_academico_id' => $this->periodo->id, 'nombre' => 'Histórica', 'tipo' => 'semestral', 'version' => '0.0', 'estado' => 'Borrador', 'es_actual' => false]);
        $this->horario($primero, 6, ['version_planificacion_id' => $historica->id]);
        $this->horario($primero, 6, ['estado' => false]);
        $datos = $this->calcular();
        $this->assertSame([3, 6, 3, 50.0], $this->medidas($datos['graficos']['horario']));
        $this->assertSame(2, $datos['pendientes']['excedentes']);
        $this->assertSame(5, $datos['graficos']['docentes']['total']);
    }

    public function test_las_coberturas_no_duplican_modulos_y_el_uso_de_recursos_se_limita_al_catalogo_activo_de_la_institucion(): void
    {
        $grupo = $this->grupo('G:1');
        $horarios = collect(range(1, 5))->map(fn ($dia) => $this->horario($grupo, $dia));
        $sede = Sede::create(['universidad_id' => $this->universidad, 'nombre' => 'Central']);
        $tipo = TipoSala::create(['nombre' => 'Sala']);
        $sala = Sala::create(['universidad_id' => $this->universidad, 'sede_id' => $sede->id, 'tipo_sala_id' => $tipo->id, 'codigo' => 'A', 'nombre' => 'Sala A', 'estado' => true]);
        $sinUso = Sala::create(['universidad_id' => $this->universidad, 'sede_id' => $sede->id, 'tipo_sala_id' => $tipo->id, 'codigo' => 'B', 'nombre' => 'Sala B', 'estado' => true]);
        $otraUniversidad = Universidad::create(['nombre' => 'Otra institución', 'sigla' => 'OTRA']);
        Sala::create(['universidad_id' => $otraUniversidad->id, 'sede_id' => $sede->id, 'tipo_sala_id' => $tipo->id, 'codigo' => 'OTRA', 'nombre' => 'Ajena', 'estado' => true]);
        Sala::create(['universidad_id' => $this->universidad, 'sede_id' => $sede->id, 'tipo_sala_id' => $tipo->id, 'codigo' => 'INACTIVA', 'nombre' => 'Retirada', 'estado' => false]);
        $lab = Laboratorio::create(['sala_id' => $sala->id, 'codigo' => 'LAB', 'nombre' => 'Laboratorio', 'estado' => true]);
        Laboratorio::create(['sala_id' => $sala->id, 'codigo' => 'RET', 'nombre' => 'Retirado', 'estado' => false]);
        $movil = LaboratorioMovil::create(['universidad_id' => $this->universidad, 'sede_id' => $sede->id, 'codigo' => 'MOV', 'nombre' => 'Móvil', 'estado' => true]);
        AsignacionSala::create(['horario_id' => $horarios[0]->id, 'sala_id' => $sala->id, 'estado' => true]);
        AsignacionSala::create(['horario_id' => $horarios[3]->id, 'sala_id' => $sinUso->id, 'estado' => false]);
        foreach ([0, 1] as $indice) AsignacionLaboratorio::create(['horario_id' => $horarios[$indice]->id, 'laboratorio_id' => $lab->id, 'estado' => true]);
        AsignacionLaboratorioMovil::create(['horario_id' => $horarios[4]->id, 'laboratorio_movil_id' => $movil->id, 'estado' => true]);
        foreach (['Responsable', 'Colaborador'] as $nombre) {
            $profesor = Profesor::create(['universidad_id' => $this->universidad, 'nombre' => $nombre, 'apellido' => 'Docente']);
            HorarioProfesor::create(['horario_id' => $horarios[0]->id, 'profesor_id' => $profesor->id, 'estado' => true]);
        }
        $datos = $this->calcular()['graficos'];
        $this->assertSame([1, 5, 4, 20.0], $this->medidas($datos['docentes']));
        $this->assertSame([2, 5, 3, 40.0], $this->medidas($datos['espacios']));
        $this->assertSame([1, 2, 1, 50.0], $this->medidas($datos['salas']));
        $this->assertSame([1, 1, 0, 100.0], $this->medidas($datos['laboratorios']));
        $this->assertSame([1, 1, 0, 100.0], $this->medidas($datos['moviles']));
    }

    public function test_los_datos_sin_carga_o_sin_grupo_se_muestran_para_revision_y_no_como_avance_completo(): void
    {
        $this->actividad->update(['cantidad_horas' => 0]);
        $this->horario($this->grupo('G:1'), 1);
        $sinGrupos = ActividadSeccion::create(['seccion_id' => $this->actividad->seccion_id, 'tipo' => 'Taller', 'cantidad_horas' => 4, 'estado' => true]);
        Horario::create(['actividad_seccion_id' => $sinGrupos->id, 'version_planificacion_id' => $this->version->id, 'dia_id' => 2, 'bloque_horario_id' => 1, 'estado' => true]);
        $datos = $this->calcular();
        $this->assertNull($datos['graficos']['horario']['porcentaje']);
        $this->assertNull($datos['graficos']['laboratorios']['porcentaje']);
        $this->assertSame(['actividades_sin_grupos' => 1, 'grupos_sin_carga' => 1, 'modulos_sin_grupo' => 1, 'excedentes' => 0], $datos['pendientes']);
    }

    private function grupo(string $codigo, array $cambios = []): GrupoSeccion
    {
        return GrupoSeccion::create(array_replace(['actividad_seccion_id' => $this->actividad->id, 'codigo' => $codigo, 'estado_activo' => true], $cambios));
    }

    private function horario(GrupoSeccion $grupo, int $dia, array $cambios = []): Horario
    {
        return Horario::create(array_replace(['actividad_seccion_id' => $grupo->actividad_seccion_id, 'grupo_seccion_id' => $grupo->id, 'version_planificacion_id' => $this->version->id, 'dia_id' => $dia, 'bloque_horario_id' => 1, 'estado' => true], $cambios));
    }

    private function calcular(): array
    {
        return app(IndicadoresProgramacion::class)->calcular($this->periodo, $this->version);
    }

    private function medidas(array $grafico): array
    {
        return [$grafico['asignados'], $grafico['total'], $grafico['restantes'], $grafico['porcentaje']];
    }
}

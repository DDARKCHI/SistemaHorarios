<?php

namespace Tests\Feature;

use App\Models;
use App\Services\MovimientosPanelCarrera;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\IsolatedDatabaseTestCase;

class MovimientosPanelCarreraTest extends IsolatedDatabaseTestCase
{
    private MovimientosPanelCarrera $movimientos;
    private Models\User $usuario;
    private Models\PeriodoAcademico $periodo;
    private Models\VersionPlanificacion $version;
    private Models\Profesor $docente;
    private Models\Carrera $carrera;
    private Models\Sala $sala;
    private Models\Horario $clase;
    private int $lunes;
    private int $martes;
    private int $primero;
    private int $segundo;

    protected function setUp(): void
    {
        parent::setUp();
        $universidad = DB::table('universidades')->value('id');
        $this->movimientos = app(MovimientosPanelCarrera::class);
        $this->usuario = Models\User::create(['name' => 'Jefatura de prueba', 'rut' => '17777777', 'email' => 'panel@example.test', 'password' => 'prueba']);
        $this->periodo = Models\PeriodoAcademico::create(['universidad_id' => $universidad, 'nombre' => '2026-2', 'anio' => 2026, 'numero_semestre' => 2, 'estado' => true]);
        $this->version = Models\VersionPlanificacion::create(['periodo_academico_id' => $this->periodo->id, 'nombre' => 'Semestral', 'tipo' => 'semestral', 'version' => '0.1', 'estado' => 'Borrador', 'es_actual' => true]);
        $sede = Models\Sede::create(['universidad_id' => $universidad, 'nombre' => 'Talcahuano', 'estado' => true]);
        $this->carrera = Models\Carrera::create(['universidad_id' => $universidad, 'sede_id' => $sede->id, 'nombre' => 'Informática', 'codigo' => 'INF', 'estado' => true]);
        $tipo = Models\TipoSala::create(['nombre' => 'Aula', 'estado' => true]);
        $this->sala = Models\Sala::create(['universidad_id' => $universidad, 'sede_id' => $sede->id, 'tipo_sala_id' => $tipo->id, 'codigo' => 'A1', 'capacidad' => 30, 'estado' => true, 'disponible' => true]);
        $this->docente = Models\Profesor::create(['universidad_id' => $universidad, 'nombre' => 'Ana', 'apellido' => 'Prueba', 'estado' => true]);
        $this->lunes = Models\Dia::where('numero', 1)->value('id');
        $this->martes = Models\Dia::where('numero', 2)->value('id');
        $this->primero = Models\BloqueHorario::where('numero', 1)->value('id');
        $this->segundo = Models\BloqueHorario::where('numero', 2)->value('id');
        $this->clase = $this->crearClase('BACK', 2, $this->docente, $this->lunes, $this->primero);
        $this->asignarSala($this->clase);
    }

    public function test_destinos_distinguen_disponibilidad_y_topes_por_carrera_y_docente(): void
    {
        $this->disponibilidad($this->martes, $this->primero);
        $this->crearClase('REDES', 2, null, $this->martes, $this->segundo);
        $this->crearClase('DISEÑO', 4, $this->docente, $this->lunes, $this->segundo);
        $r = $this->movimientos->evaluar($this->clase);

        $this->assertSame('verde', $r[$this->martes.':'.$this->primero]['nivel']);
        $this->assertSame('rojo', $r[$this->martes.':'.$this->segundo]['nivel']);
        $this->assertContains('Cruce de estudiantes con REDES.', $r[$this->martes.':'.$this->segundo]['motivos']);
        $this->assertSame('rojo', $r[$this->lunes.':'.$this->segundo]['nivel']);
        $this->assertContains('Ana Prueba ya tiene clase en DISEÑO.', $r[$this->lunes.':'.$this->segundo]['motivos']);
        $this->assertTrue($r[$this->lunes.':'.$this->primero]['bloqueado']);
    }

    public function test_conserva_espacios_y_audita_mover_y_deshacer_con_confirmacion(): void
    {
        $destino = $this->crearClase('REDES', 2, null, $this->martes, $this->primero);
        $this->asignarSala($destino);
        $this->validacion(fn () => $this->mover($this->martes, $this->primero), 'confirmar');
        $this->mover($this->martes, $this->primero, true);
        $this->assertDatabaseHas('horarios', ['id' => $this->clase->id, 'dia_id' => $this->martes]);
        $this->assertDatabaseHas('asignaciones_salas', ['horario_id' => $this->clase->id, 'sala_id' => $this->sala->id, 'estado' => true]);
        $this->assertDatabaseCount('grupos_profesores', 1);
        $this->validacion(fn () => $this->mover($this->martes, $this->segundo), 'movimiento');

        $this->movimientos->mover($this->clase, $this->lunes, $this->primero, $this->martes, $this->primero, $this->usuario->id);
        $this->assertDatabaseHas('horarios', ['id' => $this->clase->id, 'dia_id' => $this->lunes]);
        $this->assertDatabaseCount('historial_acciones', 2);
    }

    public function test_clases_de_la_misma_carrera_en_sedes_distintas_no_comparten_cohorte(): void
    {
        $otra = $this->crearClase('REDES', 2, null, $this->martes, $this->primero);
        $sede = Models\Sede::create(['universidad_id' => $this->periodo->universidad_id, 'nombre' => 'Otra sede', 'estado' => true]);
        $sala = $this->sala->replicate();
        $sala->sede_id = $sede->id;
        $sala->codigo = 'B1';
        $sala->save();
        $otra->asignacionesSalas()->create(['sala_id' => $sala->id, 'estado' => true]);
        $this->disponibilidad($this->martes, $this->primero);

        $evaluacion = $this->movimientos->evaluar($this->clase)[$this->martes.':'.$this->primero];
        $this->assertSame('verde', $evaluacion['nivel']);
    }

    public function test_duplicado_inactivo_destino_fuera_del_calendario_y_carrera_ajena_no_se_pueden_forzar(): void
    {
        $this->clase->replicate()->fill(['dia_id' => $this->martes, 'estado' => false])->save();
        $r = $this->movimientos->evaluar($this->clase)[$this->martes.':'.$this->primero];
        $this->assertSame('rojo', $r['nivel']);
        $this->assertTrue($r['bloqueado']);
        $this->validacion(fn () => $this->mover($this->martes, $this->primero, true), 'movimiento');
        Models\BloqueHorario::findOrFail($this->segundo)->update(['estado' => false]);
        $this->validacion(fn () => $this->mover($this->martes, $this->segundo, true), 'movimiento');
        $this->validacion(fn () => $this->movimientos->mover($this->clase, $this->martes, $this->primero, $this->lunes, $this->primero, $this->usuario->id, true, 999), 'movimiento');
        $this->version->update(['es_actual' => false]);
        $this->validacion(fn () => $this->mover($this->martes, $this->primero, true), 'movimiento');
        $this->assertDatabaseHas('horarios', ['id' => $this->clase->id, 'dia_id' => $this->lunes]);
        $this->assertDatabaseCount('historial_acciones', 0);
    }

    public function test_compara_intervalos_y_muestra_prioridad_en_la_sala_compartida(): void
    {
        $bloque = Models\BloqueHorario::findOrFail($this->primero)->replicate();
        $bloque->numero = 98;
        $bloque->save();
        $otro = $this->crearClase('REDES', 4, null, $this->martes, $bloque->id);
        $this->asignarSala($otro);
        foreach ([[$this->clase, 2], [$otro, 1]] as [$clase, $nivel]) {
            Models\PrioridadRecurso::create(['asignatura_id' => $clase->actividadSeccion->seccion->asignatura_id,
                'nombre' => 'Prioridad '.$nivel, 'nivel' => $nivel, 'estado' => true, 'tipo_recurso' => 'sala']);
        }

        $destino = $this->movimientos->evaluar($this->clase)[$this->martes.':'.$this->primero];
        $this->assertSame('ambar', $destino['nivel']);
        $this->assertContains('Sala A1 ya está ocupado por REDES.', $destino['motivos']);
        $this->assertContains('REDES tiene prioridad de nivel 1 para Sala A1.', $destino['motivos']);
    }

    public function test_el_laboratorio_fisico_comparte_ocupacion_con_su_sala(): void
    {
        $lab = Models\Laboratorio::create(['sala_id' => $this->sala->id, 'nombre' => 'Laboratorio A1', 'capacidad' => 25, 'estado' => true, 'disponible' => true]);
        $this->clase->asignacionesSalas()->delete();
        $this->clase->asignacionesLaboratorios()->create(['laboratorio_id' => $lab->id, 'capacidad_asignada' => 20, 'es_principal' => true, 'estado' => true]);
        $otraClase = $this->crearClase('REDES', 4, null, $this->martes, $this->primero);
        $this->asignarSala($otraClase);
        $destino = $this->movimientos->evaluar($this->clase)[$this->martes.':'.$this->primero];

        $this->assertSame('rojo', $destino['nivel']);
        $this->assertFalse($destino['bloqueado']);
        $this->assertContains('Sala A1 ya está ocupado por REDES.', $destino['motivos']);
    }

    public function test_un_grupo_paralelo_puede_compartir_modulo_y_una_solicitud_vigente_impide_mover(): void
    {
        $grupo = $this->clase->grupoSeccion->replicate();
        $grupo->numero = 2;
        $grupo->codigo = 'G:2';
        $grupo->save();
        $this->clase->replicate()->fill(['grupo_seccion_id' => $grupo->id, 'dia_id' => $this->martes])->save();
        $this->disponibilidad($this->martes, $this->primero);
        $this->assertSame('verde', $this->movimientos->evaluar($this->clase)[$this->martes.':'.$this->primero]['nivel']);

        Models\SolicitudCambioHorario::create(['horario_id' => $this->clase->id, 'profesor_id' => $this->docente->id,
            'usuario_id' => $this->usuario->id, 'motivo' => 'Ajuste de prueba', 'estado' => 'Pendiente de respuesta',
            'fecha_solicitud' => now(), 'fecha_limite' => now()->addDay(), 'estado_activo' => true]);
        $this->validacion(fn () => $this->mover($this->martes, $this->primero, true), 'movimiento');
        $this->assertDatabaseCount('historial_acciones', 0);
    }

    private function crearClase(string $codigo, int $semestre, ?Models\Profesor $docente, int $dia, int $bloque): Models\Horario
    {
        $ramo = Models\Asignatura::create(['universidad_id' => $this->periodo->universidad_id, 'codigo' => $codigo, 'nombre' => $codigo, 'estado' => true]);
        $seccion = Models\Seccion::create(['periodo_academico_id' => $this->periodo->id, 'asignatura_id' => $ramo->id, 'codigo' => '11', 'semestre' => $semestre, 'estado_activo' => true]);
        $seccion->carreras()->attach($this->carrera);
        $actividad = Models\ActividadSeccion::create(['seccion_id' => $seccion->id, 'tipo' => 'Cátedra', 'cantidad_horas' => 2, 'estado' => true]);
        $grupo = Models\GrupoSeccion::create(['actividad_seccion_id' => $actividad->id, 'codigo' => 'G:1', 'numero' => 1, 'inscritos' => 20, 'estado_activo' => true]);
        if ($docente) $grupo->profesores()->create(['profesor_id' => $docente->id, 'estado' => true]);

        return Models\Horario::create(['actividad_seccion_id' => $actividad->id, 'grupo_seccion_id' => $grupo->id,
            'version_planificacion_id' => $this->version->id, 'dia_id' => $dia, 'bloque_horario_id' => $bloque, 'estado' => true]);
    }

    private function asignarSala(Models\Horario $horario): void
    {
        $horario->asignacionesSalas()->create(['sala_id' => $this->sala->id, 'capacidad_asignada' => 20, 'es_principal' => true, 'estado' => true]);
    }

    private function disponibilidad(int $dia, int $bloque): void
    {
        Models\DisponibilidadProfesor::create(['profesor_id' => $this->docente->id, 'periodo_academico_id' => $this->periodo->id,
            'dia_id' => $dia, 'bloque_horario_id' => $bloque, 'tipo_disponibilidad' => 'Disponible', 'estado' => true]);
    }

    private function mover(int $dia, int $bloque, bool $confirmar = false): void
    {
        $this->movimientos->mover($this->clase, $dia, $bloque, $this->lunes, $this->primero, $this->usuario->id, $confirmar, $this->carrera->id);
    }

    private function validacion(callable $operacion, string $campo): void
    {
        try {
            $operacion();
            $this->fail('Se esperaba un error de validación.');
        } catch (ValidationException $error) {
            $this->assertArrayHasKey($campo, $error->errors());
        }
    }
}

<?php

namespace Tests\Feature;

use App\Models;
use App\Services\GeneracionAutomatica;
use App\Services\RevisionConflictosProgramacion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\IsolatedDatabaseTestCase;

class GeneracionAutomaticaTest extends IsolatedDatabaseTestCase
{
    private Models\User $usuario;
    private Models\PeriodoAcademico $periodo;
    private Models\VersionPlanificacion $fuente;
    private Models\Sede $sede;
    private Models\Carrera $carrera;
    private Models\Sala $sala;
    private Models\GrupoSeccion $grupo;
    private Models\Profesor $profesor;

    protected function setUp(): void
    {
        parent::setUp();

        $institucion = DB::table('universidades')->value('id');
        $this->usuario = Models\User::create([
            'name' => 'Coordinación',
            'rut' => '17777777',
            'email' => 'generacion-automatica@example.test',
            'password' => 'prueba',
        ]);
        foreach (['generar programacion semestral', 'ver programacion semestral', 'asignar profesores', 'administrar usuarios', 'gestionar conflictos'] as $permiso) {
            $this->usuario->givePermissionTo(Permission::findOrCreate($permiso, 'web'));
        }
        $this->actingAs($this->usuario);

        $this->periodo = Models\PeriodoAcademico::create([
            'universidad_id' => $institucion,
            'nombre' => '2026-2',
            'anio' => 2026,
            'numero_semestre' => 2,
            'estado' => true,
        ]);
        $this->fuente = Models\VersionPlanificacion::create([
            'periodo_academico_id' => $this->periodo->id,
            'nombre' => 'Base manual',
            'tipo' => 'semestral',
            'version' => 0.1,
            'estado' => 'Borrador',
            'es_actual' => true,
        ]);
        $this->sede = Models\Sede::create([
            'universidad_id' => $institucion, 'nombre' => 'Talcahuano', 'estado' => true,
        ]);
        $this->carrera = Models\Carrera::create([
            'universidad_id' => $institucion,
            'sede_id' => $this->sede->id,
            'nombre' => 'Informática',
            'codigo' => 'INF',
            'estado' => true,
        ]);
        $this->profesor = $this->crearProfesor('12345678', 'Ana');
        $this->grupo = $this->crearGrupo('BACK', 'Backend', $this->profesor);
        $tipo = Models\TipoSala::create([
            'nombre' => 'Aula', 'estado' => true, 'requiere_equipamiento' => false,
        ]);
        $this->sala = Models\Sala::create([
            'universidad_id' => $institucion,
            'sede_id' => $this->sede->id,
            'tipo_sala_id' => $tipo->id,
            'codigo' => 'TH-01',
            'nombre' => 'Sala principal',
            'capacidad' => 30,
            'estado' => true,
            'disponible' => true,
        ]);
    }

    private function crearProfesor(string $rut, string $nombre): Models\Profesor
    {
        return Models\Profesor::create([
            'universidad_id' => $this->periodo->universidad_id,
            'rut' => $rut,
            'nombre' => $nombre,
            'apellido' => 'Prueba',
            'estado' => true,
        ]);
    }

    private function crearGrupo(string $codigo, string $nombre, Models\Profesor $profesor, int $modulos = 2): Models\GrupoSeccion
    {
        $ramo = Models\Asignatura::create([
            'universidad_id' => $this->periodo->universidad_id,
            'codigo' => $codigo,
            'nombre' => $nombre,
            'estado' => true,
        ]);
        $seccion = Models\Seccion::create([
            'periodo_academico_id' => $this->periodo->id,
            'asignatura_id' => $ramo->id,
            'codigo' => '11',
            'semestre' => 2,
            'cupos' => 20,
            'inscritos' => 20,
            'estado_activo' => true,
        ]);
        $seccion->carreras()->attach($this->carrera->id);
        $actividad = Models\ActividadSeccion::create([
            'seccion_id' => $seccion->id,
            'tipo' => 'Cátedra',
            'cantidad_horas' => $modulos,
            'cantidad_estudiantes' => 20,
            'estado' => true,
        ]);
        $grupo = Models\GrupoSeccion::create([
            'actividad_seccion_id' => $actividad->id,
            'codigo' => '1',
            'numero' => 1,
            'cupos' => 20,
            'inscritos' => 20,
            'estado_activo' => true,
        ]);
        $grupo->profesores()->create([
            'profesor_id' => $profesor->id,
            'es_principal' => true,
            'estado' => true,
        ]);

        return $grupo;
    }

    private function slots(): array
    {
        $dias = Models\Dia::whereIn('numero', [1, 2])->orderBy('numero')->pluck('id');
        $bloques = Models\BloqueHorario::whereIn('numero', [1, 2])->orderBy('numero')->pluck('id');
        $slots = [];
        foreach ($dias as $dia) {
            foreach ($bloques as $bloque) {
                $slots[] = $dia.':'.$bloque;
            }
        }

        return $slots;
    }

    private function aprobar(?Models\Profesor $profesor = null, ?array $slots = null): void
    {
        $profesor ??= $this->profesor;
        $this->post(route('disponibilidad.store'), [
            'profesor_id' => $profesor->id,
            'periodo_academico_id' => $this->periodo->id,
            'fecha_limite' => today()->addDays(7)->toDateString(),
        ])->assertSessionHasNoErrors();
        $solicitud = Models\SolicitudDisponibilidad::latest('id')->firstOrFail();
        $url = URL::temporarySignedRoute('disponibilidad.publica', $solicitud->fecha_limite->endOfDay(), [
            'solicitud' => $solicitud->id,
        ]);
        $this->post($url, ['bloques' => $slots ?? $this->slots()])->assertSessionHasNoErrors();
        $this->post(route('disponibilidad.revisar', $solicitud), ['decision' => 'aprobar'])->assertSessionHasNoErrors();
    }

    private function generar(): Models\VersionPlanificacion
    {
        $this->post(route('programacion.semestral.generar'), [
            'version_base_id' => $this->fuente->id,
        ])->assertSessionHasNoErrors()->assertRedirect();

        return Models\VersionPlanificacion::latest('id')->firstOrFail();
    }

    private function manual(bool $conSala = false, ?Models\GrupoSeccion $grupo = null, ?Models\Profesor $profesor = null, ?string $slot = null): Models\Horario
    {
        $grupo ??= $this->grupo;
        $profesor ??= $this->profesor;
        [$dia, $bloque] = array_map('intval', explode(':', $slot ?? $this->slots()[0]));
        $horario = $this->fuente->horarios()->create([
            'actividad_seccion_id' => $grupo->actividad_seccion_id,
            'grupo_seccion_id' => $grupo->id,
            'dia_id' => $dia,
            'bloque_horario_id' => $bloque,
            'observaciones' => 'Horario ingresado manualmente',
            'estado' => true,
        ]);
        $horario->profesores()->create([
            'profesor_id' => $profesor->id,
            'es_principal' => true,
            'estado' => true,
        ]);
        if ($conSala) {
            $horario->asignacionesSalas()->create([
                'sala_id' => $this->sala->id,
                'capacidad_asignada' => 20,
                'es_principal' => true,
                'observaciones' => 'Sala elegida por coordinación',
                'estado' => true,
            ]);
        }

        return $horario;
    }

    private function rechazarGeneracion(): void
    {
        $versiones = Models\VersionPlanificacion::count();
        $horarios = Models\Horario::count();
        $salas = Models\AsignacionSala::count();
        $fuente = $this->fuente->fresh()->getAttributes();

        $this->post(route('programacion.semestral.generar'), [
            'version_base_id' => $this->fuente->id,
        ])->assertSessionHasErrors('generacion');

        $this->assertDatabaseCount('versiones_planificacion', $versiones);
        $this->assertDatabaseCount('horarios', $horarios);
        $this->assertDatabaseCount('asignaciones_salas', $salas);
        $this->assertSame($fuente, $this->fuente->fresh()->getAttributes());
    }

    public function test_completa_desde_el_ramo_dos_modulos_de_catedra_y_uno_de_laboratorio(): void
    {
        $actividad = $this->grupo->actividadSeccion;
        $actividad->update(['cantidad_horas' => 0]);
        $actividad->seccion->asignatura->update(['horas_catedra' => 2, 'horas_laboratorio' => 1]);
        $actividad->seccion->profesores()->create(['profesor_id' => $this->profesor->id, 'es_principal' => true, 'estado' => true]);
        $laboratorio = Models\Laboratorio::create([
            'sala_id' => $this->sala->id, 'codigo' => 'LAB-REFERENCIA', 'nombre' => 'Laboratorio de referencia',
            'capacidad' => 25, 'estado' => true, 'disponible' => true,
        ]);
        $this->aprobar();

        $nueva = $this->generar();

        $this->assertSame(2, (int) $actividad->fresh()->cantidad_horas);
        $this->assertSame(2, $actividad->seccion->actividades()->count());
        $lab = $actividad->seccion->actividades()->where('tipo', 'Laboratorio')->sole();
        $this->assertSame(1, (int) $lab->cantidad_horas);
        $this->assertSame(1, $lab->grupos()->count());
        $this->assertSame(2, $nueva->horarios()->where('actividad_seccion_id', $actividad->id)->count());
        $moduloLab = $nueva->horarios()->where('actividad_seccion_id', $lab->id)->sole();
        $this->assertSame($laboratorio->id, $moduloLab->asignacionesLaboratorios()->sole()->laboratorio_id);
        $this->assertSame(3, $nueva->horarios()->count());
        $this->assertSame(0, app(RevisionConflictosProgramacion::class)->revisar($nueva)['carga_observaciones']);
    }

    public function test_la_referencia_no_sobrescribe_una_carga_ya_definida_en_la_actividad(): void
    {
        $actividad = $this->grupo->actividadSeccion;
        $actividad->seccion->asignatura->update(['horas_catedra' => 4]);
        $this->aprobar();

        $nueva = $this->generar();

        $this->assertSame(2, (int) $actividad->fresh()->cantidad_horas);
        $this->assertSame(2, $nueva->horarios()->count());
    }

    public function test_una_seccion_sin_actividades_inicia_su_carga_completa_desde_el_ramo(): void
    {
        $profesor = $this->crearProfesor('98765432', 'Pedro');
        $ramo = Models\Asignatura::create([
            'universidad_id' => $this->periodo->universidad_id, 'codigo' => 'REDES',
            'nombre' => 'Redes', 'horas_catedra' => 2, 'horas_laboratorio' => 1, 'estado' => true,
        ]);
        $seccion = Models\Seccion::create([
            'periodo_academico_id' => $this->periodo->id, 'asignatura_id' => $ramo->id,
            'codigo' => '11', 'semestre' => 2, 'cupos' => 20, 'inscritos' => 20, 'estado_activo' => true,
        ]);
        $seccion->carreras()->attach($this->carrera->id);
        $seccion->profesores()->create(['profesor_id' => $profesor->id, 'estado' => true]);
        Models\Laboratorio::create([
            'sala_id' => $this->sala->id, 'codigo' => 'LAB-REDES', 'nombre' => 'Redes',
            'capacidad' => 25, 'estado' => true, 'disponible' => true,
        ]);
        $aula = $this->sala->replicate();
        $aula->codigo = 'AULA-02';
        $aula->save();
        $this->aprobar();
        $this->aprobar($profesor);

        $nueva = $this->generar();

        $this->assertSame(2, $seccion->actividades()->count());
        $this->assertSame(2, Models\GrupoSeccion::whereHas('actividadSeccion', fn ($query) => $query->where('seccion_id', $seccion->id))->count());
        $this->assertSame(5, $nueva->horarios()->count());
        $this->assertSame(0, app(RevisionConflictosProgramacion::class)->revisar($nueva)['carga_observaciones']);
    }

    public function test_un_fallo_de_generacion_revierte_la_preparacion_desde_las_horas_del_ramo(): void
    {
        $actividad = $this->grupo->actividadSeccion;
        $actividad->seccion->asignatura->update(['horas_catedra' => 2, 'horas_laboratorio' => 1]);
        $actividad->seccion->profesores()->create(['profesor_id' => $this->profesor->id, 'estado' => true]);
        $this->aprobar();

        $this->rechazarGeneracion();

        $this->assertSame(1, $actividad->seccion->actividades()->count());
        $this->assertSame(1, Models\GrupoSeccion::count());
    }

    public function test_no_reactiva_una_actividad_de_referencia_que_estaba_inactiva(): void
    {
        $actividad = $this->grupo->actividadSeccion;
        $actividad->update(['cantidad_horas' => 0]);
        $actividad->seccion->asignatura->update(['horas_catedra' => 2, 'horas_laboratorio' => 1]);
        $lab = $actividad->seccion->actividades()->create([
            'tipo' => 'Laboratorio', 'cantidad_horas' => 1, 'estado' => false,
        ]);

        $this->rechazarGeneracion();

        $this->assertFalse((bool) $lab->fresh()->estado);
        $this->assertSame(0, (int) $actividad->fresh()->cantidad_horas);
        $this->assertSame(2, $actividad->seccion->actividades()->count());
    }

    public function test_no_reactiva_grupos_inactivos_para_cumplir_una_referencia_del_ramo(): void
    {
        $this->grupo->actividadSeccion->seccion->asignatura->update(['horas_catedra' => 2]);
        $this->grupo->update(['estado_activo' => false]);

        $this->rechazarGeneracion();

        $this->assertFalse($this->grupo->fresh()->estado_activo);
        $this->assertSame(1, Models\GrupoSeccion::count());
    }

    public function test_no_cambia_la_oferta_de_un_periodo_cerrado_para_completar_la_referencia(): void
    {
        $this->grupo->actividadSeccion->seccion->asignatura->update(['horas_laboratorio' => 1]);
        $this->fuente->update(['estado' => 'Publicado']);

        $this->rechazarGeneracion();

        $this->assertSame('Publicado', $this->fuente->fresh()->estado);
        $this->assertSame(1, Models\ActividadSeccion::count());
    }

    public function test_la_ruta_principal_asigna_horarios_y_salas_a_toda_la_carga(): void
    {
        $this->aprobar();
        $antes = $this->fuente->fresh()->getAttributes();
        $nueva = $this->generar();

        $this->assertNotSame($this->fuente->id, $nueva->id);
        $this->assertSame('Borrador', $nueva->estado);
        $this->assertFalse((bool) $nueva->es_actual);
        $this->assertSame($antes, $this->fuente->fresh()->getAttributes());
        $this->assertSame(0, $this->fuente->horarios()->count());
        $this->assertSame(2, $nueva->horarios()->count());
        $this->assertSame(2, Models\AsignacionSala::whereHas('horario', fn ($h) => $h->where('version_planificacion_id', $nueva->id))->count());
        $reporte = app(RevisionConflictosProgramacion::class)->revisar($nueva);
        foreach (['conflictos', 'asignaciones', 'capacidad', 'recursos', 'disponibilidad', 'datos'] as $clave) {
            $this->assertSame(0, $reporte[$clave], $clave);
        }
        $this->assertSame(0, $reporte['carga']['pendientes']);
    }

    public function test_un_horario_manual_sin_sala_conserva_hora_docente_y_observaciones(): void
    {
        $manual = $this->manual();
        $antes = $manual->fresh()->getAttributes();
        $this->aprobar();
        $nueva = $this->generar();

        $copiado = $nueva->horarios()->where('dia_id', $manual->dia_id)
            ->where('bloque_horario_id', $manual->bloque_horario_id)->sole();
        $this->assertSame($antes, $manual->fresh()->getAttributes());
        $this->assertSame('Horario ingresado manualmente', $copiado->observaciones);
        $this->assertSame($this->profesor->id, $copiado->profesores()->sole()->profesor_id);
        $this->assertSame($this->sala->id, $copiado->asignacionesSalas()->sole()->sala_id);
        $this->assertSame(2, $nueva->horarios()->count());
        $this->assertSame(0, $manual->asignacionesSalas()->count());
    }

    public function test_la_sala_elegida_manualmente_se_conserva_en_el_borrador_nuevo(): void
    {
        $manual = $this->manual(conSala: true);
        $asignacion = $manual->asignacionesSalas()->sole();
        $otraSala = $this->sala->replicate();
        $otraSala->codigo = 'TH-02';
        $otraSala->capacidad = 20;
        $otraSala->save();
        $this->aprobar();

        $nueva = $this->generar();
        $copiado = $nueva->horarios()->where('dia_id', $manual->dia_id)
            ->where('bloque_horario_id', $manual->bloque_horario_id)->sole();
        $salaCopiada = $copiado->asignacionesSalas()->sole();

        $this->assertSame($asignacion->sala_id, $salaCopiada->sala_id);
        $this->assertSame($asignacion->capacidad_asignada, $salaCopiada->capacidad_asignada);
        $this->assertSame($asignacion->observaciones, $salaCopiada->observaciones);
        $this->assertSame(1, $manual->asignacionesSalas()->count());
    }

    public function test_varios_manuales_incompletos_conservan_sus_docentes_exclusivos_sin_usar_la_plantilla(): void
    {
        $pedro = $this->crearProfesor('98765432', 'Pedro');
        $marcela = $this->crearProfesor('65432198', 'Marcela');
        $primero = $this->manual(profesor: $pedro);
        $segundo = $this->manual(profesor: $marcela, slot: $this->slots()[1]);
        $this->aprobar($pedro, [$this->slots()[0]]);
        $this->aprobar($marcela, [$this->slots()[1]]);

        $nueva = $this->generar();
        foreach ([[$primero, $pedro], [$segundo, $marcela]] as [$original, $profesor]) {
            $copia = $nueva->horarios()->where('dia_id', $original->dia_id)
                ->where('bloque_horario_id', $original->bloque_horario_id)->sole();
            $this->assertSame($profesor->id, $copia->profesores()->sole()->profesor_id);
            $this->assertSame($this->sala->id, $copia->asignacionesSalas()->sole()->sala_id);
            $this->assertSame(0, $original->asignacionesSalas()->count());
        }
        $this->assertSame(0, Models\HorarioProfesor::where('profesor_id', $this->profesor->id)
            ->whereHas('horario', fn ($h) => $h->where('version_planificacion_id', $nueva->id))->count());
    }

    public function test_un_manual_con_aforo_referencial_se_revisa_con_los_grupos_activos(): void
    {
        $this->grupo->update(['cupos' => null, 'inscritos' => 0]);
        $inactivo = $this->grupo->replicate();
        $inactivo->codigo = '2';
        $inactivo->estado_activo = false;
        $inactivo->save();
        $manual = $this->manual(conSala: true);
        $this->aprobar();

        $nueva = $this->generar();
        $this->assertSame(2, $nueva->horarios()->count());
        $this->assertSame(1, $nueva->horarios()->where('dia_id', $manual->dia_id)
            ->where('bloque_horario_id', $manual->bloque_horario_id)->count());
        $this->assertSame(0, app(RevisionConflictosProgramacion::class)->revisar($nueva)['capacidad']);
    }

    public function test_todos_los_docentes_del_grupo_deben_compartir_la_hora_asignada(): void
    {
        $this->grupo->actividadSeccion->update(['cantidad_horas' => 1]);
        $otroProfesor = $this->crearProfesor('98765432', 'Pedro');
        $this->grupo->profesores()->create(['profesor_id' => $otroProfesor->id, 'estado' => true]);
        $this->aprobar();
        $this->aprobar($otroProfesor, [$this->slots()[1]]);

        $nueva = $this->generar();
        $horario = $nueva->horarios()->sole();
        [$dia, $bloque] = array_map('intval', explode(':', $this->slots()[1]));

        $this->assertSame($dia, $horario->dia_id);
        $this->assertSame($bloque, $horario->bloque_horario_id);
        $this->assertSame(2, $horario->profesores()->count());
    }

    public function test_la_disponibilidad_comun_insuficiente_revierte_toda_la_generacion(): void
    {
        $otroProfesor = $this->crearProfesor('98765432', 'Pedro');
        $this->grupo->profesores()->create(['profesor_id' => $otroProfesor->id, 'estado' => true]);
        $this->aprobar();
        $this->aprobar($otroProfesor, [$this->slots()[0]]);

        $this->rechazarGeneracion();
    }

    public function test_elige_la_duracion_del_modulo_disponible_sin_pedirla_en_el_formulario(): void
    {
        $this->grupo->actividadSeccion->update(['cantidad_horas' => 1]);
        $bloque = Models\BloqueHorario::create([
            'numero' => 20,
            'hora_inicio' => '07:00:00',
            'hora_fin' => '07:30:00',
            'es_almuerzo' => false,
            'estado' => true,
        ]);
        $dia = Models\Dia::where('numero', 1)->value('id');
        $this->aprobar(slots: [$dia.':'.$bloque->id]);

        $nueva = $this->generar();
        $this->assertSame($bloque->id, $nueva->horarios()->sole()->bloque_horario_id);
    }

    public function test_no_genera_sin_disponibilidad_aprobada(): void
    {
        $this->rechazarGeneracion();
    }

    public function test_una_sala_con_capacidad_insuficiente_no_se_asigna(): void
    {
        $this->sala->update(['capacidad' => 10]);
        $this->aprobar();

        $this->rechazarGeneracion();
    }

    public function test_un_laboratorio_se_reserva_junto_con_su_sala_fisica(): void
    {
        $this->grupo->actividadSeccion->update(['tipo' => 'Laboratorio']);
        $laboratorio = Models\Laboratorio::create([
            'sala_id' => $this->sala->id,
            'codigo' => 'LAB-1',
            'nombre' => 'Computación',
            'capacidad' => 25,
            'estado' => true,
            'disponible' => true,
        ]);
        $this->aprobar();

        $nueva = $this->generar();
        foreach ($nueva->horarios as $horario) {
            $this->assertSame($laboratorio->id, $horario->asignacionesLaboratorios()->sole()->laboratorio_id);
            $this->assertSame($this->sala->id, $horario->asignacionesSalas()->sole()->sala_id);
        }
        $this->assertSame(0, app(RevisionConflictosProgramacion::class)->revisar($nueva)['recursos']);
    }

    public function test_la_capacidad_del_laboratorio_tambien_debe_cubrir_el_grupo(): void
    {
        $this->grupo->actividadSeccion->update(['tipo' => 'Laboratorio']);
        Models\Laboratorio::create([
            'sala_id' => $this->sala->id,
            'codigo' => 'LAB-1',
            'nombre' => 'Computación',
            'capacidad' => 10,
            'estado' => true,
            'disponible' => true,
        ]);
        $this->aprobar();

        $this->rechazarGeneracion();
    }

    public function test_un_curso_de_mayor_prioridad_recibe_el_laboratorio_disputado(): void
    {
        $this->grupo->actividadSeccion->update(['cantidad_horas' => 1]);
        $laboratorio = Models\Laboratorio::create([
            'sala_id' => $this->sala->id,
            'codigo' => 'LAB-1',
            'nombre' => 'Computación',
            'capacidad' => 25,
            'estado' => true,
            'disponible' => true,
        ]);
        $aula = $this->sala->replicate();
        $aula->codigo = 'TH-02';
        $aula->nombre = 'Aula general';
        $aula->save();
        $otroProfesor = $this->crearProfesor('98765432', 'Pedro');
        $otroGrupo = $this->crearGrupo('GEN', 'Formación general', $otroProfesor, 1);

        foreach ([[$this->grupo, 1], [$otroGrupo, 2]] as [$grupo, $nivel]) {
            Models\PrioridadRecurso::create([
                'nombre' => 'Preferencia '.$nivel,
                'asignatura_id' => $grupo->actividadSeccion->seccion->asignatura_id,
                'tipo_recurso' => 'laboratorio',
                'clave_recurso' => 'laboratorio:todos',
                'nivel' => $nivel,
                'estado' => true,
            ]);
        }
        $this->aprobar(slots: [$this->slots()[0]]);
        $this->aprobar($otroProfesor, [$this->slots()[0]]);

        $nueva = $this->generar();
        $mayor = $nueva->horarios()->where('grupo_seccion_id', $this->grupo->id)->sole();
        $menor = $nueva->horarios()->where('grupo_seccion_id', $otroGrupo->id)->sole();

        $this->assertSame($laboratorio->id, $mayor->asignacionesLaboratorios()->sole()->laboratorio_id);
        $this->assertSame(0, $menor->asignacionesLaboratorios()->count());
        $this->assertSame($aula->id, $menor->asignacionesSalas()->sole()->sala_id);
        $this->assertSame(0, app(RevisionConflictosProgramacion::class)->revisar($nueva)['conflictos']);
    }

    public function test_el_equipamiento_requerido_debe_estar_en_el_espacio_asignado(): void
    {
        $equipo = Models\Equipamiento::create([
            'universidad_id' => $this->periodo->universidad_id,
            'nombre' => 'Computadores',
            'tipo' => 'Computación',
            'cantidad' => 20,
            'estado' => true,
            'disponible' => true,
        ]);
        $this->grupo->actividadSeccion->requerimientos()->create([
            'tipo_requerimiento' => 'Equipamiento',
            'equipamiento_id' => $equipo->id,
            'cantidad' => 20,
            'obligatorio' => true,
            'estado' => true,
        ]);
        $this->aprobar();
        $this->rechazarGeneracion();

        $equipo->ubicaciones()->create(['sala_id' => $this->sala->id, 'cantidad' => 20]);
        $nueva = $this->generar();
        $this->assertSame(0, app(RevisionConflictosProgramacion::class)->revisar($nueva)['recursos']);
    }

    public function test_dos_cursos_no_ocupan_la_misma_sala_en_un_intervalo(): void
    {
        $this->grupo->actividadSeccion->update(['cantidad_horas' => 1]);
        $otroProfesor = $this->crearProfesor('98765432', 'Pedro');
        $this->crearGrupo('OTRO', 'Otro curso', $otroProfesor, 1);
        $this->aprobar(slots: array_slice($this->slots(), 0, 2));
        $this->aprobar($otroProfesor, array_slice($this->slots(), 0, 2));

        $nueva = $this->generar();
        $this->assertSame(2, $nueva->horarios()->count());
        $slots = $nueva->horarios->map(fn ($h) => $h->dia_id.':'.$h->bloque_horario_id);
        $this->assertCount(2, $slots->unique());
        $this->assertSame(0, app(RevisionConflictosProgramacion::class)->revisar($nueva)['conflictos']);
    }

    public function test_conserva_un_horario_manual_fuera_de_disponibilidad_y_programa_el_resto_en_horas_aprobadas(): void
    {
        $manual = $this->manual(conSala: true);
        $antes = $manual->fresh()->getAttributes();
        $this->aprobar(slots: array_slice($this->slots(), 1));

        $nueva = $this->generar();

        $this->assertSame($antes, $manual->fresh()->getAttributes());
        $copiado = $nueva->horarios()->where('dia_id', $manual->dia_id)
            ->where('bloque_horario_id', $manual->bloque_horario_id)->sole();
        $this->assertSame($this->sala->id, $copiado->asignacionesSalas()->sole()->sala_id);
        $this->assertSame(2, $nueva->horarios()->count());
        $revision = app(RevisionConflictosProgramacion::class)->revisar($nueva);
        $this->assertSame(1, $revision['disponibilidad']);
        $this->assertSame($copiado->id, $revision['observaciones']->sole()['horarios'][0]['id']);
    }

    public function test_la_fuente_anterior_tambien_puede_completarse_sin_modificarla(): void
    {
        $this->fuente->update(['tipo' => 'anterior']);
        $manual = $this->manual();
        $this->aprobar();

        $nueva = $this->generar();
        $this->assertSame('semestral', $nueva->tipo);
        $this->assertSame('anterior', $this->fuente->fresh()->tipo);
        $this->assertSame(1, $this->fuente->horarios()->count());
        $this->assertSame(2, $nueva->horarios()->count());
        $this->assertSame(1, $nueva->horarios()->where('dia_id', $manual->dia_id)->where('bloque_horario_id', $manual->bloque_horario_id)->count());
    }

    public function test_un_modulo_importado_sin_grupo_se_vincula_solo_si_la_actividad_tiene_un_unico_grupo(): void
    {
        $this->fuente->update(['tipo' => 'anterior']);
        $manual = $this->manual();
        $manual->update(['grupo_seccion_id' => null]);
        $this->aprobar();

        $nueva = $this->generar();
        $this->assertNull($manual->fresh()->grupo_seccion_id);
        $this->assertSame(2, $nueva->horarios()->where('grupo_seccion_id', $this->grupo->id)->count());
        $this->assertSame(0, app(RevisionConflictosProgramacion::class)->revisar($nueva)['carga']['modulos_sin_grupo']);
    }

    public function test_una_fuente_publicada_se_copia_a_borrador_conservando_el_original(): void
    {
        $this->fuente->update(['estado' => 'Publicado']);
        $this->manual(conSala: true);
        $antes = $this->fuente->fresh()->getAttributes();
        $this->aprobar();

        $nueva = $this->generar();
        $this->assertSame('Borrador', $nueva->estado);
        $this->assertSame($antes, $this->fuente->fresh()->getAttributes());
        $this->assertSame(1, $this->fuente->horarios()->count());
    }

    public function test_un_periodo_inactivo_no_admite_generacion(): void
    {
        $this->aprobar();
        $this->periodo->update(['estado' => false]);

        $this->rechazarGeneracion();
    }

    public function test_la_oferta_completa_puede_generar_mas_de_cuarenta_grupos(): void
    {
        $this->grupo->actividadSeccion->update(['cantidad_horas' => 1]);
        $this->aprobar();
        $solicitudOriginal = Models\SolicitudDisponibilidad::latest('id')->firstOrFail();
        $respuestaOriginal = $solicitudOriginal->respuestas()->sole();
        $declaradas = Models\DisponibilidadProfesor::where('profesor_id', $this->profesor->id)->get();
        $disponibilidades = [];

        for ($numero = 1; $numero <= 40; $numero++) {
            $profesor = $this->crearProfesor((string) (20000000 + $numero), 'Docente '.$numero);
            $this->crearGrupo('RAMO-'.$numero, 'Ramo '.$numero, $profesor, 1);
            $solicitud = $solicitudOriginal->replicate();
            $solicitud->profesor_id = $profesor->id;
            $solicitud->save();
            $respuesta = $respuestaOriginal->replicate();
            $respuesta->profesor_id = $profesor->id;
            $respuesta->solicitud_disponibilidad_id = $solicitud->id;
            $respuesta->save();

            foreach ($declaradas as $declarada) {
                $disponibilidades[] = array_replace(
                    $declarada->only(['periodo_academico_id', 'dia_id', 'bloque_horario_id', 'tipo_disponibilidad', 'observaciones', 'estado']),
                    ['profesor_id' => $profesor->id, 'created_at' => now(), 'updated_at' => now()]
                );
            }
        }
        Models\DisponibilidadProfesor::insert($disponibilidades);
        for ($numero = 2; $numero <= 11; $numero++) {
            $sala = $this->sala->replicate();
            $sala->codigo = 'TH-'.$numero;
            $sala->save();
        }

        $nueva = $this->generar();
        $this->assertSame(41, $nueva->horarios()->count());
        $reporte = app(RevisionConflictosProgramacion::class)->revisar($nueva);
        $this->assertSame(0, $reporte['conflictos']);
        $this->assertSame(0, $reporte['carga']['pendientes']);
    }

    public function test_el_borrador_generado_puede_activarse_despues_de_la_revision(): void
    {
        $this->aprobar();
        $nueva = $this->generar();
        $this->assertFalse((bool) $nueva->es_actual);
        $this->assertTrue((bool) $this->fuente->fresh()->es_actual);
        $revision = app(\App\Services\ActivacionVersion::class)->revisar($nueva);
        $this->assertSame([], $revision['bloqueos']);

        $this->post(route('programacion.version.activar', $nueva), [
            'firma' => $revision['firma'],
            'confirmar' => 1,
        ])->assertSessionHasNoErrors()->assertRedirect();

        $this->assertTrue((bool) $nueva->fresh()->es_actual);
        $this->assertFalse((bool) $this->fuente->fresh()->es_actual);
        $this->assertSame(2, $nueva->horarios()->count());
    }

    public function test_repetir_la_misma_solicitud_no_duplica_versiones_ni_horarios(): void
    {
        $this->aprobar();
        $solicitud = (string) Str::uuid();
        $motor = app(GeneracionAutomatica::class);

        $primera = $motor->generar($this->fuente, $this->usuario->id, '127.0.0.1', $solicitud);
        $segunda = $motor->generar($this->fuente->fresh(), $this->usuario->id, '127.0.0.1', $solicitud);

        $this->assertSame($primera->id, $segunda->id);
        $this->assertDatabaseCount('versiones_planificacion', 2);
        $this->assertDatabaseCount('horarios', 2);
    }

    public function test_un_fallo_de_auditoria_revierte_el_borrador_completo(): void
    {
        $this->aprobar();
        $antes = $this->fuente->fresh()->getAttributes();
        Models\VersionPlanificacion::created(fn () => DB::statement('DROP TABLE historial_acciones'));

        try {
            app(GeneracionAutomatica::class)->generar($this->fuente, $this->usuario->id, null, (string) Str::uuid());
            $this->fail('Se esperaba un fallo de auditoría.');
        } catch (\Illuminate\Database\QueryException $error) {
            $this->assertStringContainsString('historial_acciones', $error->getMessage());
        } finally {
            Models\VersionPlanificacion::flushEventListeners();
        }

        $this->assertDatabaseCount('versiones_planificacion', 1);
        $this->assertDatabaseCount('horarios', 0);
        $this->assertDatabaseCount('asignaciones_salas', 0);
        $this->assertSame($antes, $this->fuente->fresh()->getAttributes());
    }

    public function test_el_endpoint_exige_permiso_de_generacion(): void
    {
        $this->usuario->revokePermissionTo('generar programacion semestral');
        $this->post(route('programacion.semestral.generar'), [
            'version_base_id' => $this->fuente->id,
        ])->assertRedirect();

        $this->assertDatabaseCount('versiones_planificacion', 1);
        $this->assertDatabaseCount('horarios', 0);
    }
}

<?php

namespace Tests\Feature;

use App\Models\ActividadSeccion;
use App\Models\AsignacionLaboratorio;
use App\Models\AsignacionLaboratorioMovil;
use App\Models\AsignacionSala;
use App\Models\Asignatura;
use App\Models\BloqueHorario;
use App\Models\GrupoProfesor;
use App\Models\GrupoSeccion;
use App\Models\Horario;
use App\Models\HorarioProfesor;
use App\Models\Laboratorio;
use App\Models\LaboratorioMovil;
use App\Models\PeriodoAcademico;
use App\Models\Profesor;
use App\Models\ProfesorVersion;
use App\Models\Sala;
use App\Models\Seccion;
use App\Models\SeccionProfesor;
use App\Models\Sede;
use App\Models\TipoSala;
use App\Models\User;
use App\Models\VersionPlanificacion;
use App\Services\RevisionConflictosProgramacion;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Permission;
use Tests\IsolatedDatabaseTestCase;

class RevisionConflictosProgramacionTest extends IsolatedDatabaseTestCase
{
    private int $universidad;
    private VersionPlanificacion $version;
    private Profesor $profesor;
    private Sala $sala;
    private int $secuencia = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->universidad = DB::table('universidades')->value('id');
        $user = User::create(['name' => 'Revisión académica', 'rut' => '71111111', 'email' => 'revision@example.test', 'password' => 'prueba']);
        foreach (['gestionar conflictos', 'ver programacion semestral'] as $permiso) $user->givePermissionTo(Permission::findOrCreate($permiso, 'web'));
        $this->actingAs($user);
        $periodo = PeriodoAcademico::create(['universidad_id' => $this->universidad, 'nombre' => '2026-2', 'anio' => 2026, 'numero_semestre' => 2]);
        $this->version = VersionPlanificacion::create(['periodo_academico_id' => $periodo->id, 'tipo' => 'semestral', 'nombre' => 'Semestral', 'version' => '1.0', 'es_actual' => true]);
        $this->profesor = Profesor::create(['universidad_id' => $this->universidad, 'nombre' => 'Ana', 'apellido' => 'Docente']);
        $sede = Sede::create(['universidad_id' => $this->universidad, 'nombre' => 'Sede de prueba']);
        $tipo = TipoSala::create(['nombre' => 'Aula']);
        $this->sala = Sala::create(['universidad_id' => $this->universidad, 'sede_id' => $sede->id, 'tipo_sala_id' => $tipo->id, 'codigo' => 'A-101']);
    }

    #[DataProvider('recursos')]
    public function test_detecta_topes_por_intervalos_reales_para_cada_tipo_de_asignacion(string $tipo): void
    {
        $a = $this->modulo('09:00:00', '10:00:00');
        $b = $this->modulo('09:30:00', '10:30:00');
        $this->compartir($a, $b, $tipo);
        $reporte = $this->reporte();
        $this->assertSame(2, $reporte['modulos']);
        $this->assertSame(1, $reporte['conflictos']);
        $fila = $reporte['observaciones']->where('tipo', 'conflicto')->sole();
        $this->assertContains($tipo, array_column($fila['recursos'], 'tipo'));
        $this->assertSame('09:30–10:00', $fila['intervalo']);
        $this->assertSame([$a->id, $b->id], array_column($fila['horarios'], 'id'));
    }

    public static function recursos(): array
    {
        return [['profesor'], ['curso'], ['sala'], ['laboratorio'], ['movil']];
    }

    public function test_no_confunde_limites_contiguos_ni_dias_distintos(): void
    {
        $a = $this->modulo('09:00:00', '10:00:00');
        $b = $this->modulo('10:00:00', '11:00:00');
        $c = $this->modulo('09:00:00', '10:00:00', ['dia_id' => DB::table('dias')->where('numero', 2)->value('id')]);
        foreach ([$a, $b, $c] as $h) HorarioProfesor::create(['horario_id' => $h->id, 'profesor_id' => $this->profesor->id]);
        $this->assertSame(0, $this->reporte()['conflictos']);
    }

    public function test_reconoce_segundos_y_solapamientos_contenidos(): void
    {
        $a = $this->modulo('09:00:10', '10:00:00');
        $b = $this->modulo('09:00:20', '09:00:40');
        $this->compartir($a, $b, 'profesor');
        $this->assertSame('09:00:20–09:00:40', $this->reporte()['observaciones']->where('tipo', 'conflicto')->sole()['intervalo']);
    }

    public function test_la_ocupacion_del_laboratorio_tambien_ocupa_la_sala_fisica(): void
    {
        $a = $this->modulo(); $b = $this->modulo();
        AsignacionSala::create(['horario_id' => $a->id, 'sala_id' => $this->sala->id]);
        $lab = Laboratorio::create(['sala_id' => $this->sala->id, 'nombre' => 'Laboratorio']);
        AsignacionLaboratorio::create(['horario_id' => $b->id, 'laboratorio_id' => $lab->id]);
        $this->assertSame(['sala'], array_column($this->reporte()['observaciones']->where('tipo', 'conflicto')->sole()['recursos'], 'tipo'));
    }

    public function test_agrupa_los_recursos_compartidos_sin_duplicar_el_cruce_ni_la_sala(): void
    {
        $a = $this->modulo(); $b = $this->modulo();
        foreach (['profesor', 'sala', 'laboratorio'] as $tipo) $this->compartir($a, $b, $tipo);
        $reporte = $this->reporte();
        $this->assertSame(1, $reporte['conflictos']);
        $this->assertEqualsCanonicalizing(['profesor', 'sala', 'laboratorio'], array_column($reporte['observaciones']->where('tipo', 'conflicto')->sole()['recursos'], 'tipo'));
    }

    public function test_ignora_asignaciones_y_modulos_inactivos_y_otras_versiones(): void
    {
        $a = $this->modulo(); $b = $this->modulo();
        $this->compartir($a, $b, 'profesor');
        $b->update(['estado' => false]);
        $this->assertSame(0, $this->reporte()['conflictos']);
        $otra = $this->version->replicate(); $otra->es_actual = false; $otra->version = '2.0'; $otra->save();
        $b->update(['estado' => true, 'version_planificacion_id' => $otra->id]);
        $this->assertSame(0, $this->reporte()['conflictos']);
        $b->update(['version_planificacion_id' => $this->version->id]);
        HorarioProfesor::where('horario_id', $b->id)->update(['estado' => false]);
        $this->assertSame(0, $this->reporte()['conflictos']);
    }

    public function test_los_docentes_directos_tienen_preferencia_sobre_los_del_grupo_y_seccion(): void
    {
        $a = $this->modulo(); $b = $this->modulo();
        $otro = Profesor::create(['universidad_id' => $this->universidad, 'nombre' => 'Otro', 'apellido' => 'Docente']);
        HorarioProfesor::create(['horario_id' => $a->id, 'profesor_id' => $this->profesor->id]);
        HorarioProfesor::create(['horario_id' => $b->id, 'profesor_id' => $otro->id]);
        GrupoProfesor::create(['grupo_seccion_id' => $b->grupo_seccion_id, 'profesor_id' => $this->profesor->id]);
        $this->assertSame(0, $this->reporte()['conflictos']);
        HorarioProfesor::where('horario_id', $b->id)->update(['estado' => false]);
        $this->assertSame(1, $this->reporte()['conflictos']);
        GrupoProfesor::where('grupo_seccion_id', $b->grupo_seccion_id)->update(['estado' => false]);
        SeccionProfesor::create(['seccion_id' => $b->actividadSeccion->seccion_id, 'profesor_id' => $this->profesor->id]);
        $this->assertSame(1, $this->reporte()['conflictos']);
    }

    public function test_un_intervalo_invalido_o_periodo_inconsistente_queda_por_revisar(): void
    {
        $this->modulo('10:00:00', '09:00:00');
        $otroPeriodo = PeriodoAcademico::create(['universidad_id' => $this->universidad, 'nombre' => '2025-2', 'anio' => 2025, 'numero_semestre' => 2]);
        $b = $this->modulo();
        $b->actividadSeccion->seccion->update(['periodo_academico_id' => $otroPeriodo->id]);
        $reporte = $this->reporte();
        $this->assertSame(2, $reporte['datos']);
        $this->assertSame(0, $reporte['conflictos']);
        $this->assertSame(2, $reporte['observaciones']->where('tipo', 'datos')->count());
    }

    public function test_usa_el_nombre_conservado_en_la_version_y_no_escribe_resultados_en_la_base(): void
    {
        $a = $this->modulo(); $b = $this->modulo(); $this->compartir($a, $b, 'profesor');
        ProfesorVersion::create(['version_planificacion_id' => $this->version->id, 'profesor_id' => $this->profesor->id, 'nombre' => 'Nombre histórico', 'apellido' => 'Conservado']);
        $tablas = ['horarios', 'horarios_profesores', 'versiones_planificacion', 'profesores', 'validaciones_horario', 'observaciones_validacion', 'historial_acciones'];
        $snapshot = fn () => collect($tablas)->mapWithKeys(fn ($tabla) => [$tabla => DB::table($tabla)->orderBy('id')->get()->toJson()])->all();
        $antes = $snapshot();
        $this->get($this->url())->assertOk()->assertSee('Nombre histórico Conservado')->assertSee('Revisión de conflictos');
        $this->assertSame($antes, $snapshot());
    }

    public function test_pagina_y_filtra_las_observaciones_conservando_el_contexto(): void
    {
        for ($n = 0; $n < 7; $n++) HorarioProfesor::create(['horario_id' => $this->modulo()->id, 'profesor_id' => $this->profesor->id]);
        $this->get($this->url(['tipo' => 'profesor']))->assertOk()->assertViewHas('observaciones', fn ($p) => $p->total() === 21 && $p->count() === 20)
            ->assertSee('Siguiente');
        $this->get($this->url(['tipo' => 'profesor', 'page' => 2]))->assertOk()->assertViewHas('observaciones', fn ($p) => $p->count() === 1 && str_contains($p->previousPageUrl(), 'tipo=profesor'));
        $this->get($this->url(['tipo' => 'sala']))->assertOk()->assertViewHas('observaciones', fn ($p) => $p->total() === 0)->assertViewHas('reporte', fn ($r) => $r['conflictos'] === 21);
        $this->get($this->url(['page' => 99]))->assertRedirect();
    }

    public function test_el_contexto_rechaza_versiones_de_otro_periodo_o_de_programacion_anterior(): void
    {
        $anterior = $this->version->replicate(); $anterior->tipo = 'anterior'; $anterior->save();
        $this->get($this->url(['version_id' => $anterior->id]))->assertRedirect()->assertSessionHasErrors('version_id');
        $otro = PeriodoAcademico::create(['universidad_id' => $this->universidad, 'nombre' => '2025-2', 'anio' => 2025, 'numero_semestre' => 2]);
        $this->get($this->url(['periodo_id' => $otro->id]))->assertRedirect()->assertSessionHasErrors('version_id');
        $this->get(route('programacion.validacion', ['periodo_id' => $otro->id]))->assertOk()->assertViewHas('reporte', null)->assertSee('Sin programación semestral para revisar');
    }

    public function test_no_presenta_una_version_vacia_como_una_validacion_completa(): void
    {
        $this->get($this->url())->assertOk()->assertSee('Esta versión no tiene módulos activos')->assertDontSee('Horario válido');
        $this->get($this->url(['tipo' => ['profesor']]))->assertRedirect()->assertSessionHasErrors('tipo');
    }

    public function test_se_exige_el_permiso_de_conflictos_y_el_acceso_se_muestra_en_programacion(): void
    {
        $this->get(route('programacion.semestral'))->assertOk()->assertSee(route('programacion.validacion'), false);
        auth()->user()->revokePermissionTo('gestionar conflictos');
        $this->get($this->url())->assertRedirect();
        $this->get(route('programacion.semestral'))->assertOk()->assertDontSee('Revisar conflictos');
        auth()->logout();
        $this->get($this->url())->assertRedirect(route('login'));
    }

    private function modulo(string $inicio = '09:00:00', string $fin = '10:00:00', array $cambios = []): Horario
    {
        $n = ++$this->secuencia;
        $asignatura = Asignatura::create(['universidad_id' => $this->universidad, 'codigo' => 'REV-'.$n, 'nombre' => 'Asignatura '.$n]);
        $seccion = Seccion::create(['asignatura_id' => $asignatura->id, 'periodo_academico_id' => $this->version->periodo_academico_id, 'codigo' => (string) $n]);
        $actividad = ActividadSeccion::create(['seccion_id' => $seccion->id, 'tipo' => 'Cátedra']);
        $grupo = GrupoSeccion::create(['actividad_seccion_id' => $actividad->id, 'codigo' => 'G:1']);
        $bloque = BloqueHorario::create(['numero' => 100 + $n, 'hora_inicio' => $inicio, 'hora_fin' => $fin]);
        return Horario::create(array_replace(['version_planificacion_id' => $this->version->id, 'actividad_seccion_id' => $actividad->id,
            'grupo_seccion_id' => $grupo->id, 'dia_id' => DB::table('dias')->where('numero', 1)->value('id'), 'bloque_horario_id' => $bloque->id], $cambios));
    }

    private function compartir(Horario $a, Horario $b, string $tipo): void
    {
        if ($tipo === 'curso') { $b->update(['actividad_seccion_id' => $a->actividad_seccion_id, 'grupo_seccion_id' => $a->grupo_seccion_id]); return; }
        if ($tipo === 'laboratorio') $lab = Laboratorio::create(['sala_id' => $this->sala->id, 'codigo' => 'LAB', 'nombre' => 'Laboratorio']);
        if ($tipo === 'movil') $movil = LaboratorioMovil::create(['universidad_id' => $this->universidad, 'sede_id' => $this->sala->sede_id, 'codigo' => 'MOV', 'nombre' => 'Laboratorio móvil']);
        foreach ([$a, $b] as $h) {
            match ($tipo) {
                'profesor' => HorarioProfesor::create(['horario_id' => $h->id, 'profesor_id' => $this->profesor->id]),
                'sala' => AsignacionSala::create(['horario_id' => $h->id, 'sala_id' => $this->sala->id]),
                'laboratorio' => AsignacionLaboratorio::create(['horario_id' => $h->id, 'laboratorio_id' => $lab->id]),
                'movil' => AsignacionLaboratorioMovil::create(['horario_id' => $h->id, 'laboratorio_movil_id' => $movil->id]),
            };
        }
    }

    private function reporte(): array { return app(RevisionConflictosProgramacion::class)->revisar($this->version); }
    private function url(array $filtros = []): string { return route('programacion.validacion', array_replace(['periodo_id' => $this->version->periodo_academico_id, 'version_id' => $this->version->id], $filtros)); }
}

<?php

namespace Tests\Feature;

use App\Models\ActividadSeccion;
use App\Models\AsignacionLaboratorio;
use App\Models\AsignacionLaboratorioMovil;
use App\Models\AsignacionSala;
use App\Models\Asignatura;
use App\Models\DisponibilidadProfesor;
use App\Models\GrupoSeccion;
use App\Models\Horario;
use App\Models\HorarioProfesor;
use App\Models\Laboratorio;
use App\Models\LaboratorioMovil;
use App\Models\PeriodoAcademico;
use App\Models\Profesor;
use App\Models\RequerimientoActividad;
use App\Models\Sala;
use App\Models\Seccion;
use App\Models\Sede;
use App\Models\TipoSala;
use App\Models\User;
use App\Models\VersionPlanificacion;
use App\Services\RevisionConflictosProgramacion;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Permission;
use Tests\IsolatedDatabaseTestCase;

class RevisionAsignacionesProgramacionTest extends IsolatedDatabaseTestCase
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
        $usuario = User::create(['name' => 'Revisión de recursos', 'rut' => '72222222', 'email' => 'capacidades@example.test', 'password' => 'prueba']);
        $usuario->givePermissionTo(Permission::findOrCreate('gestionar conflictos', 'web'));
        $this->actingAs($usuario);
        $periodo = PeriodoAcademico::create(['universidad_id' => $this->universidad, 'nombre' => '2026-2', 'anio' => 2026, 'numero_semestre' => 2]);
        $this->version = VersionPlanificacion::create(['periodo_academico_id' => $periodo->id, 'tipo' => 'semestral', 'nombre' => 'Revisión', 'version' => '1.0', 'es_actual' => true]);
        $this->profesor = Profesor::create(['universidad_id' => $this->universidad, 'nombre' => 'Ana', 'apellido' => 'Docente']);
        $sede = Sede::create(['universidad_id' => $this->universidad, 'nombre' => 'Sede']);
        $tipo = TipoSala::create(['nombre' => 'Aula']);
        $this->sala = Sala::create(['universidad_id' => $this->universidad, 'sede_id' => $sede->id, 'tipo_sala_id' => $tipo->id, 'codigo' => 'A-101', 'capacidad' => 40]);
    }

    #[DataProvider('tiposEspacio')]
    public function test_compara_la_capacidad_del_recurso_afectado_con_los_estudiantes_del_grupo(string $tipo): void
    {
        $h = $this->modulo();
        if ($tipo === 'sala') $this->sala->update(['capacidad' => 20]);
        elseif ($tipo === 'laboratorio') $this->laboratorio($h, 20);
        else $this->movil($h, 20);
        $reporte = $this->reporte();
        $this->assertSame(1, $reporte['capacidad']);
        $this->assertSame(0, $reporte['asignaciones']);
        $fila = $reporte['observaciones']->where('tipo', 'capacidad')->sole();
        $this->assertSame([$tipo], array_column($fila['recursos'], 'tipo'));
        $this->assertStringContainsString('capacidad 20; se requieren 30 plazas', $fila['mensaje']);
        $this->assertStringContainsString('inscritos del grupo', $fila['mensaje']);
    }

    public static function tiposEspacio(): array { return [['sala'], ['laboratorio'], ['movil']]; }

    public function test_una_capacidad_exacta_y_asignaciones_completas_no_generan_observaciones(): void
    {
        $h = $this->modulo(); $this->sala->update(['capacidad' => 30]);
        $this->laboratorio($h, 30); $this->movil($h, 30);
        $this->assertSame(0, $this->reporte()['capacidad']);
        $this->assertSame(0, $this->reporte()['asignaciones']);
        DisponibilidadProfesor::create(['profesor_id' => $this->profesor->id, 'periodo_academico_id' => $this->version->periodo_academico_id,
            'dia_id' => $h->dia_id, 'bloque_horario_id' => $h->bloque_horario_id]);
        RequerimientoActividad::create(['actividad_seccion_id' => $h->actividad_seccion_id, 'tipo_requerimiento' => 'Sala',
            'tipo_sala_id' => $this->sala->tipo_sala_id, 'obligatorio' => true]);
        $this->get($this->url())->assertOk()->assertSee('Sin observaciones detectadas');
    }

    public function test_agrupa_en_un_aviso_las_asignaciones_faltantes_del_modulo(): void
    {
        $this->modulo(docente: false, espacio: false);
        $reporte = $this->reporte();
        $this->assertSame(1, $reporte['asignaciones']);
        $this->assertSame(0, $reporte['capacidad']);
        $mensaje = $reporte['observaciones']->where('tipo', 'asignaciones')->sole()['mensaje'];
        $this->assertStringContainsString('Falta asignar un docente', $mensaje);
        $this->assertStringContainsString('Falta asignar una sala', $mensaje);
        $this->get($this->url(['tipo' => 'asignaciones']))->assertOk()->assertViewHas('observaciones', fn ($p) => $p->total() === 1);
    }

    public function test_un_laboratorio_movil_necesita_un_espacio_fisico(): void
    {
        $h = $this->modulo(espacio: false); $this->movil($h, 40);
        $reporte = $this->reporte();
        $this->assertSame(1, $reporte['asignaciones']);
        $this->assertSame(0, $reporte['capacidad']);
        $this->assertStringContainsString('necesita una sala física', $reporte['observaciones']->where('tipo', 'asignaciones')->sole()['mensaje']);
    }

    public function test_la_sala_del_laboratorio_cubre_la_asignacion_fisica(): void
    {
        $h = $this->modulo(espacio: false); $this->laboratorio($h, 40);
        $this->assertSame(0, $this->reporte()['asignaciones']);
        $this->assertSame(0, $this->reporte()['capacidad']);
        $this->sala->update(['capacidad' => 20]);
        $fila = $this->reporte()['observaciones']->where('tipo', 'capacidad')->sole();
        $this->assertSame(['sala'], array_column($fila['recursos'], 'tipo'));
    }

    public function test_capacidad_cero_y_estudiantes_desconocidos_quedan_pendientes(): void
    {
        $this->modulo(alumnos: 0); $this->sala->update(['capacidad' => 0]);
        $reporte = $this->reporte();
        $this->assertSame(1, $reporte['capacidad']);
        $mensaje = $reporte['observaciones']->where('tipo', 'capacidad')->sole()['mensaje'];
        $this->assertStringContainsString('Falta informar la cantidad', $mensaje);
        $this->assertStringContainsString('capacidad sin informar', $mensaje);
        $this->get($this->url())->assertOk()->assertDontSee('Sin observaciones detectadas');
    }

    public function test_los_inscritos_del_grupo_tienen_prioridad_sobre_cupos_y_totales_de_la_actividad(): void
    {
        $h = $this->modulo(alumnos: 20); $this->sala->update(['capacidad' => 25]);
        $h->grupoSeccion->update(['cupos' => 40]);
        $h->actividadSeccion->update(['cantidad_estudiantes' => 60]);
        $h->actividadSeccion->seccion->update(['inscritos' => 80]);
        $this->assertSame(0, $this->reporte()['capacidad']);
        $h->grupoSeccion->update(['inscritos' => 0, 'cupos' => 20]);
        $this->assertSame(0, $this->reporte()['capacidad']);
    }

    #[DataProvider('fuentesCantidad')]
    public function test_utiliza_la_cantidad_conocida_sin_inventar_estudiantes(string $fuente, string $origen): void
    {
        $h = $this->modulo(alumnos: 0); $this->sala->update(['capacidad' => 20]);
        match ($fuente) {
            'grupo' => $h->grupoSeccion->update(['cupos' => 30]),
            'actividad' => $h->actividadSeccion->update(['cantidad_estudiantes' => 30]),
            'inscritos' => $h->actividadSeccion->seccion->update(['inscritos' => 30]),
            'cupos' => $h->actividadSeccion->seccion->update(['cupos' => 30]),
        };
        $this->assertStringContainsString($origen, $this->reporte()['observaciones']->where('tipo', 'capacidad')->sole()['mensaje']);
    }

    public static function fuentesCantidad(): array
    {
        return [['grupo', 'cupos del grupo'], ['actividad', 'estudiantes de la actividad'], ['inscritos', 'inscritos de la sección'], ['cupos', 'cupos de la sección']];
    }

    public function test_no_asigna_automaticamente_a_un_grupo_los_estudiantes_de_toda_la_actividad(): void
    {
        $h = $this->modulo(alumnos: 0); $h->actividadSeccion->update(['cantidad_estudiantes' => 60]);
        $otro = GrupoSeccion::create(['actividad_seccion_id' => $h->actividad_seccion_id, 'codigo' => 'G:2', 'inscritos' => 30]);
        $mensaje = $this->reporte()['observaciones']->where('tipo', 'capacidad')->sole()['mensaje'];
        $this->assertStringContainsString('la actividad tiene varios grupos', $mensaje);
        $this->assertStringNotContainsString('se requieren 60 plazas', $mensaje);
        $otro->update(['estado_activo' => false]);
        $this->assertStringContainsString('se requieren 60 plazas', $this->reporte()['observaciones']->where('tipo', 'capacidad')->sole()['mensaje']);
    }

    public function test_una_distribucion_explicita_en_varias_salas_se_comprueba_por_sala(): void
    {
        $h = $this->modulo(); $this->sala->update(['capacidad' => 20]);
        AsignacionSala::where('horario_id', $h->id)->update(['capacidad_asignada' => 15]);
        $otra = $this->sala->replicate(); $otra->codigo = 'A-102'; $otra->save();
        $asignacion = AsignacionSala::create(['horario_id' => $h->id, 'sala_id' => $otra->id, 'capacidad_asignada' => 15]);
        $this->assertSame(0, $this->reporte()['capacidad']);
        $asignacion->update(['capacidad_asignada' => 10]);
        $mensaje = $this->reporte()['observaciones']->where('tipo', 'capacidad')->sole()['mensaje'];
        $this->assertStringContainsString('cubre 25 de las 30 plazas', $mensaje);
        $this->assertStringNotContainsString('capacidad 20; se requieren 30', $mensaje);
        $asignacion->update(['capacidad_asignada' => null]);
        $this->assertStringContainsString('Completa la capacidad asignada', $this->reporte()['observaciones']->where('tipo', 'capacidad')->sole()['mensaje']);
    }

    #[DataProvider('familiasDistribuidas')]
    public function test_los_laboratorios_distribuidos_no_reciben_cada_uno_la_carga_total(string $tipo): void
    {
        $h = $this->modulo();
        for ($n = 0; $n < 2; $n++) {
            if ($tipo === 'laboratorio') $this->laboratorio($h, 20, 15);
            else $this->movil($h, 20, 15);
        }
        $this->assertSame(0, $this->reporte()['capacidad']);
    }

    public static function familiasDistribuidas(): array { return [['laboratorio'], ['movil']]; }

    public function test_la_capacidad_asignada_tambien_debe_caber_en_el_espacio(): void
    {
        $h = $this->modulo(); $this->sala->update(['capacidad' => 35]);
        AsignacionSala::where('horario_id', $h->id)->update(['capacidad_asignada' => 40]);
        $this->assertStringContainsString('capacidad 35; se requieren 40', $this->reporte()['observaciones']->where('tipo', 'capacidad')->sole()['mensaje']);
    }

    public function test_los_filtros_solo_muestran_los_recursos_afectados(): void
    {
        $h = $this->modulo(); $this->movil($h, 20);
        RequerimientoActividad::create(['actividad_seccion_id' => $h->actividad_seccion_id, 'tipo_requerimiento' => 'Sala',
            'tipo_sala_id' => $this->sala->tipo_sala_id, 'obligatorio' => true]);
        $this->get($this->url(['tipo' => 'capacidad']))->assertOk()->assertViewHas('observaciones', fn ($p) => $p->total() === 1);
        $this->get($this->url(['tipo' => 'movil']))->assertOk()->assertViewHas('observaciones', fn ($p) => $p->total() === 1);
        $this->get($this->url(['tipo' => 'sala']))->assertOk()->assertViewHas('observaciones', fn ($p) => $p->total() === 0);
    }

    public function test_ignora_asignaciones_inactivas_y_otros_contextos(): void
    {
        $h = $this->modulo(); $this->movil($h, 10);
        AsignacionLaboratorioMovil::where('horario_id', $h->id)->update(['estado' => false]);
        $this->assertSame(0, $this->reporte()['capacidad']);
        HorarioProfesor::where('horario_id', $h->id)->update(['estado' => false]);
        AsignacionSala::where('horario_id', $h->id)->update(['estado' => false]);
        $this->assertSame(1, $this->reporte()['asignaciones']);
        $h->update(['estado' => false]);
        $this->assertSame(0, $this->reporte()['asignaciones']);
        $otra = $this->version->replicate(); $otra->es_actual = false; $otra->save();
        $h->update(['estado' => true, 'version_planificacion_id' => $otra->id]);
        $this->assertSame(0, $this->reporte()['modulos']);
    }

    public function test_la_consulta_conserva_todos_los_datos_de_asignaciones_y_capacidades(): void
    {
        $this->modulo(); $this->sala->update(['capacidad' => 20]);
        $tablas = ['salas', 'grupos_seccion', 'horarios', 'asignaciones_salas', 'horarios_profesores', 'validaciones_horario', 'historial_acciones'];
        $snapshot = fn () => collect($tablas)->mapWithKeys(fn ($tabla) => [$tabla => DB::table($tabla)->orderBy('id')->get()->toJson()])->all();
        $antes = $snapshot();
        $this->get($this->url(['tipo' => 'capacidad']))->assertOk()->assertSee('capacidad 20; se requieren 30 plazas');
        $this->assertSame($antes, $snapshot());
    }

    private function modulo(int $alumnos = 30, bool $docente = true, bool $espacio = true): Horario
    {
        $n = ++$this->secuencia;
        $asignatura = Asignatura::create(['universidad_id' => $this->universidad, 'codigo' => 'CAP-'.$n, 'nombre' => 'Asignatura '.$n]);
        $seccion = Seccion::create(['asignatura_id' => $asignatura->id, 'periodo_academico_id' => $this->version->periodo_academico_id, 'codigo' => (string) $n]);
        $actividad = ActividadSeccion::create(['seccion_id' => $seccion->id, 'tipo' => 'Cátedra']);
        $grupo = GrupoSeccion::create(['actividad_seccion_id' => $actividad->id, 'codigo' => 'G:1', 'inscritos' => $alumnos]);
        $h = Horario::create(['version_planificacion_id' => $this->version->id, 'actividad_seccion_id' => $actividad->id, 'grupo_seccion_id' => $grupo->id,
            'dia_id' => DB::table('dias')->where('numero', 1)->value('id'), 'bloque_horario_id' => DB::table('bloques_horarios')->where('numero', 1)->value('id')]);
        if ($docente) HorarioProfesor::create(['horario_id' => $h->id, 'profesor_id' => $this->profesor->id]);
        if ($espacio) AsignacionSala::create(['horario_id' => $h->id, 'sala_id' => $this->sala->id]);
        return $h;
    }

    private function laboratorio(Horario $h, int $capacidad, ?int $asignada = null): void
    {
        $lab = Laboratorio::create(['sala_id' => $this->sala->id, 'codigo' => 'LAB-'.(++$this->secuencia), 'nombre' => 'Laboratorio', 'capacidad' => $capacidad]);
        AsignacionLaboratorio::create(['horario_id' => $h->id, 'laboratorio_id' => $lab->id, 'capacidad_asignada' => $asignada]);
    }

    private function movil(Horario $h, int $capacidad, ?int $asignada = null): void
    {
        $movil = LaboratorioMovil::create(['universidad_id' => $this->universidad, 'sede_id' => $this->sala->sede_id, 'codigo' => 'MOV-'.(++$this->secuencia), 'nombre' => 'Laboratorio móvil', 'capacidad' => $capacidad]);
        AsignacionLaboratorioMovil::create(['horario_id' => $h->id, 'laboratorio_movil_id' => $movil->id, 'capacidad_asignada' => $asignada]);
    }

    private function reporte(): array { return app(RevisionConflictosProgramacion::class)->revisar($this->version); }
    private function url(array $filtros = []): string { return route('programacion.validacion', $filtros + ['periodo_id' => $this->version->periodo_academico_id, 'version_id' => $this->version->id]); }
}

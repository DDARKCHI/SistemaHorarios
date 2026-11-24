<?php

namespace Tests\Feature;

use App\Models\ActividadSeccion;
use App\Models\AsignacionSala;
use App\Models\Asignatura;
use App\Models\BloqueHorario;
use App\Models\DisponibilidadProfesor;
use App\Models\GrupoProfesor;
use App\Models\GrupoSeccion;
use App\Models\Horario;
use App\Models\HorarioProfesor;
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

class RevisionDisponibilidadProgramacionTest extends IsolatedDatabaseTestCase
{
    private VersionPlanificacion $version;
    private Profesor $profesor;
    private Sala $sala;
    private int $secuencia = 200;

    protected function setUp(): void
    {
        parent::setUp();
        $universidad = DB::table('universidades')->value('id');
        $usuario = User::create(['name' => 'Revisión docente', 'rut' => '73333333', 'email' => 'disponibilidad@example.test', 'password' => 'prueba']);
        $usuario->givePermissionTo(Permission::findOrCreate('gestionar conflictos', 'web'));
        $this->actingAs($usuario);
        $periodo = PeriodoAcademico::create(['universidad_id' => $universidad, 'nombre' => '2026-2', 'anio' => 2026, 'numero_semestre' => 2]);
        $this->version = VersionPlanificacion::create(['periodo_academico_id' => $periodo->id, 'tipo' => 'semestral', 'nombre' => 'Semestral', 'version' => '1.0', 'es_actual' => true]);
        $this->profesor = Profesor::create(['universidad_id' => $universidad, 'nombre' => 'Ana', 'apellido' => 'Docente']);
        $sede = Sede::create(['universidad_id' => $universidad, 'nombre' => 'Sede']);
        $tipo = TipoSala::create(['nombre' => 'Aula']);
        $this->sala = Sala::create(['universidad_id' => $universidad, 'sede_id' => $sede->id, 'tipo_sala_id' => $tipo->id, 'codigo' => 'D-101', 'capacidad' => 40]);
    }

    #[DataProvider('coberturas')]
    public function test_compara_intervalos_reales_y_respeta_vetos_y_limites(array $rangos, int $pendientes): void
    {
        $this->modulo();
        foreach ($rangos as $rango) $this->declarar(...$rango);
        $this->assertSame($pendientes, $this->reporte()['disponibilidad']);
    }

    public static function coberturas(): array
    {
        return [
            'mismas horas con otro identificador' => [[['09:00:00', '10:00:00']], 0],
            'intervalo mayor' => [[['08:00:00', '11:00:00']], 0],
            'union contigua' => [[['09:30:00', '10:00:00'], ['09:00:00', '09:30:00']], 0],
            'union superpuesta' => [[['09:00:00', '09:40:00'], ['09:30:00', '10:10:00']], 0],
            'hueco de un segundo' => [[['09:00:00', '09:30:00'], ['09:30:01', '10:00:00']], 1],
            'inicio sin cubrir' => [[['09:00:01', '10:00:00']], 1],
            'fin sin cubrir' => [[['09:00:00', '09:59:59']], 1],
            'bloques adyacentes fuera del modulo' => [[['08:00:00', '09:00:00'], ['10:00:00', '11:00:00']], 1],
            'veto superpuesto prevalece' => [[['08:00:00', '11:00:00'], ['09:30:00', '09:40:00', 'No disponible']], 1],
            'vetos adyacentes no afectan' => [[['09:00:00', '10:00:00'], ['08:00:00', '09:00:00', 'No disponible'], ['10:00:00', '11:00:00', 'No disponible']], 0],
            'normaliza el tipo declarado' => [[['09:00:00', '10:00:00', ' DISPONIBLE ']], 0],
        ];
    }

    public function test_sin_declaracion_no_se_presenta_como_disponible(): void
    {
        $this->modulo();
        $fila = $this->reporte()['observaciones']->where('tipo', 'disponibilidad')->sole();
        $this->assertStringContainsString('sin disponibilidad registrada para este período', $fila['mensaje']);
        $this->get($this->url())->assertOk()->assertDontSee('Sin observaciones detectadas')->assertDontSee('Horario válido');
    }

    public function test_solo_usa_declaraciones_activas_del_periodo_seleccionado(): void
    {
        $this->modulo();
        $this->declarar('09:00:00', '10:00:00', cambios: ['estado' => false]);
        $otro = PeriodoAcademico::create(['universidad_id' => $this->profesor->universidad_id, 'nombre' => '2025-2', 'anio' => 2025, 'numero_semestre' => 2]);
        $this->declarar('09:00:00', '10:00:00', cambios: ['periodo_academico_id' => $otro->id]);
        $this->assertStringContainsString('sin disponibilidad registrada', $this->reporte()['observaciones']->where('tipo', 'disponibilidad')->sole()['mensaje']);
        $vigente = $this->declarar('09:00:00', '10:00:00');
        $this->assertSame(0, $this->reporte()['disponibilidad']);
        $vigente->update(['dia_id' => DB::table('dias')->where('numero', 2)->value('id')]);
        $this->assertStringContainsString('queda fuera', $this->reporte()['observaciones']->where('tipo', 'disponibilidad')->sole()['mensaje']);
    }

    public function test_no_usa_la_disponibilidad_de_otro_profesor(): void
    {
        $this->modulo();
        $otro = $this->profesor->replicate(); $otro->nombre = 'Otro'; $otro->save();
        $this->declarar('09:00:00', '10:00:00', profesor: $otro);
        $this->assertSame(1, $this->reporte()['disponibilidad']);
    }

    #[DataProvider('declaracionesInvalidas')]
    public function test_las_declaraciones_incompletas_no_confirman_cobertura(string $inicio, string $fin, string $tipo): void
    {
        $this->modulo();
        $this->declarar('09:00:00', '10:00:00');
        $mala = $this->declarar($inicio, $fin, $tipo);
        $this->assertStringContainsString('datos incompletos o no reconocidos', $this->reporte()['observaciones']->where('tipo', 'disponibilidad')->sole()['mensaje']);
        $mala->update(['estado' => false]);
        $this->assertSame(0, $this->reporte()['disponibilidad']);
    }

    public static function declaracionesInvalidas(): array
    {
        return [['10:00:00', '09:00:00', 'Disponible'], ['25:00:00', '26:00:00', 'Disponible'], ['09:15:00', '09:45:00', 'Pendiente']];
    }

    public function test_los_datos_incompletos_de_otro_dia_no_afectan_la_cobertura(): void
    {
        $this->modulo();
        $this->declarar('09:00:00', '10:00:00');
        $this->declarar('10:00:00', '09:00:00', cambios: ['dia_id' => DB::table('dias')->where('numero', 2)->value('id')]);
        $this->assertSame(0, $this->reporte()['disponibilidad']);
    }

    public function test_no_duplica_aviso_de_disponibilidad_si_el_modulo_no_tiene_horas_validas(): void
    {
        $this->modulo('10:00:00', '09:00:00');
        $this->assertSame(1, $this->reporte()['datos']);
        $this->assertSame(0, $this->reporte()['disponibilidad']);
    }

    public function test_un_modulo_sin_docente_se_revisa_como_asignacion_incompleta(): void
    {
        $h = $this->modulo(); HorarioProfesor::where('horario_id', $h->id)->update(['estado' => false]);
        $this->assertSame(1, $this->reporte()['asignaciones']);
        $this->assertSame(0, $this->reporte()['disponibilidad']);
    }

    public function test_resuelve_docentes_del_modulo_grupo_y_seccion_con_la_misma_preferencia(): void
    {
        $h = $this->modulo();
        $otro = $this->profesor->replicate(); $otro->nombre = 'Otro'; $otro->save();
        $this->declarar('09:00:00', '10:00:00');
        $grupo = GrupoProfesor::create(['grupo_seccion_id' => $h->grupo_seccion_id, 'profesor_id' => $otro->id]);
        $this->assertSame(0, $this->reporte()['disponibilidad']);
        HorarioProfesor::where('horario_id', $h->id)->update(['estado' => false]);
        $this->assertSame(1, $this->reporte()['disponibilidad']);
        $grupo->update(['estado' => false]);
        SeccionProfesor::create(['seccion_id' => $h->actividadSeccion->seccion_id, 'profesor_id' => $this->profesor->id]);
        $this->assertSame(0, $this->reporte()['disponibilidad']);
    }

    public function test_revisa_todos_los_docentes_y_solo_señala_los_afectados(): void
    {
        $h = $this->modulo();
        $otro = $this->profesor->replicate(); $otro->nombre = 'Otro'; $otro->save();
        HorarioProfesor::create(['horario_id' => $h->id, 'profesor_id' => $otro->id]);
        $this->declarar('09:00:00', '10:00:00');
        ProfesorVersion::create(['version_planificacion_id' => $this->version->id, 'profesor_id' => $otro->id, 'nombre' => 'Nombre conservado', 'apellido' => 'Histórico']);
        $fila = $this->reporte()['observaciones']->where('tipo', 'disponibilidad')->sole();
        $this->assertSame([$otro->id], array_column($fila['recursos'], 'id'));
        $this->assertStringContainsString('Nombre conservado Histórico', $fila['mensaje']);
        $this->get($this->url(['tipo' => 'profesor']))->assertOk()->assertViewHas('observaciones', fn ($p) => $p->total() === 1);
        $this->get($this->url(['tipo' => 'sala']))->assertOk()->assertViewHas('observaciones', fn ($p) => $p->total() === 0);
    }

    public function test_ignora_modulos_inactivos_y_otros_contextos(): void
    {
        $h = $this->modulo(); $h->update(['estado' => false]);
        $this->assertSame(0, $this->reporte()['disponibilidad']);
        $otra = $this->version->replicate(); $otra->es_actual = false; $otra->save();
        $h->update(['estado' => true, 'version_planificacion_id' => $otra->id]);
        $this->assertSame(0, $this->reporte()['disponibilidad']);
    }

    public function test_pagina_y_filtra_disponibilidad_sin_modificar_los_datos(): void
    {
        for ($n = 0; $n < 21; $n++) $this->modulo();
        $tablas = ['horarios', 'horarios_profesores', 'disponibilidad_profesores', 'solicitudes_disponibilidad', 'respuestas_disponibilidad', 'validaciones_horario', 'historial_acciones'];
        $snapshot = fn () => collect($tablas)->mapWithKeys(fn ($tabla) => [$tabla => DB::table($tabla)->orderBy('id')->get()->toJson()])->all();
        $antes = $snapshot();
        $this->get($this->url(['tipo' => 'disponibilidad']))->assertOk()
            ->assertViewHas('observaciones', fn ($p) => $p->total() === 21 && $p->count() === 20)
            ->assertSee('¿Qué comprueba la revisión?');
        $this->get($this->url(['tipo' => 'disponibilidad', 'page' => 2]))->assertOk()
            ->assertViewHas('observaciones', fn ($p) => $p->count() === 1 && str_contains($p->previousPageUrl(), 'tipo=disponibilidad'));
        $this->assertSame($antes, $snapshot());
    }

    private function modulo(string $inicio = '09:00:00', string $fin = '10:00:00'): Horario
    {
        $n = ++$this->secuencia;
        $asignatura = Asignatura::create(['universidad_id' => $this->profesor->universidad_id, 'codigo' => 'DIS-'.$n, 'nombre' => 'Asignatura '.$n]);
        $seccion = Seccion::create(['asignatura_id' => $asignatura->id, 'periodo_academico_id' => $this->version->periodo_academico_id, 'codigo' => (string) $n]);
        $actividad = ActividadSeccion::create(['seccion_id' => $seccion->id, 'tipo' => 'Cátedra']);
        $grupo = GrupoSeccion::create(['actividad_seccion_id' => $actividad->id, 'codigo' => 'G:1', 'inscritos' => 25]);
        $bloque = BloqueHorario::create(['numero' => $n, 'hora_inicio' => $inicio, 'hora_fin' => $fin]);
        $h = Horario::create(['version_planificacion_id' => $this->version->id, 'actividad_seccion_id' => $actividad->id,
            'grupo_seccion_id' => $grupo->id, 'dia_id' => DB::table('dias')->where('numero', 1)->value('id'), 'bloque_horario_id' => $bloque->id]);
        HorarioProfesor::create(['horario_id' => $h->id, 'profesor_id' => $this->profesor->id]);
        AsignacionSala::create(['horario_id' => $h->id, 'sala_id' => $this->sala->id]);
        return $h;
    }

    private function declarar(string $inicio, string $fin, string $tipo = 'Disponible', array $cambios = [], ?Profesor $profesor = null): DisponibilidadProfesor
    {
        $bloque = BloqueHorario::create(['numero' => ++$this->secuencia, 'hora_inicio' => $inicio, 'hora_fin' => $fin]);
        return DisponibilidadProfesor::create(array_replace(['profesor_id' => ($profesor ?? $this->profesor)->id,
            'periodo_academico_id' => $this->version->periodo_academico_id, 'dia_id' => DB::table('dias')->where('numero', 1)->value('id'),
            'bloque_horario_id' => $bloque->id, 'tipo_disponibilidad' => $tipo], $cambios));
    }

    private function reporte(): array { return app(RevisionConflictosProgramacion::class)->revisar($this->version); }
    private function url(array $filtros = []): string { return route('programacion.validacion', $filtros + ['periodo_id' => $this->version->periodo_academico_id, 'version_id' => $this->version->id]); }
}

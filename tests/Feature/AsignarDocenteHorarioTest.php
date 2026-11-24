<?php

namespace Tests\Feature;

use App\Models;
use App\Services\AsignacionModulosHorario;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Permission;
use Tests\IsolatedDatabaseTestCase;

class AsignarDocenteHorarioTest extends IsolatedDatabaseTestCase
{
    private Models\PeriodoAcademico $periodo;
    private Models\VersionPlanificacion $version;
    private Models\GrupoSeccion $grupo;
    private Models\Horario $horario;
    private Models\Profesor $origen;
    private Models\Profesor $candidato;

    protected function setUp(): void
    {
        parent::setUp();
        $usuario = Models\User::create(['name' => 'Coordinación', 'rut' => '15555555', 'email' => 'docentes-horarios@example.test', 'password' => 'prueba']);
        $usuario->givePermissionTo(Permission::findOrCreate('asignar horarios', 'web'));
        $this->actingAs($usuario);
        $uni = DB::table('universidades')->value('id');
        $this->periodo = Models\PeriodoAcademico::create(['universidad_id' => $uni, 'nombre' => '2026-2', 'anio' => 2026, 'numero_semestre' => 2]);
        $this->version = Models\VersionPlanificacion::create(['periodo_academico_id' => $this->periodo->id, 'tipo' => 'semestral', 'nombre' => 'Semestral', 'version' => '1.0', 'estado' => 'Borrador', 'es_actual' => true]);
        $asignatura = Models\Asignatura::create(['universidad_id' => $uni, 'codigo' => 'ENF-01', 'nombre' => 'Enfermería', 'estado' => true]);
        $seccion = Models\Seccion::create(['periodo_academico_id' => $this->periodo->id, 'asignatura_id' => $asignatura->id, 'codigo' => '11', 'semestre' => 2]);
        $actividad = Models\ActividadSeccion::create(['seccion_id' => $seccion->id, 'tipo' => 'Cátedra', 'cantidad_horas' => 1]);
        $this->grupo = Models\GrupoSeccion::create(['actividad_seccion_id' => $actividad->id, 'codigo' => 'G:1']);
        $this->origen = Models\Profesor::create(['universidad_id' => $uni, 'nombre' => 'Docente', 'apellido' => 'Actual']);
        $this->candidato = Models\Profesor::create(['universidad_id' => $uni, 'nombre' => 'Nuevo', 'apellido' => 'Docente']);
        $this->horario = Models\Horario::create(['actividad_seccion_id' => $actividad->id, 'grupo_seccion_id' => $this->grupo->id,
            'version_planificacion_id' => $this->version->id, 'dia_id' => DB::table('dias')->where('numero', 1)->value('id'),
            'bloque_horario_id' => DB::table('bloques_horarios')->where('numero', 1)->value('id'), 'estado' => true]);
        Models\HorarioProfesor::create(['horario_id' => $this->horario->id, 'profesor_id' => $this->origen->id,
            'tipo_participacion' => 'PROFESOR RESPONSABLE', 'es_principal' => true, 'estado' => true]);
    }

    public function test_puede_asignar_en_un_grupo_completo_sin_docentes_de_grupo_y_sin_declaracion(): void
    {
        $this->get($this->pantalla())->assertOk()->assertSee('Asignar docente')->assertSee('Nuevo Docente')
            ->assertSee('data-asignar-docente', false)->assertDontSee('action="'.e(route('programacion.asignacion.horarios.guardar')).'"', false);
        $antes = $this->horario->fresh()->getAttributes();
        $this->put($this->url(), $this->datos())->assertSessionHasNoErrors()->assertRedirect($this->pantalla())
            ->assertSessionHas('success', fn ($texto) => str_contains($texto, 'no ha informado disponibilidad'));
        $this->assertSame($antes, $this->horario->fresh()->getAttributes());
        $this->assertSame([$this->candidato->id], $this->horario->profesores()->where('estado', true)->pluck('profesor_id')->all());
        $this->assertDatabaseCount('horarios', 1);
        $this->assertDatabaseCount('grupos_profesores', 0);
        $log = json_decode(DB::table('historial_acciones')->sole()->datos, true);
        $this->assertSame([$this->candidato->id], $log['profesores_sin_declaracion']);
    }

    public function test_añadir_conserva_al_actual_y_guardar_de_nuevo_no_duplica_la_asignacion(): void
    {
        $this->put($this->url(), $this->datos(['accion_docente' => 'agregar']))->assertSessionHasNoErrors();
        $this->assertSame([$this->origen->id, $this->candidato->id], $this->horario->profesores()->where('estado', true)->orderBy('id')->pluck('profesor_id')->all());
        $this->put($this->url(), $this->datos(['accion_docente' => 'agregar']))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('horarios_profesores', 2);
        $this->assertDatabaseCount('historial_acciones', 1);
    }

    public function test_los_docentes_directos_del_grupo_tambien_pueden_cubrir_modulos_pendientes(): void
    {
        $this->grupo->actividadSeccion->update(['cantidad_horas' => 2]);
        $this->get($this->pantalla())->assertOk()->assertSee('action="'.e(route('programacion.asignacion.horarios.guardar')).'"', false);
        $this->post(route('programacion.asignacion.horarios.guardar'), ['version_id' => $this->version->id, 'grupo_seccion_id' => $this->grupo->id,
            'profesores_ids' => [$this->origen->id], 'dia_id' => DB::table('dias')->where('numero', 2)->value('id'),
            'bloques_horarios' => [$this->horario->bloque_horario_id]])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('horarios', 2);
        $this->assertDatabaseCount('grupos_profesores', 0);
    }

    #[DataProvider('docenteHeredado')]
    public function test_añadir_conserva_los_docentes_heredados_del_grupo_sin_cambiar_otros_modulos(bool $elegirActual): void
    {
        $this->horario->profesores()->delete();
        Models\GrupoProfesor::create(['grupo_seccion_id' => $this->grupo->id, 'profesor_id' => $this->origen->id,
            'tipo_participacion' => 'PROFESOR RESPONSABLE', 'es_principal' => true, 'estado' => true]);
        $this->put($this->url(), $this->datos(['accion_docente' => 'agregar', 'profesor_id' => $elegirActual ? $this->origen->id : $this->candidato->id]))->assertSessionHasNoErrors();
        $this->assertSame($elegirActual ? [$this->origen->id] : [$this->origen->id, $this->candidato->id], $this->horario->profesores()->where('estado', true)->orderBy('id')->pluck('profesor_id')->all());
        $this->assertDatabaseCount('grupos_profesores', 1);
        $this->assertDatabaseHas('horarios_profesores', ['profesor_id' => $this->origen->id, 'es_principal' => true, 'estado' => true]);
    }

    public static function docenteHeredado(): array { return [[false], [true]]; }

    public function test_rechaza_un_tope_real_del_profesor_con_otro_identificador_de_bloque(): void
    {
        $bloque = $this->horario->bloqueHorario->replicate(); $bloque->numero = 190; $bloque->save();
        $ocupado = $this->horario->replicate(); $ocupado->bloque_horario_id = $bloque->id; $ocupado->save();
        Models\HorarioProfesor::create(['horario_id' => $ocupado->id, 'profesor_id' => $this->candidato->id, 'estado' => true]);
        $this->put($this->url(), $this->datos())->assertSessionHasErrors('profesor_id');
        $this->assertDatabaseHas('horarios_profesores', ['horario_id' => $this->horario->id, 'profesor_id' => $this->origen->id, 'estado' => true]);
        $this->assertDatabaseCount('historial_acciones', 0);
    }

    #[DataProvider('declaraciones')]
    public function test_respeta_las_declaraciones_existentes(string $tipo, bool $mismoDia, bool $permite): void
    {
        Models\DisponibilidadProfesor::create(['profesor_id' => $this->candidato->id, 'periodo_academico_id' => $this->periodo->id,
            'dia_id' => $mismoDia ? $this->horario->dia_id : DB::table('dias')->where('numero', 2)->value('id'),
            'bloque_horario_id' => $this->horario->bloque_horario_id, 'tipo_disponibilidad' => $tipo, 'estado' => true]);
        $respuesta = $this->put($this->url(), $this->datos());
        if ($permite) $respuesta->assertSessionHasNoErrors();
        else $respuesta->assertSessionHasErrors('profesor_id');
        $this->assertDatabaseCount('historial_acciones', $permite ? 1 : 0);
    }

    public static function declaraciones(): array { return [['Disponible', true, true], ['No disponible', true, false], ['Disponible', false, false]]; }

    public function test_no_sobrescribe_un_cambio_concurrente_y_no_edita_una_version_cerrada(): void
    {
        $datos = $this->datos();
        $this->horario->profesores()->update(['observaciones' => 'Cambio posterior']);
        $this->put($this->url(), $datos)->assertSessionHasErrors('horario');
        $this->version->update(['estado' => 'Publicada']);
        $this->put($this->url(), $this->datos())->assertSessionHasErrors('version_id');
        $this->assertDatabaseCount('historial_acciones', 0);
    }

    public function test_rechaza_docentes_inactivos_o_de_otra_institucion_y_respeta_permisos(): void
    {
        $this->candidato->update(['estado' => false]);
        $this->put($this->url(), $this->datos())->assertSessionHasErrors('profesor_id');
        $otra = Models\Universidad::create(['nombre' => 'Otra universidad', 'sigla' => 'OTRA']);
        $this->candidato->update(['estado' => true, 'universidad_id' => $otra->id]);
        $this->put($this->url(), $this->datos())->assertSessionHasErrors('profesor_id');
        auth()->user()->revokePermissionTo('asignar horarios');
        $this->put($this->url(), $this->datos())->assertRedirect();
        $this->assertDatabaseCount('historial_acciones', 0);
    }

    public function test_un_fallo_del_historial_revierte_la_sustitucion_completa(): void
    {
        $this->withoutExceptionHandling();
        Models\HorarioProfesor::created(fn () => DB::statement('DROP TABLE historial_acciones'));
        try { $this->put($this->url(), $this->datos()); $this->fail('La auditoría debe ser atómica.'); }
        catch (\Illuminate\Database\QueryException $e) { $this->assertStringContainsString('historial_acciones', $e->getMessage()); }
        $this->assertDatabaseHas('horarios_profesores', ['horario_id' => $this->horario->id, 'profesor_id' => $this->origen->id, 'estado' => true]);
        $this->assertDatabaseCount('horarios_profesores', 1);
    }

    private function datos(array $cambios = []): array
    {
        return array_replace(['version_id' => $this->version->id, 'profesor_id' => $this->candidato->id, 'accion_docente' => 'reemplazar',
            'huella_profesores' => AsignacionModulosHorario::firmaDocentes($this->horario->profesores()->where('estado', true)->get())], $cambios);
    }

    private function url(): string { return route('programacion.asignacion.horarios.profesor', $this->horario); }
    private function pantalla(): string { return route('programacion.asignacion.horarios', ['periodo_id' => $this->periodo->id, 'seccion_id' => $this->grupo->actividadSeccion->seccion_id]); }
}

<?php

namespace Tests\Feature;

use App\Models;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Permission;
use Tests\IsolatedDatabaseTestCase;

class AsignacionModulosHorarioTest extends IsolatedDatabaseTestCase
{
    private Models\PeriodoAcademico $periodo;
    private Models\VersionPlanificacion $version;
    private Models\ActividadSeccion $actividad;
    private Models\GrupoSeccion $grupo;
    private Models\Profesor $profesor;
    private Models\GrupoProfesor $asignacion;
    private Models\BloqueHorario $bloque;
    private int $dia;
    private int $secuencia = 180;

    protected function setUp(): void
    {
        parent::setUp();
        $usuario = Models\User::create(['name' => 'Coordinación', 'rut' => '76666666', 'email' => 'modulos@example.test', 'password' => 'prueba']);
        foreach (['asignar horarios', 'ver programacion semestral'] as $permiso) $usuario->givePermissionTo(Permission::findOrCreate($permiso, 'web'));
        $this->actingAs($usuario);
        $uni = DB::table('universidades')->value('id');
        $this->periodo = Models\PeriodoAcademico::create(['universidad_id' => $uni, 'nombre' => '2026-2', 'anio' => 2026, 'numero_semestre' => 2]);
        $this->version = Models\VersionPlanificacion::create(['periodo_academico_id' => $this->periodo->id, 'tipo' => 'semestral', 'nombre' => 'Semestral', 'version' => '1.0', 'estado' => 'Borrador', 'es_actual' => true]);
        $asignatura = Models\Asignatura::create(['universidad_id' => $uni, 'codigo' => 'ASG-1', 'nombre' => 'Programación']);
        $seccion = Models\Seccion::create(['periodo_academico_id' => $this->periodo->id, 'asignatura_id' => $asignatura->id, 'codigo' => '11']);
        $this->actividad = Models\ActividadSeccion::create(['seccion_id' => $seccion->id, 'tipo' => 'Cátedra', 'cantidad_horas' => 4]);
        $this->grupo = Models\GrupoSeccion::create(['actividad_seccion_id' => $this->actividad->id, 'codigo' => 'G:1']);
        $this->profesor = Models\Profesor::create(['universidad_id' => $uni, 'nombre' => 'Ana María', 'apellido' => 'Pérez']);
        $this->asignacion = Models\GrupoProfesor::create(['grupo_seccion_id' => $this->grupo->id, 'profesor_id' => $this->profesor->id,
            'tipo_participacion' => 'Responsable', 'es_principal' => true, 'observaciones' => 'Responsable de cátedra']);
        $this->bloque = $this->bloque('09:00:00', '10:00:00');
        $this->dia = DB::table('dias')->where('numero', 1)->value('id');
    }

    public function test_guarda_modulos_contiguos_con_los_docentes_elegidos_y_un_historial_atomico(): void
    {
        $otro = $this->bloque('10:00:00', '11:00:00');
        $this->post($this->guardar(), $this->datos(['bloques_horarios' => [$this->bloque->id, $otro->id], 'observaciones' => 'Trabajo práctico']))
            ->assertRedirect($this->pantalla())->assertSessionHas('success', '2 módulos horarios asignados correctamente.');
        $this->assertDatabaseCount('horarios', 2); $this->assertDatabaseCount('horarios_profesores', 2);
        $this->assertDatabaseHas('horarios_profesores', ['profesor_id' => $this->profesor->id, 'tipo_participacion' => 'Responsable', 'es_principal' => true, 'observaciones' => 'Responsable de cátedra']);
        $log = DB::table('historial_acciones')->sole(); $datos = json_decode($log->datos, true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('Asignar módulos', $log->accion);
        $this->assertCount(2, $datos['horarios_ids']);
        $this->assertSame([$this->profesor->id], $datos['profesores_sin_declaracion']);
        $this->assertSame(['09:00:00', '10:00:00'], array_column($datos['bloques'], 'hora_inicio'));
    }

    #[DataProvider('superposiciones')]
    public function test_rechaza_topes_del_docente_con_bloques_de_distinto_identificador(string $inicio, string $fin, bool $conflicto): void
    {
        $this->existente($this->bloque($inicio, $fin));
        $antes = DB::table('horarios')->count();
        $respuesta = $this->post($this->guardar(), $this->datos());
        if ($conflicto) {
            $respuesta->assertSessionHasErrors('horario');
            $this->assertDatabaseCount('horarios', $antes); $this->assertDatabaseCount('historial_acciones', 0);
        } else {
            $respuesta->assertSessionHasNoErrors(); $this->assertDatabaseCount('horarios', $antes + 1);
        }
    }

    public static function superposiciones(): array
    {
        return [['08:30:00', '09:00:00', false], ['10:00:00', '11:00:00', false], ['08:30:00', '09:00:01', true],
            ['09:59:59', '11:00:00', true], ['09:10:00', '09:20:00', true], ['08:00:00', '11:00:00', true], ['09:00:00', '10:00:00', true]];
    }

    public function test_no_permite_modulos_superpuestos_en_la_misma_solicitud(): void
    {
        $otro = $this->bloque('09:30:00', '10:30:00');
        $this->post($this->guardar(), $this->datos(['bloques_horarios' => [$this->bloque->id, $otro->id]]))->assertSessionHasErrors('bloques_horarios');
        $this->assertDatabaseCount('horarios', 0); $this->assertDatabaseCount('historial_acciones', 0);
    }

    public function test_solo_asigna_los_docentes_elegidos_y_rechaza_los_de_otra_institucion(): void
    {
        $otro = Models\Profesor::create(['universidad_id' => $this->periodo->universidad_id, 'nombre' => 'Beatriz', 'apellido' => 'López']);
        Models\GrupoProfesor::create(['grupo_seccion_id' => $this->grupo->id, 'profesor_id' => $otro->id, 'tipo_participacion' => 'Colaboradora', 'es_principal' => false]);
        $this->existente($this->bloque); // El docente no elegido está ocupado, pero Beatriz puede cubrir el nuevo módulo.
        $this->post($this->guardar(), $this->datos(['profesores_ids' => [$otro->id]]))->assertSessionHasNoErrors();
        $nuevo = Models\Horario::where('grupo_seccion_id', $this->grupo->id)->sole();
        $this->assertSame([$otro->id], $nuevo->profesores->pluck('profesor_id')->all());
        $universidad = Models\Universidad::first()->replicate(); $universidad->nombre = 'Otra institución'; $universidad->sigla = 'OTR'; $universidad->save();
        $otro->update(['universidad_id' => $universidad->id]);
        $this->post($this->guardar(), $this->datos(['profesores_ids' => [$otro->id], 'dia_id' => DB::table('dias')->where('numero', 2)->value('id')]))->assertSessionHasErrors('profesores_ids');
        $this->assertDatabaseCount('horarios', 2); $this->assertDatabaseCount('historial_acciones', 1);
    }

    public function test_un_horario_existente_con_horas_invalidas_no_se_trata_como_un_intervalo_libre(): void
    {
        $this->existente($this->bloque('10:00:00', '09:00:00'));
        $this->post($this->guardar(), $this->datos())->assertSessionHasErrors('horario');
        $this->assertDatabaseCount('horarios', 1); $this->assertDatabaseCount('historial_acciones', 0);
    }

    public function test_la_seccion_no_puede_tener_otra_actividad_simultanea_aunque_use_otro_profesor(): void
    {
        $otro = Models\Profesor::create(['universidad_id' => $this->periodo->universidad_id, 'nombre' => 'Otro', 'apellido' => 'Docente']);
        $h = $this->existente($this->bloque('09:30:00', '10:30:00'), $otro);
        $h->actividadSeccion->update(['seccion_id' => $this->actividad->seccion_id]);
        $this->post($this->guardar(), $this->datos())->assertSessionHasErrors('horario');
        $this->assertDatabaseCount('horarios', 1);
    }

    public function test_la_asignacion_directa_tiene_preferencia_sobre_los_docentes_del_grupo_y_de_la_seccion(): void
    {
        $otro = Models\Profesor::create(['universidad_id' => $this->periodo->universidad_id, 'nombre' => 'Otro', 'apellido' => 'Docente']);
        $h = $this->existente($this->bloque, $otro);
        Models\GrupoProfesor::create(['grupo_seccion_id' => $h->grupo_seccion_id, 'profesor_id' => $this->profesor->id]);
        Models\SeccionProfesor::create(['seccion_id' => $h->actividadSeccion->seccion_id, 'profesor_id' => $this->profesor->id]);
        $this->post($this->guardar(), $this->datos())->assertSessionHasNoErrors();
        $this->assertDatabaseCount('horarios', 2);
    }

    #[DataProvider('origenDocente')]
    public function test_considera_docentes_del_grupo_o_de_la_seccion_cuando_no_hay_asignacion_directa(string $origen): void
    {
        $h = $this->existente($this->bloque);
        $h->profesores()->delete();
        if ($origen === 'grupo') Models\GrupoProfesor::create(['grupo_seccion_id' => $h->grupo_seccion_id, 'profesor_id' => $this->profesor->id]);
        else Models\SeccionProfesor::create(['seccion_id' => $h->actividadSeccion->seccion_id, 'profesor_id' => $this->profesor->id]);
        $this->post($this->guardar(), $this->datos())->assertSessionHasErrors('horario');
        $this->assertDatabaseCount('horarios', 1);
    }

    public static function origenDocente(): array { return [['grupo'], ['seccion']]; }

    #[DataProvider('ignorar')]
    public function test_no_confunde_horarios_inactivos_otros_dias_versiones_ni_asignaciones_inactivas(string $caso): void
    {
        $h = $this->existente($this->bloque);
        match ($caso) {
            'horario' => $h->update(['estado' => false]),
            'dia' => $h->update(['dia_id' => DB::table('dias')->where('numero', 2)->value('id')]),
            'docente' => $h->profesores()->update(['estado' => false]),
            'version' => (function () use ($h) { $v = $this->version->replicate(); $v->es_actual = false; $v->save(); $h->update(['version_planificacion_id' => $v->id]); })(),
        };
        $this->post($this->guardar(), $this->datos())->assertSessionHasNoErrors();
        $this->assertDatabaseCount('horarios', 2);
    }

    public static function ignorar(): array { return [['horario'], ['dia'], ['docente'], ['version']]; }

    #[DataProvider('disponibilidades')]
    public function test_la_cobertura_de_disponibilidad_usa_intervalos_reales_y_respeta_vetos(array $rangos, bool $permitido): void
    {
        foreach ($rangos as [$inicio, $fin, $tipo]) $this->declarar($inicio, $fin, $tipo);
        $respuesta = $this->post($this->guardar(), $this->datos());
        if ($permitido) $respuesta->assertSessionHasNoErrors();
        else $respuesta->assertSessionHasErrors('horario');
        $this->assertDatabaseCount('horarios', $permitido ? 1 : 0);
        $this->assertDatabaseCount('historial_acciones', $permitido ? 1 : 0);
    }

    public static function disponibilidades(): array
    {
        return [
            'otro identificador' => [[['09:00:00', '10:00:00', 'Disponible']], true],
            'union contigua' => [[['09:00:00', '09:30:00', 'Disponible'], ['09:30:00', '10:00:00', 'Disponible']], true],
            'union superpuesta' => [[['08:00:00', '09:40:00', 'Disponible'], ['09:30:00', '11:00:00', 'Disponible']], true],
            'hueco de un segundo' => [[['09:00:00', '09:30:00', 'Disponible'], ['09:30:01', '10:00:00', 'Disponible']], false],
            'veto prevalece' => [[['08:00:00', '11:00:00', 'Disponible'], ['09:10:00', '09:20:00', 'No disponible']], false],
            'veto contiguo' => [[['09:00:00', '10:00:00', 'Disponible'], ['10:00:00', '11:00:00', 'No disponible']], true],
            'dato sin reconocer' => [[['08:00:00', '11:00:00', 'Disponible'], ['09:10:00', '09:20:00', 'Por confirmar']], false],
            'intervalo invalido' => [[['10:00:00', '09:00:00', 'Disponible']], false],
            'normaliza tipo' => [[['09:00:00', '10:00:00', ' DISPONIBLE ']], true],
        ];
    }

    public function test_solo_la_disponibilidad_activa_del_periodo_y_dia_elegidos_cubre_la_solicitud(): void
    {
        $d = $this->declarar('09:00:00', '10:00:00'); $d->update(['estado' => false]);
        $this->declarar('10:00:00', '11:00:00');
        $otro = $this->declarar('09:00:00', '10:00:00'); $otro->update(['dia_id' => DB::table('dias')->where('numero', 2)->value('id')]);
        $this->post($this->guardar(), $this->datos())->assertSessionHasErrors('horario');
        $this->assertDatabaseCount('horarios', 0);
    }

    #[DataProvider('inactivos')]
    public function test_rechaza_contextos_inactivos_y_versiones_que_no_se_pueden_editar(string $modelo, string $campo, mixed $valor, string $error): void
    {
        $objeto = match ($modelo) { 'dia' => Models\Dia::findOrFail($this->dia), 'seccion' => $this->actividad->seccion, default => $this->{$modelo} };
        $objeto->update([$campo => $valor]);
        $this->post($this->guardar(), $this->datos())->assertSessionHasErrors($error);
        $this->assertDatabaseCount('horarios', 0); $this->assertDatabaseCount('historial_acciones', 0);
    }

    public static function inactivos(): array
    {
        return [['grupo', 'estado_activo', false, 'horario'], ['actividad', 'estado', false, 'horario'], ['seccion', 'estado_activo', false, 'horario'],
            ['periodo', 'estado', false, 'version_id'], ['version', 'es_actual', false, 'version_id'], ['version', 'estado', 'Publicada', 'version_id'],
            ['profesor', 'estado', false, 'profesores_ids'], ['dia', 'estado', false, 'bloques_horarios'], ['bloque', 'estado', false, 'bloques_horarios'],
            ['bloque', 'hora_fin', '09:00:00', 'bloques_horarios']];
    }

    #[DataProvider('cambiosConcurrentes')]
    public function test_revalida_los_cambios_anteriores_al_bloqueo_sin_guardar_parcialmente(string $cambio, string $error): void
    {
        $hecho = false;
        DB::connection()->beforeStartingTransaction(function () use (&$hecho, $cambio) {
            if ($hecho) return; $hecho = true;
            match ($cambio) {
                'carga' => $this->actividad->update(['cantidad_horas' => 0]),
                'disponibilidad' => $this->declarar('10:00:00', '11:00:00'),
                'horario' => $this->existente($this->bloque),
                'actual' => (function () { $this->version->update(['es_actual' => false]); $nueva = $this->version->replicate(); $nueva->es_actual = true; $nueva->save(); })(),
            };
        });
        $this->post($this->guardar(), $this->datos())->assertSessionHasErrors($error);
        $this->assertTrue($hecho);
        $this->assertDatabaseCount('horarios', $cambio === 'horario' ? 1 : 0); $this->assertDatabaseCount('historial_acciones', 0);
    }

    public static function cambiosConcurrentes(): array { return [['carga', 'horario'], ['disponibilidad', 'horario'], ['horario', 'horario'], ['actual', 'version_id']]; }

    public function test_el_exceso_de_carga_y_los_identificadores_repetidos_no_crean_modulos(): void
    {
        $this->actividad->update(['cantidad_horas' => 1]);
        $otro = $this->bloque('10:00:00', '11:00:00');
        $this->post($this->guardar(), $this->datos(['bloques_horarios' => [$this->bloque->id, $otro->id]]))->assertSessionHasErrors('horario');
        $this->post($this->guardar(), $this->datos(['bloques_horarios' => [$this->bloque->id, $this->bloque->id]]))->assertSessionHasErrors('bloques_horarios.0');
        $this->post($this->guardar(), $this->datos(['version_id' => null]))->assertSessionHasErrors('version_id');
        $this->assertDatabaseCount('horarios', 0);
    }

    public function test_la_eliminacion_conserva_el_historial_de_los_docentes_y_recursos_retirados(): void
    {
        $h = $this->existente($this->bloque); $antes = $h->profesores->first()->toArray();
        $this->delete(route('programacion.asignacion.horarios.eliminar', $h))->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('horarios', ['id' => $h->id]);
        $this->assertDatabaseCount('horarios_profesores', 0);
        $log = DB::table('historial_acciones')->sole(); $datos = json_decode($log->datos, true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('Eliminar módulo', $log->accion); $this->assertSame($antes, $datos['antes']['profesores'][0]);
    }

    public function test_no_se_eliminan_modulos_historicos_ni_de_versiones_publicadas(): void
    {
        $h = $this->existente($this->bloque);
        $this->version->update(['estado' => 'Publicada']);
        $this->delete(route('programacion.asignacion.horarios.eliminar', $h))->assertSessionHasErrors('version_id');
        $this->version->update(['estado' => 'Borrador', 'es_actual' => false]);
        $this->delete(route('programacion.asignacion.horarios.eliminar', $h))->assertSessionHasErrors('version_id');
        $this->assertDatabaseHas('horarios', ['id' => $h->id]); $this->assertDatabaseCount('historial_acciones', 0);
    }

    public function test_una_eliminacion_fallida_revierte_tambien_los_docentes_y_espacios(): void
    {
        $h = $this->existente($this->bloque);
        $sede = Models\Sede::create(['universidad_id' => $this->periodo->universidad_id, 'nombre' => 'Sede de prueba']);
        $tipo = Models\TipoSala::create(['nombre' => 'Aula']);
        $sala = Models\Sala::create(['universidad_id' => $this->periodo->universidad_id, 'sede_id' => $sede->id, 'tipo_sala_id' => $tipo->id, 'codigo' => 'AUD-1']);
        Models\AsignacionSala::create(['horario_id' => $h->id, 'sala_id' => $sala->id]);
        Models\Horario::deleted(fn () => DB::statement('DROP TABLE historial_acciones'));
        $this->withoutExceptionHandling();
        try { $this->delete(route('programacion.asignacion.horarios.eliminar', $h)); $this->fail('La eliminación requiere historial atómico.'); }
        catch (\Illuminate\Database\QueryException $error) { $this->assertStringContainsString('historial_acciones', $error->getMessage()); }
        $this->assertDatabaseHas('horarios', ['id' => $h->id]);
        $this->assertDatabaseHas('horarios_profesores', ['horario_id' => $h->id]);
        $this->assertDatabaseHas('asignaciones_salas', ['horario_id' => $h->id, 'sala_id' => $sala->id]);
    }

    #[DataProvider('entradasInvalidas')]
    public function test_rechaza_entradas_malformadas_sin_mutaciones(string $campo, mixed $valor, string $error): void
    {
        $this->post($this->guardar(), $this->datos([$campo => $valor]))->assertSessionHasErrors($error);
        $this->assertDatabaseCount('horarios', 0); $this->assertDatabaseCount('historial_acciones', 0);
    }

    public static function entradasInvalidas(): array
    {
        return [['version_id', [1], 'version_id'], ['dia_id', [1], 'dia_id'], ['profesores_ids', [[1]], 'profesores_ids.0'],
            ['bloques_horarios', [[1]], 'bloques_horarios.0'], ['observaciones', ['dato'], 'observaciones'], ['observaciones', str_repeat('a', 256), 'observaciones']];
    }

    public function test_no_guarda_si_falla_el_historial_y_revierte_todos_los_modulos_del_envio(): void
    {
        $hecho = false;
        Models\Horario::created(function () use (&$hecho) { if (! $hecho) { $hecho = true; DB::statement('DROP TABLE historial_acciones'); } });
        $otro = $this->bloque('10:00:00', '11:00:00');
        $this->withoutExceptionHandling();
        try { $this->post($this->guardar(), $this->datos(['bloques_horarios' => [$this->bloque->id, $otro->id]])); $this->fail('Se requiere historial atómico.'); }
        catch (\Illuminate\Database\QueryException $error) { $this->assertStringContainsString('historial_acciones', $error->getMessage()); }
        $this->assertDatabaseCount('horarios', 0); $this->assertDatabaseCount('horarios_profesores', 0);
    }

    public function test_la_vista_de_una_version_publicada_es_de_consulta_y_no_ofrece_cambios(): void
    {
        $this->existente($this->bloque)->update(['grupo_seccion_id' => $this->grupo->id, 'actividad_seccion_id' => $this->actividad->id]);
        $this->version->update(['estado' => 'Publicada']);
        $this->get($this->pantalla())->assertOk()->assertSee('disponible para consulta')->assertDontSee('class="js-form-horario ', false)
            ->assertDontSee('¿Eliminar este horario?')->assertDontSee('Agregar horario');
    }

    public function test_un_error_recupera_las_opciones_solo_en_el_grupo_y_version_del_envio(): void
    {
        $otro = Models\GrupoSeccion::create(['actividad_seccion_id' => $this->actividad->id, 'codigo' => 'G:2']);
        Models\GrupoProfesor::create(['grupo_seccion_id' => $otro->id, 'profesor_id' => $this->profesor->id]);
        $this->from($this->pantalla())->post($this->guardar(), $this->datos(['observaciones' => 'Dato que recuperar', 'bloques_horarios' => [$this->bloque->id, $this->bloque->id]]))->assertRedirect($this->pantalla());
        $html = $this->get($this->pantalla())->assertOk()->getContent();
        $dom = new \DOMDocument; @$dom->loadHTML($html); $xpath = new \DOMXPath($dom);
        $formularios = $xpath->query('//form[contains(@class,"js-form-horario")]');
        $this->assertSame(2, $formularios->length);
        $this->assertSame(1, $xpath->query('.//input[@name="bloques_horarios[]" and @checked]', $formularios->item(0))->length);
        $this->assertSame(0, $xpath->query('.//input[@name="bloques_horarios[]" and @checked]', $formularios->item(1))->length);
        $this->assertSame('Dato que recuperar', $xpath->query('.//input[@name="observaciones"]', $formularios->item(0))->item(0)->getAttribute('value'));
        $this->assertSame('', $xpath->query('.//input[@name="observaciones"]', $formularios->item(1))->item(0)->getAttribute('value'));
        $this->assertSame((string) $this->version->id, $xpath->query('.//input[@name="version_id"]', $formularios->item(0))->item(0)->getAttribute('value'));
    }

    public function test_las_mutaciones_exigen_el_permiso_y_autenticacion(): void
    {
        $h = $this->existente($this->bloque); auth()->user()->revokePermissionTo('asignar horarios');
        $this->post($this->guardar(), $this->datos())->assertRedirect();
        $this->delete(route('programacion.asignacion.horarios.eliminar', $h))->assertRedirect();
        auth()->logout(); $this->post($this->guardar(), $this->datos())->assertRedirect(route('login'));
        $this->assertDatabaseCount('horarios', 1); $this->assertDatabaseCount('historial_acciones', 0);
    }

    private function bloque(string $inicio, string $fin): Models\BloqueHorario
    {
        return Models\BloqueHorario::create(['numero' => ++$this->secuencia, 'hora_inicio' => $inicio, 'hora_fin' => $fin, 'estado' => true]);
    }

    private function existente(Models\BloqueHorario $bloque, ?Models\Profesor $profesor = null): Models\Horario
    {
        $seccion = $this->actividad->seccion->replicate(); $seccion->codigo = 'Otra '.++$this->secuencia; $seccion->save();
        $actividad = Models\ActividadSeccion::create(['seccion_id' => $seccion->id, 'tipo' => 'Laboratorio', 'cantidad_horas' => 4]);
        $grupo = Models\GrupoSeccion::create(['actividad_seccion_id' => $actividad->id, 'codigo' => 'G:1']);
        $h = Models\Horario::create(['version_planificacion_id' => $this->version->id, 'actividad_seccion_id' => $actividad->id, 'grupo_seccion_id' => $grupo->id, 'dia_id' => $this->dia, 'bloque_horario_id' => $bloque->id]);
        Models\HorarioProfesor::create(['horario_id' => $h->id, 'profesor_id' => ($profesor ?? $this->profesor)->id]);
        return $h;
    }

    private function declarar(string $inicio, string $fin, string $tipo = 'Disponible'): Models\DisponibilidadProfesor
    {
        return Models\DisponibilidadProfesor::create(['profesor_id' => $this->profesor->id, 'periodo_academico_id' => $this->periodo->id, 'dia_id' => $this->dia,
            'bloque_horario_id' => $this->bloque($inicio, $fin)->id, 'tipo_disponibilidad' => $tipo]);
    }

    private function datos(array $cambios = []): array { return array_replace(['grupo_seccion_id' => $this->grupo->id, 'version_id' => $this->version->id, 'dia_id' => $this->dia, 'profesores_ids' => [$this->profesor->id], 'bloques_horarios' => [$this->bloque->id]], $cambios); }
    private function guardar(): string { return route('programacion.asignacion.horarios.guardar'); }
    private function pantalla(): string { return route('programacion.asignacion.horarios', ['periodo_id' => $this->periodo->id, 'seccion_id' => $this->actividad->seccion_id]); }
}

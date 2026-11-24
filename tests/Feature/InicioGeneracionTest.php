<?php

namespace Tests\Feature;

use App\Models;
use App\Services\ActivacionVersion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Spatie\Permission\Models\Permission;
use Tests\IsolatedDatabaseTestCase;

class InicioGeneracionTest extends IsolatedDatabaseTestCase
{
    public function test_el_dashboard_refleja_la_generacion_con_laboratorio_y_manual_solo_despues_de_activar(): void
    {
        $institucion = DB::table('universidades')->value('id');
        $usuario = Models\User::create([
            'name' => 'Coordinación', 'rut' => '17777777',
            'email' => 'inicio-generacion@example.test', 'password' => 'prueba',
        ]);
        foreach (['ver panel inicio', 'generar programacion semestral', 'ver programacion semestral',
            'asignar profesores', 'administrar usuarios', 'gestionar conflictos'] as $permiso) {
            $usuario->givePermissionTo(Permission::findOrCreate($permiso, 'web'));
        }
        $this->actingAs($usuario);
        $periodo = Models\PeriodoAcademico::create([
            'universidad_id' => $institucion, 'nombre' => '2026-2', 'anio' => 2026,
            'numero_semestre' => 2, 'estado' => true,
        ]);
        $base = Models\VersionPlanificacion::create([
            'periodo_academico_id' => $periodo->id, 'nombre' => 'Base manual',
            'tipo' => 'semestral', 'version' => 0.1, 'estado' => 'Borrador', 'es_actual' => true,
        ]);
        $sede = Models\Sede::create(['universidad_id' => $institucion, 'nombre' => 'Sede de prueba', 'estado' => true]);
        $carrera = Models\Carrera::create([
            'universidad_id' => $institucion, 'sede_id' => $sede->id,
            'nombre' => 'Informática de prueba', 'codigo' => 'INF', 'estado' => true,
        ]);
        $profesor = Models\Profesor::create([
            'universidad_id' => $institucion, 'rut' => '12345678',
            'nombre' => 'Ana', 'apellido' => 'Prueba', 'estado' => true,
        ]);
        $tipo = Models\TipoSala::create(['nombre' => 'Aula', 'estado' => true, 'requiere_equipamiento' => false]);
        $sala = Models\Sala::create([
            'universidad_id' => $institucion, 'sede_id' => $sede->id, 'tipo_sala_id' => $tipo->id,
            'codigo' => 'PR-AULA', 'nombre' => 'Aula de prueba', 'capacidad' => 30,
            'estado' => true, 'disponible' => true,
        ]);
        $salaLaboratorio = $sala->replicate();
        $salaLaboratorio->codigo = 'PR-LAB';
        $salaLaboratorio->nombre = 'Laboratorio de prueba';
        $salaLaboratorio->save();
        $laboratorio = Models\Laboratorio::create([
            'sala_id' => $salaLaboratorio->id, 'codigo' => 'LAB', 'nombre' => 'Computación de prueba',
            'capacidad' => 25, 'estado' => true, 'disponible' => true,
        ]);
        $grupos = collect();
        foreach (['Cátedra' => 1, 'Laboratorio' => 2] as $actividad => $modulos) {
            $ramo = Models\Asignatura::create([
                'universidad_id' => $institucion, 'codigo' => 'PR-'.$modulos,
                'nombre' => 'Ramo '.$actividad, 'estado' => true,
            ]);
            $seccion = Models\Seccion::create([
                'periodo_academico_id' => $periodo->id, 'asignatura_id' => $ramo->id,
                'codigo' => '11', 'semestre' => 2, 'cupos' => 20, 'inscritos' => 20, 'estado_activo' => true,
            ]);
            $seccion->carreras()->attach($carrera->id);
            $clase = $seccion->actividades()->create([
                'tipo' => $actividad, 'cantidad_horas' => $modulos, 'cantidad_estudiantes' => 20, 'estado' => true,
            ]);
            $grupo = $clase->grupos()->create([
                'codigo' => '1', 'numero' => 1, 'cupos' => 20, 'inscritos' => 20, 'estado_activo' => true,
            ]);
            $grupo->profesores()->create(['profesor_id' => $profesor->id, 'es_principal' => true, 'estado' => true]);
            $grupos->put($actividad, $grupo);
        }
        $dias = Models\Dia::whereIn('numero', [1, 2])->orderBy('numero')->pluck('id');
        $bloques = Models\BloqueHorario::whereIn('numero', [1, 2])->orderBy('numero')->pluck('id');
        $slots = [];
        foreach ($dias as $dia) {
            foreach ($bloques as $bloque) $slots[] = $dia.':'.$bloque;
        }
        $manual = $base->horarios()->create([
            'actividad_seccion_id' => $grupos['Cátedra']->actividad_seccion_id,
            'grupo_seccion_id' => $grupos['Cátedra']->id,
            'dia_id' => $dias->first(), 'bloque_horario_id' => $bloques->first(),
            'observaciones' => 'Horario manual conservado', 'estado' => true,
        ]);
        $manual->asignacionesSalas()->create([
            'sala_id' => $sala->id, 'capacidad_asignada' => 20, 'es_principal' => true, 'estado' => true,
        ]);
        $this->aprobarDisponibilidad($profesor, $periodo, $slots);

        $antes = $this->get(route('inicio'))->assertOk()->viewData('indicadores');
        $this->assertSame(33.3, $antes['graficos']['horario']['porcentaje']);
        $this->assertSame(100.0, $antes['graficos']['docentes']['porcentaje']);
        $this->assertSame(0, $antes['graficos']['laboratorios']['asignados']);

        $this->post(route('programacion.semestral.generar'), ['version_base_id' => $base->id])
            ->assertSessionHasNoErrors()->assertRedirect();
        $nueva = Models\VersionPlanificacion::latest('id')->firstOrFail();
        $sinActivar = $this->get(route('inicio'))->assertOk()
            ->assertViewHas('versionSemestral', fn ($version) => $version->id === $base->id)
            ->viewData('indicadores');
        $this->assertSame(33.3, $sinActivar['graficos']['horario']['porcentaje']);

        $revision = app(ActivacionVersion::class)->revisar($nueva);
        $this->assertSame([], $revision['bloqueos']);
        $this->post(route('programacion.version.activar', $nueva), [
            'firma' => $revision['firma'], 'confirmar' => 1,
        ])->assertSessionHasNoErrors()->assertRedirect();

        $respuesta = $this->get(route('inicio'))->assertOk()
            ->assertViewHas('versionSemestral', fn ($version) => $version->id === $nueva->id)
            ->assertViewHas('resumen', ['modulos' => 3, 'grupos' => 2, 'secciones' => 2])
            ->assertViewHas('totales', ['carreras' => 1, 'profesores' => 1, 'salas' => 2, 'secciones' => 2]);
        $indicadores = $respuesta->viewData('indicadores');
        foreach (['horario', 'docentes', 'espacios', 'salas', 'laboratorios'] as $clave) {
            $this->assertSame(100.0, $indicadores['graficos'][$clave]['porcentaje'], $clave);
            $this->assertSame(0, $indicadores['graficos'][$clave]['restantes'], $clave);
        }
        $this->assertNull($indicadores['graficos']['moviles']['porcentaje']);
        $this->assertSame(1, $indicadores['graficos']['laboratorios']['asignados']);
        $this->assertSame(2, $nueva->horarios()->whereHas('asignacionesLaboratorios',
            fn ($asignacion) => $asignacion->where('laboratorio_id', $laboratorio->id))->count());
        $this->assertSame(1, $base->horarios()->count());
        $this->assertSame(0, $manual->profesores()->count());
        $this->assertSame('Horario manual conservado', $manual->fresh()->observaciones);
    }

    private function aprobarDisponibilidad(Models\Profesor $profesor, Models\PeriodoAcademico $periodo, array $slots): void
    {
        $this->post(route('disponibilidad.store'), [
            'profesor_id' => $profesor->id, 'periodo_academico_id' => $periodo->id,
            'fecha_limite' => today()->addDays(7)->toDateString(),
        ])->assertSessionHasNoErrors();
        $solicitud = Models\SolicitudDisponibilidad::latest('id')->firstOrFail();
        $url = URL::temporarySignedRoute('disponibilidad.publica', $solicitud->fecha_limite->endOfDay(), [
            'solicitud' => $solicitud->id,
        ]);
        $this->post($url, ['bloques' => $slots])->assertSessionHasNoErrors();
        $this->post(route('disponibilidad.revisar', $solicitud), ['decision' => 'aprobar'])->assertSessionHasNoErrors();
    }
}

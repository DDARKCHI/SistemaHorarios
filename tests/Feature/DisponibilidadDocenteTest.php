<?php

namespace Tests\Feature;

use App\Models;
use Illuminate\Support\Facades\{DB, URL};
use Spatie\Permission\Models\Permission;
use Tests\IsolatedDatabaseTestCase;

class DisponibilidadDocenteTest extends IsolatedDatabaseTestCase
{
    private Models\Profesor $profesor;
    private Models\PeriodoAcademico $periodo;
    private Models\Dia $dia;
    private Models\BloqueHorario $bloque;
    private Models\User $usuario;

    protected function setUp(): void
    {
        parent::setUp();
        $uni = DB::table('universidades')->value('id');
        $this->usuario = Models\User::create(['name' => 'Coordinación', 'rut' => '16666666', 'email' => 'coord@example.test', 'password' => 'prueba']);
        $this->usuario->givePermissionTo(Permission::findOrCreate('asignar profesores', 'web'));
        $this->actingAs($this->usuario);
        $this->profesor = Models\Profesor::create(['universidad_id' => $uni, 'rut' => '12345678', 'nombre' => 'Ana', 'apellido' => 'Pérez', 'estado' => true]);
        $this->periodo = Models\PeriodoAcademico::create(['universidad_id' => $uni, 'nombre' => '2026-II', 'anio' => 2026, 'numero_semestre' => 2, 'estado' => true]);
        $this->dia = Models\Dia::create(['nombre' => 'Lunes', 'abreviatura' => 'LU', 'numero' => 1, 'estado' => true]);
        $this->bloque = Models\BloqueHorario::create(['numero' => 1, 'hora_inicio' => '08:10', 'hora_fin' => '09:00', 'estado' => true]);
    }

    private function crear(): Models\SolicitudDisponibilidad
    {
        $this->post(route('disponibilidad.store'), ['profesor_id' => $this->profesor->id, 'periodo_academico_id' => $this->periodo->id,
            'fecha_limite' => today()->addDays(7)->toDateString()])->assertSessionHasNoErrors()->assertRedirect();
        return Models\SolicitudDisponibilidad::latest('id')->firstOrFail();
    }

    private function enlace(Models\SolicitudDisponibilidad $s): string
    {
        return URL::temporarySignedRoute('disponibilidad.publica', $s->fecha_limite->endOfDay(), ['solicitud' => $s->id]);
    }

    private function respuesta(Models\SolicitudDisponibilidad $s): void
    {
        $this->post($this->enlace($s), ['bloques' => [$this->dia->id.':'.$this->bloque->id]])->assertSessionHasNoErrors()->assertRedirect();
    }

    public function test_gestion_enlace_privado_y_calendario_publico(): void
    {
        $s = $this->crear();
        $this->get(route('disponibilidad.index'))->assertOk()->assertSee('Ana')->assertSee('Disponibilidad docente');
        $this->get(route('disponibilidad.show', $s))->assertOk()->assertSee('Enlace privado');
        auth()->logout();
        $this->get($this->enlace($s))->assertOk()->assertSee('Módulo 1')->assertHeader('Referrer-Policy', 'no-referrer');
        $this->get('/respuesta-disponibilidad/'.$s->id)->assertForbidden();
    }

    public function test_respuesta_pendiente_no_cambia_disponibilidad_y_aprobacion_si(): void
    {
        $vieja = Models\DisponibilidadProfesor::create(['profesor_id' => $this->profesor->id, 'periodo_academico_id' => $this->periodo->id,
            'dia_id' => $this->dia->id, 'bloque_horario_id' => $this->bloque->id, 'tipo_disponibilidad' => 'No disponible', 'estado' => true]);
        $s = $this->crear(); $this->respuesta($s);
        $this->assertSame('No disponible', $vieja->fresh()->tipo_disponibilidad);
        $this->assertSame('En revisión', $s->fresh()->estado);
        $this->get(route('disponibilidad.show', $s))->assertOk()->assertSee('Aprobar disponibilidad');
        $this->post(route('disponibilidad.revisar', $s), ['decision' => 'aprobar'])->assertSessionHasNoErrors();
        $this->assertSame('Disponible', $vieja->fresh()->tipo_disponibilidad);
        $this->assertSame('Aprobada', $s->fresh()->estado);
        $this->assertDatabaseHas('respuestas_disponibilidad', ['revisado_por' => $this->usuario->id, 'respuesta' => 'Aprobada']);
        $this->post(route('disponibilidad.revisar', $s), ['decision' => 'aprobar'])->assertSessionHasErrors('decision');
    }

    public function test_rechazo_exige_motivo_conserva_vigente_y_permite_nueva_solicitud(): void
    {
        $s = $this->crear(); $this->respuesta($s);
        $this->post(route('disponibilidad.revisar', $s), ['decision' => 'rechazar'])->assertSessionHasErrors('motivo');
        $this->post(route('disponibilidad.revisar', $s), ['decision' => 'rechazar', 'motivo' => 'Confirmar jornada.'])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('disponibilidad_profesores', 0);
        $this->assertSame('Rechazada', $s->fresh()->estado);
        $this->crear();
        $this->assertDatabaseCount('solicitudes_disponibilidad', 2);
    }

    public function test_no_permite_duplicar_solicitudes_ni_responder_dos_veces(): void
    {
        $s = $this->crear();
        $this->post(route('disponibilidad.store'), ['profesor_id' => $this->profesor->id, 'periodo_academico_id' => $this->periodo->id, 'fecha_limite' => today()->toDateString()])->assertSessionHasErrors('profesor_id');
        $this->respuesta($s);
        $this->post($this->enlace($s), ['sin_disponibilidad' => 1])->assertGone();
        $this->assertDatabaseCount('respuestas_disponibilidad', 1);
    }

    public function test_cancelacion_y_vencimiento_invalidan_respuesta(): void
    {
        $s = $this->crear(); $url = $this->enlace($s);
        $this->post(route('disponibilidad.cancelar', $s))->assertSessionHasNoErrors();
        $this->post($url, ['sin_disponibilidad' => 1])->assertGone();
        $nuevo = $this->crear(); $url = $this->enlace($nuevo);
        $this->travel(9)->days();
        $this->get($url)->assertForbidden();
        $this->post($url, ['sin_disponibilidad' => 1])->assertForbidden();
        $this->assertDatabaseCount('respuestas_disponibilidad', 0);
    }

    public function test_valida_calendario_y_declaracion_sin_disponibilidad(): void
    {
        $s = $this->crear(); $url = $this->enlace($s);
        $this->post($url, [])->assertSessionHasErrors('bloques');
        $this->post($url, ['bloques' => ['9999:9999']])->assertSessionHasErrors('bloques');
        $this->post($url, ['sin_disponibilidad' => 1, 'bloques' => [$this->dia->id.':'.$this->bloque->id]])->assertSessionHasErrors('bloques');
        $this->post($url, ['sin_disponibilidad' => 1])->assertSessionHasNoErrors();
        $this->post(route('disponibilidad.revisar', $s), ['decision' => 'aprobar'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('disponibilidad_profesores', ['profesor_id' => $this->profesor->id, 'tipo_disponibilidad' => 'No disponible', 'estado' => true]);
    }

    public function test_calendario_modificado_exige_nueva_respuesta(): void
    {
        $s = $this->crear(); $this->respuesta($s);
        $this->bloque->update(['estado' => false]);
        $this->post(route('disponibilidad.revisar', $s), ['decision' => 'aprobar'])->assertSessionHasErrors('decision');
        $this->assertDatabaseCount('disponibilidad_profesores', 0);
        $this->assertSame('En revisión', $s->fresh()->estado);
    }

    public function test_inactivos_y_periodos_de_otra_institucion_no_se_admiten(): void
    {
        $this->profesor->update(['estado' => false]);
        $this->post(route('disponibilidad.store'), ['profesor_id' => $this->profesor->id, 'periodo_academico_id' => $this->periodo->id, 'fecha_limite' => today()->toDateString()])->assertSessionHasErrors('profesor_id');
        $this->assertDatabaseCount('solicitudes_disponibilidad', 0);
        $this->profesor->update(['estado' => true]);
        $otra = DB::table('universidades')->insertGetId(['nombre' => 'Otra institución', 'sigla' => 'OTRA', 'estado' => true, 'created_at' => now(), 'updated_at' => now()]);
        $this->periodo->update(['universidad_id' => $otra]);
        $this->post(route('disponibilidad.store'), ['profesor_id' => $this->profesor->id, 'periodo_academico_id' => $this->periodo->id, 'fecha_limite' => today()->toDateString()])->assertSessionHasErrors('profesor_id');
        $this->assertDatabaseCount('solicitudes_disponibilidad', 0);
    }

    public function test_cambio_de_horas_sin_cambiar_ids_tambien_invalida_la_aprobacion(): void
    {
        $s = $this->crear(); $this->respuesta($s);
        $this->bloque->update(['hora_inicio' => '07:00']);
        $this->post(route('disponibilidad.revisar', $s), ['decision' => 'aprobar'])->assertSessionHasErrors('decision');
        $this->assertDatabaseCount('disponibilidad_profesores', 0);
    }

    public function test_aprobacion_conserva_disponibilidad_de_otros_periodos_y_registra_cambios(): void
    {
        $otro = Models\PeriodoAcademico::create(['universidad_id' => $this->periodo->universidad_id, 'nombre' => '2027-I', 'anio' => 2027, 'numero_semestre' => 1, 'estado' => true]);
        $vieja = Models\DisponibilidadProfesor::create(['profesor_id' => $this->profesor->id, 'periodo_academico_id' => $otro->id, 'dia_id' => $this->dia->id,
            'bloque_horario_id' => $this->bloque->id, 'estado' => true, 'tipo_disponibilidad' => 'Disponible']);
        $antes = $vieja->fresh()->getAttributes();
        $s = $this->crear(); $this->respuesta($s);
        $this->post(route('disponibilidad.revisar', $s), ['decision' => 'aprobar'])->assertSessionHasNoErrors();
        $this->assertSame($antes, $vieja->fresh()->getAttributes());
        $this->assertDatabaseHas('historial_acciones', ['accion' => 'Aprobar disponibilidad', 'usuario_id' => $this->usuario->id, 'registro_id' => $s->id]);
    }

    public function test_docente_inactivado_despues_de_crear_solicitud_no_puede_responder(): void
    {
        $s = $this->crear(); $url = $this->enlace($s);
        $this->profesor->update(['estado' => false]);
        $this->get($url)->assertGone();
        $this->post($url, ['sin_disponibilidad' => 1])->assertGone();
        $this->assertDatabaseCount('respuestas_disponibilidad', 0);
    }

    public function test_firma_alterada_no_permite_acceder_a_otro_docente(): void
    {
        $s = $this->crear();
        $url = str_replace('signature=', 'signature=x', $this->enlace($s));
        $this->get($url)->assertForbidden();
        $this->post($url, ['sin_disponibilidad' => 1])->assertForbidden();
        $this->assertDatabaseCount('respuestas_disponibilidad', 0);
    }

    public function test_sin_permiso_no_puede_consultar_crear_ni_revisar(): void
    {
        $s = $this->crear();
        $this->usuario->revokePermissionTo('asignar profesores');
        $this->get(route('disponibilidad.index'))->assertRedirect();
        $this->get(route('disponibilidad.show', $s))->assertRedirect();
        $this->post(route('disponibilidad.revisar', $s), ['decision' => 'aprobar'])->assertRedirect();
        $this->assertSame('Pendiente', $s->fresh()->estado);
    }
}

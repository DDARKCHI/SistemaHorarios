<?php

namespace Tests\Feature;

use App\Models;
use App\Services\CuentasDocentes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Tests\IsolatedDatabaseTestCase;

class IngresoDocenteTest extends IsolatedDatabaseTestCase
{
    private function docente(): Models\User
    {
        $profesor = Models\Profesor::create([
            'universidad_id' => Models\Universidad::firstOrFail()->id,
            'rut' => '123456789', 'nombre' => 'Docente', 'apellido' => 'Prueba',
            'correo_institucional' => 'ingreso-docente@example.test', 'estado' => true,
        ]);

        return app(CuentasDocentes::class)->asegurarCuenta($profesor);
    }

    private function solicitud(Models\User $usuario): Models\SolicitudDisponibilidad
    {
        $periodo = Models\PeriodoAcademico::create([
            'universidad_id' => $usuario->profesor->universidad_id, 'nombre' => '2026-2',
            'anio' => 2026, 'numero_semestre' => 2, 'estado' => true,
        ]);

        return Models\SolicitudDisponibilidad::create([
            'profesor_id' => $usuario->profesor_id, 'periodo_academico_id' => $periodo->id,
            'fecha_solicitud' => today(), 'fecha_limite' => today()->addDays(7), 'estado' => 'Pendiente',
        ]);
    }

    public function test_login_temporal_exige_cambio_y_registra_el_ingreso(): void
    {
        $usuario = $this->docente();
        $this->post(route('login.procesar'), ['rut' => '12.345.678-9', 'password' => $usuario->clave_temporal])
            ->assertRedirect(route('cuenta.password.edit'));
        $this->assertNotNull($usuario->fresh()->primer_ingreso_en);
        $this->assertNotNull($usuario->fresh()->ultimo_ingreso_en);
        $this->get(route('mi.disponibilidad.index'))->assertRedirect(route('cuenta.password.edit'));
        $this->get(route('cuenta.password.edit'))->assertOk()->assertSee('Activa tu acceso docente');
        $this->get(route('identidad.logo-base', 'prosec-logo.svg'))->assertOk();
    }

    public function test_cambio_temporal_limpia_la_clave_y_permite_marcar_disponibilidad(): void
    {
        $usuario = $this->docente();
        $temporal = $usuario->clave_temporal;
        $solicitud = $this->solicitud($usuario);
        $this->actingAs($usuario)->put(route('cuenta.password.update'), [
            'current_password' => $temporal,
            'password' => 'MiPasswordNueva12345', 'password_confirmation' => 'MiPasswordNueva12345',
        ])->assertRedirect(route('login'));
        $this->assertFalse($usuario->fresh()->debe_cambiar_password);
        $this->assertNull($usuario->fresh()->clave_temporal);
        $this->assertTrue(Hash::check('MiPasswordNueva12345', $usuario->fresh()->password));
        $this->post(route('login.procesar'), ['rut' => $usuario->rut, 'password' => 'MiPasswordNueva12345'])
            ->assertRedirect(route('mi.disponibilidad.index'));
        $this->get(route('mi.disponibilidad.index'))->assertOk()->assertSee('Marcar disponibilidad');
        $this->get(route('programacion.semestral'))->assertOk()->assertDontSee('Completar programación');
        $this->get(route('mi.disponibilidad.show', $solicitud))->assertOk()->assertSee('Selecciona tus módulos disponibles');
        $slot = Models\Dia::firstOrFail()->id.':'.Models\BloqueHorario::firstOrFail()->id;
        $this->post(route('mi.disponibilidad.guardar', $solicitud), ['bloques' => [$slot]])
            ->assertRedirect(route('mi.disponibilidad.show', $solicitud));
        $this->assertSame('En revisión', $solicitud->fresh()->estado);
        $this->assertSame([$slot], $solicitud->respuestas()->sole()->bloques['disponibles']);
        $this->assertStringNotContainsString($temporal, DB::table('historial_acciones')->get()->toJson());
    }

    public function test_no_puede_leer_ni_responder_solicitudes_ajenas(): void
    {
        $usuario = $this->docente();
        $usuario->forceFill(['debe_cambiar_password' => false, 'clave_temporal' => null])->save();
        $solicitud = $this->solicitud($usuario);
        $otro = Models\User::create([
            'name' => 'Otra persona', 'rut' => '99999999', 'email' => 'otra-persona@example.test', 'password' => 'MiPassword123',
        ]);
        $otro->givePermissionTo(Permission::findOrCreate('enviar propuesta horario', 'web'));
        $this->actingAs($otro)->get(route('mi.disponibilidad.show', $solicitud))->assertForbidden();
        $this->post(route('mi.disponibilidad.guardar', $solicitud), ['sin_disponibilidad' => 1])->assertForbidden();
        $this->assertSame('Pendiente', $solicitud->fresh()->estado);
        $this->assertSame(0, $solicitud->respuestas()->count());
    }

    public function test_clave_temporal_no_puede_saltarse_por_una_peticion_json(): void
    {
        $usuario = $this->docente();
        $this->actingAs($usuario)->getJson(route('mi.disponibilidad.index'))
            ->assertStatus(423)->assertJsonPath('redirect', route('cuenta.password.edit'));
        $this->post(route('logout'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_un_segundo_ingreso_conserva_la_fecha_inicial(): void
    {
        $usuario = $this->docente();
        $this->post(route('login.procesar'), ['rut' => $usuario->rut, 'password' => $usuario->clave_temporal]);
        $primero = $usuario->fresh()->primer_ingreso_en->toDateTimeString();
        $this->post(route('logout'));
        $this->travel(2)->hours();
        $this->post(route('login.procesar'), ['rut' => $usuario->rut, 'password' => $usuario->clave_temporal]);
        $this->assertSame($primero, $usuario->fresh()->primer_ingreso_en->toDateTimeString());
        $this->assertTrue($usuario->fresh()->ultimo_ingreso_en->greaterThan($usuario->fresh()->primer_ingreso_en));
    }

    public function test_una_respuesta_no_se_envia_dos_veces(): void
    {
        $usuario = $this->docente();
        $usuario->forceFill(['debe_cambiar_password' => false, 'clave_temporal' => null])->save();
        $solicitud = $this->solicitud($usuario);
        $this->actingAs($usuario)->post(route('mi.disponibilidad.guardar', $solicitud), ['sin_disponibilidad' => 1])
            ->assertRedirect();
        $this->post(route('mi.disponibilidad.guardar', $solicitud), ['sin_disponibilidad' => 1])->assertGone();
        $this->assertSame(1, $solicitud->respuestas()->count());
    }
}

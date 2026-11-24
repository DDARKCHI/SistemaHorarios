<?php

namespace Tests\Feature;

use App\Models;
use App\Http\Controllers\DocenteController;
use App\Services\CuentasDocentes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\IsolatedDatabaseTestCase;

class DocentesTest extends IsolatedDatabaseTestCase
{
    private Models\User $usuario;
    private int $universidad;
    protected function setUp(): void
    {
        parent::setUp();
        $this->universidad = (int) DB::table('universidades')->value('id');
        $this->usuario = Models\User::create(['name' => 'Administración', 'rut' => '17777777', 'email' => 'docentes@example.test', 'password' => 'prueba']);
        $this->usuario->givePermissionTo(Permission::findOrCreate('administrar usuarios', 'web'));
        $this->actingAs($this->usuario);
    }
    private function datos(array $cambios = []): array
    {
        return $cambios + ['universidad_id' => $this->universidad, 'nombre' => 'Ana', 'apellido' => 'Pérez', 'rut' => '123456789', 'correo_institucional' => 'ana@example.test', 'estado' => 1];
    }
    private function docente(): Models\Profesor { return Models\Profesor::create($this->datos()); }
    private function actualizar(Models\Profesor $p, array $cambios = [])
    {
        return $this->put(route('administracion.docentes.update', $p), ['revision' => DocenteController::firma($p->fresh())] + $this->datos($cambios));
    }
    private function horario(): Models\Horario
    {
        $p = Models\PeriodoAcademico::create(['universidad_id' => $this->universidad, 'nombre' => '2026-2', 'anio' => 2026, 'numero_semestre' => 2, 'estado' => true]);
        $r = Models\Asignatura::create(['universidad_id' => $this->universidad, 'codigo' => 'BACK', 'nombre' => 'Backend']);
        $s = Models\Seccion::create(['asignatura_id' => $r->id, 'periodo_academico_id' => $p->id, 'codigo' => '11', 'estado_activo' => true]);
        $a = Models\ActividadSeccion::create(['seccion_id' => $s->id, 'tipo' => 'Cátedra', 'cantidad_horas' => 1, 'estado' => true]);
        $g = Models\GrupoSeccion::create(['actividad_seccion_id' => $a->id, 'codigo' => '1', 'numero' => 1, 'estado_activo' => true]);
        $v = Models\VersionPlanificacion::create(['periodo_academico_id' => $p->id, 'nombre' => 'Semestral', 'version' => 0.1, 'tipo' => 'semestral', 'estado' => 'Borrador', 'es_actual' => true, 'usuario_id' => $this->usuario->id]);
        return Models\Horario::create(['actividad_seccion_id' => $a->id, 'grupo_seccion_id' => $g->id, 'version_planificacion_id' => $v->id,
            'dia_id' => Models\Dia::value('id'), 'bloque_horario_id' => Models\BloqueHorario::value('id'), 'estado' => true]);
    }

    public function test_registra_y_edita_sin_crear_cuenta_de_acceso(): void
    {
        $this->get(route('administracion.docentes.create'))->assertOk()->assertSee('Registrar docente');
        $this->post(route('administracion.docentes.store'), $this->datos(['rut' => '12.345.678-9', 'nombre' => ' Ana  María ', 'correo_institucional' => ' ANA@EXAMPLE.TEST ']))->assertSessionHasNoErrors();
        $p = Models\Profesor::sole();
        $this->assertSame('123456789', $p->rut); $this->assertSame('Ana María', $p->nombre); $this->assertSame('ana@example.test', $p->correo_institucional);
        $this->get(route('administracion.docentes.edit', $p))->assertOk()->assertSee('Guardar cambios');
        $this->actualizar($p, ['unidad_academica' => 'Informática'])->assertSessionHasNoErrors();
        $this->get(route('administracion.docentes.index'))->assertOk()->assertSee('Informática');
        $this->assertDatabaseCount('users', 1); $this->assertDatabaseCount('historial_acciones', 2);
    }

    public function test_exige_rut_o_correo_institucional_y_valida_contactos(): void
    {
        $this->post(route('administracion.docentes.store'), $this->datos(['rut' => null, 'correo_institucional' => null]))->assertSessionHasErrors(['rut', 'correo_institucional']);
        $this->post(route('administracion.docentes.store'), $this->datos(['rut' => '.--', 'correo_institucional' => null]))->assertSessionHasErrors('rut');
        $this->post(route('administracion.docentes.store'), $this->datos(['correo_personal' => 'inválido']))->assertSessionHasErrors('correo_personal');
        $this->post(route('administracion.docentes.store'), $this->datos(['rut' => null]))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('profesores', 1);
    }

    public function test_duplicados_equivalentes_incluyen_inactivos_y_respetan_institucion(): void
    {
        $this->docente()->update(['rut' => '12.345.678-9', 'estado' => false]);
        $this->post(route('administracion.docentes.store'), $this->datos(['correo_institucional' => 'otra@example.test']))->assertSessionHasErrors('rut');
        $this->post(route('administracion.docentes.store'), $this->datos(['rut' => '987654321', 'correo_institucional' => 'ANA@example.test']))->assertSessionHasErrors('correo_institucional');
        $u = Models\Universidad::create(['nombre' => 'Otra', 'sigla' => 'OTR', 'estado' => true]);
        $this->post(route('administracion.docentes.store'), $this->datos(['universidad_id' => $u->id]))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('profesores', 2);
    }

    public function test_no_traslada_institucion_ni_crea_bajo_una_inactiva(): void
    {
        $p = $this->docente(); $u = Models\Universidad::create(['nombre' => 'Otra', 'sigla' => 'OTR', 'estado' => false]);
        $this->actualizar($p, ['universidad_id' => $u->id])->assertSessionHasErrors('universidad_id');
        $this->post(route('administracion.docentes.store'), $this->datos(['universidad_id' => $u->id]))->assertSessionHasErrors('universidad_id');
        $this->assertSame($this->universidad, $p->fresh()->universidad_id);
    }

    public function test_clase_actual_con_docente_explicito_impide_desactivar(): void
    {
        $p = $this->docente(); $h = $this->horario();
        Models\HorarioProfesor::create(['horario_id' => $h->id, 'profesor_id' => $p->id, 'estado' => true]);
        $this->actualizar($p, ['estado' => '0'])->assertSessionHasErrors('estado');
        $h->versionPlanificacion->periodoAcademico->update(['estado' => false]);
        $this->actualizar($p, ['estado' => '0'])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('horarios_profesores', 1); $this->assertDatabaseCount('horarios', 1);
    }

    public function test_docente_inferido_de_grupo_y_seccion_tambien_se_protege(): void
    {
        $p = $this->docente(); $h = $this->horario();
        $asignacion = Models\GrupoProfesor::create(['grupo_seccion_id' => $h->grupo_seccion_id, 'profesor_id' => $p->id, 'estado' => true]);
        $this->actualizar($p, ['estado' => 0])->assertSessionHasErrors('estado');
        $asignacion->update(['estado' => false]);
        Models\SeccionProfesor::create(['seccion_id' => $h->actividadSeccion->seccion_id, 'profesor_id' => $p->id, 'estado' => true]);
        $this->actualizar($p, ['estado' => 0])->assertSessionHasErrors('estado');
    }

    public function test_precedencia_de_docentes_no_bloquea_a_un_docente_que_ya_no_cubre_el_modulo(): void
    {
        $p = $this->docente(); $h = $this->horario();
        Models\SeccionProfesor::create(['seccion_id' => $h->actividadSeccion->seccion_id, 'profesor_id' => $p->id, 'estado' => true]);
        Models\GrupoProfesor::create(['grupo_seccion_id' => $h->grupo_seccion_id, 'profesor_id' => $p->id, 'estado' => true]);
        $otro = Models\Profesor::create($this->datos(['rut' => '987654321', 'nombre' => 'Pedro', 'correo_institucional' => 'pedro@example.test']));
        Models\HorarioProfesor::create(['horario_id' => $h->id, 'profesor_id' => $otro->id, 'estado' => true]);
        $this->actualizar($p, ['estado' => 0])->assertSessionHasNoErrors();
        $this->assertFalse((bool) $p->fresh()->estado); $this->assertTrue((bool) $otro->fresh()->estado);
    }

    public function test_docente_solo_historico_puede_desactivarse_sin_eliminar_registros(): void
    {
        $p = $this->docente(); $h = $this->horario(); $h->versionPlanificacion->update(['es_actual' => false]);
        Models\HorarioProfesor::create(['horario_id' => $h->id, 'profesor_id' => $p->id, 'estado' => true]);
        $this->actualizar($p, ['estado' => 0])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('horarios_profesores', 1); $this->assertDatabaseCount('profesores', 1);
    }

    public function test_corrige_el_rut_historico_sin_reescribir_los_datos_personales_de_la_version(): void
    {
        $p = $this->docente(); $h = $this->horario();
        $historico = Models\ProfesorVersion::create(['version_planificacion_id' => $h->version_planificacion_id, 'profesor_id' => $p->id, 'rut' => $p->rut, 'nombre' => 'Ana histórica', 'apellido' => 'Pérez']);
        $this->actualizar($p, ['rut' => '987654321', 'nombre' => 'Ana María'])->assertSessionHasNoErrors();
        $this->assertSame('987654321', $p->fresh()->rut);
        $this->assertSame('987654321', $historico->fresh()->rut);
        $this->assertSame('Ana histórica', $historico->fresh()->nombre);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_cambiar_rut_sincroniza_acceso_y_versiones_conservando_todos_los_vinculos(): void
    {
        Notification::fake();
        $profesor = $this->docente();
        $horario = $this->horario();
        $cuenta = app(CuentasDocentes::class)->asegurarCuenta($profesor);
        $credenciales = $cuenta->only(['password', 'clave_temporal', 'debe_cambiar_password', 'email', 'profesor_id']);
        $roles = $cuenta->roles->modelKeys();
        $permiso = $cuenta->getAllPermissions()->pluck('name')->sort()->values()->all();
        Models\HorarioProfesor::create(['horario_id' => $horario->id, 'profesor_id' => $profesor->id, 'estado' => true]);
        Models\GrupoProfesor::create(['grupo_seccion_id' => $horario->grupo_seccion_id, 'profesor_id' => $profesor->id, 'estado' => true]);
        Models\SeccionProfesor::create(['seccion_id' => $horario->actividadSeccion->seccion_id, 'profesor_id' => $profesor->id, 'estado' => true]);
        $segundaVersion = $horario->versionPlanificacion->replicate();
        $segundaVersion->version = 0.2;
        $segundaVersion->save();
        foreach ([$horario->version_planificacion_id, $segundaVersion->id] as $versionId) {
            Models\ProfesorVersion::create([
                'version_planificacion_id' => $versionId, 'profesor_id' => $profesor->id,
                'rut' => $profesor->rut, 'nombre' => 'Ana histórica', 'apellido' => 'Pérez',
            ]);
        }
        $solicitud = Models\SolicitudDisponibilidad::create([
            'periodo_academico_id' => $horario->versionPlanificacion->periodo_academico_id,
            'profesor_id' => $profesor->id, 'estado' => 'En revisión', 'fecha_limite' => today()->addDays(7),
        ]);
        $solicitud->respuestas()->create([
            'profesor_id' => $profesor->id, 'respuesta' => 'Pendiente de revisión',
            'fecha_respuesta' => now(), 'bloques' => ['disponibles' => [], 'calendario' => []],
        ]);
        $tablas = ['horarios_profesores', 'grupos_profesores', 'secciones_profesores', 'solicitudes_disponibilidad', 'respuestas_disponibilidad'];
        $vinculos = collect($tablas)->mapWithKeys(fn ($tabla) => [$tabla => DB::table($tabla)->orderBy('id')->get()->toJson()])->all();

        $this->actualizar($profesor, ['rut' => '19.814.651'])->assertSessionHasNoErrors();

        $this->assertSame('19814651', $profesor->fresh()->rut);
        $this->assertSame('19814651', $cuenta->fresh()->rut);
        $this->assertSame($credenciales, $cuenta->fresh()->only(array_keys($credenciales)));
        $this->assertSame($roles, $cuenta->fresh()->roles->modelKeys());
        $this->assertSame($permiso, $cuenta->fresh()->getAllPermissions()->pluck('name')->sort()->values()->all());
        $this->assertSame(['19814651', '19814651'], $profesor->versiones()->orderBy('id')->pluck('rut')->all());
        $this->assertSame($vinculos, collect($tablas)->mapWithKeys(fn ($tabla) => [$tabla => DB::table($tabla)->orderBy('id')->get()->toJson()])->all());
        $auditoria = json_decode(DB::table('historial_acciones')->where('accion', 'Actualizar docente')->sole()->datos, true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('123456789', $auditoria['antes']['rut']);
        $this->assertSame('19814651', $auditoria['despues']['rut']);
        $this->assertTrue(CuentasDocentes::buscarUsuario('19814651')->is($cuenta));
        $this->assertDatabaseCount('users', 2);
        $this->assertDatabaseCount('profesores', 1);
        Notification::assertNothingSent();
    }

    public function test_rut_ocupado_por_otra_cuenta_se_rechaza_sin_cambios_parciales(): void
    {
        $profesor = $this->docente();
        $cuenta = app(CuentasDocentes::class)->asegurarCuenta($profesor);
        $otro = Models\User::create([
            'name' => 'Otra cuenta', 'rut' => '98.765.432-1',
            'email' => 'otro-rut@example.test', 'password' => 'Personal12345',
        ]);
        $antes = $cuenta->fresh()->getAttributes();

        $this->actualizar($profesor, ['rut' => '987654321'])->assertSessionHasErrors('rut');

        $this->assertSame('123456789', $profesor->fresh()->rut);
        $this->assertSame($antes, $cuenta->fresh()->getAttributes());
        $this->assertSame('98.765.432-1', $otro->fresh()->rut);
        $this->assertDatabaseCount('historial_acciones', 0);
    }

    public function test_consolida_la_cuenta_provisoria_sin_uso_con_la_cuenta_personal_del_mismo_docente(): void
    {
        Notification::fake();
        $profesor = $this->docente();
        $profesor->update(['rut' => 'PRUEBA123']);
        $horario = $this->horario();
        $provisoria = app(CuentasDocentes::class)->asegurarCuenta($profesor);
        $provisoria->update(['email' => null]);
        $personal = Models\User::create([
            'name' => 'Ana Pérez', 'rut' => '19814651', 'email' => 'ana@example.test',
            'password' => 'MiClavePersonal12345',
        ]);
        $personal->assignRole(Role::findOrCreate('Profesor', 'web'));
        $hashPersonal = $personal->password;
        $rolesPersonales = $personal->roles->modelKeys();
        $solicitud = Models\SolicitudDisponibilidad::create([
            'profesor_id' => $profesor->id, 'periodo_academico_id' => $horario->versionPlanificacion->periodo_academico_id,
            'estado' => 'Pendiente', 'fecha_limite' => today()->addDays(7),
        ]);
        $envio = Models\EnvioAccesoDocente::create([
            'profesor_id' => $profesor->id, 'user_id' => $provisoria->id,
            'periodo_academico_id' => $solicitud->periodo_academico_id, 'solicitud_disponibilidad_id' => $solicitud->id,
            'enviado_por' => $this->usuario->id, 'lote' => (string) Str::uuid(),
            'estado' => 'enviado', 'correo' => 'ana@example.test', 'enviado_en' => now()->subDay(),
        ]);

        $this->actualizar($profesor, ['rut' => '19814651'])->assertSessionHasNoErrors();

        $this->assertSame('19814651', $profesor->fresh()->rut);
        $this->assertSame($profesor->id, $personal->fresh()->profesor_id);
        $this->assertSame($hashPersonal, $personal->fresh()->password);
        $this->assertSame($rolesPersonales, $personal->fresh()->roles->modelKeys());
        $this->assertFalse($personal->fresh()->debe_cambiar_password);
        $this->assertNull($provisoria->fresh()->profesor_id);
        $this->assertSame([], $provisoria->fresh()->roles->modelKeys());
        $this->assertSame($personal->id, $envio->fresh()->user_id);
        $this->assertSame($profesor->id, $solicitud->fresh()->profesor_id);
        $this->assertSame(1, Models\User::where('profesor_id', $profesor->id)->count());
        $this->assertDatabaseCount('users', 3);
        Notification::assertNothingSent();
    }

    public function test_una_cuenta_docente_ya_utilizada_no_se_consolida_por_correo(): void
    {
        $profesor = $this->docente();
        $cuenta = app(CuentasDocentes::class)->asegurarCuenta($profesor);
        $cuenta->forceFill(['email' => null, 'primer_ingreso_en' => now()])->save();
        $personal = Models\User::create([
            'name' => 'Ana Pérez', 'rut' => '19814651', 'email' => 'ana@example.test',
            'password' => 'MiClavePersonal12345',
        ]);

        $this->actualizar($profesor, ['rut' => '19814651'])->assertSessionHasErrors('rut');

        $this->assertSame('123456789', $profesor->fresh()->rut);
        $this->assertSame($profesor->id, $cuenta->fresh()->profesor_id);
        $this->assertNull($personal->fresh()->profesor_id);
        $this->assertDatabaseCount('historial_acciones', 0);
    }

    public function test_no_borra_el_rut_de_un_docente_que_ya_tiene_cuenta_y_solicitudes(): void
    {
        $profesor = $this->docente();
        $horario = $this->horario();
        $cuenta = app(CuentasDocentes::class)->asegurarCuenta($profesor);
        Models\SolicitudDisponibilidad::create([
            'periodo_academico_id' => $horario->versionPlanificacion->periodo_academico_id,
            'profesor_id' => $profesor->id, 'estado' => 'Pendiente', 'fecha_limite' => today()->addDay(),
        ]);

        $this->actualizar($profesor, ['rut' => null])->assertSessionHasErrors('rut');

        $this->assertSame('123456789', $profesor->fresh()->rut);
        $this->assertSame('123456789', $cuenta->fresh()->rut);
        $this->assertDatabaseCount('historial_acciones', 0);
    }

    public function test_fallo_de_auditoria_revierte_rut_de_ficha_cuenta_y_versiones(): void
    {
        $profesor = $this->docente();
        $horario = $this->horario();
        $cuenta = app(CuentasDocentes::class)->asegurarCuenta($profesor);
        $credenciales = $cuenta->fresh()->getAttributes();
        $version = Models\ProfesorVersion::create([
            'version_planificacion_id' => $horario->version_planificacion_id, 'profesor_id' => $profesor->id,
            'rut' => $profesor->rut, 'nombre' => 'Ana histórica', 'apellido' => 'Pérez',
        ]);
        $this->withoutExceptionHandling();
        Models\Profesor::updated(fn () => DB::statement('DROP TABLE historial_acciones'));
        try {
            $this->actualizar($profesor, ['rut' => '19814651']);
            $this->fail('Se esperaba fallo de auditoría.');
        } catch (\Illuminate\Database\QueryException $error) {
            $this->assertStringContainsString('historial_acciones', $error->getMessage());
        } finally {
            Models\Profesor::flushEventListeners();
        }

        $this->assertSame('123456789', $profesor->fresh()->rut);
        $this->assertSame($credenciales, $cuenta->fresh()->getAttributes());
        $this->assertSame('123456789', $version->fresh()->rut);
        $this->assertDatabaseCount('historial_acciones', 0);
    }

    public function test_correo_identificador_se_conserva_hasta_completar_rut(): void
    {
        $p = Models\Profesor::create($this->datos(['rut' => null])); $h = $this->horario();
        Models\HorarioProfesor::create(['horario_id' => $h->id, 'profesor_id' => $p->id, 'estado' => true]);
        $this->actualizar($p, ['rut' => null, 'correo_institucional' => 'otro@example.test'])->assertSessionHasErrors('correo_institucional');
        $this->actualizar($p, ['rut' => '123456789', 'correo_institucional' => 'otro@example.test'])->assertSessionHasNoErrors();
        $this->assertSame('123456789', $p->fresh()->rut);
    }

    public function test_disponibilidad_en_curso_impide_desactivar_hasta_revisar(): void
    {
        $p = $this->docente(); $h = $this->horario();
        $s = Models\SolicitudDisponibilidad::create(['periodo_academico_id' => $h->versionPlanificacion->periodo_academico_id, 'profesor_id' => $p->id, 'estado' => 'Pendiente', 'fecha_limite' => today()]);
        $this->actualizar($p, ['estado' => 0])->assertSessionHasErrors('estado');
        $s->update(['estado' => 'En revisión', 'fecha_limite' => today()->subDay()]);
        $this->actualizar($p, ['estado' => 0])->assertSessionHasErrors('estado');
        $s->update(['estado' => 'Aprobada']);
        $this->actualizar($p, ['estado' => 0])->assertSessionHasNoErrors();
    }

    public function test_solicitud_cambio_vigente_incluye_docentes_participantes(): void
    {
        $p = $this->docente(); $h = $this->horario();
        $otro = Models\Profesor::create($this->datos(['rut' => '987654321', 'correo_institucional' => 'otro@example.test']));
        $s = Models\SolicitudCambioHorario::create(['horario_id' => $h->id, 'profesor_id' => $otro->id, 'usuario_id' => $this->usuario->id, 'estado' => 'Pendiente de respuesta', 'estado_activo' => true, 'fecha_limite' => now()->addDay(), 'motivo' => 'Cambio']);
        $s->respuestasDocentes()->create(['profesor_id' => $p->id, 'estado' => 'Pendiente']);
        $this->actualizar($p, ['rut' => '567890123'])->assertSessionHasNoErrors();
        $this->assertSame('567890123', $p->fresh()->rut);
        $this->assertSame($p->id, $s->respuestasDocentes()->sole()->profesor_id);
        $this->actualizar($p, ['estado' => 0])->assertSessionHasErrors('estado');
        $s->update(['estado' => 'Cancelada', 'estado_activo' => false]);
        $this->actualizar($p, ['estado' => 0])->assertSessionHasNoErrors();
    }

    public function test_busqueda_por_nombre_completo_rut_formateado_y_por_estado(): void
    {
        $p = $this->docente();
        $this->get(route('administracion.docentes.index', ['q' => 'Ana Pérez']))->assertOk()->assertSee('Pérez, Ana');
        $this->get(route('administracion.docentes.index', ['q' => '12.345.678-9']))->assertOk()->assertSee('Pérez, Ana');
        $p->update(['estado' => false]);
        $this->get(route('administracion.docentes.index'))->assertOk()->assertDontSee('Pérez, Ana');
        $this->get(route('administracion.docentes.index', ['estado' => 'inactivos']))->assertOk()->assertSee('Pérez, Ana');
        $this->get(route('administracion.docentes.index', ['page' => 999]))->assertRedirect();
    }

    public function test_firma_concurrente_validacion_y_permisos(): void
    {
        $p = $this->docente(); $firma = DocenteController::firma($p); $p->update(['nombre' => 'Actualizado']);
        $url = route('administracion.docentes.edit', $p);
        $this->from($url)->put(route('administracion.docentes.update', $p), ['revision' => $firma] + $this->datos())->assertSessionHasErrors('revision');
        $this->get($url)->assertOk()->assertSee('value="'.$firma.'"', false);
        $this->from(route('administracion.docentes.create'))->post(route('administracion.docentes.store'), $this->datos(['nombre' => [1], 'rut' => [1]]))->assertSessionHasErrors(['nombre', 'rut']);
        $this->get(route('administracion.docentes.create'))->assertOk();
        $this->usuario->revokePermissionTo('administrar usuarios');
        $this->get(route('administracion.docentes.index'))->assertRedirect(); $this->post(route('administracion.docentes.store'), $this->datos())->assertRedirect();
        $this->put(route('administracion.docentes.update', $p), $this->datos())->assertRedirect();
        auth()->logout(); $this->get(route('administracion.docentes.index'))->assertRedirect(route('login'));
    }

    public function test_guardar_sin_cambios_no_duplica_historial_y_fallo_revierte_alta(): void
    {
        $p = $this->docente(); $this->actualizar($p)->assertSessionHasNoErrors(); $this->assertDatabaseCount('historial_acciones', 0);
        $this->withoutExceptionHandling();
        Models\Profesor::created(fn () => DB::statement('DROP TABLE historial_acciones'));
        try { $this->post(route('administracion.docentes.store'), $this->datos(['rut' => '987654321', 'correo_institucional' => 'otra@example.test'])); $this->fail('Se esperaba fallo de auditoría.'); }
        catch (\Illuminate\Database\QueryException $e) { $this->assertStringContainsString('historial_acciones', $e->getMessage()); }
        finally { Models\Profesor::flushEventListeners(); }
        $this->assertDatabaseCount('profesores', 1);
    }
}

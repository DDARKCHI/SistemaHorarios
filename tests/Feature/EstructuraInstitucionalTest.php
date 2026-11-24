<?php

namespace Tests\Feature;

use App\Models;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Tests\IsolatedDatabaseTestCase;

class EstructuraInstitucionalTest extends IsolatedDatabaseTestCase
{
    private Models\User $usuario;
    private int $universidad;
    private Models\Sede $sede;

    protected function setUp(): void
    {
        parent::setUp();
        $this->universidad = (int) DB::table('universidades')->value('id');
        $this->usuario = Models\User::create(['name' => 'Administración', 'rut' => '17777777', 'email' => 'estructura@example.test', 'password' => 'prueba']);
        $this->usuario->givePermissionTo(Permission::findOrCreate('administrar usuarios', 'web'));
        $this->actingAs($this->usuario);
        $this->sede = Models\Sede::create(['universidad_id' => $this->universidad, 'nombre' => 'Talcahuano', 'codigo' => 'TH', 'estado' => true]);
    }

    private function ruta(string $accion, string $tipo, ?int $id = null): string
    {
        return route('administracion.estructura.'.$accion, array_filter(['tipo' => $tipo, 'id' => $id]));
    }
    private function datosCarrera(array $cambios = []): array
    {
        return $cambios + ['universidad_id' => $this->universidad, 'nombre' => 'Informática', 'codigo' => 'INF', 'sede_id' => $this->sede->id, 'estado' => 1];
    }
    private function carrera(): Models\Carrera
    {
        return Models\Carrera::create($this->datosCarrera());
    }
    private function seccion(Models\Carrera $carrera, bool $vigente = true): Models\Seccion
    {
        $p = Models\PeriodoAcademico::create(['universidad_id' => $this->universidad, 'nombre' => '2026-II', 'anio' => 2026, 'numero_semestre' => 2, 'estado' => $vigente]);
        $a = Models\Asignatura::create(['universidad_id' => $this->universidad, 'codigo' => 'BACK', 'nombre' => 'Backend']);
        $s = Models\Seccion::create(['asignatura_id' => $a->id, 'periodo_academico_id' => $p->id, 'codigo' => '11', 'estado_activo' => true]);
        $s->carreras()->attach($carrera->id); return $s;
    }

    public function test_crea_y_edita_sede_y_registra_historial_sin_eliminar_datos(): void
    {
        $this->get($this->ruta('create', 'sedes'))->assertOk()->assertSee('Crear sede');
        $datos = ['universidad_id' => $this->universidad, 'nombre' => '  Concepción  ', 'codigo' => ' CCP ', 'direccion' => 'Calle  123', 'estado' => 1];
        $this->post($this->ruta('store', 'sedes'), $datos)->assertSessionHasNoErrors();
        $sede = Models\Sede::where('codigo', 'CCP')->firstOrFail();
        $this->assertSame('Concepción', $sede->nombre); $this->assertSame('Calle 123', $sede->direccion);
        $this->get($this->ruta('edit', 'sedes', $sede->id))->assertOk()->assertSee('Guardar cambios');
        $datos['nombre'] = 'Concepción Centro';
        $this->put($this->ruta('update', 'sedes', $sede->id), $datos)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('historial_acciones', 2);
        $this->assertDatabaseCount('sedes', 2);
    }

    public function test_carrera_multisede_incluye_principal_y_conserva_facultad(): void
    {
        $segunda = Models\Sede::create(['universidad_id' => $this->universidad, 'nombre' => 'Concepción', 'estado' => true]);
        $f = Models\FacultadInstituto::create(['universidad_id' => $this->universidad, 'nombre' => 'Instituto', 'estado' => true]);
        $this->get($this->ruta('create', 'carreras'))->assertOk()->assertSee('Sedes de la carrera');
        $this->post($this->ruta('store', 'carreras'), $this->datosCarrera(['sedes' => [$segunda->id], 'facultad_instituto_id' => $f->id, 'regimen' => 'Semestral']))->assertSessionHasNoErrors();
        $c = Models\Carrera::sole();
        $this->assertSame($this->sede->id, $c->sede_id); $this->assertSame($f->id, $c->facultad_instituto_id);
        $this->assertEqualsCanonicalizing([$this->sede->id, $segunda->id], $c->sedes->pluck('id')->all());
        $this->get($this->ruta('edit', 'carreras', $c->id))->assertOk()->assertSee('Concepción');
        $this->get($this->ruta('index', 'carreras'))->assertOk()->assertSee('Informática')->assertSee('Talcahuano');
    }

    public function test_rechaza_sedes_y_facultades_de_otra_institucion(): void
    {
        $otra = Models\Universidad::create(['nombre' => 'Otra universidad', 'sigla' => 'OTR', 'estado' => true]);
        $sede = Models\Sede::create(['universidad_id' => $otra->id, 'nombre' => 'Otra sede', 'estado' => true]);
        $facultad = Models\FacultadInstituto::create(['universidad_id' => $otra->id, 'nombre' => 'Otra facultad', 'estado' => true]);
        $this->post($this->ruta('store', 'carreras'), $this->datosCarrera(['sede_id' => $sede->id]))->assertSessionHasErrors('sedes');
        $this->post($this->ruta('store', 'carreras'), $this->datosCarrera(['sedes' => [$sede->id]]))->assertSessionHasErrors('sedes');
        $this->post($this->ruta('store', 'carreras'), $this->datosCarrera(['facultad_instituto_id' => $facultad->id]))->assertSessionHasErrors('facultad_instituto_id');
        $this->assertDatabaseCount('carreras', 0); $this->assertDatabaseCount('carreras_sedes', 0);
    }

    public function test_nombres_y_codigos_duplicados_incluyen_inactivos_y_no_bloquean_otra_institucion(): void
    {
        $this->post($this->ruta('store', 'sedes'), ['universidad_id' => $this->universidad, 'nombre' => ' TALCAHUANO ', 'codigo' => 'OTRO', 'estado' => 1])->assertSessionHasErrors('nombre');
        $this->post($this->ruta('store', 'sedes'), ['universidad_id' => $this->universidad, 'nombre' => 'Otro nombre', 'codigo' => 'th', 'estado' => 1])->assertSessionHasErrors('codigo');
        $this->sede->update(['estado' => false]);
        $this->post($this->ruta('store', 'sedes'), ['universidad_id' => $this->universidad, 'nombre' => 'Talcahuano', 'estado' => 1])->assertSessionHasErrors('nombre');
        $otra = Models\Universidad::create(['nombre' => 'Otra universidad', 'sigla' => 'OTR', 'estado' => true]);
        $this->post($this->ruta('store', 'sedes'), ['universidad_id' => $otra->id, 'nombre' => 'Talcahuano', 'codigo' => 'TH', 'estado' => 1])->assertSessionHasNoErrors();
    }

    public function test_no_cambia_institucion_del_registro_existente(): void
    {
        $otra = Models\Universidad::create(['nombre' => 'Otra universidad', 'sigla' => 'OTR', 'estado' => true]);
        $this->put($this->ruta('update', 'sedes', $this->sede->id), ['universidad_id' => $otra->id, 'nombre' => 'Talcahuano', 'estado' => 1])->assertSessionHasErrors('universidad_id');
        $this->assertSame($this->universidad, $this->sede->fresh()->universidad_id);
    }

    public function test_no_quita_sede_historica_de_carrera_con_secciones_pero_permite_agregar(): void
    {
        $c = $this->carrera(); $this->seccion($c, false);
        $otra = Models\Sede::create(['universidad_id' => $this->universidad, 'nombre' => 'Concepción', 'estado' => true]);
        $this->put($this->ruta('update', 'carreras', $c->id), $this->datosCarrera(['sedes' => [$otra->id]]))->assertSessionHasNoErrors();
        $this->put($this->ruta('update', 'carreras', $c->id), $this->datosCarrera(['sede_id' => $otra->id, 'sedes' => [$otra->id]]))->assertSessionHasErrors('sedes');
        $this->assertEqualsCanonicalizing([$this->sede->id, $otra->id], $c->sedes()->pluck('sedes.id')->all());
        $this->assertDatabaseCount('secciones_carreras', 1);
    }

    public function test_desactivacion_de_carrera_protege_periodos_vigentes(): void
    {
        $c = $this->carrera(); $s = $this->seccion($c);
        $this->put($this->ruta('update', 'carreras', $c->id), $this->datosCarrera(['estado' => 0]))->assertSessionHasErrors('estado');
        $s->periodoAcademico->update(['estado' => false]);
        $this->put($this->ruta('update', 'carreras', $c->id), $this->datosCarrera(['estado' => 0]))->assertSessionHasNoErrors();
        $this->assertFalse((bool) $c->fresh()->estado); $this->assertDatabaseCount('secciones_carreras', 1);
    }

    public function test_desactivacion_de_sede_no_deja_carreras_o_espacios_activos(): void
    {
        $c = $this->carrera();
        $datos = ['universidad_id' => $this->universidad, 'nombre' => 'Talcahuano', 'codigo' => 'TH', 'estado' => 0];
        $this->put($this->ruta('update', 'sedes', $this->sede->id), $datos)->assertSessionHasErrors('estado');
        $c->update(['estado' => false]);
        $tipo = Models\TipoSala::create(['nombre' => 'Sala']);
        $sala = Models\Sala::create(['universidad_id' => $this->universidad, 'sede_id' => $this->sede->id, 'tipo_sala_id' => $tipo->id, 'codigo' => 'TH-01', 'capacidad' => 20, 'estado' => true]);
        $this->put($this->ruta('update', 'sedes', $this->sede->id), $datos)->assertSessionHasErrors('estado');
        $sala->update(['estado' => false]);
        $this->put($this->ruta('update', 'sedes', $this->sede->id), $datos)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('salas', 1); $this->assertDatabaseCount('carreras', 1);
    }

    public function test_filtros_paginacion_y_busqueda_literal(): void
    {
        Models\Sede::create(['universidad_id' => $this->universidad, 'nombre' => 'Sede 100%', 'estado' => false]);
        $this->get($this->ruta('index', 'sedes').'?q=100%25&estado=todos')->assertOk()->assertSee('Sede 100%')->assertDontSee('Editar sede inexistente');
        $this->get($this->ruta('index', 'sedes').'?page=999')->assertRedirect();
        $this->get($this->ruta('index', 'sedes').'?estado=incorrecto')->assertSessionHasErrors('estado');
    }

    public function test_sin_permiso_o_sesion_no_consulta_ni_modifica(): void
    {
        $this->usuario->revokePermissionTo('administrar usuarios');
        foreach (['sedes', 'carreras'] as $tipo) {
            $this->get($this->ruta('index', $tipo))->assertRedirect(); $this->get($this->ruta('create', $tipo))->assertRedirect();
            $this->post($this->ruta('store', $tipo), [])->assertRedirect();
        }
        $this->put($this->ruta('update', 'sedes', $this->sede->id), [])->assertRedirect();
        auth()->logout(); $this->get($this->ruta('index', 'sedes'))->assertRedirect(route('login'));
    }

    public function test_conserva_vinculos_inactivos_previos_pero_no_admite_nuevos(): void
    {
        $c = $this->carrera();
        $f = Models\FacultadInstituto::create(['universidad_id' => $this->universidad, 'nombre' => 'Instituto histórico', 'estado' => false]);
        $c->update(['facultad_instituto_id' => $f->id]);
        $this->sede->update(['estado' => false]);
        $datos = $this->datosCarrera(['facultad_instituto_id' => $f->id]);
        $this->put($this->ruta('update', 'carreras', $c->id), $datos)->assertSessionHasNoErrors();
        $this->get($this->ruta('edit', 'carreras', $c->id))->assertOk()->assertSee('Instituto histórico');
        $this->post($this->ruta('store', 'carreras'), $this->datosCarrera(['nombre' => 'Nueva', 'codigo' => 'NUE']))->assertSessionHasErrors('sedes');
        $this->sede->update(['estado' => true]);
        $this->post($this->ruta('store', 'carreras'), $this->datosCarrera(['nombre' => 'Nueva', 'codigo' => 'NUE', 'facultad_instituto_id' => $f->id]))->assertSessionHasErrors('facultad_instituto_id');
        $this->assertDatabaseCount('carreras', 1);
    }

    public function test_desactivacion_de_sede_revisa_carreras_multisede_y_laboratorios_moviles(): void
    {
        $otra = Models\Sede::create(['universidad_id' => $this->universidad, 'nombre' => 'Concepción', 'estado' => true]);
        $c = $this->carrera(); $c->sedes()->attach($otra->id);
        $datos = ['universidad_id' => $this->universidad, 'nombre' => 'Concepción', 'estado' => 0];
        $this->put($this->ruta('update', 'sedes', $otra->id), $datos)->assertSessionHasErrors('estado');
        $c->update(['estado' => false]);
        $m = Models\LaboratorioMovil::create(['universidad_id' => $this->universidad, 'sede_id' => $otra->id, 'codigo' => 'MOV-01', 'nombre' => 'Carro de computadores', 'capacidad' => 20, 'estado' => true]);
        $this->put($this->ruta('update', 'sedes', $otra->id), $datos)->assertSessionHasErrors('estado');
        $m->update(['estado' => false]);
        $this->put($this->ruta('update', 'sedes', $otra->id), $datos)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('carreras_sedes', ['carrera_id' => $c->id, 'sede_id' => $otra->id]);
        $this->assertDatabaseHas('laboratorios_moviles', ['id' => $m->id]);
    }

    public function test_permite_corregir_sedes_sin_historial_y_no_duplica_auditoria_sin_cambios(): void
    {
        $c = $this->carrera();
        $otra = Models\Sede::create(['universidad_id' => $this->universidad, 'nombre' => 'Concepción', 'estado' => true]);
        $c->sedes()->attach([$this->sede->id, $otra->id]);
        $this->put($this->ruta('update', 'carreras', $c->id), $this->datosCarrera())->assertSessionHasNoErrors();
        $this->assertSame([$this->sede->id], $c->sedes()->pluck('sedes.id')->all());
        $this->assertDatabaseCount('historial_acciones', 1);
        $this->put($this->ruta('update', 'carreras', $c->id), $this->datosCarrera())->assertSessionHasNoErrors();
        $this->assertDatabaseCount('historial_acciones', 1);
    }

    public function test_validacion_de_campos_malformados_se_muestra_sin_romper_formulario(): void
    {
        $url = $this->ruta('create', 'carreras');
        $this->from($url)->post($this->ruta('store', 'carreras'), ['nombre' => ['inválido'], 'universidad_id' => [1], 'sedes' => 'inválido', 'estado' => [1]])
            ->assertRedirect($url)->assertSessionHasErrors(['nombre', 'universidad_id', 'sedes', 'estado']);
        $this->get($url)->assertOk()->assertSee('Crear carrera');
    }

    public function test_no_bloquea_edicion_de_duplicados_heredados_y_si_rechaza_nuevos_codigos_duplicados(): void
    {
        $c = $this->carrera();
        Models\Carrera::create($this->datosCarrera());
        $this->put($this->ruta('update', 'carreras', $c->id), $this->datosCarrera(['regimen' => 'Semestral']))->assertSessionHasNoErrors();
        $this->post($this->ruta('store', 'carreras'), $this->datosCarrera(['nombre' => 'Otra carrera', 'codigo' => 'inf']))->assertSessionHasErrors('codigo');
        $this->assertSame('Semestral', $c->fresh()->regimen);
    }

    public function test_fallo_auditoria_revierte_carrera_y_pivotes(): void
    {
        $this->withoutExceptionHandling();
        Models\Carrera::created(fn () => DB::statement('DROP TABLE historial_acciones'));
        try { $this->post($this->ruta('store', 'carreras'), $this->datosCarrera()); $this->fail('Se esperaba fallo de auditoría.'); }
        catch (\Illuminate\Database\QueryException $e) { $this->assertStringContainsString('historial_acciones', $e->getMessage()); }
        finally { Models\Carrera::flushEventListeners(); }
        $this->assertDatabaseCount('carreras', 0); $this->assertDatabaseCount('carreras_sedes', 0);
    }
}

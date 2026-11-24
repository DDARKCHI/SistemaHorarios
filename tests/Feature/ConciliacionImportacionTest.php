<?php

namespace Tests\Feature;

use App\Models;
use App\Services\{ConciliacionOfertaImportada, ProgramacionCargaService};
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{DB, Storage};
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Tests\IsolatedDatabaseTestCase;

class ConciliacionImportacionTest extends IsolatedDatabaseTestCase
{
    private Models\User $usuario;
    private Models\PeriodoAcademico $periodo;
    private Models\Sede $sede;
    private Models\Carrera $carrera;
    private Models\Asignatura $ramo;
    private Models\Seccion $seccion;
    private Models\ActividadSeccion $actividad;
    private Models\GrupoSeccion $grupo;
    private Models\Profesor $profesor;

    protected function setUp(): void
    {
        parent::setUp(); Storage::fake('local');
        $uni = Models\Universidad::firstOrFail()->id;
        $this->usuario = Models\User::create(['name' => 'Coordinación ficticia', 'rut' => '77777777', 'email' => 'importar@example.test', 'password' => 'prueba']);
        $this->usuario->givePermissionTo(Permission::findOrCreate('ver programacion anterior', 'web'), Permission::findOrCreate('importar programacion anterior', 'web')); $this->actingAs($this->usuario);
        $this->periodo = Models\PeriodoAcademico::create(['universidad_id' => $uni, 'nombre' => 'Segundo semestre 2026', 'anio' => 2026, 'numero_semestre' => 2, 'estado' => true]);
        $this->sede = Models\Sede::create(['universidad_id' => $uni, 'nombre' => 'Concepción', 'codigo' => null, 'estado' => true]);
        $this->carrera = Models\Carrera::create(['universidad_id' => $uni, 'nombre' => 'Informática', 'codigo' => 'INF', 'sede_id' => $this->sede->id, 'estado' => true]);
        $this->ramo = Models\Asignatura::create(['universidad_id' => $uni, 'nombre' => 'Backend manual', 'codigo' => 'inf101', 'horas_catedra' => 2, 'estado' => true]);
        $this->seccion = Models\Seccion::create(['periodo_academico_id' => $this->periodo->id, 'asignatura_id' => $this->ramo->id, 'codigo' => '11', 'nombre' => 'Nombre manual', 'semestre' => 2, 'cupos' => 20, 'inscritos' => 18, 'estado_activo' => true]);
        $this->seccion->carreras()->attach($this->carrera->id);
        $this->actividad = $this->seccion->actividades()->create(['tipo' => 'Cátedra', 'cantidad_horas' => 2, 'cantidad_estudiantes' => 18, 'estado' => true]);
        $this->grupo = $this->actividad->grupos()->create(['codigo' => 'Práctico A', 'numero' => 1, 'cupos' => 20, 'inscritos' => 18, 'estado_activo' => true]);
        $this->profesor = Models\Profesor::create(['universidad_id' => $uni, 'nombre' => 'Nombre conservado', 'apellido' => 'Apellido manual', 'rut' => '12345678', 'correo_institucional' => 'docente@example.test', 'estado' => true]);
        $this->seccion->profesores()->create(['profesor_id' => $this->profesor->id, 'estado' => true, 'es_principal' => true]);
        $this->grupo->profesores()->create(['profesor_id' => $this->profesor->id, 'estado' => true, 'es_principal' => true]);
        $tipo = Models\TipoSala::create(['nombre' => 'Aula', 'estado' => true]);
        Models\Sala::create(['universidad_id' => $uni, 'sede_id' => $this->sede->id, 'tipo_sala_id' => $tipo->id, 'codigo' => 'CON-1', 'capacidad' => 40, 'estado' => true, 'disponible' => true]);
    }
    public function registro(array $cambios = []): array
    {
        return $cambios + ['ID_CURSO' => 'EXT-123', 'COD_RAMO' => 'INF101', 'RAMO_NOMBRE' => 'Nombre de archivo', 'SECC' => '11', 'SEMESTRE' => '2026-2', 'SEDE' => 'Concepción',
            'PROF_RESP' => '12.345.678', 'NOM_PROF_RESP' => 'Nombre De Archivo', 'CORREO_INST' => 'DOCENTE@example.test', 'UA' => 'Instituto', 'NOM_CARR' => 'Informática', 'COD_CARR' => 'INF',
            'CUPO' => '30', 'INSCRITOS' => '27', 'MODALIDAD' => 'Presencial', 'CATEGORIA' => 'Obligatoria', 'TIPO_DICTACION' => 'Semestral', 'COD_SEDE' => 'CON', 'ESTADO_CURSO' => 'Abierto', 'ID_RAMO' => '101',
            'NIVEL_UA' => '4', 'MOD_CATEDRA' => '6', 'HORARIO' => 'ca: LU.1/G:1 (CON-1) MA.1/G:1 (CON-1)'];
    }
    private function plan(array $cambios = []): array { return (new ConciliacionOfertaImportada)->analizar([$this->registro($cambios)]); }
    private function cargar(array $cambios = []): Models\VersionPlanificacion
    {
        $plan = $this->plan($cambios);
        return app(ProgramacionCargaService::class)->cargar([$this->registro($cambios)], $this->usuario->id, 'ficticio.csv', $plan['firma']);
    }
    public function analizarHttp(array $cambios = [])
    {
        $r = array_change_key_case($this->registro($cambios), CASE_LOWER); $f = fopen('php://temp', 'r+');
        fputcsv($f, array_keys($r), escape: ''); fputcsv($f, array_values($r), escape: ''); rewind($f); $csv = stream_get_contents($f); fclose($f);
        return $this->post(route('programacion.importar.analizar'), ['archivo' => UploadedFile::fake()->createWithContent('ficticio.csv', $csv)->mimeType('text/csv')])->assertOk();
    }
    private function confirmarHttp($r)
    {
        return $this->post(route('programacion.importar.confirmar'), ['ruta_temporal' => $r->viewData('resultado')['ruta_temporal']]);
    }

    public function test_reconoce_periodo_ramo_seccion_y_grupo_sin_modificar_definiciones_manuales(): void
    {
        $antes = collect([$this->periodo, $this->sede, $this->carrera, $this->ramo, $this->seccion, $this->actividad, $this->grupo, $this->profesor])->map(fn ($m) => $m->fresh()->getAttributes())->all();
        $p = $this->plan(); $this->assertSame([], $p['errores']);
        foreach (['carreras', 'asignaturas', 'secciones', 'actividades', 'grupos', 'profesores'] as $tipo) $this->assertSame(['nuevos' => 0, 'reutilizados' => 1], $p['resumen'][$tipo]);
        $v = $this->cargar(); $this->assertSame($this->periodo->id, $v->periodo_academico_id); $this->assertSame(2, $v->horarios()->count());
        $despues = collect([$this->periodo, $this->sede, $this->carrera, $this->ramo, $this->seccion, $this->actividad, $this->grupo, $this->profesor])->map(fn ($m) => $m->fresh()->getAttributes())->all();
        $this->assertSame($antes, $despues); $this->assertSame([$this->grupo->id], $v->horarios()->pluck('grupo_seccion_id')->unique()->all());
        $this->assertDatabaseCount('secciones', 1); $this->assertDatabaseCount('grupos_seccion', 1); $this->assertDatabaseCount('profesores', 1); $this->assertDatabaseCount('sedes', 1);
        $this->assertDatabaseCount('historial_acciones', 1);
    }
    public function test_vista_previa_y_confirmacion_http_reutilizan_datos_y_consumen_el_pendiente(): void
    {
        $r = $this->analizarHttp()->assertSee('Qué ocurre al guardar')
            ->assertSee('se creará una nueva versión de la programación anterior')->assertSee('Confirmar carga');
        $this->assertSame(0, $r->viewData('resultado')['comparacion']['secciones']['nuevas']);
        $this->assertDatabaseCount('versiones_planificacion', 0);
        $this->confirmarHttp($r)->assertRedirect()->assertSessionHas('success');
        $this->assertSame([], session('importaciones_pendientes')); $this->assertDatabaseCount('secciones', 1);
    }
    public function test_importar_no_reasigna_profesores_de_plantilla_ni_horarios_manuales(): void
    {
        $actual = Models\VersionPlanificacion::create(['periodo_academico_id' => $this->periodo->id, 'tipo' => 'semestral', 'nombre' => 'Actual', 'version' => 0.1, 'estado' => 'Borrador', 'es_actual' => true]);
        $h = $actual->horarios()->create(['actividad_seccion_id' => $this->actividad->id, 'grupo_seccion_id' => $this->grupo->id, 'dia_id' => Models\Dia::first()->id, 'bloque_horario_id' => Models\BloqueHorario::first()->id, 'estado' => true]);
        $antes = $h->fresh()->getAttributes(); $plantilla = $this->seccion->profesores()->get()->toJson();
        $v = $this->cargar(['PROF_RESP' => '87654321', 'CORREO_INST' => 'otro@example.test']);
        $this->assertSame($antes, $h->fresh()->getAttributes()); $this->assertTrue($actual->fresh()->es_actual);
        $this->assertSame($plantilla, $this->seccion->profesores()->get()->toJson());
        $this->assertSame(2, $v->horarios()->whereHas('profesores.profesor', fn ($p) => $p->where('rut', '87654321'))->count());
        $this->assertSame($this->profesor->id, $this->grupo->profesores()->sole()->profesor_id);
    }
    public function test_una_nueva_importacion_no_duplica_oferta_y_conserva_version_anterior(): void
    {
        $primera = $this->cargar(); $horarios = $primera->horarios()->get()->toJson();
        $segunda = $this->cargar(['CUPO' => '31']); $this->assertSame(0.2, (float) $segunda->version);
        $this->assertSame($horarios, $primera->horarios()->get()->toJson()); $this->assertFalse($primera->fresh()->es_actual);
        $this->assertDatabaseCount('secciones', 1); $this->assertDatabaseCount('actividades_seccion', 1); $this->assertDatabaseCount('grupos_seccion', 1);
    }
    public function test_archivo_de_dos_periodos_no_se_carga_parcialmente(): void
    {
        $filas = [$this->registro(), $this->registro(['SEMESTRE' => '2027-1'])];
        $p = (new ConciliacionOfertaImportada)->analizar($filas); $this->assertNotEmpty($p['errores']);
        try { app(ProgramacionCargaService::class)->cargar($filas, $this->usuario->id, 'ficticio.csv'); $this->fail(); } catch (ValidationException) { $this->assertDatabaseCount('versiones_planificacion', 0); }
    }
    public function test_mismo_codigo_de_seccion_en_ramos_distintos_no_comparte_registro(): void
    {
        $filas = [$this->registro(), $this->registro(['COD_RAMO' => 'INF202', 'RAMO_NOMBRE' => 'Otro ramo'])];
        $v = app(ProgramacionCargaService::class)->cargar($filas, $this->usuario->id, 'ficticio.csv');
        $this->assertDatabaseCount('secciones', 2); $this->assertSame(2, $v->horarios()->pluck('actividad_seccion_id')->unique()->count());
    }
    public function test_conflicto_de_identificador_se_muestra_sin_guardar_archivo_pendiente(): void
    {
        $this->seccion->update(['identificador_externo' => 'OTRA-SECCION']);
        $r = $this->analizarHttp()->assertSee('otro identificador externo')->assertDontSee('Confirmar carga');
        $this->assertArrayNotHasKey('ruta_temporal', $r->viewData('resultado')); $this->assertSame([], Storage::disk('local')->allFiles());
        $this->assertDatabaseCount('versiones_planificacion', 0);
    }
    public function test_ambiguedad_de_secciones_y_grupos_no_se_resuelve_escogiendo_el_primero(): void
    {
        $s = $this->seccion->replicate(); $s->setRelations([]); $s->save();
        $this->assertNotEmpty($this->plan()['errores']); $s->delete();
        $this->actividad->grupos()->create(['codigo' => 'G:1', 'numero' => 1, 'estado_activo' => true]);
        $this->assertNotEmpty($this->plan()['errores']);
    }
    public function test_rut_y_correo_de_docentes_diferentes_no_se_fusionan(): void
    {
        Models\Profesor::create(['universidad_id' => $this->periodo->universidad_id, 'rut' => '99999999', 'nombre' => 'Otro', 'apellido' => 'Ficticio', 'correo_institucional' => 'otro@example.test']);
        $this->assertNotEmpty($this->plan(['CORREO_INST' => 'otro@example.test'])['errores']);
        $this->assertNotEmpty($this->plan(['PROF_RESP' => '88888888'])['errores']);
        $this->assertDatabaseCount('profesores', 2);
    }
    public function test_docente_manual_solo_con_correo_se_reutiliza_sin_reescribir_identificador(): void
    {
        $this->profesor->update(['rut' => null]); $this->cargar();
        $this->assertDatabaseCount('profesores', 1); $this->assertNull($this->profesor->fresh()->rut);
    }
    public function test_carrera_sede_o_nombre_con_codigo_conflictivo_exige_revision(): void
    {
        $this->assertNotEmpty($this->plan(['COD_CARR' => 'OTRO'])['errores']);
        $this->sede->update(['codigo' => 'OTRA']); $this->assertNotEmpty($this->plan()['errores']);
    }
    public function test_sede_adicional_no_se_agrega_automaticamente_a_carrera_existente(): void
    {
        $otra = Models\Sede::create(['universidad_id' => $this->periodo->universidad_id, 'codigo' => 'OTR', 'nombre' => 'Otra sede', 'estado' => true]);
        $this->assertNotEmpty($this->plan(['SEDE' => $otra->nombre, 'COD_SEDE' => $otra->codigo])['errores']);
        $this->assertSame(0, $this->carrera->sedes()->count());
    }
    public function test_carrera_ajena_no_se_agrega_automaticamente_a_seccion_existente(): void
    {
        Models\Carrera::create(['universidad_id' => $this->periodo->universidad_id, 'codigo' => 'OTR', 'nombre' => 'Otra carrera', 'sede_id' => $this->sede->id, 'estado' => true]);
        $this->assertNotEmpty($this->plan(['COD_CARR' => 'OTR', 'NOM_CARR' => 'Otra carrera'])['errores']);
        $this->assertSame(1, $this->seccion->carreras()->count());
    }
    public function test_periodo_archivado_o_institucion_inactiva_no_permiten_carga(): void
    {
        $this->periodo->update(['estado' => false]); $this->assertNotEmpty($this->plan()['errores']);
        $this->periodo->update(['estado' => true]); Models\Universidad::query()->update(['estado' => false]); $this->assertNotEmpty($this->plan()['errores']);
    }
    public function test_oferta_cerrada_se_reutiliza_sin_cambios_pero_no_se_amplia(): void
    {
        $v = Models\VersionPlanificacion::create(['periodo_academico_id' => $this->periodo->id, 'tipo' => 'semestral', 'version' => 0.1, 'nombre' => 'Publicación', 'estado' => 'Publicado', 'es_actual' => true]);
        $antes = $v->fresh()->getAttributes(); $this->cargar(); $this->assertSame($antes, $v->fresh()->getAttributes());
        foreach ([['SECC' => '22'], ['HORARIO' => 'la: LU.1/G:1'], ['HORARIO' => 'ca: LU.1/G:2']] as $cambio) $this->assertNotEmpty($this->plan($cambio)['errores']);
    }
    public function test_cambio_manual_tras_analizar_obliga_a_repetir_la_vista_previa(): void
    {
        $r = $this->analizarHttp(); $this->grupo->update(['cupos' => 22]);
        $this->confirmarHttp($r)->assertRedirect()->assertSessionHas('error', fn ($e) => str_contains($e, 'cambiaron'));
        $this->assertDatabaseCount('versiones_planificacion', 0);
    }
    public function test_modificar_el_archivo_sin_cambiar_columnas_no_elude_el_analisis(): void
    {
        $r = $this->analizarHttp(); $ruta = $r->viewData('resultado')['ruta_temporal'];
        Storage::disk('local')->put($ruta, str_replace('EXT-123', 'EXT-999', Storage::disk('local')->get($ruta)));
        $this->confirmarHttp($r)->assertSessionHas('error', fn ($e) => str_contains($e, 'archivo cambió'));
        $this->assertDatabaseCount('versiones_planificacion', 0);
    }
    public function test_vencimiento_retira_solo_el_archivo_de_esa_sesion(): void
    {
        $r = $this->analizarHttp(); $ruta = $r->viewData('resultado')['ruta_temporal']; $this->travel(31)->minutes();
        $this->confirmarHttp($r)->assertSessionHas('error'); Storage::disk('local')->assertMissing($ruta);
        $this->assertSame([], session('importaciones_pendientes')); $this->assertDatabaseCount('versiones_planificacion', 0);
    }
    public function test_fallo_auditoria_revierte_version_nueva_y_estado_de_anterior(): void
    {
        $anterior = $this->cargar(); $antes = $anterior->fresh()->getAttributes();
        DB::unprepared("CREATE TRIGGER falla_importacion BEFORE INSERT ON historial_acciones BEGIN SELECT RAISE(ABORT, 'fallo simulado'); END");
        try { $this->cargar(['CUPO' => '31']); $this->fail(); } catch (\Illuminate\Database\QueryException) { $this->assertDatabaseCount('versiones_planificacion', 1); }
        $this->assertSame($antes, $anterior->fresh()->getAttributes());
    }
    public function test_grupo_con_cero_inicial_se_concilia_con_su_numero_sin_duplicarlo(): void
    {
        $this->cargar(['HORARIO' => 'ca: LU.1/G:01 (CON-1) MA.1/G:1 (CON-1)']);
        $this->assertDatabaseCount('grupos_seccion', 1); $this->assertDatabaseCount('horarios', 2);
    }
    public function test_oferta_inactiva_se_conserva_sin_reactivarla(): void
    {
        $this->seccion->update(['estado_activo' => false]); $this->grupo->update(['estado_activo' => false]);
        $this->cargar(); $this->assertFalse($this->seccion->fresh()->estado_activo); $this->assertFalse($this->grupo->fresh()->estado_activo);
    }
    public function test_identificadores_distintos_en_el_mismo_archivo_bloquean_la_seccion(): void
    {
        $p = (new ConciliacionOfertaImportada)->analizar([$this->registro(), $this->registro(['ID_CURSO' => 'EXT-999'])]);
        $this->assertNotEmpty($p['errores']);
    }
    public function test_archivo_identico_no_crea_otra_version_y_reconoce_periodo_de_nombre_manual(): void
    {
        $v = $this->cargar(); $r = $this->analizarHttp()->assertDontSee('Confirmar carga')->assertSee('otra versión idéntica');
        $c = $r->viewData('resultado')['comparacion'];
        $this->assertSame($v->id, $c['version_anterior']['id']);
        $this->assertSame(0, $c['grupos']['nuevos']); $this->assertSame(0, $c['horarios']['nuevos']);
        try { $this->cargar(); $this->fail(); } catch (ValidationException) { $this->assertDatabaseCount('versiones_planificacion', 1); }
    }
    public function test_cambio_solo_de_sala_se_puede_confirmar_sin_cambiar_la_version_previa(): void
    {
        $sala = Models\Sala::firstOrFail()->replicate(); $sala->codigo = 'CON-2'; $sala->save();
        $primera = $this->cargar(); $antes = $primera->horarios()->first()->asignacionesSalas()->sole()->sala_id;
        $r = $this->analizarHttp(['HORARIO' => 'ca: LU.1/G:1 (CON-2) MA.1/G:1 (CON-2)'])->assertSee('Confirmar carga');
        $this->confirmarHttp($r)->assertSessionHas('success');
        $this->assertSame($antes, $primera->horarios()->first()->asignacionesSalas()->sole()->sala_id);
        $nueva = Models\VersionPlanificacion::latest('id')->firstOrFail(); $this->assertSame($sala->id, $nueva->horarios()->first()->asignacionesSalas()->sole()->sala_id);
    }
    public function test_no_infiere_docentes_manuales_si_el_archivo_no_informa_ninguno(): void
    {
        $this->assertNotEmpty($this->plan(['PROF_RESP' => '', 'CORREO_INST' => ''])['errores']);
    }
    public function test_responsables_generales_del_archivo_se_materializan_en_modulos_sin_detalle_docente(): void
    {
        $filas = [$this->registro(), $this->registro(['PROF_RESP' => '87654321', 'CORREO_INST' => 'otro@example.test'])];
        $v = app(ProgramacionCargaService::class)->cargar($filas, $this->usuario->id, 'ficticio.csv');
        $this->assertSame(2, $v->horarios()->count()); $this->assertSame(2, $v->horarios()->first()->profesores()->count());
        $p = (new ConciliacionOfertaImportada)->analizar(array_reverse($filas)); $this->assertTrue($p['sin_cambios']);
    }
    public function test_detalle_docente_prevalece_sobre_responsable_general_del_archivo(): void
    {
        $filas = [$this->registro(), $this->registro(['PROF_RESP' => '87654321', 'CORREO_INST' => 'otro@example.test', 'HORARIO_PROFESOR' => 'ca: LU.1/G:1'])];
        $v = app(ProgramacionCargaService::class)->cargar($filas, $this->usuario->id, 'ficticio.csv');
        $lunes = Models\Dia::where('numero', 1)->value('id');
        $this->assertSame(1, $v->horarios()->where('dia_id', $lunes)->sole()->profesores()->count());
        $this->assertSame('87654321', $v->horarios()->where('dia_id', $lunes)->sole()->profesores()->sole()->profesor->rut);
    }
    public function test_grupo_con_codigo_y_numero_contradictorios_se_rechaza(): void
    {
        $this->grupo->update(['codigo' => 'G:1', 'numero' => 2]); $this->assertNotEmpty($this->plan()['errores']);
    }
    public function test_ramos_nuevos_con_codigo_en_distinta_caja_se_crean_una_sola_vez(): void
    {
        $filas = [$this->registro(['COD_RAMO' => 'nuevo101']), $this->registro(['COD_RAMO' => 'NUEVO101'])];
        app(ProgramacionCargaService::class)->cargar($filas, $this->usuario->id, 'ficticio.csv');
        $this->assertDatabaseCount('asignaturas', 2); $this->assertDatabaseCount('secciones', 2);
    }
    public function test_correo_compartido_por_rut_distintos_del_archivo_no_se_fusiona(): void
    {
        $this->profesor->update(['rut' => null]);
        $p = (new ConciliacionOfertaImportada)->analizar([$this->registro(), $this->registro(['PROF_RESP' => '87654321'])]);
        $this->assertNotEmpty($p['errores']);
    }
    public function test_rut_informado_en_otra_fila_del_mismo_correo_no_duplica_docente_nuevo(): void
    {
        $filas = [$this->registro(['PROF_RESP' => '', 'CORREO_INST' => 'nuevo@example.test']), $this->registro(['PROF_RESP' => '87654321', 'CORREO_INST' => 'nuevo@example.test'])];
        $p = (new ConciliacionOfertaImportada)->analizar($filas); $this->assertSame(1, $p['resumen']['profesores']['nuevos']);
        $v = app(ProgramacionCargaService::class)->cargar($filas, $this->usuario->id, 'ficticio.csv');
        $this->assertDatabaseCount('profesores', 2); $this->assertSame(1, $v->horarios()->first()->profesores()->count());
        $this->assertSame('87654321', $v->horarios()->first()->profesores()->sole()->profesor->rut);
    }
    public function test_clase_pendiente_sin_docentes_en_archivo_ni_plantillas_se_puede_importar(): void
    {
        $this->seccion->profesores()->delete(); $this->grupo->profesores()->delete();
        $p = $this->plan(['PROF_RESP' => '', 'CORREO_INST' => '']); $this->assertSame([], $p['errores']);
        $v = $this->cargar(['PROF_RESP' => '', 'CORREO_INST' => '']);
        $this->assertSame(2, $v->horarios()->count()); $this->assertSame(0, $v->horarios()->first()->profesores()->count());
    }
    public function test_cambio_de_plantilla_de_grupo_invalida_el_analisis_previo(): void
    {
        $r = $this->analizarHttp(); $this->grupo->profesores()->update(['es_principal' => false]);
        $this->confirmarHttp($r)->assertSessionHas('error', fn ($e) => str_contains($e, 'cambiaron'));
        $this->assertDatabaseCount('versiones_planificacion', 0);
    }
}

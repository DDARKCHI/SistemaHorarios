<?php

namespace Tests\Feature;

use App\Models;
use App\Services\PreparacionGeneracion;
use Illuminate\Support\Facades\{DB, URL};
use Spatie\Permission\Models\Permission;
use Tests\IsolatedDatabaseTestCase;

class ExportacionHorarioPdfTest extends IsolatedDatabaseTestCase
{
    private Models\User $usuario;
    private Models\PeriodoAcademico $periodo;
    private Models\VersionPlanificacion $version;
    private Models\Profesor $profesor;
    private Models\GrupoSeccion $grupo;
    private Models\Horario $horario;

    protected function setUp(): void
    {
        parent::setUp();
        $uni = DB::table('universidades')->value('id');
        $this->usuario = Models\User::create(['name' => 'Planificación', 'rut' => '17777777', 'email' => 'preparacion@example.test', 'password' => 'prueba']);
        foreach (['generar programacion semestral', 'asignar profesores', 'ver programacion semestral'] as $permiso) $this->usuario->givePermissionTo(Permission::findOrCreate($permiso, 'web'));
        $this->actingAs($this->usuario);
        $this->periodo = Models\PeriodoAcademico::create(['universidad_id' => $uni, 'nombre' => '2026-II', 'anio' => 2026, 'numero_semestre' => 2, 'estado' => true]);
        $this->version = Models\VersionPlanificacion::create(['periodo_academico_id' => $this->periodo->id, 'nombre' => 'Semestral', 'tipo' => 'semestral', 'version' => '1.0', 'estado' => 'Borrador', 'es_actual' => true]);
        $asignatura = Models\Asignatura::create(['universidad_id' => $uni, 'codigo' => 'BACK-1', 'nombre' => 'Backend', 'estado' => true]);
        $seccion = Models\Seccion::create(['asignatura_id' => $asignatura->id, 'periodo_academico_id' => $this->periodo->id, 'codigo' => '11', 'cupos' => 20, 'inscritos' => 20, 'estado_activo' => true]);
        $actividad = Models\ActividadSeccion::create(['seccion_id' => $seccion->id, 'tipo' => 'Cátedra', 'cantidad_horas' => 1, 'cantidad_estudiantes' => 20, 'estado' => true]);
        $this->grupo = Models\GrupoSeccion::create(['actividad_seccion_id' => $actividad->id, 'codigo' => 'G:1', 'numero' => 1, 'cupos' => 20, 'inscritos' => 20, 'estado_activo' => true]);
        $this->profesor = Models\Profesor::create(['universidad_id' => $uni, 'nombre' => 'Ana', 'apellido' => 'Pérez', 'rut' => '12345678', 'estado' => true]);
        Models\GrupoProfesor::create(['grupo_seccion_id' => $this->grupo->id, 'profesor_id' => $this->profesor->id, 'estado' => true, 'es_principal' => true]);
        $this->horario = Models\Horario::create(['actividad_seccion_id' => $actividad->id, 'grupo_seccion_id' => $this->grupo->id, 'version_planificacion_id' => $this->version->id,
            'dia_id' => DB::table('dias')->where('numero', 1)->value('id'), 'bloque_horario_id' => DB::table('bloques_horarios')->where('numero', 1)->value('id'), 'estado' => true]);
        $sede = Models\Sede::create(['universidad_id' => $uni, 'nombre' => 'Talcahuano', 'estado' => true]);
        $tipo = Models\TipoSala::create(['nombre' => 'Cátedra', 'estado' => true, 'requiere_equipamiento' => false]);
        $sala = Models\Sala::create(['universidad_id' => $uni, 'sede_id' => $sede->id, 'tipo_sala_id' => $tipo->id, 'codigo' => 'TH-01', 'nombre' => 'TH-01', 'capacidad' => 30, 'estado' => true, 'disponible' => true]);
        Models\AsignacionSala::create(['horario_id' => $this->horario->id, 'sala_id' => $sala->id, 'estado' => true, 'es_principal' => true]);
    }

    private function aprobar(array $datos = []): Models\SolicitudDisponibilidad
    {
        $this->post(route('disponibilidad.store'), ['profesor_id' => $this->profesor->id, 'periodo_academico_id' => $this->periodo->id, 'fecha_limite' => today()->addDays(7)->toDateString()])->assertSessionHasNoErrors();
        $solicitud = Models\SolicitudDisponibilidad::latest('id')->firstOrFail();
        $url = URL::temporarySignedRoute('disponibilidad.publica', $solicitud->fecha_limite->endOfDay(), ['solicitud' => $solicitud->id]);
        $this->post($url, $datos ?: ['bloques' => [$this->horario->dia_id.':'.$this->horario->bloque_horario_id]])->assertSessionHasNoErrors();
        $this->post(route('disponibilidad.revisar', $solicitud), ['decision' => 'aprobar'])->assertSessionHasNoErrors();
        return $solicitud;
    }

    private function publicar(): void
    {
        $this->aprobar();
        foreach (['aprobar', 'publicar'] as $etapa) {
            $r = app(\App\Services\CierreProgramacion::class)->revisar($this->version->fresh());
            $this->post(route('programacion.version.cerrar', $this->version), ['etapa' => $etapa, 'firma' => $r['firma'], 'confirmar' => 1])->assertSessionHasNoErrors();
        }
    }

    public function test_descarga_pdf_real_en_ambos_formatos_y_sin_modificar_publicacion(): void
    {
        $this->publicar(); $cierre = Models\CierreProgramacion::where('etapa', 'Publicado')->sole(); $antes = $cierre->getAttributes();
        foreach (['detalle', 'semanal'] as $formato) {
            $r = $this->get(route('programacion.version.pdf', ['version' => $this->version, 'formato' => $formato]));
            $r->assertOk()->assertHeader('Content-Type', 'application/pdf')->assertHeader('X-Content-Type-Options', 'nosniff');
            $this->assertStringStartsWith('%PDF-', $r->getContent());
            $this->assertGreaterThan(15000, strlen($r->getContent()));
        }
        $this->assertSame($antes, $cierre->fresh()->getAttributes());
        $this->assertDatabaseCount('cierres_programacion', 2);
    }

    public function test_pdf_adopta_la_marca_actual_y_conserva_los_datos_publicados(): void
    {
        $this->publicar(); $cierre = Models\CierreProgramacion::where('etapa', 'Publicado')->sole(); $antes = $cierre->getAttributes();
        Models\ConfiguracionInstitucion::create(['universidad_id' => $this->periodo->universidad_id, 'nombre_sistema' => 'Campus Azul', 'lema_sistema' => 'Planificación', 'color_principal' => '#1752A2', 'color_secundario' => '#172554', 'color_fondo' => '#EFF6FF', 'identidad_activa' => true, 'estado' => true]);
        app(\App\Services\IdentidadVisual::class)->olvidar();
        $filas = app(\App\Services\ConsultaPublicacion::class)->preparar(\Illuminate\Http\Request::create('/', 'GET'), $cierre)['filas'];
        $html = view('programacion.pdf.horario', ['cierre' => $cierre, 'filas' => $filas->map(fn ($f) => $f + ['codigo_horario' => 'ca: LU.1']), 'formato' => 'detalle', 'filtros' => [], 'semanas' => [], 'logo' => ''])->render();
        $this->assertStringContainsString('Horario publicado Campus Azul', $html); $this->assertStringContainsString('solid #1752A2', $html);
        foreach (['detalle', 'semanal'] as $formato) {
            $r = $this->get(route('programacion.version.pdf', ['version' => $this->version, 'formato' => $formato]))->assertOk()->assertHeader('Content-Type', 'application/pdf');
            $this->assertStringStartsWith('%PDF-', $r->getContent());
        }
        $this->assertSame($antes, $cierre->fresh()->getAttributes());
    }

    public function test_filtros_de_consulta_y_exportacion_son_coherentes_y_validan_opciones(): void
    {
        $this->publicar();
        $this->get(route('programacion.version.publicada', ['version' => $this->version, 'curso' => 'BACK-1 · Backend']))->assertOk()->assertSee('PDF semanal')->assertViewHas('horarios', fn ($h) => $h->total() === 1);
        $this->get(route('programacion.version.pdf', ['version' => $this->version, 'curso' => 'otro']))->assertSessionHasErrors('curso');
        $this->get(route('programacion.version.pdf', ['version' => $this->version, 'q' => 'noexiste']))->assertSessionHasErrors('pdf');
        $this->get(route('programacion.version.pdf', ['version' => $this->version, 'formato' => '../ruta']))->assertSessionHasErrors('formato');
    }

    public function test_no_exporta_borradores_o_sin_permisos_o_sesion(): void
    {
        $this->get(route('programacion.version.pdf', $this->version))->assertNotFound();
        $this->publicar();
        $this->usuario->revokePermissionTo('ver programacion semestral');
        $this->get(route('programacion.version.pdf', $this->version))->assertRedirect();
        auth()->logout(); $this->get(route('programacion.version.pdf', $this->version))->assertRedirect(route('login'));
    }

    public function test_limitacion_de_tamano_no_recorta_el_documento_silenciosamente(): void
    {
        $this->publicar(); $cierre = Models\CierreProgramacion::where('etapa', 'Publicado')->sole();
        $datos = $cierre->datos; $datos['filas'] = array_fill(0, 301, $datos['filas'][0]); $cierre->update(['datos' => $datos]);
        $this->get(route('programacion.version.pdf', $this->version))->assertSessionHasErrors('pdf');
    }

    public function test_generacion_usa_datos_conservados_y_escapa_html(): void
    {
        $this->publicar(); $cierre = Models\CierreProgramacion::where('etapa', 'Publicado')->sole();
        $this->profesor->update(['apellido' => 'CAMBIO']); Models\Sala::query()->update(['codigo' => 'CAMBIO']);
        $datos = app(\App\Services\ConsultaPublicacion::class)->preparar(\Illuminate\Http\Request::create('/', 'GET'), $cierre);
        $this->assertStringContainsString('Pérez', $datos['filas']->first()['docentes']);
        $fila = $datos['filas']->first(); $fila['curso'] = '<script>alert(1)</script>';
        $html = view('programacion.pdf.horario', ['cierre' => $cierre, 'filas' => collect([$fila + ['codigo_horario' => 'ca: LU.1/G:1 (TH-01)']]), 'formato' => 'detalle', 'filtros' => [], 'semanas' => [], 'logo' => ''])->render();
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function test_codigo_institucional_conserva_dia_modulo_grupo_y_sala(): void
    {
        $this->assertSame('ca: LU.7/G:1 (TH-02)', \App\Services\ConsultaPublicacion::codigo(['actividad' => 'Cátedra', 'dia' => 'Lunes', 'modulo' => 7, 'grupo' => 'G:1', 'espacios' => 'TH-02']));
    }

    public function test_filtros_de_carrera_sede_y_nivel_utilizan_solo_datos_publicados(): void
    {
        $this->publicar();
        $cierre = Models\CierreProgramacion::where('etapa', 'Publicado')->sole();
        $datos = $cierre->datos; $datos['filas'][0]['carreras'] = 'Informática'; $datos['filas'][0]['nivel'] = 2; $datos['filas'][0]['sedes'] = 'Talcahuano'; $cierre->update(['datos' => $datos]);
        $r = $this->get(route('programacion.version.publicada', ['version' => $this->version, 'carreras' => 'Informática', 'nivel' => '2', 'sedes' => 'Talcahuano']));
        $r->assertOk()->assertViewHas('horarios', fn ($h) => $h->total() === 1);
        $this->get(route('programacion.version.publicada', ['version' => $this->version, 'sedes' => 'Otra sede']))->assertSessionHasErrors('sedes');
    }

    public function test_filtra_por_un_docente_o_carrera_de_una_clase_compartida(): void
    {
        $this->publicar(); $cierre = Models\CierreProgramacion::where('etapa', 'Publicado')->sole();
        $datos = $cierre->datos; $datos['filas'][0]['docentes'] = 'Pérez, Ana / Soto, Luis'; $datos['filas'][0]['carreras'] = 'Informática / Ingeniería'; $cierre->update(['datos' => $datos]);
        $this->get(route('programacion.version.publicada', ['version' => $this->version, 'docentes' => 'Soto, Luis', 'carreras' => 'Ingeniería']))
            ->assertOk()->assertViewHas('horarios', fn ($h) => $h->total() === 1);
    }
}

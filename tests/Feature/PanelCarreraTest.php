<?php

namespace Tests\Feature;

use App\Models;
use App\Support\PermisosSistema;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\IsolatedDatabaseTestCase;

class PanelCarreraTest extends IsolatedDatabaseTestCase
{
    private int $universidad;
    private Models\User $jefatura;
    private Models\PeriodoAcademico $periodo;
    private Models\VersionPlanificacion $version;
    private Models\Carrera $carrera;
    private Models\TipoSala $tipoSala;
    private Models\Profesor $docente;
    private Models\GrupoSeccion $grupo;
    private Models\Horario $horario;
    private int $lunes;
    private int $martes;
    private int $primero;
    private int $segundo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->universidad = DB::table('universidades')->value('id');
        $this->jefatura = $this->usuario('Jefe de carrera', ['ver programacion semestral', 'asignar horarios', 'asignar profesores', 'generar programacion semestral']);
        $this->periodo = Models\PeriodoAcademico::create(['universidad_id' => $this->universidad, 'nombre' => '2026-2', 'anio' => 2026, 'numero_semestre' => 2, 'estado' => true]);
        $this->version = Models\VersionPlanificacion::create(['periodo_academico_id' => $this->periodo->id, 'nombre' => 'Semestral', 'tipo' => 'semestral', 'version' => '0.1', 'estado' => 'Borrador', 'es_actual' => true]);
        $sede = Models\Sede::create(['universidad_id' => $this->universidad, 'nombre' => 'Talcahuano', 'estado' => true]);
        $this->carrera = Models\Carrera::create(['universidad_id' => $this->universidad, 'sede_id' => $sede->id, 'nombre' => 'Ingeniería en Informática', 'codigo' => 'INF', 'estado' => true]);
        $this->tipoSala = Models\TipoSala::create(['nombre' => 'Aula de prueba', 'estado' => true]);
        $this->docente = Models\Profesor::create(['universidad_id' => $this->universidad, 'nombre' => 'Ana', 'apellido' => 'Prueba', 'estado' => true]);
        $this->grupo = $this->crearGrupo('BACK', 'Desarrollo de aplicaciones', $this->docente);
        $this->lunes = Models\Dia::where('numero', 1)->value('id');
        $this->martes = Models\Dia::where('numero', 2)->value('id');
        $this->primero = Models\BloqueHorario::where('numero', 1)->value('id');
        $this->segundo = Models\BloqueHorario::where('numero', 2)->value('id');
        $this->horario = $this->crearHorario($this->grupo, $this->lunes, $this->primero);
    }

    public function test_jefatura_y_administracion_pueden_abrir_el_panel_y_los_demas_perfiles_no(): void
    {
        $url = route('programacion.panel-carrera', $this->filtros());
        $this->get($url)->assertRedirect(route('login'));

        $docente = $this->usuario('Docente', ['ver programacion semestral', 'asignar horarios']);
        $this->actingAs($docente)->get($url)->assertForbidden();
        $this->patchJson($this->rutaHorario('mover'), $this->destino($this->martes, $this->segundo))->assertForbidden();

        $asistente = $this->usuario('Asistente académico', ['ver programacion semestral']);
        $this->actingAs($asistente)->get($url)->assertForbidden();
        $this->get(route('programacion.panel-carrera.exportar', $this->filtros()))->assertForbidden();

        $administrador = $this->usuario('Administrador', ['ver programacion semestral']);
        $this->actingAs($administrador)->get($url)->assertOk()->assertSee('Panel de carrera')
            ->assertViewHas('puedeEditarHorarios', false);
        $this->patchJson($this->rutaHorario('mover'), $this->destino($this->martes, $this->segundo))->assertForbidden();

        $this->actingAs($this->jefatura)->get($url)->assertOk()->assertViewHas('puedeEditarHorarios', true)
            ->assertSee('Ingeniería en Informática')->assertSee('Desarrollo de aplicaciones');
        $this->assertDatabaseHas('horarios', ['id' => $this->horario->id, 'dia_id' => $this->lunes]);
    }

    public function test_login_de_jefatura_abre_el_panel_y_conserva_el_acceso_a_inicio(): void
    {
        $jefatura = $this->usuario('Jefe de carrera', []);
        $jefatura->roles->sole()->givePermissionTo([
            Permission::findOrCreate('ver panel inicio', 'web'),
            Permission::findOrCreate('ver programacion semestral', 'web'),
            Permission::findOrCreate('asignar horarios', 'web'),
        ]);

        $this->post(route('login.procesar'), ['rut' => $jefatura->rut, 'password' => 'prueba'])
            ->assertRedirectToRoute('programacion.panel-carrera');
        $this->get(route('programacion.panel-carrera'))->assertOk()->assertViewHas('puedeEditarHorarios', true)
            ->assertViewHas('puedeAsignarDocentes', false);
        $this->get(route('inicio'))->assertOk()->assertViewHas('accesos', fn (array $accesos) => $accesos[0]['titulo'] === 'Panel de carrera');
    }

    public function test_login_de_jefatura_respeta_el_cambio_obligatorio_de_contrasena(): void
    {
        $this->jefatura->forceFill(['debe_cambiar_password' => true])->save();

        $this->post(route('login.procesar'), ['rut' => $this->jefatura->rut, 'password' => 'prueba'])
            ->assertRedirectToRoute('cuenta.password.edit');
        $this->get(route('programacion.panel-carrera'))->assertRedirectToRoute('cuenta.password.edit');
    }

    public function test_entrada_conserva_los_destinos_de_administracion_y_programacion_sin_acceso_al_panel(): void
    {
        $administrador = $this->usuario('Administrador', ['ver panel inicio', 'ver programacion semestral']);
        $administrador->assignRole(Role::findByName('Jefe de carrera', 'web'));
        $this->assertSame('inicio', PermisosSistema::entrada($administrador));

        $jefaturaSinProgramacion = $this->usuario('Jefe de carrera', ['ver panel inicio']);
        $this->assertSame('inicio', PermisosSistema::entrada($jefaturaSinProgramacion));
        $this->actingAs($jefaturaSinProgramacion)->get(route('programacion.panel-carrera'))->assertForbidden();

        $docente = $this->usuario('Jefe de carrera', ['ver programacion semestral']);
        $docente->forceFill(['profesor_id' => $this->docente->id])->save();
        $this->assertSame('mi.disponibilidad.index', PermisosSistema::entrada($docente));
    }

    public function test_muestra_el_cruce_confirma_el_movimiento_y_permite_volver_al_origen(): void
    {
        $this->actingAs($this->jefatura);
        $otro = $this->crearGrupo('REDES', 'Redes de datos');
        $this->crearHorario($otro, $this->martes, $this->segundo);
        $this->getJson($this->rutaHorario('evaluar', $this->filtros()))->assertOk()
            ->assertJsonPath('opciones.'.$this->martes.':'.$this->segundo.'.nivel', 'rojo')
            ->assertJsonPath('opciones.'.$this->martes.':'.$this->segundo.'.bloqueado', false);

        $destino = $this->destino($this->martes, $this->segundo);
        $this->patchJson($this->rutaHorario('mover'), $destino)->assertUnprocessable()->assertJsonValidationErrors('confirmar');
        $this->patchJson($this->rutaHorario('mover'), $destino + ['confirmar' => true])->assertOk();
        $this->assertDatabaseHas('horarios', ['id' => $this->horario->id, 'dia_id' => $this->martes, 'bloque_horario_id' => $this->segundo]);
        $this->patchJson($this->rutaHorario('mover'), $destino + ['confirmar' => true])->assertUnprocessable()->assertJsonValidationErrors('movimiento');

        $volver = array_replace($this->destino($this->lunes, $this->primero), ['dia_origen' => $this->martes, 'bloque_origen' => $this->segundo]);
        $this->patchJson($this->rutaHorario('mover'), $volver)->assertOk();
        $this->assertDatabaseHas('horarios', ['id' => $this->horario->id, 'dia_id' => $this->lunes, 'bloque_horario_id' => $this->primero]);
        $this->assertDatabaseCount('historial_acciones', 2);
    }

    public function test_actualiza_responsable_confirmacion_y_carga_de_un_grupo(): void
    {
        $this->actingAs($this->jefatura);
        $docente = Models\Profesor::create(['universidad_id' => $this->universidad, 'nombre' => 'José', 'apellido' => 'Prueba', 'estado' => true]);
        $parametros = $this->filtros() + ['pestana' => 'ramos'];
        $this->post(route('programacion.panel-carrera.asignar', $this->grupo), $parametros + ['profesor_id' => $docente->id])
            ->assertRedirect(route('programacion.panel-carrera', $parametros))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('grupos_profesores', ['grupo_seccion_id' => $this->grupo->id, 'profesor_id' => $docente->id, 'es_principal' => true]);
        $this->postJson(route('programacion.panel-carrera.confirmar', $this->grupo), $parametros + ['profesor_id' => $this->docente->id, 'confirmado' => true])
            ->assertUnprocessable()->assertJsonValidationErrors('profesor_id');
        $this->confirmar($docente);

        $this->post(route('programacion.panel-carrera.agregar', $this->grupo), $parametros + ['dia_id' => $this->martes, 'bloque_id' => $this->segundo])
            ->assertRedirect(route('programacion.panel-carrera', $parametros))->assertSessionHasNoErrors();
        $nuevo = Models\Horario::where('dia_id', $this->martes)->where('bloque_horario_id', $this->segundo)->sole();
        $this->assertDatabaseHas('horarios_profesores', ['horario_id' => $nuevo->id, 'profesor_id' => $docente->id, 'estado' => true]);
        $this->get(route('programacion.panel-carrera', $parametros))->assertOk()->assertViewHas('panel', fn (array $panel) =>
            $panel['indicadores']['confirmadas'] === 1 && $panel['indicadores']['completas'] === 1);
        $this->delete($this->rutaHorario('quitar', $parametros, $nuevo))->assertRedirect(route('programacion.panel-carrera', $parametros))->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('horarios', ['id' => $nuevo->id]);
        $this->assertDatabaseHas('horarios', ['id' => $this->horario->id]);
    }

    public function test_asigna_un_espacio_libre_compatible_y_rechaza_falta_de_capacidad(): void
    {
        $this->actingAs($this->jefatura);
        $reducida = $this->crearSala('A-10', 10);
        $ocupada = $this->crearSala('A-20', 20);
        $libre = $this->crearSala('A-30', 30);
        $otro = $this->crearHorario($this->crearGrupo('REDES', 'Redes de datos'), $this->lunes, $this->primero);
        $otro->asignacionesSalas()->create(['sala_id' => $ocupada->id, 'es_principal' => true, 'estado' => true]);
        $url = $this->rutaHorario('espacio');
        foreach ([$reducida, $ocupada] as $sala) {
            $this->postJson($url, $this->filtros() + ['espacio' => 'sala:'.$sala->id])->assertUnprocessable()->assertJsonValidationErrors('espacio');
        }
        $this->post($url, $this->filtros() + ['espacio' => 'auto'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('asignaciones_salas', ['horario_id' => $this->horario->id, 'sala_id' => $libre->id, 'capacidad_asignada' => 20, 'estado' => true]);
        $this->assertDatabaseHas('asignaciones_salas', ['horario_id' => $otro->id, 'sala_id' => $ocupada->id, 'estado' => true]);
    }

    public function test_prefiere_laboratorio_fijo_y_usa_movil_si_esta_ocupado_y_la_sala_es_compatible(): void
    {
        $this->actingAs($this->jefatura);
        $actividad = $this->grupo->actividadSeccion;
        $actividad->update(['tipo' => 'Laboratorio']);
        $actividad->requerimientos()->create(['tipo_requerimiento' => 'Sala', 'tipo_sala_id' => $this->tipoSala->id,
            'cantidad' => 1, 'obligatorio' => true, 'estado' => true]);
        $salaFija = $this->crearSala('LAB-1', 25);
        $salaMovil = $this->crearSala('A-20', 25);
        $fijo = Models\Laboratorio::create(['sala_id' => $salaFija->id, 'nombre' => 'Laboratorio fijo de prueba',
            'capacidad' => 25, 'estado' => true, 'disponible' => true]);
        $movil = Models\LaboratorioMovil::create(['universidad_id' => $this->universidad, 'sede_id' => $this->carrera->sede_id,
            'codigo' => 'MOV-PRUEBA', 'nombre' => 'Carro de prueba', 'capacidad' => 25, 'estado' => true, 'disponible' => true]);
        $url = $this->rutaHorario('espacio');
        $this->post($url, $this->filtros() + ['espacio' => 'auto'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('asignaciones_laboratorios', ['horario_id' => $this->horario->id, 'laboratorio_id' => $fijo->id, 'estado' => true]);

        $otro = $this->crearHorario($this->crearGrupo('REDES', 'Redes de datos'), $this->lunes, $this->primero);
        $otro->asignacionesSalas()->create(['sala_id' => $salaFija->id, 'es_principal' => true, 'estado' => true]);
        $this->post($url, $this->filtros() + ['espacio' => 'auto'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('asignaciones_laboratorios', ['horario_id' => $this->horario->id, 'laboratorio_id' => $fijo->id, 'estado' => false]);
        $this->assertDatabaseHas('asignaciones_laboratorios_moviles', ['horario_id' => $this->horario->id, 'laboratorio_movil_id' => $movil->id, 'estado' => true]);
        $this->assertDatabaseHas('asignaciones_salas', ['horario_id' => $this->horario->id, 'sala_id' => $salaMovil->id, 'estado' => true]);
    }

    public function test_confirma_un_responsable_importado_directamente_al_modulo(): void
    {
        $this->actingAs($this->jefatura);
        $this->grupo->profesores()->delete();
        $this->horario->profesores()->create(['profesor_id' => $this->docente->id, 'es_principal' => true, 'estado' => true]);
        $this->confirmar($this->docente);
        $this->get(route('programacion.panel-carrera', $this->filtros() + ['pestana' => 'ramos']))
            ->assertOk()->assertSee('Docente confirmado');
    }

    public function test_descarga_excel_nativo_con_datos_del_panel_como_texto_y_no_como_formulas(): void
    {
        $this->actingAs($this->jefatura);
        $this->grupo->actividadSeccion->seccion->asignatura->update(['nombre' => '=SUM(1,2)']);
        $respuesta = $this->get(route('programacion.panel-carrera.exportar', $this->filtros()))->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $archivo = tempnam(sys_get_temp_dir(), 'prosec-xlsx-');
        try {
            file_put_contents($archivo, $respuesta->streamedContent());
            $libro = IOFactory::createReader('Xlsx')->load($archivo);
            $this->assertSame(['Resumen', 'Horario', 'Ramos y grupos', 'Carga docente', 'Laboratorios', 'Control'], $libro->getSheetNames());
            $this->assertSame($this->carrera->nombre, $libro->getSheetByName('Resumen')->getCell('B2')->getValue());
            $this->assertSame('BACK', $libro->getSheetByName('Horario')->getCell('A2')->getValue());
            $this->assertSame('=SUM(1,2)', $libro->getSheetByName('Horario')->getCell('B2')->getValue());
            $this->assertSame(DataType::TYPE_STRING, $libro->getSheetByName('Horario')->getCell('B2')->getDataType());
            $this->assertSame(DataType::TYPE_FORMULA, $libro->getSheetByName('Ramos y grupos')->getCell('H2')->getDataType());
        } finally {
            isset($libro) && $libro->disconnectWorksheets();
            unlink($archivo);
        }
    }

    public function test_copia_del_panel_conserva_el_horario_y_las_confirmaciones_y_abre_la_version_en_consulta(): void
    {
        $this->actingAs($this->jefatura);
        $this->confirmar($this->docente);
        $referencia = ['version' => $this->version, 'carrera_id' => $this->carrera->id];
        $pantalla = $this->get(route('programacion.version.copia', $referencia))->assertOk();
        $this->post(route('programacion.version.copiar', $this->version), [
            'firma' => $pantalla->viewData('firma'), 'solicitud' => $pantalla->viewData('solicitud'), 'activar' => false,
            'confirmar' => true, 'nombre_borrador' => 'Previo a los ajustes', 'carrera_id' => $this->carrera->id,
        ])->assertSessionHasNoErrors()->assertRedirect();

        $copia = Models\VersionPlanificacion::where('nombre', 'Previo a los ajustes')->sole();
        $datos = array_replace($this->filtros(), ['version_id' => $copia->id, 'pestana' => 'borradores']);
        $this->get(route('programacion.panel-carrera', $datos))->assertOk()->assertSee('Previo a los ajustes')
            ->assertViewHas('puedeEditarHorarios', false);
        $this->assertDatabaseHas('confirmaciones_grupo_programacion', ['version_planificacion_id' => $copia->id, 'grupo_seccion_id' => $this->grupo->id, 'profesor_id' => $this->docente->id]);
        $reemplazo = Models\Profesor::create(['universidad_id' => $this->universidad, 'nombre' => 'Responsable', 'apellido' => 'Nuevo', 'estado' => true]);
        $this->post(route('programacion.panel-carrera.asignar', $this->grupo), $this->filtros() + ['profesor_id' => $reemplazo->id])
            ->assertSessionHasNoErrors();
        $this->get(route('programacion.panel-carrera', $datos))->assertViewHas('panel', fn ($p) => $p['indicadores']['confirmadas'] === 1);
        $this->get(route('programacion.panel-carrera', $this->filtros()))->assertViewHas('panel', fn ($p) => $p['indicadores']['confirmadas'] === 0);
        $this->assertDatabaseHas('horarios', ['version_planificacion_id' => $copia->id, 'dia_id' => $this->lunes, 'bloque_horario_id' => $this->primero]);
        $this->assertDatabaseHas('versiones_planificacion', ['id' => $this->version->id, 'es_actual' => true]);
        $this->assertDatabaseHas('historial_acciones', ['accion' => 'Crear borrador para ajustes', 'registro_id' => $copia->id]);
    }

    private function usuario(string $rol, array $permisos): Models\User
    {
        $usuario = Models\User::create(['name' => $rol, 'rut' => (string) (17000000 + Models\User::count()),
            'email' => 'perfil'.Models\User::count().'@example.test', 'password' => 'prueba']);
        $usuario->assignRole(Role::findOrCreate($rol, 'web'));
        foreach ($permisos as $permiso) $usuario->givePermissionTo(Permission::findOrCreate($permiso, 'web'));

        return $usuario;
    }

    private function crearGrupo(string $codigo, string $nombre, ?Models\Profesor $docente = null): Models\GrupoSeccion
    {
        $ramo = Models\Asignatura::create(['universidad_id' => $this->universidad, 'codigo' => $codigo, 'nombre' => $nombre, 'estado' => true]);
        $seccion = Models\Seccion::create(['periodo_academico_id' => $this->periodo->id, 'asignatura_id' => $ramo->id, 'codigo' => '11', 'semestre' => 2, 'estado_activo' => true]);
        $seccion->carreras()->attach($this->carrera);
        $actividad = $seccion->actividades()->create(['tipo' => 'Cátedra', 'cantidad_horas' => 2, 'estado' => true]);
        $grupo = $actividad->grupos()->create(['codigo' => 'G:1', 'numero' => 1, 'inscritos' => 20, 'estado_activo' => true]);
        if ($docente) $grupo->profesores()->create(['profesor_id' => $docente->id, 'es_principal' => true, 'estado' => true]);

        return $grupo;
    }

    private function crearHorario(Models\GrupoSeccion $grupo, int $dia, int $bloque): Models\Horario
    {
        return $grupo->horarios()->create(['actividad_seccion_id' => $grupo->actividad_seccion_id, 'version_planificacion_id' => $this->version->id,
            'dia_id' => $dia, 'bloque_horario_id' => $bloque, 'estado' => true]);
    }

    private function crearSala(string $codigo, int $capacidad): Models\Sala
    {
        return Models\Sala::create(['universidad_id' => $this->universidad, 'sede_id' => $this->carrera->sede_id, 'tipo_sala_id' => $this->tipoSala->id,
            'codigo' => $codigo, 'capacidad' => $capacidad, 'estado' => true, 'disponible' => true]);
    }

    private function confirmar(Models\Profesor $docente): void
    {
        $this->post(route('programacion.panel-carrera.confirmar', $this->grupo), $this->filtros() + ['profesor_id' => $docente->id, 'confirmado' => true])
            ->assertRedirect()->assertSessionHasNoErrors();
    }

    private function filtros(): array
    {
        return ['periodo_id' => $this->periodo->id, 'version_id' => $this->version->id, 'carrera_id' => $this->carrera->id];
    }

    private function rutaHorario(string $accion, array $query = [], ?Models\Horario $horario = null): string
    {
        return route('programacion.panel-carrera.'.$accion, ['horario' => $horario ?? $this->horario] + $query);
    }

    private function destino(int $dia, int $bloque): array
    {
        return ['carrera_id' => $this->carrera->id, 'dia_id' => $dia, 'bloque_id' => $bloque,
            'dia_origen' => $this->lunes, 'bloque_origen' => $this->primero];
    }
}

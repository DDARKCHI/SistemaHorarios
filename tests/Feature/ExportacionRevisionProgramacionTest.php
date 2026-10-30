<?php

namespace Tests\Feature;

use App\Models;
use App\Services\RevisionConflictosProgramacion;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Permission;
use Tests\IsolatedDatabaseTestCase;

class ExportacionRevisionProgramacionTest extends IsolatedDatabaseTestCase
{
    private Models\PeriodoAcademico $periodo;
    private Models\VersionPlanificacion $version;
    private Models\Profesor $profesor;
    private Models\Sala $sala;
    private int $secuencia = 200;

    protected function setUp(): void
    {
        parent::setUp();
        $usuario = Models\User::create(['name' => 'Revisión exportable', 'rut' => '78888888', 'email' => 'exportacion@example.test', 'password' => 'prueba']);
        $usuario->givePermissionTo(Permission::findOrCreate('gestionar conflictos', 'web'));
        $this->actingAs($usuario);
        $uni = DB::table('universidades')->value('id');
        $this->periodo = Models\PeriodoAcademico::create(['universidad_id' => $uni, 'nombre' => '2026-2', 'anio' => 2026, 'numero_semestre' => 2]);
        $this->version = Models\VersionPlanificacion::create(['periodo_academico_id' => $this->periodo->id, 'tipo' => 'semestral', 'nombre' => 'Semestral de prueba', 'version' => '1.0', 'estado' => 'Borrador', 'es_actual' => true]);
        $this->profesor = Models\Profesor::create(['universidad_id' => $uni, 'nombre' => 'María José', 'apellido' => 'Muñoz']);
        $sede = Models\Sede::create(['universidad_id' => $uni, 'nombre' => 'Concepción']);
        $tipo = Models\TipoSala::create(['nombre' => 'Aula']);
        $this->sala = Models\Sala::create(['universidad_id' => $uni, 'sede_id' => $sede->id, 'tipo_sala_id' => $tipo->id, 'codigo' => 'A-101', 'capacidad' => 20]);
    }

    public function test_descarga_excel_con_contexto_totales_y_las_clases_involucradas(): void
    {
        $a = $this->modulo(); $b = $this->modulo();
        Models\ProfesorVersion::create(['version_planificacion_id' => $this->version->id, 'profesor_id' => $this->profesor->id, 'nombre' => 'María histórica', 'apellido' => 'Muñoz']);
        $libro = $this->descargar(['tipo' => 'sala']);
        try {
            $this->assertSame(['Resumen', 'Observaciones'], $libro->getSheetNames());
            $resumen = $libro->getSheet(0);
            $this->assertSame('2026-2', $resumen->getCell('B3')->getValue());
            $this->assertSame($this->version->id, $resumen->getCell('B4')->getValue());
            $this->assertSame('v1.0 · Semestral de prueba', $resumen->getCell('B5')->getValue());
            $this->assertSame('Borrador · Actual', $resumen->getCell('B6')->getValue());
            $this->assertSame('Salas', $resumen->getCell('B7')->getValue());
            $this->assertSame(3, $resumen->getCell('B8')->getValue()); // Un cruce y dos capacidades insuficientes.
            $this->assertSame(2, $resumen->getCell('B14')->getValue());
            $this->assertSame(1, $resumen->getCell('B15')->getValue());
            $this->assertSame(2, $resumen->getCell('B24')->getValue());
            $this->assertSame(2, $resumen->getCell('B25')->getValue());
            $this->assertSame(0, $resumen->getCell('B26')->getValue());
            $this->assertSame(0, $resumen->getCell('B27')->getValue());
            $this->assertStringContainsString('no constituye una aprobación', $resumen->getCell('B11')->getValue());
            $detalle = $libro->getSheet(1);
            $filas = array_slice($detalle->toArray(), 1);
            $cruce = collect($filas)->firstWhere(1, 'Tope horario');
            $this->assertNotNull($cruce);
            $this->assertStringContainsString('María histórica Muñoz', $cruce[6]);
            $this->assertStringContainsString('Salas: A-101', $cruce[6]);
            $this->assertSame($a->id."\n".$b->id, $cruce[7]);
            $this->assertSame('09:00–10:00', $cruce[3]);
            $this->assertStringContainsString('Sección 00201 · Cátedra · G:1', $cruce[9]);
            $this->assertSame('A2', $detalle->getFreezePane());
            $this->assertSame('A1:K4', $detalle->getAutoFilter()->getRange());
        } finally { $libro->disconnectWorksheets(); }
    }

    public function test_exporta_todas_las_paginas_y_el_enlace_conserva_solo_los_filtros_aplicados(): void
    {
        for ($n = 0; $n < 7; $n++) $this->modulo(conSala: false);
        $html = $this->get(route('programacion.validacion', ['periodo_id' => $this->periodo->id, 'version_id' => $this->version->id, 'tipo' => 'profesor', 'page' => 2, 'extra' => 'no-propagar']))
            ->assertOk()->assertViewHas('observaciones', fn ($p) => $p->total() === 28 && $p->count() === 8);
        $html->assertSee($this->url(['tipo' => 'profesor']))->assertDontSee('extra=no-propagar')->assertSee('Exportar a Excel')->assertSee('todas las páginas del filtro aplicado');
        $libro = $this->descargar(['tipo' => 'profesor', 'page' => 2]);
        try {
            $this->assertSame(28, $libro->getSheet(0)->getCell('B8')->getValue());
            $this->assertSame(29, $libro->getSheet(1)->getHighestDataRow());
            $this->assertSame(28, $libro->getSheet(1)->getCell('A29')->getValue());
        } finally { $libro->disconnectWorksheets(); }
    }

    #[DataProvider('filtros')]
    public function test_la_exportacion_y_la_pantalla_comparten_el_mismo_filtro(string $tipo): void
    {
        $a = $this->modulo(); $this->modulo();
        $a->actividadSeccion->update(['cantidad_horas' => 2]);
        $a->bloqueHorario->update(['hora_inicio' => '10:00:00', 'hora_fin' => '09:00:00']);
        $respuesta = $this->get(route('programacion.validacion', ['periodo_id' => $this->periodo->id, 'version_id' => $this->version->id, 'tipo' => $tipo]))->assertOk();
        $observaciones = $respuesta->viewData('observaciones');
        $libro = $this->descargar(['tipo' => $tipo]);
        try {
            $this->assertSame($observaciones->total(), $libro->getSheet(0)->getCell('B8')->getValue());
            $this->assertSame($observaciones->total() + 1, $libro->getSheet(1)->getHighestDataRow());
            $this->assertSame($observaciones->pluck('mensaje')->all(), array_column(array_slice($libro->getSheet(1)->toArray(), 1), 5));
        } finally { $libro->disconnectWorksheets(); }
    }

    public static function filtros(): array
    {
        return array_map(fn ($tipo) => [$tipo], array_keys(RevisionConflictosProgramacion::TIPOS));
    }

    public function test_exporta_carga_sin_horarios_y_conserva_codigos_tildes_comillas_y_saltos_de_linea(): void
    {
        $h = $this->modulo();
        $asignatura = $h->actividadSeccion->seccion->asignatura;
        $asignatura->update(['nombre' => "Programación; \"Ñ\"\nLínea dos"]);
        $h->delete();
        $libro = $this->descargar(['tipo' => 'carga']);
        try {
            $fila = $libro->getSheet(1)->toArray()[1];
            $this->assertSame('Carga por revisar', $fila[1]);
            $this->assertSame('Módulos pendientes', $fila[4]);
            $this->assertNull($fila[7]);
            $this->assertSame($asignatura->codigo." · Programación; \"Ñ\"\nLínea dos", $fila[8]);
            $this->assertSame('Sección 00201 · Cátedra · G:1', $fila[9]);
            $this->assertSame('Sin módulo asociado', $fila[10]);
        } finally { $libro->disconnectWorksheets(); }
    }

    #[DataProvider('textos')]
    public function test_los_textos_que_parecen_formulas_permanecen_como_texto(string $texto): void
    {
        $h = $this->modulo();
        $this->periodo->update(['nombre' => $texto]);
        $h->actividadSeccion->seccion->asignatura->update(['codigo' => '', 'nombre' => $texto]);
        $h->delete();
        $libro = $this->descargar(['tipo' => 'carga']);
        try {
            foreach ([$libro->getSheet(0)->getCell('B3'), $libro->getSheet(1)->getCell('I2')] as $celda) {
                $this->assertSame($texto, $celda->getValue());
                $this->assertSame(DataType::TYPE_STRING, $celda->getDataType());
            }
            foreach ($libro->getAllSheets() as $hoja) foreach ($hoja->getCellCollection()->getCoordinates() as $coordenada) {
                $this->assertNotSame(DataType::TYPE_FORMULA, $hoja->getCell($coordenada)->getDataType());
            }
        } finally { $libro->disconnectWorksheets(); }
    }

    public static function textos(): array
    {
        return [['=HYPERLINK("https://example.test","abrir")'], ['+1+1'], ['-1+1'], ['@SUM(A1:A2)'], ["\t=1+1"]];
    }

    public function test_descargar_no_modifica_los_datos_ni_registra_una_aprobacion(): void
    {
        $this->modulo(); $this->modulo();
        $tablas = ['horarios', 'horarios_profesores', 'asignaciones_salas', 'versiones_planificacion', 'profesores', 'validaciones_horario', 'observaciones_validacion', 'historial_acciones'];
        $snapshot = fn () => collect($tablas)->mapWithKeys(fn ($tabla) => [$tabla => DB::table($tabla)->orderBy('id')->get()->toJson()])->all();
        $antes = $snapshot();
        $libro = $this->descargar(); $libro->disconnectWorksheets();
        $this->assertSame($antes, $snapshot());
    }

    public function test_el_archivo_puede_estar_vacio_sin_presentarse_como_aprobacion_y_la_pantalla_no_ofrece_exportar(): void
    {
        $this->get(route('programacion.validacion', ['periodo_id' => $this->periodo->id]))->assertOk()->assertDontSee('Exportar a Excel');
        $libro = $this->descargar();
        try {
            $this->assertSame(0, $libro->getSheet(0)->getCell('B8')->getValue());
            $this->assertSame(1, $libro->getSheet(1)->getHighestDataRow());
            $this->assertStringContainsString('no constituye una aprobación', $libro->getSheet(0)->getCell('B11')->getValue());
        } finally { $libro->disconnectWorksheets(); }
    }

    public function test_respeta_la_version_expresa_y_la_actual_predeterminada_incluyendo_historicos(): void
    {
        $this->modulo();
        $otra = $this->version->replicate(); $otra->version = '2.0'; $otra->es_actual = false; $otra->save();
        $libro = $this->descargar(['version_id' => $otra->id, 'tipo' => 'asignaciones']);
        try { $this->assertSame($otra->id, $libro->getSheet(0)->getCell('B4')->getValue()); }
        finally { $libro->disconnectWorksheets(); }
        $libro = $this->descargar(['version_id' => null]);
        try { $this->assertSame($this->version->id, $libro->getSheet(0)->getCell('B4')->getValue()); }
        finally { $libro->disconnectWorksheets(); }
        $this->periodo->update(['estado' => false]);
        $libro = $this->descargar(); $libro->disconnectWorksheets();
    }

    public function test_rechaza_periodos_y_versiones_ajenas_o_inexistentes_y_no_exporta_sin_version(): void
    {
        $otro = $this->periodo->replicate(); $otro->anio = 2025; $otro->save();
        $anterior = $this->version->replicate(); $anterior->tipo = 'anterior'; $anterior->es_actual = false; $anterior->save();
        foreach ([['version_id' => $anterior->id], ['periodo_id' => $otro->id], ['version_id' => 999999]] as $filtro) {
            $this->get($this->url($filtro))->assertRedirect()->assertSessionHasErrors('version_id');
        }
        $this->get($this->url(['periodo_id' => $otro->id, 'version_id' => null]))->assertRedirect()->assertSessionHasErrors('version_id');
        $this->get($this->url(['periodo_id' => 999999]))->assertRedirect()->assertSessionHasErrors('periodo_id');
    }

    #[DataProvider('consultasInvalidas')]
    public function test_rechaza_consultas_invalidas_sin_descargar(string $campo, mixed $valor): void
    {
        $this->get($this->url([$campo => $valor]))->assertRedirect(route('programacion.validacion'))->assertSessionHasErrors($campo);
    }

    public static function consultasInvalidas(): array
    {
        return [['tipo', 'otro'], ['tipo', ['profesor']], ['periodo_id', ['1']], ['version_id', '../1'], ['page', 0]];
    }

    public function test_la_descarga_exige_autenticacion_y_permiso_de_conflictos(): void
    {
        auth()->user()->revokePermissionTo('gestionar conflictos');
        $this->get($this->url())->assertRedirect();
        auth()->logout(); $this->get($this->url())->assertRedirect(route('login'));
    }

    private function descargar(array $filtros = []): Spreadsheet
    {
        $tipo = $filtros['tipo'] ?? 'todos';
        $versionId = $filtros['version_id'] ?? $this->version->id;
        $respuesta = $this->get($this->url($filtros))->assertOk()
            ->assertDownload('revision-periodo-'.$this->periodo->id.'-version-'.$versionId.'-'.$tipo.'.xlsx')
            ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString('no-store', $respuesta->headers->get('Cache-Control'));
        $contenido = $respuesta->streamedContent();
        $this->assertStringStartsWith('PK', $contenido);
        $temporal = tempnam(sys_get_temp_dir(), 'prosec-export-test-');
        try {
            file_put_contents($temporal, $contenido);
            return (new Xlsx)->load($temporal);
        } finally { unlink($temporal); }
    }

    private function modulo(bool $conSala = true): Models\Horario
    {
        $n = ++$this->secuencia;
        $asignatura = Models\Asignatura::create(['universidad_id' => $this->periodo->universidad_id, 'codigo' => 'EXP-'.$n, 'nombre' => 'Programación '.$n]);
        $seccion = Models\Seccion::create(['periodo_academico_id' => $this->periodo->id, 'asignatura_id' => $asignatura->id, 'codigo' => '00'.$n]);
        $actividad = Models\ActividadSeccion::create(['seccion_id' => $seccion->id, 'tipo' => 'Cátedra', 'cantidad_horas' => 1]);
        $grupo = Models\GrupoSeccion::create(['actividad_seccion_id' => $actividad->id, 'codigo' => 'G:1', 'inscritos' => 30]);
        $bloque = Models\BloqueHorario::create(['numero' => $n, 'hora_inicio' => '09:00:00', 'hora_fin' => '10:00:00']);
        $h = Models\Horario::create(['version_planificacion_id' => $this->version->id, 'actividad_seccion_id' => $actividad->id, 'grupo_seccion_id' => $grupo->id,
            'dia_id' => DB::table('dias')->where('numero', 1)->value('id'), 'bloque_horario_id' => $bloque->id]);
        Models\HorarioProfesor::create(['horario_id' => $h->id, 'profesor_id' => $this->profesor->id]);
        if ($conSala) Models\AsignacionSala::create(['horario_id' => $h->id, 'sala_id' => $this->sala->id]);
        return $h;
    }

    private function url(array $filtros = []): string
    {
        return route('programacion.validacion.exportar', array_replace(['periodo_id' => $this->periodo->id, 'version_id' => $this->version->id], $filtros));
    }
}

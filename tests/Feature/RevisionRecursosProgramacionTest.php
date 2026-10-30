<?php

namespace Tests\Feature;

use App\Models;
use App\Services\RevisionConflictosProgramacion;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Permission;
use Tests\IsolatedDatabaseTestCase;

class RevisionRecursosProgramacionTest extends IsolatedDatabaseTestCase
{
    private Models\VersionPlanificacion $version;
    private Models\Sala $sala;
    private int $secuencia = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $universidad = DB::table('universidades')->value('id');
        $usuario = Models\User::create(['name' => 'Revisión de espacios', 'rut' => '75555555', 'email' => 'recursos@example.test', 'password' => 'prueba']);
        $usuario->givePermissionTo(Permission::findOrCreate('gestionar conflictos', 'web'));
        $this->actingAs($usuario);
        $periodo = Models\PeriodoAcademico::create(['universidad_id' => $universidad, 'nombre' => '2026-2', 'anio' => 2026, 'numero_semestre' => 2]);
        $this->version = Models\VersionPlanificacion::create(['periodo_academico_id' => $periodo->id, 'tipo' => 'semestral', 'nombre' => 'Semestral', 'version' => '1.0', 'es_actual' => true]);
        $sede = Models\Sede::create(['universidad_id' => $universidad, 'nombre' => 'Sede']);
        $tipo = Models\TipoSala::create(['nombre' => 'Aula']);
        $this->sala = Models\Sala::create(['universidad_id' => $universidad, 'sede_id' => $sede->id, 'tipo_sala_id' => $tipo->id, 'codigo' => 'R-101', 'capacidad' => 40]);
    }

    #[DataProvider('familias')]
    public function test_comprueba_el_tipo_de_sala_del_espacio_y_la_sala_del_laboratorio(string $familia): void
    {
        $h = $this->modulo();
        if ($familia === 'Laboratorio') $this->laboratorio($h);
        if ($familia === 'Laboratorio móvil') $this->movil($h);
        $this->requerir($h, $familia, ['tipo_sala_id' => $this->sala->tipo_sala_id, 'capacidad_minima' => 40]);
        $this->assertSame(0, $this->reporte()['recursos']);
        $otro = Models\TipoSala::create(['nombre' => 'Laboratorio especializado']);
        $this->sala->update(['tipo_sala_id' => $otro->id]);
        $this->assertSame(1, $this->reporte()['recursos']);
        $this->assertStringContainsString('tipo Aula', $this->fila()['mensaje']);
    }

    public static function familias(): array { return [['Sala'], ['Laboratorio'], ['Laboratorio móvil']]; }

    public function test_no_duplica_una_sala_asignada_directamente_y_a_traves_de_su_laboratorio(): void
    {
        $h = $this->modulo(); $this->laboratorio($h);
        $this->requerir($h, 'Sala', ['cantidad' => 2]);
        $this->assertStringContainsString('se pueden cubrir 1', $this->fila()['mensaje']);
        $otra = $this->otraSala();
        Models\AsignacionSala::create(['horario_id' => $h->id, 'sala_id' => $otra->id]);
        $this->assertSame(0, $this->reporte()['recursos']);
    }

    public function test_no_reutiliza_un_recurso_para_dos_requerimientos_simultaneos(): void
    {
        $h = $this->modulo();
        $this->requerir($h, 'Sala'); $this->requerir($h, 'Sala');
        $this->assertSame(1, $this->reporte()['recursos']);
        $this->assertStringContainsString('sin reutilizar recursos', $this->fila()['mensaje']);
        Models\AsignacionSala::create(['horario_id' => $h->id, 'sala_id' => $this->otraSala()->id]);
        $this->assertSame(0, $this->reporte()['recursos']);
    }

    public function test_reasigna_candidatos_para_no_producir_un_faltante_por_el_orden_de_las_reglas(): void
    {
        $h = $this->modulo();
        $this->requerir($h, 'Sala');
        $this->requerir($h, 'Sala', ['tipo_sala_id' => $this->sala->tipo_sala_id]);
        $tipo = Models\TipoSala::create(['nombre' => 'Taller']);
        $otra = $this->otraSala(['tipo_sala_id' => $tipo->id]);
        Models\AsignacionSala::create(['horario_id' => $h->id, 'sala_id' => $otra->id]);
        $this->assertSame(0, $this->reporte()['recursos']);
    }

    public function test_el_minimo_se_comprueba_por_recurso_sin_sumar_capacidades_de_varias_salas(): void
    {
        $h = $this->modulo(); $this->requerir($h, 'Sala', ['capacidad_minima' => 50]);
        Models\AsignacionSala::create(['horario_id' => $h->id, 'sala_id' => $this->otraSala()->id]);
        $this->assertStringContainsString('capacidad mínima 50 por recurso', $this->fila()['mensaje']);
        $this->assertStringContainsString('se pueden cubrir 0', $this->fila()['mensaje']);
        $this->sala->update(['capacidad' => 50]);
        $this->assertSame(0, $this->reporte()['recursos']);
    }

    #[DataProvider('configuracionesInvalidas')]
    public function test_no_da_por_cumplidas_reglas_sin_datos_validos(array $datos): void
    {
        $h = $this->modulo(); $this->requerir($h, 'Sala', $datos);
        $this->assertStringContainsString('revisa la cantidad', $this->fila()['mensaje']);
    }

    public static function configuracionesInvalidas(): array { return [[['cantidad' => 0]], [['capacidad_minima' => 0]]]; }

    public function test_una_cantidad_grande_no_se_convierte_en_recursos_ficticios(): void
    {
        $h = $this->modulo(); $this->requerir($h, 'Sala', ['cantidad' => 65535]);
        $this->assertStringContainsString('exige 65535', $this->fila()['mensaje']);
        $this->assertStringContainsString('se pueden cubrir 1', $this->fila()['mensaje']);
    }

    #[DataProvider('tiposManuales')]
    public function test_los_tipos_desconocidos_y_el_equipamiento_quedan_para_revision(string $tipo): void
    {
        $h = $this->modulo(); $this->requerir($h, $tipo);
        Models\Equipamiento::create(['universidad_id' => $this->sala->universidad_id, 'nombre' => 'Proyector', 'tipo' => 'Audiovisual', 'cantidad' => 50]);
        $this->assertStringContainsString('requiere revisión', $this->fila()['mensaje']);
        $this->get($this->url())->assertOk()->assertSee('Compatibilidad por revisar')->assertDontSee('Sin observaciones para este filtro');
    }

    public static function tiposManuales(): array { return [['Equipamiento'], ['Laboratorio de química']]; }

    public function test_un_nombre_coincidente_no_reemplaza_el_vinculo_al_recurso(): void
    {
        $h = $this->modulo(); $this->requerir($h, 'Sala', ['nombre_recurso' => $this->sala->codigo]);
        $this->assertStringContainsString('verifica el recurso solicitado por nombre', $this->fila()['mensaje']);
    }

    public function test_los_requerimientos_inactivos_u_opcionales_no_se_tratan_como_obligatorios(): void
    {
        $h = $this->modulo();
        $this->requerir($h, 'No comprobable', ['estado' => false]);
        $this->requerir($h, 'No comprobable', ['obligatorio' => false]);
        $this->assertSame(0, $this->reporte()['recursos']);
    }

    #[DataProvider('catalogoNoUtilizable')]
    public function test_los_recursos_inactivos_o_no_habilitados_se_señalan_aunque_no_haya_requerimientos(string $familia, string $campo): void
    {
        $h = $this->modulo();
        $recurso = match ($familia) { 'sala' => $this->sala, 'laboratorio' => $this->laboratorio($h), 'movil' => $this->movil($h) };
        if ($familia === 'movil') $this->requerir($h, 'Sala', ['tipo_sala_id' => $this->sala->tipo_sala_id]);
        $recurso->update([$campo => false]);
        $fila = $this->fila();
        $this->assertContains($familia, array_column($fila['recursos'], 'tipo'));
        $this->assertStringContainsString($campo === 'estado' ? 'registro inactivo' : 'uso no habilitado', $fila['mensaje']);
    }

    public static function catalogoNoUtilizable(): array
    {
        return [['sala', 'estado'], ['sala', 'disponible'], ['laboratorio', 'estado'], ['laboratorio', 'disponible'], ['movil', 'estado'], ['movil', 'disponible']];
    }

    public function test_la_sala_inhabilitada_del_laboratorio_tambien_lo_inhabilita(): void
    {
        $h = $this->modulo(); $this->laboratorio($h); $this->sala->update(['disponible' => false]);
        $this->assertSame(['sala', 'laboratorio'], array_column($this->fila()['recursos'], 'tipo'));
        $this->assertStringContainsString('la sala del laboratorio no está activa', $this->fila()['mensaje']);
    }

    public function test_un_recurso_de_otra_institucion_no_satisface_un_requerimiento(): void
    {
        $h = $this->modulo(); $this->requerir($h, 'Sala');
        $otra = Models\Universidad::create(['nombre' => 'Otra', 'sigla' => 'OTRA']);
        $this->sala->update(['universidad_id' => $otra->id]);
        $this->assertStringContainsString('no pertenece a la institución', $this->fila()['mensaje']);
        $this->assertStringContainsString('se pueden cubrir 0', $this->fila()['mensaje']);
    }

    public function test_un_tipo_de_sala_inactivo_y_el_equipamiento_sin_vinculo_no_se_certifican(): void
    {
        $this->modulo(); $this->sala->tipoSala->update(['requiere_equipamiento' => true]);
        $this->assertStringContainsString('falta vincular los equipos', $this->fila()['mensaje']);
        $this->sala->tipoSala->update(['requiere_equipamiento' => false, 'estado' => false]);
        $this->assertStringContainsString('tipo de sala ausente o inactivo', $this->fila()['mensaje']);
    }

    public function test_un_movil_necesita_la_regla_de_tipo_de_sala_y_la_sede_compatible(): void
    {
        $h = $this->modulo(); $movil = $this->movil($h);
        $this->assertStringContainsString('define un requerimiento obligatorio', $this->fila()['mensaje']);
        $this->requerir($h, 'Sala', ['tipo_sala_id' => $this->sala->tipo_sala_id]);
        $this->assertSame(0, $this->reporte()['recursos']);
        $sede = Models\Sede::create(['universidad_id' => $this->sala->universidad_id, 'nombre' => 'Otra sede']);
        $movil->update(['sede_id' => $sede->id]);
        $this->assertStringContainsString('revisa su sede', $this->fila()['mensaje']);
        foreach (['sala', 'movil'] as $tipo) $this->get($this->url(['tipo' => $tipo]))->assertOk()->assertViewHas('observaciones', fn ($p) => $p->total() === 1);
    }

    public function test_varias_salas_de_distintas_sedes_no_acreditan_la_ubicacion_del_movil(): void
    {
        $h = $this->modulo(); $this->movil($h);
        $this->requerir($h, 'Sala', ['tipo_sala_id' => $this->sala->tipo_sala_id]);
        $sede = Models\Sede::create(['universidad_id' => $this->sala->universidad_id, 'nombre' => 'Otra sede']);
        $otra = $this->otraSala(['sede_id' => $sede->id]);
        Models\AsignacionSala::create(['horario_id' => $h->id, 'sala_id' => $otra->id]);
        $this->assertStringContainsString('no se puede comprobar una ubicación común', $this->fila()['mensaje']);
    }

    public function test_no_asume_que_el_movil_utilizara_la_sala_del_tipo_correcto_si_hay_otras_distintas(): void
    {
        $h = $this->modulo(); $this->movil($h);
        $this->requerir($h, 'Sala', ['tipo_sala_id' => $this->sala->tipo_sala_id]);
        $tipo = Models\TipoSala::create(['nombre' => 'Taller']);
        Models\AsignacionSala::create(['horario_id' => $h->id, 'sala_id' => $this->otraSala(['tipo_sala_id' => $tipo->id])->id]);
        $this->assertStringContainsString('revisa qué espacio utilizará', $this->fila()['mensaje']);
    }

    public function test_los_filtros_solo_muestran_los_recursos_involucrados(): void
    {
        $h = $this->modulo(); $lab = $this->laboratorio($h); $lab->update(['disponible' => false]);
        $this->get($this->url(['tipo' => 'laboratorio']))->assertOk()->assertViewHas('observaciones', fn ($p) => $p->total() === 1);
        foreach (['sala', 'profesor', 'movil'] as $tipo) $this->get($this->url(['tipo' => $tipo]))->assertOk()->assertViewHas('observaciones', fn ($p) => $p->total() === 0);
    }

    public function test_ignora_asignaciones_y_modulos_inactivos_y_otras_versiones(): void
    {
        $h = $this->modulo(); $this->sala->update(['disponible' => false]);
        Models\AsignacionSala::where('horario_id', $h->id)->update(['estado' => false]);
        $this->assertSame(0, $this->reporte()['recursos']);
        Models\AsignacionSala::where('horario_id', $h->id)->update(['estado' => true]);
        $h->update(['estado' => false]);
        $this->assertSame(0, $this->reporte()['recursos']);
        $otra = $this->version->replicate(); $otra->es_actual = false; $otra->save();
        $h->update(['estado' => true, 'version_planificacion_id' => $otra->id]);
        $this->assertSame(0, $this->reporte()['recursos']);
    }

    public function test_pagina_y_revisa_sin_modificar_las_asignaciones_ni_el_catalogo(): void
    {
        foreach (range(1, 21) as $n) $this->modulo();
        $this->sala->update(['disponible' => false]);
        $tablas = ['salas', 'laboratorios', 'laboratorios_moviles', 'equipamientos', 'requerimientos_actividad', 'horarios', 'asignaciones_salas', 'historial_acciones'];
        $snapshot = fn () => collect($tablas)->mapWithKeys(fn ($tabla) => [$tabla => DB::table($tabla)->orderBy('id')->get()->toJson()])->all();
        $antes = $snapshot();
        $this->get($this->url())->assertOk()->assertViewHas('observaciones', fn ($p) => $p->total() === 21 && $p->count() === 20)->assertSee('Siguiente');
        $this->get($this->url(['page' => 2]))->assertOk()->assertViewHas('observaciones', fn ($p) => $p->count() === 1 && str_contains($p->previousPageUrl(), 'tipo=recursos'));
        $this->assertSame($antes, $snapshot());
    }

    private function modulo(): Models\Horario
    {
        $n = ++$this->secuencia;
        $asignatura = Models\Asignatura::create(['universidad_id' => $this->sala->universidad_id, 'codigo' => 'REC-'.$n, 'nombre' => 'Asignatura '.$n]);
        $seccion = Models\Seccion::create(['asignatura_id' => $asignatura->id, 'periodo_academico_id' => $this->version->periodo_academico_id, 'codigo' => (string) $n]);
        $actividad = Models\ActividadSeccion::create(['seccion_id' => $seccion->id, 'tipo' => 'Cátedra']);
        $grupo = Models\GrupoSeccion::create(['actividad_seccion_id' => $actividad->id, 'codigo' => 'G:1', 'inscritos' => 20]);
        $h = Models\Horario::create(['version_planificacion_id' => $this->version->id, 'actividad_seccion_id' => $actividad->id, 'grupo_seccion_id' => $grupo->id,
            'dia_id' => DB::table('dias')->where('numero', 1)->value('id'), 'bloque_horario_id' => DB::table('bloques_horarios')->where('numero', 1)->value('id')]);
        Models\AsignacionSala::create(['horario_id' => $h->id, 'sala_id' => $this->sala->id]);
        return $h;
    }

    private function requerir(Models\Horario $h, string $tipo, array $cambios = []): Models\RequerimientoActividad
    {
        return Models\RequerimientoActividad::create(array_replace(['actividad_seccion_id' => $h->actividad_seccion_id, 'tipo_requerimiento' => $tipo, 'cantidad' => 1, 'obligatorio' => true], $cambios));
    }

    private function otraSala(array $cambios = []): Models\Sala
    {
        $otra = $this->sala->replicate(); $otra->fill($cambios + ['codigo' => 'R-'.(++$this->secuencia)]); $otra->save(); return $otra;
    }

    private function laboratorio(Models\Horario $h): Models\Laboratorio
    {
        $lab = Models\Laboratorio::create(['sala_id' => $this->sala->id, 'codigo' => 'LAB-'.(++$this->secuencia), 'nombre' => 'Laboratorio', 'capacidad' => 40]);
        Models\AsignacionLaboratorio::create(['horario_id' => $h->id, 'laboratorio_id' => $lab->id]); return $lab;
    }

    private function movil(Models\Horario $h): Models\LaboratorioMovil
    {
        $movil = Models\LaboratorioMovil::create(['universidad_id' => $this->sala->universidad_id, 'sede_id' => $this->sala->sede_id, 'codigo' => 'MOV-'.(++$this->secuencia), 'nombre' => 'Móvil', 'capacidad' => 40]);
        Models\AsignacionLaboratorioMovil::create(['horario_id' => $h->id, 'laboratorio_movil_id' => $movil->id]); return $movil;
    }

    private function reporte(): array { return app(RevisionConflictosProgramacion::class)->revisar($this->version); }
    private function fila(): array { return $this->reporte()['observaciones']->where('tipo', 'recursos')->sole(); }
    private function url(array $filtros = []): string { return route('programacion.validacion', $filtros + ['periodo_id' => $this->version->periodo_academico_id, 'version_id' => $this->version->id, 'tipo' => 'recursos']); }
}

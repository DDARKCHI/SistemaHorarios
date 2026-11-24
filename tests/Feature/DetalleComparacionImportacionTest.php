<?php

namespace Tests\Feature;

use App\Models;
use App\Services\DetalleComparacionImportacion;
use App\Services\ProgramacionCargaService;
use App\Services\ProgramacionComparacionService;
use Illuminate\Support\Facades\DB;
use Tests\IsolatedDatabaseTestCase;

class DetalleComparacionImportacionTest extends IsolatedDatabaseTestCase
{
    private Models\User $usuario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->usuario = Models\User::create([
            'name' => 'Coordinación ficticia', 'rut' => '77777777',
            'email' => 'detalle@example.test', 'password' => 'prueba',
        ]);
    }

    private function fila(array $cambios = []): array
    {
        return $cambios + [
            'SEMESTRE' => '2026-2', 'ID_CURSO' => 'EXT-11', 'COD_RAMO' => 'INF101',
            'RAMO_NOMBRE' => 'Backend', 'SECC' => '11', 'NIVEL_UA' => '2',
            'COD_CARR' => 'INF', 'NOM_CARR' => 'Informática',
            'COD_SEDE' => 'TH', 'SEDE' => 'Talcahuano',
            'PROF_RESP' => '12345678', 'NOM_PROF_RESP' => 'Ana Pérez',
            'CORREO_INST' => 'ana@example.test', 'CORREO_PERS' => '',
            'UA' => 'Instituto', 'TIPO_CONTRATO' => 'Planta', 'TIPO_PROFESOR' => 'Profesor responsable',
            'CUPO' => '25', 'INSCRITOS' => '20', 'MOD_CATEDRA' => '2',
            'MODALIDAD' => 'Presencial', 'CATEGORIA' => 'Obligatoria', 'TIPO_DICTACION' => 'Semestral',
            'HORARIO' => 'ca: LU.1/G:1 (TH-1) MA.1/G:1 (TH-1)',
            'HORARIO_PROFESOR' => 'ca: LU.1/G:1 (TH-1) MA.1/G:1 (TH-1)',
        ];
    }

    private function cargar(?array $filas = null): Models\VersionPlanificacion
    {
        return app(ProgramacionCargaService::class)->cargar($filas ?? [$this->fila()], $this->usuario->id, 'ficticio.csv');
    }

    private function comparar(array $filas): array
    {
        return app(ProgramacionComparacionService::class)->comparar($filas)['detalle_cambios'];
    }

    public function test_muestra_unicamente_el_correo_editado_y_conserva_el_catalogo_docente(): void
    {
        $version = $this->cargar();
        $detalles = $this->comparar([$this->fila(['CORREO_INST' => 'ana.nueva@example.test'])]);

        $this->assertCount(1, $detalles);
        $this->assertSame('Docente', $detalles[0]['entidad']);
        $this->assertSame('Correo institucional', $detalles[0]['campo']);
        $this->assertSame('ana@example.test', $detalles[0]['anterior']);
        $this->assertSame('ana.nueva@example.test', $detalles[0]['nuevo']);
        $this->assertStringContainsString('Ana Pérez', $detalles[0]['nombre']);
        $this->assertSame('ana@example.test', Models\Profesor::firstOrFail()->correo_institucional);
        $this->assertSame(1, Models\VersionPlanificacion::count());
        $this->assertSame(2, $version->horarios()->count());
    }

    public function test_presenta_el_semestre_sin_reinterpretar_los_datos_historicos(): void
    {
        $version = $this->cargar();
        $snapshot = app(DetalleComparacionImportacion::class)->snapshot([$this->fila()]);
        $seccionGuardada = array_values($snapshot['entidades']['Sección'])[0];
        $this->assertSame('2', $seccionGuardada['datos']['Nivel de la carrera']);
        $this->assertSame([], $this->comparar([$this->fila(['NIVEL_UA' => '02'])]));
        $detalles = $this->comparar([$this->fila(['NIVEL_UA' => '3'])]);
        $this->assertCount(1, $detalles);
        $this->assertSame('Semestre de la carrera', $detalles[0]['campo']);
        $this->assertSame('2', $detalles[0]['anterior']);
        $this->assertSame('3', $detalles[0]['nuevo']);
        $this->assertSame(2, Models\Seccion::firstOrFail()->semestre);
        $this->assertSame(2, $version->horarios()->count());
    }

    public function test_un_cambio_de_sala_no_repite_datos_del_curso_ni_de_profesores(): void
    {
        $this->cargar();
        $detalles = $this->comparar([$this->fila([
            'HORARIO' => 'ca: LU.1/G:1 (TH-2) MA.1/G:1 (TH-1)',
        ])]);

        $this->assertCount(1, $detalles);
        $this->assertSame('Horario', $detalles[0]['entidad']);
        $this->assertSame('Sala', $detalles[0]['campo']);
        $this->assertStringContainsString('TH-1', $detalles[0]['anterior']);
        $this->assertStringContainsString('TH-2', $detalles[0]['nuevo']);
    }

    public function test_un_modulo_movido_muestra_solo_la_hora_editada(): void
    {
        $this->cargar();
        $detalles = $this->comparar([$this->fila([
            'HORARIO' => 'ca: LU.2/G:1 (TH-1) MA.1/G:1 (TH-1)',
            'HORARIO_PROFESOR' => 'ca: LU.2/G:1 (TH-1) MA.1/G:1 (TH-1)',
        ])]);

        $this->assertCount(1, $detalles);
        $this->assertSame('Módulo', $detalles[0]['campo']);
        $this->assertSame('1', $detalles[0]['anterior']);
        $this->assertSame('2', $detalles[0]['nuevo']);
    }

    public function test_detecta_datos_del_archivo_aunque_los_catalogos_manuales_se_conserven(): void
    {
        $version = $this->cargar();
        Models\Seccion::firstOrFail()->update(['cupos' => 40]);

        $this->assertSame([], $this->comparar([$this->fila()]));
        $detalles = $this->comparar([$this->fila(['CUPO' => '26'])]);
        $this->assertCount(1, $detalles);
        $this->assertSame('Cupos', $detalles[0]['campo']);
        $this->assertSame('25', $detalles[0]['anterior']);
        $this->assertSame('26', $detalles[0]['nuevo']);
        $this->assertSame(40, Models\Seccion::firstOrFail()->cupos);

        $datos = json_decode(DB::table('historial_acciones')->where('registro_id', $version->id)
            ->where('accion', 'Importar programación anterior')->value('datos'), true);
        $this->assertSame(1, $datos['datos_importados']['version']);
    }

    public function test_no_inventa_cambios_por_mayusculas_rut_espacios_numeros_o_orden(): void
    {
        $this->cargar();
        $detalles = $this->comparar([$this->fila([
            'PROF_RESP' => '12.345.678', 'NOM_PROF_RESP' => ' ANA   PÉREZ ',
            'CORREO_INST' => 'ANA@EXAMPLE.TEST', 'CUPO' => '025',
            'HORARIO' => 'ca: MA.1/G:1 (th-1) LU.1/G:1 (th-1)',
            'HORARIO_PROFESOR' => 'ca: MA.1/G:1 (th-1) LU.1/G:1 (th-1)',
        ])]);

        $this->assertSame([], $detalles);
    }

    public function test_un_correo_retirado_se_muestra_como_dato_eliminado(): void
    {
        $this->cargar([$this->fila(['CORREO_PERS' => 'personal@example.test'])]);
        $detalles = $this->comparar([$this->fila()]);

        $this->assertCount(1, $detalles);
        $this->assertSame('eliminado', $detalles[0]['tipo']);
        $this->assertSame('Correo personal', $detalles[0]['campo']);
        $this->assertNull($detalles[0]['nuevo']);
    }

    public function test_muestra_que_docente_se_agrega_y_quien_se_retira_de_la_version(): void
    {
        $this->cargar();
        $detalles = $this->comparar([$this->fila([
            'PROF_RESP' => '87654321', 'NOM_PROF_RESP' => 'Pedro Soto', 'CORREO_INST' => 'pedro@example.test',
        ])]);
        $docentes = array_values(array_filter($detalles, fn ($d) => $d['entidad'] === 'Docente'));

        $this->assertCount(2, $docentes);
        $this->assertSame('agregado', $docentes[0]['tipo']);
        $this->assertStringContainsString('Pedro Soto', $docentes[0]['nombre']);
        $this->assertSame('eliminado', $docentes[1]['tipo']);
        $this->assertStringContainsString('Ana Pérez', $docentes[1]['nombre']);
        $this->assertSame(1, Models\Profesor::count());
        $this->assertSame('Ana', Models\Profesor::firstOrFail()->nombre);
    }

    public function test_retira_un_modulo_del_archivo_con_su_identificacion(): void
    {
        $this->cargar();
        $detalles = $this->comparar([$this->fila([
            'HORARIO' => 'ca: LU.1/G:1 (TH-1)', 'HORARIO_PROFESOR' => 'ca: LU.1/G:1 (TH-1)',
        ])]);

        $this->assertCount(1, $detalles);
        $this->assertSame('eliminado', $detalles[0]['tipo']);
        $this->assertSame('Horario', $detalles[0]['entidad']);
        $this->assertStringContainsString('MA', $detalles[0]['anterior']);
        $this->assertSame(2, Models\Horario::count());
    }

    public function test_las_versiones_anteriores_sin_snapshot_comparan_correo_y_sala_sin_repetir_campos(): void
    {
        $version = $this->cargar();
        $audit = DB::table('historial_acciones')->where('accion', 'Importar programación anterior')
            ->where('registro_id', $version->id)->first();
        $datos = json_decode($audit->datos, true);
        unset($datos['datos_importados']);
        DB::table('historial_acciones')->where('id', $audit->id)->update(['datos' => json_encode($datos)]);

        $this->assertSame([], $this->comparar([$this->fila()]));
        $detalles = $this->comparar([$this->fila(['CORREO_INST' => 'nueva@example.test'])]);
        $this->assertCount(1, $detalles);
        $this->assertSame('Correo institucional', $detalles[0]['campo']);
        $sala = $this->comparar([$this->fila(['HORARIO' => 'ca: LU.1/G:1 (TH-2) MA.1/G:1 (TH-1)'])]);
        $this->assertCount(1, $sala);
        $this->assertSame('Sala', $sala[0]['campo']);
    }

    public function test_la_primera_importacion_identifica_los_registros_nuevos(): void
    {
        $detalles = $this->comparar([$this->fila()]);
        $profesores = array_values(array_filter($detalles, fn ($d) => $d['entidad'] === 'Docente'));

        $this->assertCount(1, $profesores);
        $this->assertSame('agregado', $profesores[0]['tipo']);
        $this->assertStringContainsString('Ana Pérez', $profesores[0]['nombre']);
        $this->assertStringContainsString('ana@example.test', $profesores[0]['nuevo']);
        $this->assertSame(0, Models\Profesor::count());
    }

    public function test_una_seccion_historica_sin_horario_no_se_anuncia_como_nueva(): void
    {
        $version = $this->cargar([$this->fila(['HORARIO' => '', 'HORARIO_PROFESOR' => ''])]);
        $audit = DB::table('historial_acciones')->where('accion', 'Importar programación anterior')
            ->where('registro_id', $version->id)->first();
        $datos = json_decode($audit->datos, true);
        unset($datos['datos_importados']);
        DB::table('historial_acciones')->where('id', $audit->id)->update(['datos' => json_encode($datos)]);

        $this->assertSame([], $this->comparar([$this->fila(['HORARIO' => '', 'HORARIO_PROFESOR' => ''])]));
    }

    public function test_la_carrera_retirada_de_una_seccion_se_identifica_sin_borrar_su_catalogo(): void
    {
        $otra = $this->fila(['COD_CARR' => 'ANA', 'NOM_CARR' => 'Analista']);
        $this->cargar([$this->fila(), $otra]);
        $detalles = $this->comparar([$this->fila()]);
        $secciones = array_values(array_filter($detalles, fn ($d) => $d['entidad'] === 'Sección'));

        $this->assertCount(1, $secciones);
        $this->assertSame('Carreras', $secciones[0]['campo']);
        $this->assertStringContainsString('Analista', $secciones[0]['anterior']);
        $this->assertStringNotContainsString('Analista', $secciones[0]['nuevo']);
        $this->assertSame(2, Models\Carrera::count());
    }
}

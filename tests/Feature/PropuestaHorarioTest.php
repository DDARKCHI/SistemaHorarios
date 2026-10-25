<?php

namespace Tests\Feature;

use App\Models\DisponibilidadProfesor;
use App\Models\PeriodoAcademico;
use App\Models\Profesor;
use App\Models\Universidad;
use App\Models\User;
use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Permission;
use Tests\IsolatedDatabaseTestCase;

class PropuestaHorarioTest extends IsolatedDatabaseTestCase
{
    private int $universidadId;

    private Profesor $profesor;

    private Profesor $otroProfesor;

    private PeriodoAcademico $periodo;

    private PeriodoAcademico $otroPeriodo;

    private array $dias;

    private array $bloques;

    protected function setUp(): void
    {
        parent::setUp();

        $this->universidadId = DB::table('universidades')->value('id');
        $usuario = User::create([
            'name' => 'Docente de prueba', 'rut' => '22222222',
            'email' => 'disponibilidad@example.test', 'password' => 'clave-de-prueba',
        ]);
        foreach (['enviar propuesta horario', 'ver programacion semestral'] as $permiso) {
            $usuario->givePermissionTo(Permission::findOrCreate($permiso, 'web'));
        }
        $this->actingAs($usuario);
        $this->profesor = Profesor::create([
            'universidad_id' => $this->universidadId, 'rut' => $usuario->rut,
            'nombre' => 'Docente', 'apellido' => 'De prueba', 'estado' => true,
        ]);
        $this->otroProfesor = Profesor::create([
            'universidad_id' => $this->universidadId, 'rut' => '33333333',
            'nombre' => 'Otro docente', 'apellido' => 'De prueba', 'estado' => true,
        ]);
        $this->periodo = $this->crearPeriodo();
        $this->otroPeriodo = $this->crearPeriodo(['nombre' => '2025-2', 'anio' => 2025, 'estado' => false]);
        $this->dias = DB::table('dias')->where('estado', true)->orderBy('numero')->pluck('id', 'numero')->all();
        $this->bloques = DB::table('bloques_horarios')->where('estado', true)->orderBy('numero')->pluck('id', 'numero')->all();
        $this->crearDisponibilidad($this->profesor, $this->periodo, 1, 1, ['observaciones' => 'Selección propia actual']);
        $this->crearDisponibilidad($this->profesor, $this->periodo, 1, 2, [
            'estado' => false, 'tipo_disponibilidad' => 'No disponible', 'observaciones' => 'Registro propio inactivo',
        ]);
        $this->crearDisponibilidad($this->profesor, $this->otroPeriodo, 3, 3, ['observaciones' => 'Disponibilidad histórica propia']);
        $this->crearDisponibilidad($this->otroProfesor, $this->periodo, 4, 4, ['observaciones' => 'Disponibilidad de otro docente']);
    }

    public function test_el_formulario_transporta_su_periodo_y_preselecciona_solo_la_disponibilidad_propia_activa(): void
    {
        $otraUniversidad = Universidad::create(['nombre' => 'Otra universidad', 'sigla' => 'OTRA']);
        $this->crearPeriodo(['universidad_id' => $otraUniversidad->id, 'nombre' => '2030-2', 'anio' => 2030]);
        $antes = $this->snapshot();

        $response = $this->get(route('programacion.propuesta'))
            ->assertOk()
            ->assertViewHas('periodo', fn ($periodo) => $periodo->is($this->periodo))
            ->assertViewHas('profesor', fn ($profesor) => $profesor->is($this->profesor));

        $this->assertSame((string) $this->periodo->id, $this->periodoDelFormulario($response));
        $this->assertSame([$this->par(1, 1)], $this->seleccionesMarcadas($response));
        $this->assertSame($antes, $this->snapshot());
    }

    public function test_guardar_normaliza_y_deduplica_pares_sin_cambiar_otro_docente_ni_otro_periodo(): void
    {
        $fueraDelDestino = $this->snapshotFueraDelDestino();
        $primerPar = $this->par(2, 2);
        $segundoPar = $this->par(3, 4);
        $conCeros = str_pad((string) $this->dias[2], 3, '0', STR_PAD_LEFT)
            .'-'.str_pad((string) $this->bloques[2], 3, '0', STR_PAD_LEFT);

        $this->enviar([
            'periodo_id' => $this->periodo->id,
            'disponibilidades' => [$primerPar, $conCeros, $primerPar, $segundoPar],
        ])->assertRedirect(route('programacion.propuesta'))->assertSessionHasNoErrors()->assertSessionHas('success');

        $guardadas = DisponibilidadProfesor::where('profesor_id', $this->profesor->id)
            ->where('periodo_academico_id', $this->periodo->id)
            ->orderBy('dia_id')->orderBy('bloque_horario_id')->get();
        $this->assertCount(2, $guardadas);
        $this->assertSame([$primerPar, $segundoPar], $guardadas->map(
            fn ($fila) => $fila->dia_id.'-'.$fila->bloque_horario_id
        )->all());
        $this->assertTrue($guardadas->every(fn ($fila) => (bool) $fila->estado && $fila->tipo_disponibilidad === 'Disponible'));
        $this->assertSame($fueraDelDestino, $this->snapshotFueraDelDestino());
    }

    #[DataProvider('seleccionesVacias')]
    public function test_guardar_sin_seleccion_es_valido_y_solo_limpia_el_destino_propio(bool $campoPresente): void
    {
        $fueraDelDestino = $this->snapshotFueraDelDestino();
        $datos = ['periodo_id' => $this->periodo->id];
        if ($campoPresente) {
            $datos['disponibilidades'] = [];
        }

        $this->enviar($datos)->assertRedirect(route('programacion.propuesta'))
            ->assertSessionHasNoErrors()->assertSessionHas('success');

        $this->assertDatabaseMissing('disponibilidad_profesores', [
            'profesor_id' => $this->profesor->id, 'periodo_academico_id' => $this->periodo->id,
        ]);
        $this->assertSame($fueraDelDestino, $this->snapshotFueraDelDestino());
    }

    public static function seleccionesVacias(): array
    {
        return ['casillas ausentes' => [false], 'array vacío' => [true]];
    }

    #[DataProvider('paresInvalidos')]
    public function test_un_par_inexistente_o_inactivo_rechaza_toda_la_seleccion_sin_guardado_parcial(string $caso): void
    {
        $invalido = match ($caso) {
            'dia_inexistente' => '999999-'.$this->bloques[5],
            'bloque_inexistente' => $this->dias[5].'-999999',
            'dia_inactivo' => $this->par(6, 5),
            'bloque_inactivo' => $this->par(5, 15),
        };
        if ($caso === 'dia_inactivo') {
            DB::table('dias')->where('id', $this->dias[6])->update(['estado' => false]);
        }
        if ($caso === 'bloque_inactivo') {
            DB::table('bloques_horarios')->where('id', $this->bloques[15])->update(['estado' => false]);
        }
        $antes = $this->snapshot();

        $this->enviar([
            'periodo_id' => $this->periodo->id,
            'disponibilidades' => [$this->par(2, 2), $invalido],
        ])->assertRedirect(route('programacion.propuesta'))
            ->assertSessionHasErrors('disponibilidades')->assertSessionMissing('success');

        $this->assertSame($antes, $this->snapshot());
    }

    public static function paresInvalidos(): array
    {
        return [
            'día inexistente' => ['dia_inexistente'],
            'bloque inexistente' => ['bloque_inexistente'],
            'día inactivo' => ['dia_inactivo'],
            'bloque inactivo' => ['bloque_inactivo'],
        ];
    }

    #[DataProvider('periodosInvalidos')]
    public function test_el_periodo_es_obligatorio_entero_y_de_la_institucion_del_docente(string $caso): void
    {
        $datos = ['disponibilidades' => [$this->par(2, 2)]];
        if ($caso === 'no_entero') {
            $datos['periodo_id'] = 'no-es-un-periodo';
        }
        if ($caso === 'otra_universidad') {
            $otraUniversidad = Universidad::create(['nombre' => 'Otra universidad', 'sigla' => 'OTRA']);
            $extranjero = $this->crearPeriodo([
                'universidad_id' => $otraUniversidad->id, 'nombre' => '2030-2', 'anio' => 2030,
            ]);
            $datos['periodo_id'] = $extranjero->id;
        }
        $antes = $this->snapshot();

        $this->enviar($datos)->assertRedirect(route('programacion.propuesta'))
            ->assertSessionHasErrors('periodo_id')->assertSessionMissing('success');

        $this->assertSame($antes, $this->snapshot());
        $regreso = $this->get(route('programacion.propuesta'))->assertOk();
        $this->assertSame((string) $this->periodo->id, $this->periodoDelFormulario($regreso));
        $this->assertSame([$this->par(1, 1)], $this->seleccionesMarcadas($regreso));
        $this->assertSame($antes, $this->snapshot());
    }

    public static function periodosInvalidos(): array
    {
        return [
            'período omitido' => ['omitido'],
            'identificador no entero' => ['no_entero'],
            'período de otra universidad' => ['otra_universidad'],
        ];
    }

    public function test_una_pagina_obsoleta_no_borra_otro_periodo_ni_repuebla_su_formulario_con_la_seleccion_vieja(): void
    {
        $paginaAnterior = $this->get(route('programacion.propuesta'))->assertOk();
        $this->assertSame((string) $this->periodo->id, $this->periodoDelFormulario($paginaAnterior));
        // El empate de año y semestre también debe resolverse por el ID más reciente.
        $nuevoPeriodo = $this->crearPeriodo(['nombre' => '2026-2 actualizado']);
        $this->crearDisponibilidad($this->profesor, $nuevoPeriodo, 5, 5, ['observaciones' => 'Selección del nuevo período']);
        $antes = $this->snapshot();

        $this->enviar(['periodo_id' => $this->periodo->id])
            ->assertSessionHasErrors('periodo_id')->assertSessionMissing('success');
        $this->assertSame($antes, $this->snapshot());
        $this->enviar(['periodo_id' => $this->periodo->id, 'disponibilidades' => [$this->par(1, 1)]])
            ->assertSessionHasErrors('periodo_id')->assertSessionMissing('success');
        $this->assertSame($antes, $this->snapshot());

        $paginaActual = $this->get(route('programacion.propuesta'))
            ->assertOk()
            ->assertViewHas('periodo', fn ($periodo) => $periodo->is($nuevoPeriodo))
            ->assertSee('El período académico cambió. Actualiza la página y revisa tu propuesta antes de guardarla.');
        $this->assertSame((string) $nuevoPeriodo->id, $this->periodoDelFormulario($paginaActual));
        $this->assertSame([$this->par(5, 5)], $this->seleccionesMarcadas($paginaActual));
        $this->assertSame($antes, $this->snapshot());
    }

    #[DataProvider('formatosInvalidos')]
    public function test_los_formatos_invalidos_preservan_los_datos_y_el_get_de_regreso_no_falla(string $caso): void
    {
        $antes = $this->snapshot();
        $valor = $caso === 'escalar' ? 'no-es-un-array' : ['malformado'];

        $this->enviar(['periodo_id' => $this->periodo->id, 'disponibilidades' => $valor])
            ->assertRedirect(route('programacion.propuesta'))->assertSessionHasErrors()->assertSessionMissing('success');
        $this->assertSame($antes, $this->snapshot());
        $regreso = $this->get(route('programacion.propuesta'))->assertOk();
        $this->assertSame((string) $this->periodo->id, $this->periodoDelFormulario($regreso));
        if ($caso === 'escalar') {
            $this->assertSame([$this->par(1, 1)], $this->seleccionesMarcadas($regreso));
        }
        $this->assertSame($antes, $this->snapshot());
    }

    public static function formatosInvalidos(): array
    {
        return ['campo escalar' => ['escalar'], 'par con formato inválido' => ['par_malformado']];
    }

    public function test_un_dia_desactivado_antes_de_la_transaccion_impide_el_reemplazo_completo(): void
    {
        $antes = $this->snapshot();
        $cambioAplicado = false;
        DB::connection()->beforeStartingTransaction(function () use (&$cambioAplicado) {
            if ($cambioAplicado) {
                return;
            }
            $cambioAplicado = true;
            DB::table('dias')->where('id', $this->dias[2])->update(['estado' => false]);
        });

        $this->enviar(['periodo_id' => $this->periodo->id, 'disponibilidades' => [$this->par(2, 2)]])
            ->assertSessionHasErrors('disponibilidades')->assertSessionMissing('success');

        $this->assertTrue($cambioAplicado);
        $this->assertSame($antes, $this->snapshot());
    }

    public function test_un_periodo_activado_antes_de_la_transaccion_no_recibe_la_propuesta_del_periodo_anterior(): void
    {
        $cambioAplicado = false;
        $esperadoTrasCambio = null;
        DB::connection()->beforeStartingTransaction(function () use (&$cambioAplicado, &$esperadoTrasCambio) {
            if ($cambioAplicado) {
                return;
            }
            $cambioAplicado = true;
            $nuevoPeriodo = $this->crearPeriodo(['nombre' => '2027-1', 'anio' => 2027, 'numero_semestre' => 1]);
            $this->crearDisponibilidad($this->profesor, $nuevoPeriodo, 6, 6, ['observaciones' => 'Propuesta del nuevo período']);
            $esperadoTrasCambio = $this->snapshot();
        });

        $this->enviar(['periodo_id' => $this->periodo->id, 'disponibilidades' => [$this->par(2, 2)]])
            ->assertSessionHasErrors('periodo_id')->assertSessionMissing('success');

        $this->assertTrue($cambioAplicado);
        $this->assertNotNull($esperadoTrasCambio);
        $this->assertSame($esperadoTrasCambio, $this->snapshot());
    }

    private function enviar(array $datos): TestResponse
    {
        return $this->from(route('programacion.propuesta'))->post(route('programacion.propuesta.guardar'), $datos);
    }

    private function crearPeriodo(array $cambios = []): PeriodoAcademico
    {
        return PeriodoAcademico::create(array_replace([
            'universidad_id' => $this->universidadId, 'nombre' => '2026-2',
            'anio' => 2026, 'numero_semestre' => 2, 'estado' => true,
        ], $cambios));
    }

    private function crearDisponibilidad(Profesor $profesor, PeriodoAcademico $periodo, int $dia, int $bloque, array $cambios = []): DisponibilidadProfesor
    {
        return DisponibilidadProfesor::create(array_replace([
            'profesor_id' => $profesor->id, 'periodo_academico_id' => $periodo->id,
            'dia_id' => $this->dias[$dia], 'bloque_horario_id' => $this->bloques[$bloque],
            'tipo_disponibilidad' => 'Disponible', 'estado' => true,
        ], $cambios));
    }

    private function par(int $dia, int $bloque): string
    {
        return $this->dias[$dia].'-'.$this->bloques[$bloque];
    }

    private function snapshot(): array
    {
        return DB::table('disponibilidad_profesores')->orderBy('id')->get()->map(fn ($fila) => (array) $fila)->all();
    }

    private function snapshotFueraDelDestino(): array
    {
        return DB::table('disponibilidad_profesores')
            ->where(function ($query) {
                $query->where('profesor_id', '!=', $this->profesor->id)
                    ->orWhere('periodo_academico_id', '!=', $this->periodo->id);
            })
            ->orderBy('id')->get()->map(fn ($fila) => (array) $fila)->all();
    }

    private function xpath(TestResponse $response): DOMXPath
    {
        $documento = new DOMDocument;
        $documento->loadHTML($response->getContent(), LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);

        return new DOMXPath($documento);
    }

    private function periodoDelFormulario(TestResponse $response): string
    {
        $nodo = $this->xpath($response)->query('//form[@id="form-propuesta"]//input[@type="hidden" and @name="periodo_id"]')->item(0);
        $this->assertNotNull($nodo, 'La propuesta debe incluir el período académico mostrado.');

        return $nodo->getAttribute('value');
    }

    private function seleccionesMarcadas(TestResponse $response): array
    {
        $valores = [];
        foreach ($this->xpath($response)->query('//input[@name="disponibilidades[]" and @checked]') as $nodo) {
            $valores[] = $nodo->getAttribute('value');
        }
        sort($valores);

        return $valores;
    }
}

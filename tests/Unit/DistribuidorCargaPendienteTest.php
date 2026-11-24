<?php

namespace Tests\Unit;

use App\Services\DistribuidorCargaPendiente;
use PHPUnit\Framework\TestCase;

class DistribuidorCargaPendienteTest extends TestCase
{
    private function opciones(): array
    {
        return ['a' => ['dia_id' => 1, 'inicio' => 8 * 3600, 'fin' => 9 * 3600, 'orden' => 1],
            'b' => ['dia_id' => 1, 'inicio' => 9 * 3600, 'fin' => 10 * 3600, 'orden' => 2],
            'solapado' => ['dia_id' => 1, 'inicio' => 8 * 3600 + 1800, 'fin' => 9 * 3600 + 1800, 'orden' => 3],
            'martes' => ['dia_id' => 2, 'inicio' => 8 * 3600, 'fin' => 9 * 3600, 'orden' => 4]];
    }
    private function trabajo(string $id, int $grupo, array $slots = ['a', 'b'], array $recursos = [], int $prioridad = 256): array
    {
        return ['id' => $id, 'orden' => 1, 'grupo_id' => $grupo, 'carga' => 1, 'prioridad' => $prioridad, 'opciones' => $slots, 'recursos' => ['grupo:'.$grupo, ...$recursos]];
    }
    private function paquete(string $sala): array { return ['recursos' => ['sala:'.$sala], 'preferencia' => 0]; }

    public function test_elige_espacios_diferentes_en_el_mismo_intervalo(): void
    {
        $t = [$this->trabajo('uno', 1, ['a']), $this->trabajo('dos', 2, ['a'])];
        $p = [1 => ['x' => $this->paquete('x'), 'y' => $this->paquete('y')], 2 => ['x' => $this->paquete('x'), 'y' => $this->paquete('y')]];
        $r = (new DistribuidorCargaPendiente)->distribuir($t, $this->opciones(), $p, []);
        $this->assertSame('completo', $r['estado']); $this->assertNotSame($r['plan']['uno']['paquete'], $r['plan']['dos']['paquete']);
        $this->assertTrue((new DistribuidorCargaPendiente)->valido($r['plan'], $t, $this->opciones(), $p, []));
    }
    public function test_docentes_y_secciones_comparten_reserva_aunque_la_sala_cambie(): void
    {
        foreach (['profesor:8', 'curso:4', 'movil:1'] as $recurso) {
            $t = [$this->trabajo('uno', 1, ['a'], [$recurso]), $this->trabajo('dos', 2, ['a'], [$recurso])];
            $p = [1 => ['x' => $this->paquete('x')], 2 => ['y' => $this->paquete('y')]];
            $r = (new DistribuidorCargaPendiente)->distribuir($t, $this->opciones(), $p, []);
            $this->assertSame('sin_solucion', $r['estado']); $this->assertSame([], $r['plan']);
        }
    }
    public function test_compara_intervalos_reales_con_modulos_fijos_y_admite_adyacentes(): void
    {
        $t = [$this->trabajo('nuevo', 1, ['a', 'solapado', 'b'])]; $p = [1 => ['x' => $this->paquete('x')]];
        $f = [['id' => 1, 'dia_id' => 1, 'inicio' => 8 * 3600, 'fin' => 9 * 3600, 'recursos' => ['sala:x']]];
        $r = (new DistribuidorCargaPendiente)->distribuir($t, $this->opciones(), $p, $f);
        $this->assertSame('completo', $r['estado']); $this->assertSame('b', $r['plan']['nuevo']['slot']);
    }
    public function test_prioridad_resuelve_el_orden_cuando_las_restricciones_son_iguales(): void
    {
        $t = [$this->trabajo('menor', 1, prioridad: 2), $this->trabajo('mayor', 2, prioridad: 1)];
        $p = [1 => ['x' => $this->paquete('x')], 2 => ['x' => $this->paquete('x')]];
        $r = (new DistribuidorCargaPendiente)->distribuir($t, $this->opciones(), $p, []);
        $this->assertSame('a', $r['plan']['mayor']['slot']); $this->assertSame('b', $r['plan']['menor']['slot']);
    }
    public function test_retrocede_cuando_la_primera_combinacion_impide_completar(): void
    {
        // Ambos tienen cuatro combinaciones; el primero debe dejar libre la única sala del segundo en a.
        $t = [$this->trabajo('primero', 1, ['a', 'b']), $this->trabajo('segundo', 2, ['a', 'b'], ['profesor:2'])];
        $p = [1 => ['x' => $this->paquete('x'), 'y' => $this->paquete('y')], 2 => ['x' => $this->paquete('x'), 'z' => $this->paquete('z')]];
        $f = [['id' => 1, 'dia_id' => 1, 'inicio' => 9 * 3600, 'fin' => 10 * 3600, 'recursos' => ['profesor:2']],
            ['id' => 2, 'dia_id' => 1, 'inicio' => 8 * 3600, 'fin' => 9 * 3600, 'recursos' => ['sala:z']]];
        $r = (new DistribuidorCargaPendiente)->distribuir($t, $this->opciones(), $p, $f);
        $this->assertSame('completo', $r['estado']); $this->assertSame('a', $r['plan']['segundo']['slot']); $this->assertSame('x', $r['plan']['segundo']['paquete']);
        $this->assertTrue((new DistribuidorCargaPendiente)->valido($r['plan'], $t, $this->opciones(), $p, $f));
    }
    public function test_modulos_intercambiables_de_grupo_no_se_superponen(): void
    {
        $a = $this->trabajo('primero', 1); $b = $this->trabajo('segundo', 1); $b['orden'] = 2;
        $p = [1 => ['x' => $this->paquete('x')]]; $r = (new DistribuidorCargaPendiente)->distribuir([$a, $b], $this->opciones(), $p, []);
        $this->assertSame('completo', $r['estado']); $this->assertSame('a', $r['plan']['primero']['slot']); $this->assertSame('b', $r['plan']['segundo']['slot']);
    }
    public function test_limites_no_devuelven_propuestas_parciales(): void
    {
        $t = [$this->trabajo('uno', 1)]; $p = [1 => ['x' => $this->paquete('x')]];
        foreach ([[1, 8.0], [1000, 0.0]] as [$intentos, $segundos]) {
            $r = (new DistribuidorCargaPendiente)->distribuir($t, $this->opciones(), $p, [], $intentos, $segundos);
            $this->assertSame('limite', $r['estado']); $this->assertSame([], $r['plan']);
        }
    }

    public function test_concentra_varios_modulos_del_docente_en_el_dia_que_ya_asiste(): void
    {
        $primero = $this->trabajo('uno', 1, ['a', 'b', 'martes'], ['profesor:1']);
        $segundo = $primero;
        $primero['carga'] = $segundo['carga'] = 2;
        $segundo['id'] = 'dos';
        $segundo['orden'] = 2;
        $paquetes = [1 => ['sala' => $this->paquete('x')]];
        $motor = new DistribuidorCargaPendiente;

        $resultado = $motor->distribuir([$primero, $segundo], $this->opciones(), $paquetes, []);

        $this->assertSame('completo', $resultado['estado']);
        $this->assertSame('a', $resultado['plan']['uno']['slot']);
        $this->assertSame('b', $resultado['plan']['dos']['slot']);
        $this->assertTrue($motor->valido($resultado['plan'], [$primero, $segundo], $this->opciones(), $paquetes, []));
    }

    public function test_concentra_clases_de_distintos_ramos_del_mismo_docente(): void
    {
        $trabajos = [
            $this->trabajo('ramo-a', 1, ['a'], ['profesor:1']),
            $this->trabajo('ramo-b', 2, ['b', 'martes'], ['profesor:1']),
        ];
        $paquetes = [1 => ['sala' => $this->paquete('x')], 2 => ['sala' => $this->paquete('x')]];

        $resultado = (new DistribuidorCargaPendiente)->distribuir($trabajos, $this->opciones(), $paquetes, []);

        $this->assertSame('a', $resultado['plan']['ramo-a']['slot']);
        $this->assertSame('b', $resultado['plan']['ramo-b']['slot']);
    }

    public function test_prefiere_adjuntar_una_clase_a_una_jornada_manual_antes_que_crear_otro_dia(): void
    {
        $opciones = [
            'junto' => ['dia_id' => 1, 'inicio' => 12 * 3600, 'fin' => 13 * 3600, 'orden' => 1],
            'otro-dia' => ['dia_id' => 2, 'inicio' => 8 * 3600, 'fin' => 9 * 3600, 'orden' => 2],
        ];
        $fijos = [['id' => 10, 'dia_id' => 1, 'inicio' => 13 * 3600, 'fin' => 14 * 3600, 'recursos' => ['profesor:1']]];
        $trabajo = $this->trabajo('nuevo', 1, array_keys($opciones), ['profesor:1']);
        $paquetes = [1 => ['sala' => $this->paquete('x')]];

        $resultado = (new DistribuidorCargaPendiente)->distribuir([$trabajo], $opciones, $paquetes, $fijos);

        $this->assertSame('junto', $resultado['plan']['nuevo']['slot']);
    }

    public function test_evitar_una_doble_jornada_puede_prevalecer_sobre_usar_el_mismo_dia(): void
    {
        $opciones = [
            'tarde' => ['dia_id' => 1, 'inicio' => 15 * 3600, 'fin' => 16 * 3600, 'orden' => 1],
            'manana' => ['dia_id' => 2, 'inicio' => 8 * 3600, 'fin' => 9 * 3600, 'orden' => 2],
        ];
        $fijos = [['id' => 10, 'dia_id' => 1, 'inicio' => 8 * 3600, 'fin' => 9 * 3600, 'recursos' => ['profesor:1']]];
        $trabajo = $this->trabajo('nuevo', 1, array_keys($opciones), ['profesor:1']);
        $paquetes = [1 => ['sala' => $this->paquete('x')]];

        $resultado = (new DistribuidorCargaPendiente)->distribuir([$trabajo], $opciones, $paquetes, $fijos);

        $this->assertSame('manana', $resultado['plan']['nuevo']['slot']);
    }

    public function test_rellena_una_ventana_docente_antes_que_abrir_otro_dia(): void
    {
        $fijos = [
            ['id' => 10, 'dia_id' => 1, 'inicio' => 8 * 3600, 'fin' => 9 * 3600, 'recursos' => ['profesor:1']],
            ['id' => 11, 'dia_id' => 1, 'inicio' => 10 * 3600, 'fin' => 11 * 3600, 'recursos' => ['profesor:1']],
        ];
        $trabajo = $this->trabajo('nuevo', 1, ['b', 'martes'], ['profesor:1']);
        $paquetes = [1 => ['sala' => $this->paquete('x')]];

        $resultado = (new DistribuidorCargaPendiente)->distribuir([$trabajo], $this->opciones(), $paquetes, $fijos);

        $this->assertSame('b', $resultado['plan']['nuevo']['slot']);
    }

    public function test_prefiere_terminar_temprano_antes_que_ajustar_levemente_el_tamano_de_la_sala(): void
    {
        $trabajo = $this->trabajo('nuevo', 1, ['a', 'solapado'], ['profesor:1']);
        $grande = $this->paquete('grande');
        $grande['preferencia'] = 0.9;
        $paquetes = [1 => ['grande' => $grande, 'pequena' => $this->paquete('pequena')]];
        $fijos = [['id' => 10, 'dia_id' => 1, 'inicio' => 8 * 3600, 'fin' => 8 * 3600 + 1800, 'recursos' => ['sala:pequena']]];

        $resultado = (new DistribuidorCargaPendiente)->distribuir([$trabajo], $this->opciones(), $paquetes, $fijos);

        $this->assertSame('a', $resultado['plan']['nuevo']['slot']);
        $this->assertSame('grande', $resultado['plan']['nuevo']['paquete']);
    }

    public function test_la_prioridad_prevalece_aunque_el_otro_curso_tenga_menos_combinaciones(): void
    {
        $trabajos = [$this->trabajo('menor', 1), $this->trabajo('mayor', 2, ['a', 'b', 'martes'], prioridad: 1)];
        $paquetes = [1 => ['lab' => $this->paquete('lab')], 2 => ['lab' => $this->paquete('lab')]];

        $resultado = (new DistribuidorCargaPendiente)->distribuir($trabajos, $this->opciones(), $paquetes, []);

        $this->assertSame('completo', $resultado['estado']);
        $this->assertSame('a', $resultado['plan']['mayor']['slot']);
        $this->assertSame('b', $resultado['plan']['menor']['slot']);
    }

    public function test_prefiere_el_laboratorio_con_prioridad_a_una_sala_ordinaria(): void
    {
        $trabajos = [$this->trabajo('backend', 1, ['a'])];
        $laboratorio = $this->paquete('lab') + ['prioridad' => 1];
        $laboratorio['preferencia'] = 20;
        $paquetes = [1 => ['aula' => $this->paquete('aula'), 'lab' => $laboratorio]];

        $resultado = (new DistribuidorCargaPendiente)->distribuir($trabajos, $this->opciones(), $paquetes, []);

        $this->assertSame('lab', $resultado['plan']['backend']['paquete']);
    }

    public function test_conserva_el_espacio_admitido_del_modulo_y_lo_comprueba_al_validar(): void
    {
        $motor = new DistribuidorCargaPendiente;
        $trabajo = $this->trabajo('manual', 1, ['a']);
        $trabajo['paquetes'] = ['elegida'];
        $trabajo['horario_id'] = 12;
        $paquetes = [1 => ['otra' => $this->paquete('a'), 'elegida' => $this->paquete('z')]];

        $resultado = $motor->distribuir([$trabajo], $this->opciones(), $paquetes, []);

        $this->assertSame('elegida', $resultado['plan']['manual']['paquete']);
        $this->assertTrue($motor->valido($resultado['plan'], [$trabajo], $this->opciones(), $paquetes, []));
        $alterado = $resultado['plan'];
        $alterado['manual']['paquete'] = 'otra';
        $this->assertFalse($motor->valido($alterado, [$trabajo], $this->opciones(), $paquetes, []));
        $trabajo['paquetes'] = [];
        $this->assertSame('sin_opciones', $motor->distribuir([$trabajo], $this->opciones(), $paquetes, [])['estado']);
        $trabajo['paquetes'] = ['ajena'];
        $this->assertSame('sin_opciones', $motor->distribuir([$trabajo], $this->opciones(), $paquetes, [])['estado']);
    }

    public function test_un_modulo_manual_posterior_no_impide_crear_carga_en_una_hora_anterior(): void
    {
        $manual = $this->trabajo('manual', 1, ['b']);
        $manual['horario_id'] = 15;
        $nuevo = $this->trabajo('nuevo', 1, ['a']);
        $nuevo['orden'] = 2;
        $paquetes = [1 => ['sala' => $this->paquete('x')]];

        $resultado = (new DistribuidorCargaPendiente)->distribuir([$manual, $nuevo], $this->opciones(), $paquetes, []);

        $this->assertSame('completo', $resultado['estado']);
        $this->assertSame('b', $resultado['plan']['manual']['slot']);
        $this->assertSame('a', $resultado['plan']['nuevo']['slot']);
    }

    public function test_modulos_con_opciones_distintas_no_se_tratan_como_intercambiables(): void
    {
        $primero = $this->trabajo('primero', 1, ['b']);
        $segundo = $this->trabajo('segundo', 1, ['a']);
        $segundo['orden'] = 2;
        $paquetes = [1 => ['sala' => $this->paquete('x')]];

        $resultado = (new DistribuidorCargaPendiente)->distribuir([$primero, $segundo], $this->opciones(), $paquetes, []);

        $this->assertSame('completo', $resultado['estado']);
    }

    public function test_si_se_acotan_alternativas_informa_limite_y_no_imposibilidad(): void
    {
        $trabajos = [$this->trabajo('primero', 1, ['a'], prioridad: 1), $this->trabajo('segundo', 2, ['a'])];
        $salasReservadas = [];
        $paquetes = [1 => [], 2 => []];
        for ($numero = 1; $numero <= 129; $numero++) {
            $clave = sprintf('sala-%03d', $numero);
            $paquetes[1][$clave] = $this->paquete($clave);
            if ($numero <= 128) $salasReservadas[] = 'sala:'.$clave;
        }
        $paquetes[2]['sala'] = ['recursos' => $salasReservadas, 'preferencia' => 0];

        $resultado = (new DistribuidorCargaPendiente)->distribuir($trabajos, $this->opciones(), $paquetes, []);

        $this->assertSame('limite', $resultado['estado']);
        $this->assertSame([], $resultado['plan']);
    }

    public function test_no_enumera_el_producto_completo_de_espacios_y_horas_en_casos_factibles(): void
    {
        $opciones = [];
        for ($indice = 0; $indice < 50; $indice++) {
            $opciones['hora-'.$indice] = ['dia_id' => intdiv($indice, 10) + 1, 'inicio' => (8 + $indice % 10) * 3600,
                'fin' => (9 + $indice % 10) * 3600, 'orden' => $indice + 1];
        }
        $espacios = [];
        for ($indice = 0; $indice < 150; $indice++) $espacios['sala-'.$indice] = $this->paquete((string) $indice);
        $trabajos = $paquetes = [];
        for ($grupo = 1; $grupo <= 50; $grupo++) {
            $trabajos[] = $this->trabajo('grupo-'.$grupo, $grupo, array_keys($opciones));
            $paquetes[$grupo] = $espacios;
        }

        $motor = new DistribuidorCargaPendiente;
        $resultado = $motor->distribuir($trabajos, $opciones, $paquetes, []);

        $this->assertSame('completo', $resultado['estado']);
        $this->assertLessThan(50000, $resultado['intentos']);
        $this->assertTrue($motor->valido($resultado['plan'], $trabajos, $opciones, $paquetes, []));
    }

    public function test_los_modulos_del_grupo_se_mantienen_en_una_misma_sede(): void
    {
        $primero = $this->trabajo('uno', 1);
        $segundo = $this->trabajo('dos', 1);
        $segundo['orden'] = 2;
        $paquetes = [1 => ['a' => $this->paquete('a') + ['sede_id' => 1], 'z' => $this->paquete('z') + ['sede_id' => 2]]];
        $fijos = [['id' => 99, 'dia_id' => 1, 'inicio' => 9 * 3600, 'fin' => 10 * 3600, 'recursos' => ['sala:a']]];
        $motor = new DistribuidorCargaPendiente;

        $resultado = $motor->distribuir([$primero, $segundo], $this->opciones(), $paquetes, $fijos);

        $this->assertSame('completo', $resultado['estado']);
        $this->assertSame('z', $resultado['plan']['uno']['paquete']);
        $this->assertSame('z', $resultado['plan']['dos']['paquete']);
        $this->assertTrue($motor->valido($resultado['plan'], [$primero, $segundo], $this->opciones(), $paquetes, $fijos));
        $alterado = $resultado['plan'];
        $alterado['uno']['paquete'] = 'a';
        $this->assertFalse($motor->valido($alterado, [$primero, $segundo], $this->opciones(), $paquetes, $fijos));
    }

    public function test_los_modulos_fijos_tambien_reservan_la_sede_del_grupo(): void
    {
        $trabajo = $this->trabajo('nuevo', 1, ['b']);
        $paquetes = [1 => ['a' => $this->paquete('a') + ['sede_id' => 1], 'z' => $this->paquete('z') + ['sede_id' => 2]]];
        $fijos = [['id' => 99, 'grupo_id' => 1, 'sede_id' => 2, 'dia_id' => 1, 'inicio' => 8 * 3600, 'fin' => 9 * 3600, 'recursos' => []]];
        $motor = new DistribuidorCargaPendiente;

        $resultado = $motor->distribuir([$trabajo], $this->opciones(), $paquetes, $fijos);

        $this->assertSame('z', $resultado['plan']['nuevo']['paquete']);
        $alterado = $resultado['plan'];
        $alterado['nuevo']['paquete'] = 'a';
        $this->assertFalse($motor->valido($alterado, [$trabajo], $this->opciones(), $paquetes, $fijos));
    }

    public function test_los_horarios_manuales_se_resuelven_antes_de_la_carga_nueva(): void
    {
        $manual = $this->trabajo('manual', 1, ['a'], prioridad: 2);
        $manual['horario_id'] = 99;
        $nuevo = $this->trabajo('nuevo', 2, prioridad: 1);
        $paquetes = [1 => ['lab' => $this->paquete('lab')], 2 => ['lab' => $this->paquete('lab')]];

        $resultado = (new DistribuidorCargaPendiente)->distribuir([$nuevo, $manual], $this->opciones(), $paquetes, []);

        $this->assertSame('manual', array_key_first($resultado['plan']));
        $this->assertSame('a', $resultado['plan']['manual']['slot']);
        $this->assertSame('b', $resultado['plan']['nuevo']['slot']);
    }

    public function test_completa_dos_mil_modulos_con_treinta_salas_y_mil_grupos(): void
    {
        $opciones = [];
        for ($dia = 1; $dia <= 5; $dia++) {
            for ($modulo = 0; $modulo < 15; $modulo++) {
                $opciones[$dia.':'.$modulo] = ['dia_id' => $dia, 'inicio' => (8 + $modulo) * 3600,
                    'fin' => (9 + $modulo) * 3600, 'orden' => count($opciones) + 1];
            }
        }
        $espacios = [];
        for ($sala = 1; $sala <= 30; $sala++) $espacios['sala-'.$sala] = $this->paquete((string) $sala) + ['sede_id' => 1];
        $trabajos = $paquetes = [];
        for ($grupo = 1; $grupo <= 1000; $grupo++) {
            for ($orden = 1; $orden <= 2; $orden++) {
                $trabajo = $this->trabajo($grupo.':'.$orden, $grupo, array_keys($opciones), ['profesor:'.$grupo]);
                $trabajo['carga'] = 2;
                $trabajo['orden'] = $orden;
                $trabajos[] = $trabajo;
            }
            $paquetes[$grupo] = $espacios;
        }
        $motor = new DistribuidorCargaPendiente;

        $resultado = $motor->distribuir($trabajos, $opciones, $paquetes, [], 5000000, 15);

        $this->assertSame('completo', $resultado['estado']);
        $this->assertCount(2000, $resultado['plan']);
        $this->assertTrue($motor->valido($resultado['plan'], $trabajos, $opciones, $paquetes, []));
    }
    public function test_rechaza_plan_incompleto_ajeno_y_con_reservas_alteradas(): void
    {
        $motor = new DistribuidorCargaPendiente; $t = [$this->trabajo('uno', 1)]; $p = [1 => ['x' => $this->paquete('x')]];
        $r = $motor->distribuir($t, $this->opciones(), $p, []); $plan = $r['plan'];
        $this->assertFalse($motor->valido([], $t, $this->opciones(), $p, []));
        $malo = $plan; $malo['uno']['grupo_id'] = 9; $this->assertFalse($motor->valido($malo, $t, $this->opciones(), $p, []));
        $malo = $plan; $malo['uno']['paquete'] = 'ajeno'; $this->assertFalse($motor->valido($malo, $t, $this->opciones(), $p, []));
        $f = [['id' => 9, 'dia_id' => 1, 'inicio' => 0, 'fin' => 86400, 'recursos' => ['sala:x']]];
        $this->assertFalse($motor->valido($plan, $t, $this->opciones(), $p, $f));
    }
}

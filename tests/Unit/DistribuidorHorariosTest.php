<?php

namespace Tests\Unit;

use App\Services\DistribuidorHorarios;
use PHPUnit\Framework\TestCase;

class DistribuidorHorariosTest extends TestCase
{
    private function opciones(): array
    {
        return [
            '1:1' => ['dia_id' => 1, 'numero_dia' => 1, 'inicio' => 8 * 3600, 'fin' => 9 * 3600],
            '1:2' => ['dia_id' => 1, 'numero_dia' => 1, 'inicio' => 8 * 3600 + 1800, 'fin' => 9 * 3600 + 1800],
            '1:3' => ['dia_id' => 1, 'numero_dia' => 1, 'inicio' => 9 * 3600, 'fin' => 10 * 3600],
            '2:1' => ['dia_id' => 2, 'numero_dia' => 2, 'inicio' => 8 * 3600, 'fin' => 9 * 3600],
        ];
    }

    private function trabajo(int $id, array $opciones, array $recursos = ['sala:1'], int $prioridad = 256): array
    {
        return ['id' => $id, 'opciones' => $opciones, 'recursos' => $recursos, 'prioridad' => $prioridad, 'original' => '1:1'];
    }

    public function test_evade_solapamientos_reales_y_permite_intervalos_contiguos(): void
    {
        $motor = new DistribuidorHorarios;
        $t = [$this->trabajo(1, ['1:1']), $this->trabajo(2, ['1:2', '1:3'])];
        $r = $motor->distribuir($t, $this->opciones());
        $this->assertSame('completo', $r['estado']);
        $this->assertSame('1:3', $r['plan'][2]);
        $this->assertTrue($motor->valido($r['plan'], $t, $this->opciones()));
    }

    public function test_conflictos_por_cualquier_recurso_y_por_dia(): void
    {
        foreach (['profesor:1', 'curso:1', 'sala:1', 'laboratorio:1', 'movil:1'] as $recurso) {
            $t = [$this->trabajo(1, ['1:1'], [$recurso]), $this->trabajo(2, ['1:1', '2:1'], [$recurso])];
            $r = (new DistribuidorHorarios)->distribuir($t, $this->opciones());
            $this->assertSame('completo', $r['estado']); $this->assertSame('2:1', $r['plan'][2]);
        }
    }

    public function test_modulos_independientes_pueden_coincidir(): void
    {
        $r = (new DistribuidorHorarios)->distribuir([$this->trabajo(1, ['1:1'], ['sala:1']), $this->trabajo(2, ['1:1'], ['sala:2'])], $this->opciones());
        $this->assertSame('completo', $r['estado']);
    }

    public function test_busca_alternativas_con_retroceso(): void
    {
        $t = [$this->trabajo(1, ['1:1', '1:3']), $this->trabajo(2, ['1:1', '2:1']), $this->trabajo(3, ['1:1', '2:1'])];
        $motor = new DistribuidorHorarios; $r = $motor->distribuir($t, $this->opciones());
        $this->assertSame('completo', $r['estado']); $this->assertTrue($motor->valido($r['plan'], $t, $this->opciones()));
        $this->assertSame('1:3', $r['plan'][1]);
    }

    public function test_distingue_imposibilidad_y_limite_sin_devolver_planes_parciales(): void
    {
        $t = [$this->trabajo(1, ['1:1']), $this->trabajo(2, ['1:1'])];
        $motor = new DistribuidorHorarios;
        $this->assertSame('sin_solucion', $motor->distribuir($t, $this->opciones())['estado']);
        $r = $motor->distribuir($t, $this->opciones(), 0);
        $this->assertSame('limite', $r['estado']); $this->assertSame([], $r['plan']);
        $this->assertSame('sin_opciones', $motor->distribuir([$this->trabajo(1, [])], $this->opciones())['estado']);
    }

    public function test_rechaza_planes_incompletos_alterados_o_con_topes(): void
    {
        $t = [$this->trabajo(1, ['1:1']), $this->trabajo(2, ['1:1', '1:3'])];
        $motor = new DistribuidorHorarios;
        foreach ([[1 => '1:1'], [1 => '1:1', 2 => '1:2'], [1 => '1:1', 2 => '1:1'], [1 => '1:1', 2 => '1:3', 3 => '2:1']] as $plan) $this->assertFalse($motor->valido($plan, $t, $this->opciones()));
    }

    public function test_prioriza_nivel_uno_entre_trabajos_igual_de_restringidos(): void
    {
        $r = (new DistribuidorHorarios)->distribuir([$this->trabajo(1, ['1:1', '2:1'], ['sala:1'], 2), $this->trabajo(2, ['1:1', '2:1'], ['sala:1'], 1)], $this->opciones());
        $this->assertSame('1:1', $r['plan'][2]);
        $this->assertSame('2:1', $r['plan'][1]);
    }

    public function test_prefiere_distribuir_dos_modulos_del_grupo_en_dos_dias(): void
    {
        $t = [];
        foreach ([1, 2] as $id) $t[] = $this->trabajo($id, ['1:1', '1:3', '2:1'], ['curso:1', 'grupo:1']) + ['grupo_id' => 1, 'carga' => 2];
        $r = (new DistribuidorHorarios)->distribuir($t, $this->opciones());
        $this->assertSame('completo', $r['estado']);
        $this->assertCount(2, array_unique(array_map(fn ($id) => $this->opciones()[$id]['dia_id'], $r['plan'])));
    }
}

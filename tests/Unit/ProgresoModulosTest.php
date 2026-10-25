<?php

namespace Tests\Unit;

use App\Support\ProgresoModulos;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ProgresoModulosTest extends TestCase
{
    #[DataProvider('cargasHorarias')]
    public function test_el_estado_y_la_posibilidad_de_agregar_dependen_de_cantidades_exactas(
        int $asignados,
        int $requeridos,
        string $estado,
        ?float $porcentaje,
        int $faltantes,
        int $excedentes,
        bool $puedeAgregar
    ): void {
        $progreso = ProgresoModulos::calcular($asignados, $requeridos);

        $this->assertSame($estado, $progreso['estado']);
        $this->assertSame($porcentaje, $progreso['porcentaje']);
        $this->assertSame($faltantes, $progreso['faltantes']);
        $this->assertSame($excedentes, $progreso['excedentes']);
        $this->assertSame($puedeAgregar, $progreso['puede_agregar']);
    }

    public static function cargasHorarias(): array
    {
        return [
            'sin horarios' => [0, 3, 'sin_asignar', 0.0, 3, 0, true],
            'asignación parcial' => [1, 3, 'pendiente', 33.3, 2, 0, true],
            'exactamente completa' => [3, 3, 'completo', 100.0, 0, 0, false],
            'un módulo excedido' => [3, 2, 'excedido', 150.0, 0, 1, false],
            'varios módulos excedidos' => [5, 2, 'excedido', 250.0, 0, 3, false],
            'pendiente que antes redondeaba a cien' => [199, 200, 'pendiente', 99.5, 1, 0, true],
            'pendiente que antes redondeaba a cero' => [1, 255, 'pendiente', 0.4, 254, 0, true],
            'exceso pequeño visible' => [256, 255, 'excedido', 100.4, 0, 1, false],
            'carga cero sin horarios' => [0, 0, 'sin_carga', null, 0, 0, false],
            'carga cero con horarios' => [1, 0, 'sin_carga', null, 0, 0, false],
            'carga inválida' => [1, -1, 'sin_carga', null, 0, 0, false],
        ];
    }
}

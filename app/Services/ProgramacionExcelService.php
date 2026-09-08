<?php

namespace App\Services;

class ProgramacionExcelService
{
    public function analizar(array $hojas): array
    {
        $registros = $hojas[0] ?? [];

        if (empty($registros)) {
            return [
                'valido' => false,
                'error' => 'El archivo no contiene información.',
                'encabezados' => [],
                'registros' => [],
                'total_registros' => 0,
                'filas_vacias' => 0,
                'total_columnas' => 0,
                'faltantes' => [],
            ];
        }

        $encabezados = array_keys($registros[0] ?? []);

        $filasVacias = 0;
        $registrosNormalizados = [];

        foreach ($registros as $fila) {
            if ($this->filaEstaVacia($fila)) {
                $filasVacias++;
                continue;
            }

            $registrosNormalizados[] = $fila;
        }

        $columnasRequeridas = [
            'id_curso',
            'cod_ramo',
            'ramo_nombre',
            'secc',
            'semestre',
            'sede',
            'prof_resp',
            'nom_prof_resp',
            'ua',
            'nom_carr',
            'cupo',
            'modalidad',
            'categoria',
            'tipo_dictacion',
            'cod_sede',
            'estado_curso',
            'id_ramo',
        ];

        $faltantes = array_values(
            array_diff($columnasRequeridas, $encabezados)
        );

        return [
            'valido' => empty($faltantes),
            'error' => empty($faltantes)
                ? null
                : 'El archivo no contiene todas las columnas requeridas.',
            'encabezados' => $encabezados,
            'registros' => $registrosNormalizados,
            'total_registros' => count($registrosNormalizados),
            'filas_vacias' => $filasVacias,
            'total_columnas' => count($encabezados),
            'faltantes' => $faltantes,
        ];
    }

    private function filaEstaVacia(array $fila): bool
    {
        foreach ($fila as $valor) {
            if ($valor !== null && trim((string) $valor) !== '') {
                return false;
            }
        }

        return true;
    }
}
<?php

namespace App\Services;

class ProgramacionExcelService
{
    public function analizar(array $hojas): array
    {
        $filas = $hojas[0] ?? [];

        if (empty($filas)) {
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

        $encabezadosOriginales = $filas[0];

        $encabezados = array_map(
            fn ($encabezado) => $this->normalizarEncabezado($encabezado),
            $encabezadosOriginales
        );

        $registros = [];
        $filasVacias = 0;

        foreach (array_slice($filas, 1) as $fila) {
            $fila = array_values($fila);

            if ($this->filaEstaVacia($fila)) {
                $filasVacias++;
                continue;
            }

            $registro = [];

            foreach ($encabezados as $indice => $encabezado) {
                if ($encabezado === '') {
                    continue;
                }

                $registro[$encabezado] = $fila[$indice] ?? null;
            }

            $registros[] = $registro;
        }

        $columnasRequeridas = [
            'ID_CURSO',
            'COD_RAMO',
            'RAMO_NOMBRE',
            'SECC',
            'SEMESTRE',
            'SEDE',
            'PROF_RESP',
            'NOM_PROF_RESP',
            'UA',
            'NOM_CARR',
            'CUPO',
            'MODALIDAD',
            'CATEGORIA',
            'TIPO_DICTACION',
            'COD_SEDE',
            'ESTADO_CURSO',
            'ID_RAMO',
        ];

        $faltantes = array_values(
            array_diff($columnasRequeridas, $encabezados)
        );

        return [
            'valido' => empty($faltantes),
            'error' => null,
            'encabezados' => $encabezados,
            'registros' => $registros,
            'total_registros' => count($registros),
            'filas_vacias' => $filasVacias,
            'total_columnas' => count($encabezados),
            'faltantes' => $faltantes,
        ];
    }

    private function normalizarEncabezado($encabezado): string
    {
        $encabezado = trim((string) $encabezado);

        $equivalencias = [
            'DURACIÃ“N_RAMO' => 'DURACION_RAMO',
            'DURACIÓN_RAMO' => 'DURACION_RAMO',
            'DURACION_RAMO' => 'DURACION_RAMO',
        ];

        if (isset($equivalencias[$encabezado])) {
            return $equivalencias[$encabezado];
        }

        return $encabezado;
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
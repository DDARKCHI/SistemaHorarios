<?php

namespace App\Http\Controllers;

use App\Imports\ProgramacionImport;
use App\Services\ProgramacionExcelService;
use App\Services\ProgramacionComparacionService;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class ImportacionProgramacionController extends Controller
{
    public function index()
    {
        return view('programacion.importar');
    }

    public function analizar(Request $request)
    {
        $request->validate([
            'archivo' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:20480'],
        ]);

        $hojas = Excel::toArray(
            new ProgramacionImport,
            $request->file('archivo')
        );

        $excelService = new ProgramacionExcelService();

        $resultado = $excelService->analizar($hojas);

        if (!$resultado['valido']) {
            return view('programacion.importar', [
                'resultado' => $resultado + [
                    'nombre_archivo' => $request->file('archivo')->getClientOriginalName(),
                ],
            ]);
        }

        $comparacionService = new ProgramacionComparacionService();

        $comparacion = $comparacionService->comparar(
            $resultado['registros']
        );

        return view('programacion.importar', [
            'resultado' => [
                'nombre_archivo' => $request->file('archivo')->getClientOriginalName(),
                'total_registros' => $resultado['total_registros'],
                'filas_vacias' => $resultado['filas_vacias'],
                'total_columnas' => $resultado['total_columnas'],
                'encabezados' => $resultado['encabezados'],
                'faltantes' => $resultado['faltantes'],
                'valido' => $resultado['valido'],
                'primeras_filas' => array_slice($resultado['registros'], 0, 5),
                'comparacion' => $comparacion,
            ],
        ]);
    }
}
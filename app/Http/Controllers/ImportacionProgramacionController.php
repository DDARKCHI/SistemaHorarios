<?php

namespace App\Http\Controllers;

use App\Imports\ProgramacionImport;
use App\Services\ProgramacionCargaService;
use App\Services\ProgramacionComparacionService;
use App\Services\ProgramacionExcelService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
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
            'archivo' => [
                'required',
                'file',
                'mimes:xlsx,xls,csv',
                'max:20480',
            ],
        ]);

        $archivo = $request->file('archivo');

        $hojas = Excel::toArray(
            new ProgramacionImport,
            $archivo
        );

        $excelService = new ProgramacionExcelService();

        $resultado = $excelService->analizar($hojas);

        if (!$resultado['valido']) {
            return view('programacion.importar', [
                'resultado' => $resultado + [
                    'nombre_archivo' =>
                        $archivo->getClientOriginalName(),
                ],
            ]);
        }

        $rutaTemporal = $archivo->store(
            'importaciones/pendientes'
        );

        $comparacionService =
            new ProgramacionComparacionService();

        $comparacion = $comparacionService->comparar(
            $resultado['registros']
        );

        return view('programacion.importar', [
            'resultado' => [
                'nombre_archivo' =>
                    $archivo->getClientOriginalName(),

                'ruta_temporal' =>
                    $rutaTemporal,

                'total_registros' =>
                    $resultado['total_registros'],

                'filas_vacias' =>
                    $resultado['filas_vacias'],

                'total_columnas' =>
                    $resultado['total_columnas'],

                'encabezados' =>
                    $resultado['encabezados'],

                'faltantes' =>
                    $resultado['faltantes'],

                'valido' =>
                    $resultado['valido'],

                'primeras_filas' =>
                    array_slice(
                        $resultado['registros'],
                        0,
                        5
                    ),

                'comparacion' =>
                    $comparacion,
            ],
        ]);
    }

    public function confirmar(Request $request)
    {
        $request->validate([
            'ruta_temporal' => [
                'required',
                'string',
            ],
            'nombre_archivo' => [
                'required',
                'string',
                'max:255',
            ],
        ]);

        $ruta = $request->input(
            'ruta_temporal'
        );

        if (!Storage::exists($ruta)) {
            return redirect()
                ->route('programacion.importar')
                ->with(
                    'error',
                    'El archivo temporal ya no está disponible. Vuelve a cargarlo.'
                );
        }

        try {
            $rutaCompleta = Storage::path($ruta);

            $hojas = Excel::toArray(
                new ProgramacionImport,
                $rutaCompleta
            );

            $excelService =
                new ProgramacionExcelService();

            $resultado =
                $excelService->analizar($hojas);

            if (!$resultado['valido']) {
                Storage::delete($ruta);

                return redirect()
                    ->route('programacion.importar')
                    ->with(
                        'error',
                        'El archivo ya no cumple con la estructura requerida.'
                    );
            }

            $cargaService =
                new ProgramacionCargaService();

            $version = $cargaService->cargar(
                $resultado['registros'],
                auth()->id(),
                $request->input('nombre_archivo')
            );

            Storage::delete($ruta);

            return redirect()
                ->route('programacion.importar')
                ->with(
                    'success',
                    'La programación fue cargada correctamente. Se creó la versión '
                    . $version->version
                    . '.'
                );
        } catch (\Throwable $e) {
            return redirect()
                ->route('programacion.importar')
                ->with(
                    'error',
                    'No fue posible completar la carga: '
                    . $e->getMessage()
                );
        }
    }

    public function cancelar(Request $request)
    {
        $ruta = $request->input(
            'ruta_temporal'
        );

        if ($ruta && Storage::exists($ruta)) {
            Storage::delete($ruta);
        }

        return redirect()
            ->route('programacion.importar');
    }
}
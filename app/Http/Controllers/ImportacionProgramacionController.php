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

        $excelService = new ProgramacionExcelService;

        $resultado = $excelService->analizar($hojas);

        if (! $resultado['valido']) {
            return view('programacion.importar', [
                'resultado' => $resultado + [
                    'nombre_archivo' => $archivo->getClientOriginalName(),
                ],
            ]);
        }

        $comparacionService =
            new ProgramacionComparacionService;

        $comparacion = $comparacionService->comparar(
            $resultado['registros']
        );

        $rutaTemporal = $archivo->store(
            'importaciones/pendientes',
            'local'
        );

        if (! $rutaTemporal) {
            return redirect()
                ->route('programacion.importar')
                ->with('error', 'No fue posible guardar el archivo temporal. Vuelve a cargarlo.');
        }

        $request->session()->put('importaciones_pendientes.'.hash('sha256', $rutaTemporal), [
            'ruta' => $rutaTemporal,
            'usuario_id' => (string) $request->user()->getAuthIdentifier(),
            'nombre_archivo' => $archivo->getClientOriginalName(),
        ]);

        return view('programacion.importar', [
            'resultado' => [
                'nombre_archivo' => $archivo->getClientOriginalName(),

                'ruta_temporal' => $rutaTemporal,

                'total_registros' => $resultado['total_registros'],

                'filas_vacias' => $resultado['filas_vacias'],

                'total_columnas' => $resultado['total_columnas'],

                'encabezados' => $resultado['encabezados'],

                'faltantes' => $resultado['faltantes'],

                'valido' => $resultado['valido'],

                'primeras_filas' => array_slice(
                    $resultado['registros'],
                    0,
                    5
                ),

                'comparacion' => $comparacion,
            ],
        ]);
    }

    public function confirmar(Request $request)
    {
        $pendiente = $this->archivoPendiente($request);

        if (! $pendiente) {
            return $this->analisisNoDisponible();
        }

        $ruta = $pendiente['ruta'];
        $disco = Storage::disk('local');

        if (! $disco->exists($ruta)) {
            $this->olvidarArchivoPendiente($request, $ruta);

            return redirect()
                ->route('programacion.importar')
                ->with(
                    'error',
                    'El archivo temporal ya no está disponible. Vuelve a cargarlo.'
                );
        }

        try {
            $rutaCompleta = $disco->path($ruta);

            $hojas = Excel::toArray(
                new ProgramacionImport,
                $rutaCompleta
            );

            $excelService =
                new ProgramacionExcelService;

            $resultado =
                $excelService->analizar($hojas);

            if (! $resultado['valido']) {
                $disco->delete($ruta);
                $this->olvidarArchivoPendiente($request, $ruta);

                return redirect()
                    ->route('programacion.importar')
                    ->with(
                        'error',
                        'El archivo ya no cumple con la estructura requerida.'
                    );
            }

            $cargaService =
                new ProgramacionCargaService;

            $version = $cargaService->cargar(
                $resultado['registros'],
                auth()->id(),
                $pendiente['nombre_archivo']
            );

            $disco->delete($ruta);
            $this->olvidarArchivoPendiente($request, $ruta);

            return redirect()
                ->route('programacion.importar')
                ->with(
                    'success',
                    'La programación fue cargada correctamente. Se creó la versión '
                    .$version->version
                    .'.'
                );
        } catch (\Throwable $e) {
            return redirect()
                ->route('programacion.importar')
                ->with(
                    'error',
                    'No fue posible completar la carga: '
                    .$e->getMessage()
                );
        }
    }

    public function cancelar(Request $request)
    {
        $pendiente = $this->archivoPendiente($request);

        if (! $pendiente) {
            return $this->analisisNoDisponible();
        }

        $ruta = $pendiente['ruta'];
        Storage::disk('local')->delete($ruta);
        $this->olvidarArchivoPendiente($request, $ruta);

        return redirect()
            ->route('programacion.importar');
    }

    private function archivoPendiente(Request $request): ?array
    {
        $datos = $request->validate([
            'ruta_temporal' => ['required', 'string', 'max:255'],
        ]);
        $ruta = $datos['ruta_temporal'];
        $pendiente = $request->session()->get('importaciones_pendientes.'.hash('sha256', $ruta));

        if (! is_array($pendiente)
            || ($pendiente['ruta'] ?? null) !== $ruta
            || ($pendiente['usuario_id'] ?? null) !== (string) $request->user()->getAuthIdentifier()) {
            return null;
        }

        return $pendiente;
    }

    private function olvidarArchivoPendiente(Request $request, string $ruta): void
    {
        $request->session()->forget('importaciones_pendientes.'.hash('sha256', $ruta));
    }

    private function analisisNoDisponible()
    {
        return redirect()
            ->route('programacion.importar')
            ->with('error', 'El archivo no corresponde a un análisis pendiente de esta sesión. Vuelve a cargarlo.');
    }
}

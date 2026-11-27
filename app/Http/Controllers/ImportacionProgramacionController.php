<?php

namespace App\Http\Controllers;

use App\Imports\ProgramacionImport;
use App\Services\ProgramacionCargaService;
use App\Services\ProgramacionComparacionService;
use App\Services\ProgramacionExcelService;
use App\Services\ConciliacionOfertaImportada;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
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

        try {
            $hojas = Excel::toArray(new ProgramacionImport, $archivo);
        } catch (\Throwable $error) {
            report($error);

            return redirect()->route('programacion.importar')->withErrors([
                'archivo' => 'No se pudo leer el archivo. Ábrelo en tu planilla de cálculo, guárdalo como XLSX, XLS o CSV y vuelve a cargarlo.',
            ]);
        }

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

        $conciliacion = (new ConciliacionOfertaImportada)->analizar($resultado['registros']);
        $comparacion = $comparacionService->comparar($resultado['registros'], $conciliacion['periodo_id']);
        $comparacion['conciliacion'] = ['resumen' => $conciliacion['resumen'], 'errores' => array_slice($conciliacion['errores'], 0, 20),
            'total_errores' => count($conciliacion['errores']), 'avisos' => $conciliacion['avisos']];
        $comparacion['totales']['errores'] += count($conciliacion['errores']);
        foreach (['carreras', 'asignaturas', 'profesores', 'secciones', 'grupos'] as $tipo) $comparacion['totales'][$tipo] = array_sum($conciliacion['resumen'][$tipo]);
        // La huella contempla también recursos y datos que no aparecen como cambios de módulo.
        $comparacion['hay_cambios'] = ! $conciliacion['sin_cambios'];
        if ($conciliacion['sin_cambios']) {
            $comparacion['hay_cambios'] = false;
            $comparacion['resumen_cambios'] = [];
            $comparacion['detalle_cambios'] = [];
            $comparacion['conciliacion']['avisos'][] = 'Este contenido coincide con la última importación confirmada del período. No se creará otra versión idéntica.';
        }
        if ($comparacion['es_nueva_programacion']) {
            foreach (['carreras', 'asignaturas', 'profesores', 'secciones'] as $tipo) {
                $resumen = $conciliacion['resumen'][$tipo];
                $comparacion[$tipo] = [in_array($tipo, ['carreras', 'asignaturas', 'secciones'], true) ? 'nuevas' : 'nuevos' => $resumen['nuevos'],
                    'existentes' => $resumen['reutilizados'], 'modificados' => 0];
            }
        }
        if ($conciliacion['errores'] || $comparacion['errores'] || $conciliacion['sin_cambios']) {
            return view('programacion.importar', ['resultado' => collect($resultado)->except('registros')->all() + [
                'nombre_archivo' => $archivo->getClientOriginalName(), 'comparacion' => $comparacion,
                'primeras_filas' => array_slice($resultado['registros'], 0, 5),
            ]]);
        }

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
            'firma' => $conciliacion['firma'],
            'archivo_hash' => hash_file('sha256', $archivo->getRealPath()),
            'creada' => now()->timestamp,
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

            if (! hash_equals($pendiente['archivo_hash'] ?? '', hash_file('sha256', $rutaCompleta))) {
                return redirect()->route('programacion.importar')->with('error', 'El archivo cambió desde el análisis. Vuelve a analizarlo antes de confirmar.');
            }

            $cargaService =
                new ProgramacionCargaService;

            $version = $cargaService->cargar(
                $resultado['registros'],
                auth()->id(),
                $pendiente['nombre_archivo'],
                $pendiente['firma']
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
        } catch (ValidationException $e) {
            return redirect()->route('programacion.importar')->with('error', implode(' ', collect($e->errors())->flatten()->take(5)->all()));
        } catch (\Throwable $e) {
            report($e);
            return redirect()
                ->route('programacion.importar')
                ->with(
                    'error',
                    'No fue posible completar la carga. No se guardaron los cambios; revisa el archivo y vuelve a intentarlo.'
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

        if (! isset($pendiente['creada'], $pendiente['firma'], $pendiente['archivo_hash']) || $pendiente['creada'] < now()->subMinutes(30)->timestamp) {
            Storage::disk('local')->delete($ruta);
            $this->olvidarArchivoPendiente($request, $ruta);
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

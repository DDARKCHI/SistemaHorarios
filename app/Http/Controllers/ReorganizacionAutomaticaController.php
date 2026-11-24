<?php

namespace App\Http\Controllers;

use App\Models\VersionPlanificacion;
use App\Services\{DistribuidorHorarios, ReorganizacionAutomatica};
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ReorganizacionAutomaticaController extends Controller
{
    public function generar(Request $request, ReorganizacionAutomatica $motor, DistribuidorHorarios $distribuidor)
    {
        $datos = $request->validate(['version_id' => ['required', 'integer', 'exists:versiones_planificacion,id']]);
        $version = VersionPlanificacion::findOrFail($datos['version_id']);
        $entrada = $motor->preparar($version);
        $resultado = $distribuidor->distribuir($entrada['trabajos'], $entrada['opciones']);
        if ($resultado['estado'] !== 'completo') {
            $mensaje = match ($resultado['estado']) {
                'sin_opciones' => 'Una clase no tiene módulos de la misma duración, comunes para todos sus docentes y fuera del almuerzo. Revisa sus disponibilidades aprobadas.',
                'sin_solucion' => 'No existe una distribución sin topes manteniendo estos docentes y recursos en los módulos disponibles. Revisa las asignaciones o amplía la disponibilidad.',
                'limite' => 'La búsqueda alcanzó su límite sin encontrar una propuesta completa. Esto no demuestra que no exista una solución. Revisa los recursos o las disponibilidades más restringidas.',
                default => 'No hay módulos completos que reorganizar.',
            };
            throw ValidationException::withMessages(['generacion' => $mensaje]);
        }
        if (! $distribuidor->valido($resultado['plan'], $entrada['trabajos'], $entrada['opciones'])) throw ValidationException::withMessages(['generacion' => 'No fue posible comprobar la propuesta. No se guardó ningún horario.']);
        $token = (string) Str::uuid();
        $propuestas = collect($request->session()->get('reorganizaciones', []))->filter(fn ($p) => $p['creada'] >= now()->subMinutes(30)->timestamp)->take(-2)->all();
        $propuestas[$token] = ['token' => $token, 'usuario_id' => $request->user()->id, 'creada' => now()->timestamp,
            'version_id' => $version->id, 'firma' => $entrada['firma'], 'plan' => $resultado['plan']];
        $request->session()->put('reorganizaciones', $propuestas);
        return redirect()->route('programacion.reorganizacion.show', $token);
    }

    public function show(Request $request, string $token, ReorganizacionAutomatica $motor)
    {
        $validacion = $request->validate(['page' => ['nullable', 'integer', 'min:1']]);
        $propuesta = $this->propuesta($request, $token);
        $version = VersionPlanificacion::findOrFail($propuesta['version_id']);
        $datos = $motor->preparar($version);
        if (! hash_equals($datos['firma'], $propuesta['firma'])) return redirect()->route('programacion.preparacion', ['periodo_id' => $version->periodo_academico_id, 'version_id' => $version->id])->withErrors(['generacion' => 'Los datos cambiaron. Genera una propuesta nueva para revisar los cambios actuales.']);
        $filas = collect($motor->presentar($datos, $propuesta['plan']));
        $cambios = $filas->where('cambio', true)->count();
        $total = $filas->count();
        $pagina = $validacion['page'] ?? 1;
        $horarios = new LengthAwarePaginator($filas->forPage($pagina, 20)->values(), $total, 20, $pagina, ['path' => route('programacion.reorganizacion.show', $token)]);
        if ($pagina > $horarios->lastPage()) return redirect()->to($horarios->url($horarios->lastPage()));
        return response()->view('programacion.reorganizacion', compact('version', 'token', 'total', 'cambios', 'horarios'))->header('Cache-Control', 'no-store, private');
    }

    public function guardar(Request $request, string $token, ReorganizacionAutomatica $motor)
    {
        $propuesta = $this->propuesta($request, $token);
        $version = VersionPlanificacion::findOrFail($propuesta['version_id']);
        $nueva = $motor->guardar($version, $propuesta, $request->user()->id, $request->ip());
        $request->session()->forget('reorganizaciones.'.$token);
        return redirect()->route('programacion.semestral', ['periodo_id' => $nueva->periodo_academico_id, 'version_id' => $nueva->id])
            ->with('success', 'Propuesta guardada como borrador V'.$nueva->version.'. La versión actual se conserva. Selecciona un filtro para consultar sus horarios.');
    }

    private function propuesta(Request $request, string $token): array
    {
        $p = $request->session()->get('reorganizaciones.'.$token);
        abort_unless(is_array($p) && (int) $p['usuario_id'] === (int) $request->user()->id && $p['creada'] >= now()->subMinutes(30)->timestamp, 410, 'La propuesta venció o no pertenece a esta sesión. Genera una nueva.');
        return $p;
    }
}

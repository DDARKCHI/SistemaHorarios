<?php

namespace App\Http\Controllers;

use App\Models\VersionPlanificacion;
use App\Services\CopiaBorrador;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CopiaBorradorController extends Controller
{
    public function show(Request $request, VersionPlanificacion $version, CopiaBorrador $copia)
    {
        abort_unless($version->tipo === 'semestral', 404);
        $solicitud = (string) Str::uuid();
        $tokens = array_filter($request->session()->get('copias_borrador', []), fn ($t) => $t['creada'] >= now()->subMinutes(30)->timestamp);
        $tokens = array_slice($tokens, -9, null, true);
        $tokens[$solicitud] = ['version' => $version->id, 'usuario' => $request->user()->id, 'creada' => now()->timestamp];
        $request->session()->put('copias_borrador', $tokens);
        return response()->view('programacion.copiar-borrador', compact('version', 'solicitud') + $copia->revisar($version))->header('Cache-Control', 'no-store, private');
    }
    public function store(Request $request, VersionPlanificacion $version, CopiaBorrador $copia)
    {
        $datos = $request->validate(['firma' => ['required', 'regex:/^[a-f0-9]{64}$/'], 'solicitud' => ['required', 'uuid'], 'activar' => ['required', 'boolean'], 'confirmar' => ['accepted']]);
        $token = $request->session()->get('copias_borrador.'.$datos['solicitud']);
        abort_unless(is_array($token) && (int) $token['version'] === (int) $version->id && (int) $token['usuario'] === (int) $request->user()->id && $token['creada'] >= now()->subMinutes(30)->timestamp, 410, 'La revisión venció. Abre de nuevo Crear borrador para ajustes.');
        $nueva = $copia->crear($version, $datos['firma'], (bool) $datos['activar'], $datos['solicitud'], $request->user()->id, $request->ip());
        return redirect()->route('programacion.semestral', ['periodo_id' => $nueva->periodo_academico_id, 'version_id' => $nueva->id])
            ->with('success', 'Borrador V'.$nueva->version.' creado. '.($nueva->es_actual ? 'Es la versión actual para trabajar en los ajustes.' : 'La programación actual se conserva.').' Los horarios de la fuente no se modificaron.');
    }
}

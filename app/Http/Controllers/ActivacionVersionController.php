<?php

namespace App\Http\Controllers;

use App\Models\VersionPlanificacion;
use App\Services\ActivacionVersion;
use Illuminate\Http\Request;

class ActivacionVersionController extends Controller
{
    public function show(VersionPlanificacion $version, ActivacionVersion $activacion)
    {
        abort_unless($version->tipo === 'semestral', 404);
        return response()->view('programacion.activar-version', ['version' => $version] + $activacion->revisar($version))->header('Cache-Control', 'no-store, private');
    }

    public function store(Request $request, VersionPlanificacion $version, ActivacionVersion $activacion)
    {
        $datos = $request->validate(['firma' => ['required', 'string', 'size:64', 'regex:/^[a-f0-9]+$/'], 'confirmar' => ['accepted']]);
        $version = $activacion->activar($version, $datos['firma'], $request->user()->id, $request->ip());
        return redirect()->route('programacion.semestral', ['periodo_id' => $version->periodo_academico_id, 'version_id' => $version->id])
            ->with('success', 'La versión V'.$version->version.' es ahora la programación actual. Las versiones anteriores y sus horarios se conservan.');
    }
}

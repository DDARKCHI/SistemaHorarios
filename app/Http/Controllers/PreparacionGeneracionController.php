<?php

namespace App\Http\Controllers;

use App\Models\{PeriodoAcademico, VersionPlanificacion};
use App\Services\PreparacionGeneracion;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Validator;

class PreparacionGeneracionController extends Controller
{
    public function index(Request $request, PreparacionGeneracion $preparacion)
    {
        $validacion = Validator::make($request->query(), ['periodo_id' => ['nullable', 'integer', 'exists:periodos_academicos,id'],
            'version_id' => ['nullable', 'integer', 'min:1'], 'page' => ['nullable', 'integer', 'min:1']]);
        if ($validacion->fails()) return redirect()->route('programacion.preparacion')->withErrors($validacion);
        $datos = $validacion->validated();
        $periodos = PeriodoAcademico::orderByDesc('anio')->orderByDesc('numero_semestre')->orderByDesc('id')->get();
        $periodo = isset($datos['periodo_id']) ? $periodos->firstWhere('id', $datos['periodo_id']) : ($periodos->firstWhere('estado', true) ?? $periodos->first());
        $versiones = VersionPlanificacion::where('periodo_academico_id', $periodo?->id)->whereIn('tipo', ['anterior', 'semestral'])->orderByDesc('id')->get();
        $version = isset($datos['version_id']) ? $versiones->firstWhere('id', $datos['version_id'])
            : ($versiones->where('tipo', 'semestral')->firstWhere('es_actual', true) ?? $versiones->first());
        if (isset($datos['version_id']) && ! $version) return redirect()->route('programacion.preparacion', ['periodo_id' => $periodo?->id])->withErrors(['version_id' => 'Selecciona una versión del período elegido.']);
        $reporte = $periodo ? $preparacion->revisar($periodo, $version) : null;
        $filas = $reporte['observaciones'] ?? collect();
        $observaciones = new LengthAwarePaginator($filas->forPage($datos['page'] ?? 1, 20)->values(), $filas->count(), 20, $datos['page'] ?? 1,
            ['path' => route('programacion.preparacion'), 'query' => array_filter(['periodo_id' => $periodo?->id, 'version_id' => $version?->id])]);
        if ($observaciones->currentPage() > $observaciones->lastPage()) return redirect()->to($observaciones->url($observaciones->lastPage()));
        return view('programacion.preparacion', compact('periodos', 'periodo', 'versiones', 'version', 'reporte', 'observaciones'));
    }
}

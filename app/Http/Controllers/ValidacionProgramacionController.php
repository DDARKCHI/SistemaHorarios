<?php

namespace App\Http\Controllers;

use App\Models\PeriodoAcademico;
use App\Models\VersionPlanificacion;
use App\Services\RevisionConflictosProgramacion;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ValidacionProgramacionController extends Controller
{
    public function index(Request $request, RevisionConflictosProgramacion $revision)
    {
        $validador = Validator::make($request->query(), [
            'periodo_id' => ['nullable', 'integer', 'min:1', 'exists:periodos_academicos,id'],
            'version_id' => ['nullable', 'integer', 'min:1'], 'tipo' => ['nullable', 'string', Rule::in(array_keys(RevisionConflictosProgramacion::TIPOS))],
            'page' => ['nullable', 'integer', 'min:1'],
        ], [
            'periodo_id.*' => 'Selecciona un período académico válido.',
            'version_id.*' => 'Selecciona una versión semestral válida.',
            'tipo.*' => 'Selecciona un tipo de revisión disponible.',
            'page.*' => 'Selecciona una página válida.',
        ]);
        if ($validador->fails()) return redirect()->route('programacion.validacion')->withErrors($validador);
        $datos = $validador->validated();
        $periodos = PeriodoAcademico::orderByDesc('anio')->orderByDesc('numero_semestre')->orderByDesc('id')->get();
        $periodoId = $datos['periodo_id'] ?? $periodos->first()?->id;
        $versiones = VersionPlanificacion::where('periodo_academico_id', $periodoId)->where('tipo', 'semestral')->orderByDesc('id')->get();
        $version = filled($datos['version_id'] ?? null) ? $versiones->firstWhere('id', (int) $datos['version_id'])
            : ($versiones->firstWhere('es_actual', true) ?? $versiones->first());
        if (filled($datos['version_id'] ?? null) && ! $version) {
            return redirect()->route('programacion.validacion', ['periodo_id' => $periodoId])
                ->withErrors(['version_id' => 'Selecciona una versión semestral del período elegido.']);
        }
        $tipo = $datos['tipo'] ?? 'todos';
        $reporte = $version ? $revision->revisar($version) : null;
        $filas = ($reporte['observaciones'] ?? collect())->filter(fn ($fila) => $tipo === 'todos'
            || ($tipo === 'datos' ? $fila['tipo'] === 'datos' : collect($fila['recursos'])->contains('tipo', $tipo)))->values();
        $pagina = (int) ($datos['page'] ?? 1);
        $observaciones = new LengthAwarePaginator($filas->forPage($pagina, 20)->values(), $filas->count(), 20, $pagina,
            ['path' => route('programacion.validacion'), 'query' => array_filter(['periodo_id' => $periodoId, 'version_id' => $version?->id, 'tipo' => $tipo])]);
        if ($pagina > $observaciones->lastPage()) return redirect()->to($observaciones->url($observaciones->lastPage()));
        $tipos = RevisionConflictosProgramacion::TIPOS;

        return view('programacion.validacion', compact('periodos', 'periodoId', 'versiones', 'version', 'tipo', 'tipos', 'reporte', 'observaciones'));
    }
}

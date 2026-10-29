<?php

namespace App\Http\Controllers;

use App\Models\PeriodoAcademico;
use App\Models\User;
use App\Models\VersionPlanificacion;
use App\Services\HistorialReemplazos;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class HistorialReemplazoController extends Controller
{
    private const FILTROS = ['q', 'periodo_id', 'version_id', 'usuario_id', 'desde', 'hasta'];

    public function index(Request $request, HistorialReemplazos $historial)
    {
        $validador = Validator::make($request->query(), [
            'q' => ['bail', 'nullable', 'string', 'max:80'],
            'periodo_id' => ['bail', 'nullable', 'integer', 'min:1', Rule::exists('periodos_academicos', 'id')],
            'version_id' => ['bail', 'nullable', 'integer', 'min:1', Rule::exists('versiones_planificacion', 'id')],
            'usuario_id' => ['bail', 'nullable', 'integer', 'min:1', Rule::exists('users', 'id')],
            'desde' => ['bail', 'nullable', 'date_format:Y-m-d'],
            'hasta' => ['bail', 'nullable', 'date_format:Y-m-d', ...($request->filled('desde') ? ['after_or_equal:desde'] : [])],
            'page' => ['bail', 'nullable', 'integer', 'min:1'],
        ], [
            'q.*' => 'La búsqueda debe ser un texto de hasta 80 caracteres.',
            'periodo_id.*' => 'Selecciona un período registrado.', 'version_id.*' => 'Selecciona una versión registrada.',
            'usuario_id.*' => 'Selecciona un usuario registrado.', 'desde.*' => 'Ingresa una fecha inicial válida.',
            'hasta.*' => 'Ingresa una fecha final válida, igual o posterior a la fecha inicial.', 'page.*' => 'Selecciona una página válida.',
        ]);
        if ($validador->fails()) {
            $validos = Arr::only($validador->valid(), self::FILTROS);
            if ($validador->errors()->has('periodo_id')) {
                unset($validos['version_id']);
            }

            return redirect()->route('programacion.intercambio.historial', $validos)->withErrors($validador);
        }
        $filtros = array_replace(array_fill_keys(self::FILTROS, null), Arr::only($validador->validated(), self::FILTROS));
        $filtros['q'] = trim($filtros['q'] ?? '');
        if ($filtros['periodo_id'] && $filtros['version_id'] && ! VersionPlanificacion::whereKey($filtros['version_id'])
            ->where('periodo_academico_id', $filtros['periodo_id'])->exists()) {
            return redirect()->route('programacion.intercambio.historial', Arr::except($filtros, 'version_id'))
                ->withErrors(['version_id' => 'La versión no pertenece al período seleccionado. Vuelve a elegirla.']);
        }
        $registros = $historial->filtrar($historial->consulta(), $filtros)->orderByDesc('h.created_at')->orderByDesc('h.id')
            ->paginate(12)->appends(array_filter($filtros, fn ($valor) => $valor !== null && $valor !== ''));
        if ($registros->currentPage() > $registros->lastPage()) {
            return redirect()->route('programacion.intercambio.historial', array_filter($filtros) + ['page' => $registros->lastPage()]);
        }
        $registros->through(fn ($fila) => $historial->presentar($fila));
        $periodos = PeriodoAcademico::orderByDesc('anio')->orderByDesc('numero_semestre')->orderByDesc('id')->get();
        $versiones = VersionPlanificacion::when($filtros['periodo_id'], fn ($q) => $q->where('periodo_academico_id', $filtros['periodo_id']))
            ->with('periodoAcademico')->orderByDesc('id')->get();
        $usuarios = User::whereIn('id', $historial->consulta()->reorder()->select('h.usuario_id'))->orderBy('name')->get(['id', 'name']);

        return view('programacion.historial-reemplazos.index', compact('registros', 'filtros', 'periodos', 'versiones', 'usuarios'));
    }

    public function show(int $registro, HistorialReemplazos $historial)
    {
        $fila = $historial->consulta()->where('h.id', $registro)->first();
        abort_unless($fila, 404);

        return view('programacion.historial-reemplazos.detalle', ['registro' => $historial->presentar($fila, true)]);
    }
}

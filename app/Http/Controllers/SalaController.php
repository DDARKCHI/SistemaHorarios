<?php

namespace App\Http\Controllers;

use App\Models\Sala;
use App\Models\Sede;
use App\Models\TipoSala;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class SalaController extends Controller
{
    private const FILTROS = ['q', 'sede_id', 'tipo_sala_id', 'capacidad_minima', 'estado', 'uso'];

    public function index(Request $request)
    {
        $validador = Validator::make($request->query(), [
            'q' => ['bail', 'nullable', 'string', 'max:100'],
            'sede_id' => ['bail', 'nullable', 'integer', 'min:1', Rule::exists('sedes', 'id')],
            'tipo_sala_id' => ['bail', 'nullable', 'integer', 'min:1', Rule::exists('tipos_sala', 'id')],
            'capacidad_minima' => ['bail', 'nullable', 'integer', 'min:0', 'max:65535'],
            'estado' => ['bail', 'nullable', Rule::in(['activas', 'inactivas', 'todas'])],
            'uso' => ['bail', 'nullable', Rule::in(['habilitadas', 'deshabilitadas'])],
            'page' => ['bail', 'nullable', 'integer', 'min:1'],
        ], [
            'q.string' => 'La búsqueda debe ser un texto.',
            'q.max' => 'La búsqueda no puede superar los 100 caracteres.',
            'sede_id.integer' => 'Selecciona una sede válida.',
            'sede_id.min' => 'Selecciona una sede válida.',
            'sede_id.exists' => 'La sede seleccionada ya no está registrada.',
            'tipo_sala_id.integer' => 'Selecciona un tipo de sala válido.',
            'tipo_sala_id.min' => 'Selecciona un tipo de sala válido.',
            'tipo_sala_id.exists' => 'El tipo de sala seleccionado ya no está registrado.',
            'capacidad_minima.integer' => 'La capacidad mínima debe ser un número entero.',
            'capacidad_minima.min' => 'La capacidad mínima no puede ser negativa.',
            'capacidad_minima.max' => 'La capacidad mínima no puede superar las 65.535 personas.',
            'estado.in' => 'Selecciona un estado de registro válido.',
            'uso.in' => 'Selecciona una habilitación de uso válida.',
            'page.integer' => 'El número de página no es válido.',
            'page.min' => 'El número de página debe ser mayor que cero.',
        ]);

        if ($validador->fails()) {
            // Retirar los valores inválidos evita volver a una URL que repita el error.
            return redirect()->route('salas.index', Arr::only($validador->valid(), self::FILTROS))
                ->withErrors($validador);
        }

        $filtros = array_replace([
            'q' => '', 'sede_id' => null, 'tipo_sala_id' => null,
            'capacidad_minima' => null, 'estado' => 'activas', 'uso' => null,
        ], Arr::only($validador->validated(), self::FILTROS));
        $filtros['q'] = trim($filtros['q'] ?? '');
        $filtros['estado'] = $filtros['estado'] ?: 'activas';

        $consulta = Sala::with(['sede', 'tipoSala']);

        if ($filtros['q'] !== '') {
            // El texto buscado es literal, incluidos los caracteres especiales de LIKE.
            $termino = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $filtros['q']).'%';
            $consulta->where(fn ($query) => $query
                ->whereRaw("codigo LIKE ? ESCAPE '!'", [$termino])
                ->orWhereRaw("nombre LIKE ? ESCAPE '!'", [$termino])
                ->orWhereRaw("edificio LIKE ? ESCAPE '!'", [$termino]));
        }

        foreach (['sede_id', 'tipo_sala_id'] as $campo) {
            if ($filtros[$campo] !== null) {
                $consulta->where($campo, $filtros[$campo]);
            }
        }

        if ($filtros['capacidad_minima'] !== null) {
            $consulta->where('capacidad', '>=', $filtros['capacidad_minima']);
        }
        if ($filtros['estado'] !== 'todas') {
            $consulta->where('estado', $filtros['estado'] === 'activas');
        }
        if ($filtros['uso'] !== null && $filtros['uso'] !== '') {
            $consulta->where('disponible', $filtros['uso'] === 'habilitadas');
        }

        $parametros = array_filter($filtros, fn ($valor) => $valor !== null && $valor !== '');
        $salas = $consulta->orderBy('codigo')->orderBy('id')->paginate(12)->appends($parametros);

        if ($salas->currentPage() > $salas->lastPage()) {
            return redirect()->route('salas.index', $parametros + ['page' => $salas->lastPage()]);
        }

        $totales = Sala::query()->selectRaw('COUNT(*) AS total, SUM(CASE WHEN estado = 1 THEN 1 ELSE 0 END) AS activas, COUNT(DISTINCT sede_id) AS sedes')->first();
        $resumen = [
            'total' => (int) $totales->total,
            'activas' => (int) $totales->activas,
            'sedes' => (int) $totales->sedes,
        ];
        $sedes = Sede::orderBy('nombre')->orderBy('id')->get();
        $tiposSala = TipoSala::orderBy('nombre')->orderBy('id')->get();
        $hayFiltros = $filtros['q'] !== '' || $filtros['sede_id'] !== null
            || $filtros['tipo_sala_id'] !== null || $filtros['capacidad_minima'] !== null
            || $filtros['estado'] !== 'activas' || ! empty($filtros['uso']);

        return view('salas.index', compact('salas', 'sedes', 'tiposSala', 'filtros', 'resumen', 'hayFiltros'));
    }
}

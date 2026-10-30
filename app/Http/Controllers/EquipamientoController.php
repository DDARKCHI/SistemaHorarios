<?php

namespace App\Http\Controllers;

use App\Models\Equipamiento;
use App\Models\Universidad;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class EquipamientoController extends Controller
{
    private const FILTROS = ['q', 'universidad_id', 'tipo', 'cantidad_minima', 'estado', 'uso'];

    public function index(Request $request)
    {
        $validador = Validator::make($request->query(), [
            'q' => ['bail', 'nullable', 'string', 'max:100'],
            'universidad_id' => ['bail', 'nullable', 'integer', 'min:1', Rule::exists('universidades', 'id')],
            'tipo' => ['bail', 'nullable', 'string', 'max:100', Rule::exists('equipamientos', 'tipo')],
            'cantidad_minima' => ['bail', 'nullable', 'integer', 'min:0', 'max:65535'],
            'estado' => ['bail', 'nullable', Rule::in(['activos', 'inactivos', 'todos'])],
            'uso' => ['bail', 'nullable', Rule::in(['habilitados', 'deshabilitados'])],
            'page' => ['bail', 'nullable', 'integer', 'min:1'],
        ], [
            'q.*' => 'La búsqueda debe ser un texto de hasta 100 caracteres.',
            'universidad_id.*' => 'Selecciona una institución registrada.',
            'tipo.*' => 'Selecciona un tipo de equipamiento registrado.',
            'cantidad_minima.*' => 'La cantidad mínima debe ser un entero entre 0 y 65.535.',
            'estado.*' => 'Selecciona un estado de registro válido.',
            'uso.*' => 'Selecciona una habilitación de uso válida.',
            'page.*' => 'El número de página debe ser un entero positivo.',
        ]);
        if ($validador->fails()) {
            return redirect()->route('recursos.index', Arr::only($validador->valid(), self::FILTROS))->withErrors($validador);
        }
        $filtros = array_replace(['q' => '', 'universidad_id' => null, 'tipo' => null, 'cantidad_minima' => null,
            'estado' => 'activos', 'uso' => null], Arr::only($validador->validated(), self::FILTROS));
        $filtros['q'] = trim($filtros['q'] ?? '');
        $filtros['tipo'] = trim($filtros['tipo'] ?? '');
        $filtros['estado'] = $filtros['estado'] ?: 'activos';
        $consulta = Equipamiento::with('universidad');
        foreach (preg_split('/\s+/u', mb_strtolower(Str::ascii($filtros['q'])), -1, PREG_SPLIT_NO_EMPTY) as $palabra) {
            $literal = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $palabra).'%';
            $consulta->where(function ($query) use ($literal) {
                foreach (['nombre', 'tipo', 'descripcion'] as $columna) {
                    $expresion = "COALESCE($columna, '')";
                    foreach (['Á' => 'a', 'É' => 'e', 'Í' => 'i', 'Ó' => 'o', 'Ú' => 'u', 'Ü' => 'u', 'Ñ' => 'n',
                        'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n'] as $de => $a) {
                        $expresion = "REPLACE($expresion, '$de', '$a')";
                    }
                    $query->orWhereRaw("LOWER($expresion) LIKE ? ESCAPE '!'", [$literal]);
                }
            });
        }
        if ($filtros['universidad_id'] !== null) {
            $consulta->where('universidad_id', $filtros['universidad_id']);
        }
        if ($filtros['tipo'] !== '') {
            $consulta->where('tipo', $filtros['tipo']);
        }
        if ($filtros['cantidad_minima'] !== null) {
            $consulta->where('cantidad', '>=', $filtros['cantidad_minima']);
        }
        if ($filtros['estado'] !== 'todos') {
            $consulta->where('estado', $filtros['estado'] === 'activos');
        }
        if (filled($filtros['uso'])) {
            $consulta->where('disponible', $filtros['uso'] === 'habilitados');
        }
        $parametros = array_filter($filtros, fn ($valor) => $valor !== null && $valor !== '');
        $equipamientos = $consulta->orderBy('nombre')->orderBy('tipo')->orderBy('id')->paginate(12)->appends($parametros);
        if ($equipamientos->currentPage() > $equipamientos->lastPage()) {
            return redirect()->route('recursos.index', $parametros + ['page' => $equipamientos->lastPage()]);
        }
        $totales = Equipamiento::query()->selectRaw('COUNT(*) AS total, SUM(CASE WHEN estado = 1 THEN 1 ELSE 0 END) AS activos, SUM(CASE WHEN estado = 1 THEN cantidad ELSE 0 END) AS unidades')->first();
        $resumen = ['total' => (int) $totales->total, 'activos' => (int) $totales->activos, 'unidades' => (int) $totales->unidades];
        $instituciones = Universidad::orderBy('nombre')->orderBy('id')->get();
        $tipos = Equipamiento::query()->select('tipo')->distinct()->orderBy('tipo')->pluck('tipo');

        return view('recursos.index', compact('equipamientos', 'filtros', 'resumen', 'instituciones', 'tipos'));
    }

    public function create()
    {
        return $this->formulario(new Equipamiento(['cantidad' => 1, 'estado' => true, 'disponible' => true]));
    }

    public function edit(Equipamiento $equipamiento)
    {
        return $this->formulario($equipamiento);
    }

    public function store(Request $request)
    {
        return $this->guardar($request, new Equipamiento);
    }

    public function update(Request $request, Equipamiento $equipamiento)
    {
        return $this->guardar($request, $equipamiento);
    }

    private function formulario(Equipamiento $equipamiento)
    {
        $instituciones = Universidad::where('estado', true)
            ->when($equipamiento->exists, fn ($query) => $query->orWhere('id', $equipamiento->universidad_id))
            ->orderBy('nombre')->orderBy('id')->get();
        $tipos = Equipamiento::query()->select('tipo')->distinct()->orderBy('tipo')->pluck('tipo');

        return view('recursos.formulario', compact('equipamiento', 'instituciones', 'tipos'));
    }

    private function guardar(Request $request, Equipamiento $equipamiento)
    {
        $esNuevo = ! $equipamiento->exists;
        $equipamiento = DB::transaction(function () use ($request, $equipamiento, $esNuevo) {
            $equipamiento = $esNuevo ? $equipamiento : Equipamiento::query()->lockForUpdate()->findOrFail($equipamiento->id);
            $datos = $request->validate([
                'universidad_id' => ['bail', 'required', 'integer', Rule::exists('universidades', 'id')],
                'nombre' => ['required', 'string', 'max:100'],
                'tipo' => ['required', 'string', 'max:100'],
                'descripcion' => ['nullable', 'string', 'max:255'],
                'cantidad' => ['required', 'integer', 'min:0', 'max:65535'],
                'estado' => ['required', 'boolean'], 'disponible' => ['required', 'boolean'],
            ], [
                'universidad_id.*' => 'Selecciona una institución registrada.',
                'nombre.*' => 'El nombre es obligatorio y no puede superar los 100 caracteres.',
                'tipo.*' => 'El tipo es obligatorio y no puede superar los 100 caracteres.',
                'descripcion.*' => 'La descripción debe ser un texto de hasta 255 caracteres.',
                'cantidad.*' => 'La cantidad debe ser un número entero entre 0 y 65.535.',
                'estado.*' => 'Selecciona un estado de registro válido.',
                'disponible.*' => 'Selecciona una habilitación de uso válida.',
            ]);
            $institucion = Universidad::query()->lockForUpdate()->findOrFail($datos['universidad_id']);
            if (! $institucion->estado && ($esNuevo || (int) $equipamiento->universidad_id !== (int) $institucion->id)) {
                throw ValidationException::withMessages(['universidad_id' => 'Selecciona una institución activa. Al editar puedes conservar la institución actual.']);
            }
            $antes = $esNuevo ? null : $equipamiento->only($equipamiento->getFillable());
            $equipamiento->fill($datos);
            if (! $esNuevo && ! $equipamiento->isDirty()) {
                return $equipamiento;
            }
            $equipamiento->save();
            DB::table('historial_acciones')->insert([
                'usuario_id' => $request->user()->id, 'accion' => $esNuevo ? 'Crear equipamiento' : 'Actualizar equipamiento',
                'modulo' => 'Equipamiento y recursos', 'tabla_afectada' => 'equipamientos', 'registro_id' => $equipamiento->id,
                'descripcion' => $esNuevo ? 'Registro de equipamiento en el inventario institucional.' : 'Actualización del inventario de equipamiento.',
                'datos' => json_encode(['antes' => $antes, 'despues' => $equipamiento->only($equipamiento->getFillable())], JSON_THROW_ON_ERROR),
                'direccion_ip' => $request->ip(), 'created_at' => now(), 'updated_at' => now(),
            ]);

            return $equipamiento;
        }, 3);

        return redirect()->route('recursos.index', ['q' => $equipamiento->nombre, 'universidad_id' => $equipamiento->universidad_id, 'estado' => 'todos'])
            ->with('success', $esNuevo ? 'Equipamiento registrado correctamente.' : 'Equipamiento actualizado correctamente.');
    }
}

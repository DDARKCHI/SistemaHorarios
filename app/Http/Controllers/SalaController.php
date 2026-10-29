<?php

namespace App\Http\Controllers;

use App\Models\Sala;
use App\Models\Sede;
use App\Models\TipoSala;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SalaController extends Controller
{
    private const FILTROS = ['q', 'sede_id', 'tipo_sala_id', 'capacidad_minima', 'estado', 'uso'];

    public function create()
    {
        return $this->formulario(new Sala(['capacidad' => 0, 'estado' => true, 'disponible' => true]));
    }

    public function edit(Sala $sala)
    {
        return $this->formulario($sala);
    }

    public function store(Request $request)
    {
        return $this->guardar($request, new Sala);
    }

    public function update(Request $request, Sala $sala)
    {
        return $this->guardar($request, $sala);
    }

    private function formulario(Sala $sala)
    {
        $sedes = Sede::query()->where('estado', true)
            ->when($sala->exists, fn ($query) => $query->orWhere('id', $sala->sede_id))
            ->orderBy('nombre')->orderBy('id')->get();
        $tiposSala = TipoSala::query()->where('estado', true)
            ->when($sala->exists, fn ($query) => $query->orWhere('id', $sala->tipo_sala_id))
            ->orderBy('nombre')->orderBy('id')->get();

        return view('salas.formulario', compact('sala', 'sedes', 'tiposSala'));
    }

    private function guardar(Request $request, Sala $sala)
    {
        $esNueva = ! $sala->exists;

        try {
            $sala = DB::transaction(function () use ($request, $sala, $esNueva) {
                $sala = $esNueva ? $sala : Sala::query()->lockForUpdate()->findOrFail($sala->id);
                $datos = $request->validate([
                    'sede_id' => ['bail', 'required', 'integer', Rule::exists('sedes', 'id')->where(fn ($query) => $query
                        ->where('estado', true)->when($sala->exists, fn ($q) => $q->orWhere('id', $sala->sede_id)))],
                    'tipo_sala_id' => ['bail', 'required', 'integer', Rule::exists('tipos_sala', 'id')->where(fn ($query) => $query
                        ->where('estado', true)->when($sala->exists, fn ($q) => $q->orWhere('id', $sala->tipo_sala_id)))],
                    'codigo' => ['bail', 'required', 'string', 'max:30', Rule::unique('salas', 'codigo')
                        ->where('sede_id', $request->integer('sede_id'))->ignore($sala->id)],
                    'nombre' => ['nullable', 'string', 'max:100'],
                    'capacidad' => ['required', 'integer', 'min:0', 'max:65535'],
                    'edificio' => ['nullable', 'string', 'max:100'],
                    'piso' => ['nullable', 'string', 'max:30'],
                    'descripcion' => ['nullable', 'string', 'max:5000'],
                    'estado' => ['required', 'boolean'],
                    'disponible' => ['required', 'boolean'],
                ], [
                    'sede_id.*' => 'Selecciona una sede activa. Si editas una sala, también puedes conservar su sede actual.',
                    'tipo_sala_id.*' => 'Selecciona un tipo de sala activo. Si editas una sala, también puedes conservar su tipo actual.',
                    'codigo.required' => 'El código de la sala es obligatorio.',
                    'codigo.string' => 'El código de la sala debe ser un texto.',
                    'codigo.max' => 'El código no puede superar los 30 caracteres.',
                    'codigo.unique' => 'Ya existe una sala con este código en la sede seleccionada.',
                    'nombre.*' => 'El nombre debe ser un texto de hasta 100 caracteres.',
                    'capacidad.*' => 'La capacidad debe ser un número entero entre 0 y 65.535.',
                    'edificio.*' => 'El edificio debe ser un texto de hasta 100 caracteres.',
                    'piso.*' => 'El piso debe ser un texto de hasta 30 caracteres.',
                    'descripcion.*' => 'La descripción debe ser un texto de hasta 5.000 caracteres.',
                    'estado.*' => 'Selecciona un estado de registro válido.',
                    'disponible.*' => 'Selecciona una habilitación de uso válida.',
                ]);

                if ($sala->exists && (int) $datos['sede_id'] !== (int) $sala->sede_id
                    && (DB::table('asignaciones_salas')->where('sala_id', $sala->id)->exists() || $sala->laboratorios()->exists())) {
                    throw ValidationException::withMessages([
                        'sede_id' => 'La sala tiene horarios o laboratorios vinculados. Conserva su sede y registra otra sala si corresponde.',
                    ]);
                }

                $sede = Sede::query()->lockForUpdate()->findOrFail($datos['sede_id']);
                if ($sala->exists && (int) $datos['capacidad'] > 0 && (int) $datos['capacidad'] !== $sala->capacidad
                    && $sala->laboratorios()->where('estado', true)->where('capacidad', '>', $datos['capacidad'])->exists()) {
                    throw ValidationException::withMessages([
                        'capacidad' => 'La capacidad de la sala no puede ser menor que la de sus laboratorios activos. Actualiza primero la capacidad de esos laboratorios.',
                    ]);
                }
                $antes = $esNueva ? null : $sala->only($sala->getFillable());
                // La institución se obtiene de la sede; no se acepta desde el formulario.
                $sala->fill($datos + ['universidad_id' => $sede->universidad_id]);

                if (! $esNueva && ! $sala->isDirty()) {
                    return $sala;
                }

                $sala->save();
                DB::table('historial_acciones')->insert([
                    'usuario_id' => $request->user()->id,
                    'accion' => $esNueva ? 'Crear sala' : 'Actualizar sala',
                    'modulo' => 'Salas y recursos', 'tabla_afectada' => 'salas', 'registro_id' => $sala->id,
                    'descripcion' => $esNueva ? 'Registro de una sala en el catálogo.' : 'Actualización de los datos de una sala.',
                    'datos' => json_encode(['antes' => $antes, 'despues' => $sala->only($sala->getFillable())], JSON_THROW_ON_ERROR),
                    'direccion_ip' => $request->ip(), 'created_at' => now(), 'updated_at' => now(),
                ]);

                return $sala;
            });
        } catch (UniqueConstraintViolationException $error) {
            throw ValidationException::withMessages(['codigo' => 'Ya existe una sala con este código en la sede seleccionada.']);
        }

        return redirect()->route('salas.index', ['q' => $sala->codigo, 'sede_id' => $sala->sede_id, 'estado' => 'todas'])
            ->with('success', $esNueva ? 'Sala creada correctamente.' : 'Sala actualizada correctamente.');
    }

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

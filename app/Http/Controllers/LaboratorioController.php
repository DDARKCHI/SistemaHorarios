<?php

namespace App\Http\Controllers;

use App\Models\Laboratorio;
use App\Models\LaboratorioMovil;
use App\Models\Sala;
use App\Models\Sede;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class LaboratorioController extends Controller
{
    private const FILTROS = ['tipo', 'q', 'sede_id', 'capacidad_minima', 'estado', 'uso'];

    public function index(Request $request)
    {
        $validador = Validator::make($request->query(), [
            'tipo' => ['bail', 'nullable', Rule::in(['fijo', 'movil'])],
            'q' => ['bail', 'nullable', 'string', 'max:100'],
            'sede_id' => ['bail', 'nullable', 'integer', 'min:1', Rule::exists('sedes', 'id')],
            'capacidad_minima' => ['bail', 'nullable', 'integer', 'min:0', 'max:65535'],
            'estado' => ['bail', 'nullable', Rule::in(['activos', 'inactivos', 'todos'])],
            'uso' => ['bail', 'nullable', Rule::in(['habilitados', 'deshabilitados'])],
            'page' => ['bail', 'nullable', 'integer', 'min:1'],
        ], [
            'tipo.*' => 'Selecciona laboratorios fijos o móviles.',
            'q.*' => 'La búsqueda debe ser un texto de hasta 100 caracteres.',
            'sede_id.*' => 'Selecciona una sede registrada.',
            'capacidad_minima.*' => 'La capacidad mínima debe ser un número entero entre 0 y 65.535.',
            'estado.*' => 'Selecciona un estado de registro válido.',
            'uso.*' => 'Selecciona una habilitación de uso válida.',
            'page.*' => 'El número de página debe ser un entero positivo.',
        ]);
        if ($validador->fails()) {
            return redirect()->route('laboratorios.index', Arr::only($validador->valid(), self::FILTROS))->withErrors($validador);
        }

        $filtros = array_replace(['tipo' => 'fijo', 'q' => '', 'sede_id' => null, 'capacidad_minima' => null,
            'estado' => 'activos', 'uso' => null], Arr::only($validador->validated(), self::FILTROS));
        $filtros['tipo'] = $filtros['tipo'] ?: 'fijo';
        $filtros['estado'] = $filtros['estado'] ?: 'activos';
        $filtros['q'] = trim($filtros['q'] ?? '');
        $tipo = $filtros['tipo'];
        $modelo = $this->modelo($tipo);
        $consulta = $modelo::query()->with($tipo === 'fijo' ? ['sala.sede'] : ['sede']);
        if ($filtros['q'] !== '') {
            $literal = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $filtros['q']).'%';
            $consulta->where(function ($query) use ($tipo, $literal) {
                $query->whereRaw("codigo LIKE ? ESCAPE '!'", [$literal])->orWhereRaw("nombre LIKE ? ESCAPE '!'", [$literal]);
                if ($tipo === 'fijo') {
                    $query->orWhereHas('sala', fn ($sala) => $sala->whereRaw("codigo LIKE ? ESCAPE '!'", [$literal]));
                }
            });
        }
        if ($filtros['sede_id'] !== null) {
            $tipo === 'fijo'
                ? $consulta->whereHas('sala', fn ($sala) => $sala->where('sede_id', $filtros['sede_id']))
                : $consulta->where('sede_id', $filtros['sede_id']);
        }
        if ($filtros['capacidad_minima'] !== null) {
            $consulta->where('capacidad', '>=', $filtros['capacidad_minima']);
        }
        if ($filtros['estado'] !== 'todos') {
            $consulta->where('estado', $filtros['estado'] === 'activos');
        }
        if (filled($filtros['uso'])) {
            $consulta->where('disponible', $filtros['uso'] === 'habilitados');
        }
        $parametros = array_filter($filtros, fn ($valor) => $valor !== null && $valor !== '');
        $laboratorios = $consulta->orderBy('codigo')->orderBy('nombre')->orderBy('id')->paginate(12)->appends($parametros);
        if ($laboratorios->currentPage() > $laboratorios->lastPage()) {
            return redirect()->route('laboratorios.index', $parametros + ['page' => $laboratorios->lastPage()]);
        }
        $resumen = ['fijos' => Laboratorio::count(), 'moviles' => LaboratorioMovil::count(),
            'activos' => Laboratorio::where('estado', true)->count() + LaboratorioMovil::where('estado', true)->count()];
        $sedes = Sede::orderBy('nombre')->orderBy('id')->get();

        return view('laboratorios.index', compact('laboratorios', 'tipo', 'filtros', 'resumen', 'sedes'));
    }

    public function create(string $tipo)
    {
        $modelo = $this->modelo($tipo);

        return $this->formulario($tipo, new $modelo(['capacidad' => 0, 'estado' => true, 'disponible' => true]));
    }

    public function edit(string $tipo, int $id)
    {
        $modelo = $this->modelo($tipo);

        return $this->formulario($tipo, $modelo::findOrFail($id));
    }

    public function store(Request $request, string $tipo)
    {
        return $this->guardar($request, $tipo);
    }

    public function update(Request $request, string $tipo, int $id)
    {
        return $this->guardar($request, $tipo, $id);
    }

    private function modelo(string $tipo): string
    {
        return match ($tipo) {
            'fijo' => Laboratorio::class,
            'movil' => LaboratorioMovil::class,
            default => abort(404),
        };
    }

    private function formulario(string $tipo, Laboratorio|LaboratorioMovil $laboratorio)
    {
        $campoUbicacion = $tipo === 'fijo' ? 'sala_id' : 'sede_id';
        $ubicaciones = $tipo === 'fijo'
            ? Sala::with('sede')->where(fn ($query) => $query
                ->where(fn ($q) => $q->where('estado', true)->where('disponible', true)->whereHas('sede', fn ($sede) => $sede->where('estado', true)))
                ->when($laboratorio->exists, fn ($q) => $q->orWhere('id', $laboratorio->sala_id)))
                ->orderBy('codigo')->orderBy('id')->get()
            : Sede::where('estado', true)->when($laboratorio->exists, fn ($query) => $query->orWhere('id', $laboratorio->sede_id))
                ->orderBy('nombre')->orderBy('id')->get();

        return view('laboratorios.formulario', compact('tipo', 'laboratorio', 'ubicaciones', 'campoUbicacion'));
    }

    private function guardar(Request $request, string $tipo, ?int $id = null)
    {
        $modelo = $this->modelo($tipo);
        $campoUbicacion = $tipo === 'fijo' ? 'sala_id' : 'sede_id';
        try {
            $laboratorio = DB::transaction(function () use ($request, $tipo, $id, $modelo, $campoUbicacion) {
                $laboratorio = $id === null ? new $modelo : $modelo::query()->lockForUpdate()->findOrFail($id);
                $datos = $request->validate([
                    $campoUbicacion => ['bail', 'required', 'integer', Rule::exists($tipo === 'fijo' ? 'salas' : 'sedes', 'id')],
                    'codigo' => ['bail', $tipo === 'fijo' ? 'nullable' : 'required', 'string', 'max:30'],
                    'nombre' => ['required', 'string', 'max:100'],
                    'capacidad' => ['required', 'integer', 'min:0', 'max:65535'],
                    'descripcion' => ['nullable', 'string', 'max:5000'],
                    'estado' => ['required', 'boolean'], 'disponible' => ['required', 'boolean'],
                ], [
                    $campoUbicacion.'.*' => $tipo === 'fijo' ? 'Selecciona una sala registrada.' : 'Selecciona una sede registrada.',
                    'codigo.*' => 'El código debe ser un texto de hasta 30 caracteres y es obligatorio para laboratorios móviles.',
                    'nombre.*' => 'El nombre es obligatorio y no puede superar los 100 caracteres.',
                    'capacidad.*' => 'La capacidad debe ser un número entero entre 0 y 65.535.',
                    'descripcion.*' => 'La descripción debe ser un texto de hasta 5.000 caracteres.',
                    'estado.*' => 'Selecciona un estado de registro válido.',
                    'disponible.*' => 'Selecciona una habilitación de uso válida.',
                ]);
                $ubicacion = $tipo === 'fijo'
                    ? Sala::with('sede')->lockForUpdate()->findOrFail($datos[$campoUbicacion])
                    : Sede::query()->lockForUpdate()->findOrFail($datos[$campoUbicacion]);
                $mismaUbicacion = $laboratorio->exists && (int) $laboratorio->{$campoUbicacion} === (int) $ubicacion->id;
                $habilitada = $ubicacion->estado && ($tipo === 'movil' || ($ubicacion->disponible && $ubicacion->sede?->estado));
                if (! $habilitada && ! $mismaUbicacion) {
                    throw ValidationException::withMessages([$campoUbicacion => $tipo === 'fijo'
                        ? 'Selecciona una sala activa y habilitada de una sede activa.' : 'Selecciona una sede activa.']);
                }
                // El bloqueo de la ubicación serializa también la comprobación de códigos de laboratorios fijos.
                Validator::make(['codigo' => array_key_exists('codigo', $datos) ? $datos['codigo'] : $laboratorio->codigo], [
                    'codigo' => ['nullable', Rule::unique($laboratorio->getTable(), 'codigo')
                        ->where($campoUbicacion, $ubicacion->id)->ignore($laboratorio->id)],
                ], ['codigo.unique' => 'Ya existe un laboratorio con este código en la ubicación seleccionada.'])->validate();

                $tablaAsignaciones = $tipo === 'fijo' ? 'asignaciones_laboratorios' : 'asignaciones_laboratorios_moviles';
                $campoAsignacion = $tipo === 'fijo' ? 'laboratorio_id' : 'laboratorio_movil_id';
                if ($laboratorio->exists && ! $mismaUbicacion
                    && DB::table($tablaAsignaciones)->where($campoAsignacion, $laboratorio->id)->exists()) {
                    throw ValidationException::withMessages([$campoUbicacion => 'El laboratorio tiene horarios vinculados. Conserva su ubicación y registra otro recurso si corresponde.']);
                }
                if ($tipo === 'fijo' && $ubicacion->capacidad > 0 && (int) $datos['capacidad'] > $ubicacion->capacidad) {
                    throw ValidationException::withMessages(['capacidad' => 'La capacidad del laboratorio no puede superar las '.$ubicacion->capacidad.' personas registradas para su sala.']);
                }
                $antes = $laboratorio->exists ? $laboratorio->only($laboratorio->getFillable()) : null;
                $laboratorio->fill($datos + ($tipo === 'movil' ? ['universidad_id' => $ubicacion->universidad_id] : []));
                if ($id !== null && ! $laboratorio->isDirty()) {
                    return $laboratorio;
                }
                $laboratorio->save();
                DB::table('historial_acciones')->insert([
                    'usuario_id' => $request->user()->id, 'accion' => $id === null ? 'Crear laboratorio' : 'Actualizar laboratorio',
                    'modulo' => 'Laboratorios', 'tabla_afectada' => $laboratorio->getTable(), 'registro_id' => $laboratorio->id,
                    'descripcion' => ($id === null ? 'Registro' : 'Actualización').' de laboratorio '.($tipo === 'movil' ? 'móvil.' : 'fijo.'),
                    'datos' => json_encode(['tipo' => $tipo, 'antes' => $antes, 'despues' => $laboratorio->only($laboratorio->getFillable())], JSON_THROW_ON_ERROR),
                    'direccion_ip' => $request->ip(), 'created_at' => now(), 'updated_at' => now(),
                ]);

                return $laboratorio;
            }, 3);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['codigo' => 'Ya existe un laboratorio con este código en la ubicación seleccionada.']);
        }

        return redirect()->route('laboratorios.index', ['tipo' => $tipo, 'q' => $laboratorio->codigo ?: $laboratorio->nombre, 'estado' => 'todos'])
            ->with('success', $id === null ? 'Laboratorio creado correctamente.' : 'Laboratorio actualizado correctamente.');
    }
}

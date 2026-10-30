<?php

namespace App\Http\Controllers;

use App\Models\PrioridadRecurso;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PrioridadRecursoController extends Controller
{
    public function index(Request $request)
    {
        $validador = Validator::make($request->query(), [
            'q' => ['nullable', 'string', 'max:100'], 'estado' => ['nullable', Rule::in(['activas', 'inactivas', 'todas'])],
            'nivel' => ['nullable', 'integer', 'min:1', 'max:255'], 'page' => ['nullable', 'integer', 'min:1'],
        ], ['q.*' => 'La búsqueda admite hasta 100 caracteres.', 'nivel.*' => 'El nivel debe ser un entero entre 1 y 255.',
            'estado.*' => 'Selecciona un estado disponible.', 'page.*' => 'Selecciona una página válida.']);
        if ($validador->fails()) return redirect()->route('prioridades.index')->withErrors($validador);
        $datos = $validador->validated();
        $q = trim($datos['q'] ?? ''); $estado = $datos['estado'] ?? 'activas'; $nivel = $datos['nivel'] ?? null;
        $consulta = PrioridadRecurso::withCount('requerimientosActividad');
        if ($estado !== 'todas') $consulta->where('estado', $estado === 'activas');
        if ($nivel !== null) $consulta->where('nivel', $nivel);
        if ($q !== '') {
            $literal = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $q).'%';
            $consulta->where(fn ($query) => $query->whereRaw("nombre LIKE ? ESCAPE '!'", [$literal])->orWhereRaw("COALESCE(descripcion, '') LIKE ? ESCAPE '!'", [$literal]));
        }
        $contexto = array_filter(compact('q', 'estado', 'nivel'), fn ($valor) => $valor !== null && $valor !== '');
        $prioridades = $consulta->orderBy('nivel')->orderBy('nombre')->orderBy('id')->paginate(12)->appends($contexto);
        if ($prioridades->currentPage() > $prioridades->lastPage()) return redirect()->to($prioridades->url($prioridades->lastPage()));
        return view('prioridades.index', compact('prioridades', 'q', 'estado', 'nivel'));
    }

    public function create()
    {
        return view('prioridades.formulario', ['prioridad' => new PrioridadRecurso(['nivel' => 1, 'estado' => true])]);
    }

    public function edit(PrioridadRecurso $prioridad)
    {
        return view('prioridades.formulario', compact('prioridad'));
    }

    public function store(Request $request)
    {
        return $this->guardar($request);
    }

    public function update(Request $request, PrioridadRecurso $prioridad)
    {
        return $this->guardar($request, $prioridad);
    }

    private function guardar(Request $request, ?PrioridadRecurso $prioridad = null)
    {
        if (is_string($request->input('nombre'))) $request->merge(['nombre' => Str::squish($request->input('nombre'))]);
        $nueva = $prioridad === null;
        $registro = DB::transaction(function () use ($request, $prioridad, $nueva) {
            $registro = $nueva ? new PrioridadRecurso : PrioridadRecurso::lockForUpdate()->findOrFail($prioridad->id);
            $nombre = ['required', 'string', 'max:100'];
            if ($nueva || $request->input('nombre') !== $registro->nombre) $nombre[] = Rule::unique('prioridades_recursos', 'nombre')->ignore($registro->id);
            $datos = $request->validate(['nombre' => $nombre, 'descripcion' => ['nullable', 'string', 'max:255'],
                'nivel' => ['required', 'integer', 'min:1', 'max:255'], 'estado' => ['required', 'boolean']], [
                'nombre.unique' => 'Ya existe una prioridad con este nombre.', 'nombre.required' => 'Indica un nombre para la prioridad.',
                'nombre.string' => 'El nombre debe ser un texto.', 'nombre.max' => 'El nombre admite hasta 100 caracteres.',
                'descripcion.*' => 'La descripción admite hasta 255 caracteres.', 'nivel.*' => 'El nivel debe ser un entero entre 1 y 255.',
                'estado.*' => 'Selecciona un estado de registro válido.',
            ]);
            $antes = $nueva ? null : $registro->only($registro->getFillable());
            $registro->fill($datos);
            if (! $nueva && ! $registro->isDirty()) return $registro;
            $registro->save();
            DB::table('historial_acciones')->insert(['usuario_id' => $request->user()->id,
                'accion' => $nueva ? 'Crear prioridad' : 'Actualizar prioridad', 'modulo' => 'Prioridades de recursos',
                'tabla_afectada' => 'prioridades_recursos', 'registro_id' => $registro->id,
                'descripcion' => 'Configuración de una prioridad para los requerimientos de actividades.',
                'datos' => json_encode(['antes' => $antes, 'despues' => $registro->only($registro->getFillable())], JSON_THROW_ON_ERROR),
                'direccion_ip' => $request->ip(), 'created_at' => now(), 'updated_at' => now()]);
            return $registro;
        }, 3);
        return redirect()->route('prioridades.index', ['q' => $registro->nombre, 'estado' => 'todas'])
            ->with('success', $nueva ? 'Prioridad registrada correctamente.' : 'Prioridad actualizada correctamente.');
    }
}

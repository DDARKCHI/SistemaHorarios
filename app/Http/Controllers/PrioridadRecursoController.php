<?php

namespace App\Http\Controllers;

use App\Models\PrioridadRecurso;
use App\Models\Asignatura;
use App\Models\TipoSala;
use App\Models\Equipamiento;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

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
        $consulta = PrioridadRecurso::with(['asignatura', 'tipoSala'])->withCount('requerimientosActividad');
        if ($estado !== 'todas') $consulta->where('estado', $estado === 'activas');
        if ($nivel !== null) $consulta->where('nivel', $nivel);
        if ($q !== '') {
            $literal = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $q).'%';
            $consulta->where(fn ($query) => $query->whereRaw("nombre LIKE ? ESCAPE '!'", [$literal])->orWhereRaw("COALESCE(descripcion, '') LIKE ? ESCAPE '!'", [$literal])
                ->orWhereHas('asignatura', fn ($a) => $a->whereRaw("nombre LIKE ? ESCAPE '!'", [$literal])->orWhereRaw("codigo LIKE ? ESCAPE '!'", [$literal])));
        }
        $contexto = array_filter(compact('q', 'estado', 'nivel'), fn ($valor) => $valor !== null && $valor !== '');
        $prioridades = $consulta->orderBy('nivel')->orderBy('nombre')->orderBy('id')->paginate(12)->appends($contexto);
        if ($prioridades->currentPage() > $prioridades->lastPage()) return redirect()->to($prioridades->url($prioridades->lastPage()));
        return view('prioridades.index', compact('prioridades', 'q', 'estado', 'nivel'));
    }

    public function create()
    {
        return $this->formulario(new PrioridadRecurso(['nivel' => 1, 'estado' => true, 'tipo_recurso' => 'laboratorio']));
    }

    public function edit(PrioridadRecurso $prioridad)
    {
        return $this->formulario($prioridad);
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
        if ($request->input('alcance') === 'curso' || $request->has('asignatura_id') || $prioridad?->asignatura_id) {
            return $this->guardarPorCurso($request, $prioridad);
        }
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

    private function formulario(PrioridadRecurso $prioridad)
    {
        $asignaturas = Asignatura::where(fn ($q) => $q->where('estado', true)
            ->when($prioridad->asignatura_id, fn ($actual) => $actual->orWhere('id', $prioridad->asignatura_id)))
            ->with('universidad')->orderBy('nombre')->orderBy('codigo')->get();
        $tiposSala = TipoSala::where('estado', true)->when($prioridad->tipo_sala_id, fn ($q) => $q->orWhere('id', $prioridad->tipo_sala_id))->orderBy('nombre')->get()->reject(fn ($tipo) => $tipo->esDeImportacion());
        $tiposEquipamiento = Equipamiento::where('estado', true)->where('disponible', true)->select('tipo')->distinct()->orderBy('tipo')->pluck('tipo');
        if ($prioridad->tipo_equipamiento && !$tiposEquipamiento->contains($prioridad->tipo_equipamiento)) $tiposEquipamiento->push($prioridad->tipo_equipamiento);
        return view('prioridades.formulario', compact('prioridad', 'asignaturas', 'tiposSala', 'tiposEquipamiento'));
    }

    private function guardarPorCurso(Request $request, ?PrioridadRecurso $prioridad)
    {
        $nueva = $prioridad === null;
        $registro = DB::transaction(function () use ($request, $prioridad, $nueva) {
            $registro = $nueva ? new PrioridadRecurso : PrioridadRecurso::lockForUpdate()->findOrFail($prioridad->id);
            $datos = $request->validate([
                'asignatura_id' => ['required', 'integer', 'exists:asignaturas,id'],
                'tipo_recurso' => ['required', Rule::in(array_keys(PrioridadRecurso::RECURSOS))],
                'tipo_sala_id' => ['nullable', 'integer', 'exists:tipos_sala,id'],
                'tipo_equipamiento' => ['nullable', 'string', 'max:100'],
                'nivel' => ['required', 'integer', 'min:1', 'max:255'],
                'estado' => ['required', 'boolean'], 'descripcion' => ['nullable', 'string', 'max:255'],
            ], ['asignatura_id.*' => 'Selecciona el curso o ramo que tendrá la prioridad.',
                'tipo_recurso.*' => 'Selecciona un tipo de recurso disponible.', 'nivel.*' => 'El nivel debe ser un entero entre 1 y 255.',
                'tipo_sala_id.*' => 'Selecciona un tipo de sala o laboratorio registrado.',
                'tipo_equipamiento.*' => 'Selecciona un tipo de equipamiento registrado.',
                'estado.*' => 'Selecciona un estado de registro válido.', 'descripcion.*' => 'La descripción admite hasta 255 caracteres.']);
            // Serializar las reglas del mismo ramo, incluso cuando aún no tiene prioridades.
            $curso = Asignatura::lockForUpdate()->findOrFail($datos['asignatura_id']);
            if (!$curso->estado && ($nueva || (int) $registro->asignatura_id !== (int) $curso->id)) {
                throw ValidationException::withMessages(['asignatura_id' => 'Selecciona un curso activo. Al editar puedes conservar el actual.']);
            }
            $datos['tipo_sala_id'] = $datos['tipo_sala_id'] ?? null;
            $datos['tipo_equipamiento'] = $datos['tipo_equipamiento'] ?? null;
            if ($datos['tipo_sala_id'] && !in_array($datos['tipo_recurso'], ['sala', 'laboratorio'], true)) {
                throw ValidationException::withMessages(['tipo_sala_id' => 'El tipo de sala solo corresponde a salas y laboratorios fijos.']);
            }
            if ($datos['tipo_sala_id']) {
                $tipo = TipoSala::lockForUpdate()->findOrFail($datos['tipo_sala_id']);
                if (!$tipo->estado && ($nueva || (int) $registro->tipo_sala_id !== (int) $tipo->id)) throw ValidationException::withMessages(['tipo_sala_id' => 'Selecciona un tipo activo.']);
            }
            if ($datos['tipo_equipamiento'] && (!in_array($datos['tipo_recurso'], ['equipamiento', 'laboratorio'], true)
                || (!Equipamiento::where('universidad_id', $curso->universidad_id)->where('tipo', $datos['tipo_equipamiento'])->where('estado', true)->where('disponible', true)->exists()
                    && ($nueva || $registro->tipo_equipamiento !== $datos['tipo_equipamiento'] || (int) $registro->asignatura_id !== (int) $curso->id)))) {
                throw ValidationException::withMessages(['tipo_equipamiento' => 'Elige un tipo de equipamiento activo de la institución del curso.']);
            }
            $datos['clave_recurso'] = $datos['tipo_recurso'].':'.($datos['tipo_sala_id'] ?? ($datos['tipo_equipamiento'] ? Str::lower(Str::ascii($datos['tipo_equipamiento'])) : 'todos'));
            if ($datos['tipo_recurso'] === 'laboratorio' && $datos['tipo_equipamiento']) {
                $datos['clave_recurso'] = 'laboratorio:'.($datos['tipo_sala_id'] ?? 'todos').':equipo='.Str::lower(Str::ascii($datos['tipo_equipamiento']));
            }
            if (PrioridadRecurso::where('asignatura_id', $curso->id)->where('clave_recurso', $datos['clave_recurso'])->when(!$nueva, fn ($q) => $q->whereKeyNot($registro->id))->exists()) {
                throw ValidationException::withMessages(['asignatura_id' => 'Este curso ya tiene una prioridad para ese recurso. Edita la regla existente.']);
            }
            $datos['nombre'] = Str::limit($curso->codigo.' · '.$curso->nombre.' · '.PrioridadRecurso::RECURSOS[$datos['tipo_recurso']], 100, '');
            $antes = $nueva ? null : $registro->only($registro->getFillable());
            $registro->fill($datos);
            if (!$nueva && $registro->isDirty(['asignatura_id', 'tipo_recurso', 'tipo_sala_id', 'tipo_equipamiento']) && $registro->requerimientosActividad()->exists()) {
                throw ValidationException::withMessages(['asignatura_id' => 'La prioridad tiene requerimientos vinculados. Conserva su curso y recurso; puedes cambiar el nivel, la descripción y el estado.']);
            }
            if (!$nueva && !$registro->isDirty()) return $registro;
            $registro->save();
            DB::table('historial_acciones')->insert(['usuario_id' => $request->user()->id,
                'accion' => $nueva ? 'Crear prioridad' : 'Actualizar prioridad', 'modulo' => 'Prioridades de recursos',
                'tabla_afectada' => 'prioridades_recursos', 'registro_id' => $registro->id,
                'descripcion' => 'Preferencia de un curso completo sobre un tipo de recurso.',
                'datos' => json_encode(['antes' => $antes, 'despues' => $registro->only($registro->getFillable())], JSON_THROW_ON_ERROR),
                'direccion_ip' => $request->ip(), 'created_at' => now(), 'updated_at' => now()]);
            return $registro;
        }, 3);
        return redirect()->route('prioridades.index', ['q' => $registro->asignatura->codigo, 'estado' => 'todas'])
            ->with('success', $nueva ? 'Prioridad del curso registrada correctamente.' : 'Prioridad del curso actualizada correctamente.');
    }
}

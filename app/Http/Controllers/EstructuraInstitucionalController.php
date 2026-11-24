<?php

namespace App\Http\Controllers;

use App\Models;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\{Rule, ValidationException};

class EstructuraInstitucionalController extends Controller
{
    private const TIPOS = ['sedes' => Models\Sede::class, 'carreras' => Models\Carrera::class];

    public function index(Request $request, string $tipo)
    {
        $modelo = $this->modelo($tipo);
        $datos = $request->validate(['q' => ['nullable', 'string', 'max:100'], 'universidad_id' => ['nullable', 'integer', 'exists:universidades,id'],
            'estado' => ['nullable', Rule::in(['activos', 'inactivos', 'todos'])], 'page' => ['nullable', 'integer', 'min:1']]);
        $universidades = Models\Universidad::orderBy('nombre')->get();
        $estado = $datos['estado'] ?? 'activos'; $q = trim($datos['q'] ?? '');
        $consulta = $modelo::with('universidad');
        if ($tipo === 'sedes') $consulta->withCount('salas', 'laboratoriosMoviles');
        else $consulta->with('sede', 'sedes', 'facultadInstituto')->withCount('secciones');
        if ($estado !== 'todos') $consulta->where('estado', $estado === 'activos');
        if (! empty($datos['universidad_id'])) $consulta->where('universidad_id', $datos['universidad_id']);
        if ($q !== '') {
            $literal = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $q).'%';
            $consulta->where(fn ($c) => $c->whereRaw("nombre LIKE ? ESCAPE '!'", [$literal])->orWhereRaw("COALESCE(codigo, '') LIKE ? ESCAPE '!'", [$literal]));
        }
        $registros = $consulta->orderBy('nombre')->orderBy('id')->paginate(12)->withQueryString();
        if ($registros->currentPage() > $registros->lastPage()) return redirect()->to($registros->url($registros->lastPage()));
        return view('administracion.estructura.index', compact('tipo', 'registros', 'universidades', 'estado', 'q'));
    }

    public function create(string $tipo)
    {
        $modelo = $this->modelo($tipo);
        return $this->formulario($tipo, new $modelo(['estado' => true]));
    }
    public function edit(string $tipo, int $id)
    {
        $modelo = $this->modelo($tipo);
        return $this->formulario($tipo, $modelo::findOrFail($id));
    }
    public function store(Request $request, string $tipo) { return $this->guardar($request, $tipo); }
    public function update(Request $request, string $tipo, int $id) { return $this->guardar($request, $tipo, $id); }

    private function formulario(string $tipo, $registro)
    {
        $universidades = Models\Universidad::where('estado', true)->when($registro->exists, fn ($q) => $q->orWhere('id', $registro->universidad_id))->orderBy('nombre')->get();
        $sedesElegidas = $tipo === 'carreras' && $registro->exists ? $registro->sedes->pluck('id')->push($registro->sede_id)->filter()->unique()->map(fn ($id) => (int) $id)->values()->all() : [];
        $sedes = Models\Sede::where('estado', true)->when($sedesElegidas, fn ($q) => $q->orWhereIn('id', $sedesElegidas))->orderBy('nombre')->get();
        $facultades = Models\FacultadInstituto::where('estado', true)->when($registro->exists && $tipo === 'carreras', fn ($q) => $q->orWhere('id', $registro->facultad_instituto_id))->orderBy('nombre')->get();
        $universidadId = old('universidad_id', $registro->universidad_id ?? ($universidades->count() === 1 ? $universidades->first()->id : null));
        return view('administracion.estructura.formulario', compact('tipo', 'registro', 'universidades', 'sedes', 'facultades', 'sedesElegidas', 'universidadId'));
    }

    private function guardar(Request $request, string $tipo, ?int $id = null)
    {
        $modelo = $this->modelo($tipo);
        foreach (['nombre', 'codigo', 'direccion', 'regimen', 'tipo_estudio'] as $campo) if (is_string($request->input($campo))) $request->merge([$campo => Str::squish($request->input($campo)) ?: null]);
        $reglas = ['universidad_id' => ['required', 'integer', 'exists:universidades,id'], 'nombre' => ['required', 'string', 'max:'.($tipo === 'sedes' ? 100 : 150)],
            'codigo' => ['nullable', 'string', 'max:'.($tipo === 'sedes' ? 20 : 30)], 'estado' => ['required', 'boolean']];
        if ($tipo === 'sedes') $reglas['direccion'] = ['nullable', 'string', 'max:200'];
        else $reglas += ['sede_id' => ['required', 'integer', 'exists:sedes,id'], 'sedes' => ['nullable', 'array', 'max:100'], 'sedes.*' => ['integer', 'distinct', 'exists:sedes,id'],
            'facultad_instituto_id' => ['nullable', 'integer', 'exists:facultades_institutos,id'], 'regimen' => ['nullable', 'string', 'max:50'], 'tipo_estudio' => ['nullable', 'string', 'max:100']];
        $datos = $request->validate($reglas, ['nombre.required' => 'Indica el nombre.', 'sede_id.required' => 'Selecciona una sede principal.',
            'universidad_id.required' => 'Selecciona la institución.', 'sedes.*.distinct' => 'No repitas una sede.', 'estado.*' => 'Selecciona un estado válido.']);
        $registro = DB::transaction(function () use ($request, $tipo, $modelo, $id, $datos) {
            $universidad = Models\Universidad::lockForUpdate()->findOrFail($datos['universidad_id']);
            $registro = $id ? $modelo::lockForUpdate()->findOrFail($id) : new $modelo;
            if ($id && (int) $registro->universidad_id !== (int) $universidad->id) $this->rechazar('universidad_id', 'La institución de un registro existente no puede cambiarse.');
            if (! $universidad->estado && ! $id) $this->rechazar('universidad_id', 'Selecciona una institución activa.');
            foreach (['nombre', 'codigo'] as $campo) {
                if (! filled($datos[$campo] ?? null) || ($id && $datos[$campo] === $registro->$campo)) continue;
                if ($modelo::where('universidad_id', $universidad->id)->when($id, fn ($q) => $q->whereKeyNot($id))
                    ->whereRaw('LOWER(TRIM('.$campo.')) = ?', [mb_strtolower($datos[$campo])])->exists()) $this->rechazar($campo, 'Ya existe un registro con este '.$campo.' en la institución, incluso entre los inactivos.');
            }
            $antes = $id ? $registro->getAttributes() : null;
            $sedesAntes = $sedesNuevas = [];
            if ($tipo === 'carreras') {
                $sedesAntes = $id ? $registro->sedes()->pluck('sedes.id')->push($registro->sede_id)->filter()->map(fn ($id) => (int) $id)->unique()->values()->all() : [];
                $sedesNuevas = collect($datos['sedes'] ?? [])->push($datos['sede_id'])->map(fn ($id) => (int) $id)->unique()->sort()->values()->all();
                $sedes = Models\Sede::whereIn('id', $sedesNuevas)->orderBy('id')->lockForUpdate()->get();
                foreach ($sedes as $sede) if ((int) $sede->universidad_id !== (int) $universidad->id || (! $sede->estado && ! in_array((int) $sede->id, $sedesAntes, true))) $this->rechazar('sedes', 'Selecciona sedes activas de la misma institución. Al editar puedes conservar una sede inactiva ya vinculada.');
                if ($id && array_diff($sedesAntes, $sedesNuevas) && $registro->secciones()->exists()) $this->rechazar('sedes', 'Esta carrera tiene secciones registradas. Conserva las sedes anteriores para mantener sus consultas históricas; puedes añadir nuevas.');
                if (! empty($datos['facultad_instituto_id'])) {
                    $facultad = Models\FacultadInstituto::lockForUpdate()->findOrFail($datos['facultad_instituto_id']);
                    if ((int) $facultad->universidad_id !== (int) $universidad->id || (! $facultad->estado && (int) $registro->facultad_instituto_id !== (int) $facultad->id)) $this->rechazar('facultad_instituto_id', 'Selecciona una facultad o instituto activo de la misma institución.');
                }
                if (! $datos['estado'] && $id && $registro->secciones()->where('estado_activo', true)->whereHas('periodoAcademico', fn ($q) => $q->where('estado', true))->exists()) $this->rechazar('estado', 'La carrera tiene secciones activas en un período vigente. Revisa su actividad antes de desactivarla.');
                if (! $datos['estado'] && $id && ($registro->planesEstudio()->where('estado', true)->exists() || $registro->nivelesCarrera()->where('estado', true)->exists())) $this->rechazar('estado', 'La carrera tiene planes de estudio o niveles activos. Revisa esos registros antes de desactivarla.');
            } elseif (! $datos['estado'] && $id) {
                $carrerasActivas = Models\Carrera::where('estado', true)->where(fn ($q) => $q->where('sede_id', $id)->orWhereHas('sedes', fn ($s) => $s->where('sedes.id', $id)))->exists();
                if ($carrerasActivas || $registro->salas()->where('estado', true)->exists() || $registro->laboratoriosMoviles()->where('estado', true)->exists()) $this->rechazar('estado', 'Esta sede tiene carreras, salas o laboratorios móviles activos. Revisa esos registros antes de desactivarla.');
            }
            $campos = $datos; unset($campos['sedes']);
            $registro->fill($campos);
            $sucio = $registro->isDirty();
            $registro->save();
            $cambioSedes = false;
            if ($tipo === 'carreras') {
                $cambios = $registro->sedes()->sync($sedesNuevas);
                $cambioSedes = collect($cambios)->contains(fn ($ids) => count($ids) > 0);
            }
            if ($sucio || $cambioSedes || ! $id) DB::table('historial_acciones')->insert(['usuario_id' => $request->user()->id, 'accion' => ($id ? 'Actualizar ' : 'Crear ').($tipo === 'sedes' ? 'sede' : 'carrera'),
                'modulo' => 'Estructura institucional', 'tabla_afectada' => $tipo, 'registro_id' => $registro->id,
                'descripcion' => 'Gestión institucional conservando los registros y sus relaciones.', 'datos' => json_encode(['antes' => $antes, 'despues' => $registro->getAttributes(),
                    'sedes_anteriores' => $sedesAntes, 'sedes_actuales' => $sedesNuevas], JSON_THROW_ON_ERROR), 'direccion_ip' => $request->ip(), 'created_at' => now(), 'updated_at' => now()]);
            return $registro;
        }, 3);
        return redirect()->route('administracion.estructura.edit', ['tipo' => $tipo, 'id' => $registro->id])->with('success', ($tipo === 'sedes' ? 'Sede' : 'Carrera').' guardada correctamente.');
    }

    private function modelo(string $tipo): string { abort_unless(isset(self::TIPOS[$tipo]), 404); return self::TIPOS[$tipo]; }
    private function rechazar(string $campo, string $mensaje): never { throw ValidationException::withMessages([$campo => $mensaje]); }
}

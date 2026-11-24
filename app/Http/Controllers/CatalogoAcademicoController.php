<?php

namespace App\Http\Controllers;

use App\Models;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\{Rule, ValidationException};

class CatalogoAcademicoController extends Controller
{
    private const CATALOGOS = [
        'facultades' => [Models\FacultadInstituto::class, 'Facultades e institutos', 'unidad académica'],
        'planes' => [Models\PlanEstudio::class, 'Planes de estudio', 'plan de estudio'],
        'niveles' => [Models\NivelCarrera::class, 'Semestres de carrera', 'semestre de carrera'],
    ];

    public function index(Request $request, string $tipo)
    {
        [$modelo, $titulo, $singular] = $this->catalogo($tipo);
        $filtros = $request->validate([
            'q' => ['nullable', 'string', 'max:100'], 'estado' => ['nullable', Rule::in(['activos', 'inactivos', 'todos'])],
            'universidad_id' => ['nullable', 'integer', 'exists:universidades,id'],
            'carrera_id' => ['nullable', 'integer', 'exists:carreras,id'], 'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $estado = $filtros['estado'] ?? 'activos';
        $q = trim($filtros['q'] ?? '');
        $consulta = $modelo::query();
        if ($tipo === 'facultades') {
            $consulta->with(['universidad', 'carreras.sede', 'carreras.sedes'])->withCount('carreras');
            if (! empty($filtros['universidad_id'])) $consulta->where('universidad_id', $filtros['universidad_id']);
        } else {
            $consulta->with('carrera.universidad');
            if (! empty($filtros['universidad_id'])) $consulta->whereHas('carrera', fn ($c) => $c->where('universidad_id', $filtros['universidad_id']));
            if (! empty($filtros['carrera_id'])) $consulta->where('carrera_id', $filtros['carrera_id']);
        }
        if ($estado !== 'todos') $consulta->where('estado', $estado === 'activos');
        if ($q !== '') {
            $literal = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $q).'%';
            $consulta->where(function ($c) use ($literal, $tipo) {
                $c->whereRaw("COALESCE(nombre, '') LIKE ? ESCAPE '!'", [$literal]);
                if ($tipo !== 'niveles') $c->orWhereRaw("COALESCE(codigo, '') LIKE ? ESCAPE '!'", [$literal]);
                if ($tipo === 'planes') $c->orWhereRaw("COALESCE(version, '') LIKE ? ESCAPE '!'", [$literal]);
                if ($tipo === 'niveles') $c->orWhereRaw("CAST(numero AS CHAR) LIKE ? ESCAPE '!'", [$literal]);
            });
        }
        $registros = $consulta->orderBy($tipo === 'niveles' ? 'numero' : 'nombre')->orderBy('id')->paginate(12)->withQueryString();
        if ($registros->currentPage() > $registros->lastPage()) return redirect()->to($registros->url($registros->lastPage()));
        $universidades = Models\Universidad::orderBy('nombre')->get();
        $carreras = Models\Carrera::with('universidad')->when(! empty($filtros['universidad_id']), fn ($c) => $c->where('universidad_id', $filtros['universidad_id']))->orderBy('nombre')->get();
        return view('administracion.academicos.index', compact('tipo', 'titulo', 'singular', 'filtros', 'estado', 'q', 'registros', 'universidades', 'carreras'));
    }

    public function create(string $tipo)
    {
        [$modelo] = $this->catalogo($tipo);
        return $this->formulario($tipo, new $modelo(['estado' => true]));
    }

    public function edit(string $tipo, int $id)
    {
        [$modelo] = $this->catalogo($tipo);
        return $this->formulario($tipo, $modelo::findOrFail($id));
    }

    public function store(Request $request, string $tipo) { return $this->guardar($request, $tipo); }
    public function update(Request $request, string $tipo, int $id) { return $this->guardar($request, $tipo, $id); }

    private function formulario(string $tipo, $registro)
    {
        [, $titulo, $singular] = $this->catalogo($tipo);
        $universidades = Models\Universidad::where('estado', true)->when($tipo === 'facultades' && $registro->exists, fn ($q) => $q->orWhere('id', $registro->universidad_id))->orderBy('nombre')->get();
        $carreras = Models\Carrera::with('universidad')->where(function ($q) use ($registro) {
            $q->where('estado', true)->whereHas('universidad', fn ($u) => $u->where('estado', true));
            if ($registro->exists && $registro->carrera_id) $q->orWhere('id', $registro->carrera_id);
        })->orderBy('nombre')->get();
        return view('administracion.academicos.formulario', compact('tipo', 'titulo', 'singular', 'registro', 'universidades', 'carreras'));
    }

    private function guardar(Request $request, string $tipo, ?int $id = null)
    {
        [$modelo, , $singular] = $this->catalogo($tipo);
        foreach (['nombre', 'codigo', 'version', 'tipo'] as $campo) {
            if (is_string($request->input($campo))) $request->merge([$campo => Str::squish($request->input($campo)) ?: null]);
        }
        $reglas = ['estado' => ['required', 'boolean'], 'nombre' => ['required', 'string', 'max:'.($tipo === 'niveles' ? 100 : 150)]];
        if ($tipo === 'facultades') $reglas += ['universidad_id' => ['required', 'integer', 'exists:universidades,id'], 'codigo' => ['nullable', 'string', 'max:20'], 'tipo' => ['required', 'string', 'max:30']];
        else $reglas['carrera_id'] = ['required', 'integer', 'exists:carreras,id'];
        if ($tipo === 'planes') $reglas += ['codigo' => ['nullable', 'string', 'max:30'], 'version' => ['nullable', 'string', 'max:30'], 'anio_inicio' => ['nullable', 'integer', 'between:1900,2200']];
        if ($tipo === 'niveles') $reglas['numero'] = ['required', 'integer', 'between:1,255'];
        $datos = $request->validate($reglas, ['nombre.required' => 'Indica el nombre.', 'carrera_id.required' => 'Selecciona la carrera.', 'universidad_id.required' => 'Selecciona la institución.']);
        $registro = DB::transaction(function () use ($request, $tipo, $modelo, $id, $datos, $singular) {
            // Serializar las escrituras del mismo catálogo con su institución o carrera.
            $padre = $tipo === 'facultades'
                ? Models\Universidad::lockForUpdate()->findOrFail($datos['universidad_id'])
                : Models\Carrera::lockForUpdate()->findOrFail($datos['carrera_id']);
            $registro = $id ? $modelo::lockForUpdate()->findOrFail($id) : new $modelo;
            $clavePadre = $tipo === 'facultades' ? 'universidad_id' : 'carrera_id';
            if ($id && (int) $registro->$clavePadre !== (int) $padre->id) $this->rechazar($clavePadre, 'La asociación de un registro existente no puede cambiarse. Crea otro registro si corresponde.');
            if (! $id && (! $padre->estado || ($tipo !== 'facultades' && ! $padre->universidad->estado))) $this->rechazar($clavePadre, 'Selecciona un registro activo de una institución activa.');
            if ($id && $datos['estado'] && ! $registro->estado && (! $padre->estado || ($tipo !== 'facultades' && ! $padre->universidad->estado))) $this->rechazar('estado', 'Activa primero la institución y la carrera correspondiente.');
            $otros = fn () => $modelo::where($clavePadre, $padre->id)->when($id, fn ($c) => $c->whereKeyNot($id));
            foreach ($tipo === 'niveles' ? ['numero'] : ['codigo'] as $campo) {
                if (! filled($datos[$campo] ?? null) || ($id && (string) $datos[$campo] === (string) $registro->$campo)) continue;
                $duplicado = $campo === 'numero' ? $otros()->where('numero', $datos[$campo])->exists()
                    : $otros()->whereRaw('LOWER(TRIM(codigo)) = ?', [mb_strtolower($datos[$campo])])->exists();
                if ($duplicado) $this->rechazar($campo, 'Ya existe un registro con este '.($campo === 'numero' ? 'número' : 'código').', incluso entre los inactivos.');
            }
            if ($tipo === 'facultades' && (! $id || $datos['nombre'] !== $registro->nombre)
                && $otros()->whereRaw('LOWER(TRIM(nombre)) = ?', [mb_strtolower($datos['nombre'])])->exists()) $this->rechazar('nombre', 'Ya existe una unidad académica con este nombre en la institución.');
            if ($tipo === 'planes' && (! $id || $datos['nombre'] !== $registro->nombre || ($datos['version'] ?? null) !== $registro->version)
                && $otros()->whereRaw('LOWER(TRIM(nombre)) = ?', [mb_strtolower($datos['nombre'])])
                    ->whereRaw("LOWER(TRIM(COALESCE(version, ''))) = ?", [mb_strtolower($datos['version'] ?? '')])->exists()) $this->rechazar('nombre', 'Ya existe este nombre y versión de plan en la carrera.');
            if ($tipo === 'facultades' && $id && ! $datos['estado'] && $registro->carreras()->where('estado', true)->exists()) $this->rechazar('estado', 'Esta unidad académica tiene carreras activas. Revisa esas carreras antes de desactivarla.');
            if ($tipo === 'niveles' && $id) {
                $secciones = $padre->secciones()->where('semestre', $registro->numero);
                if ((int) $datos['numero'] !== (int) $registro->numero && $secciones->exists()) $this->rechazar('numero', 'Hay secciones registradas en este semestre. Conserva el número para mantener su historial.');
                if (! $datos['estado'] && $secciones->where('estado_activo', true)->whereHas('periodoAcademico', fn ($p) => $p->where('estado', true))->exists()) $this->rechazar('estado', 'Hay secciones activas de este semestre en un período vigente.');
            }
            $antes = $id ? $registro->getAttributes() : null;
            $registro->fill($datos);
            if ($registro->isDirty() || ! $id) {
                $registro->save();
                DB::table('historial_acciones')->insert([
                    'usuario_id' => $request->user()->id, 'accion' => ($id ? 'Actualizar ' : 'Crear ').$singular,
                    'modulo' => 'Catálogos académicos', 'tabla_afectada' => $registro->getTable(), 'registro_id' => $registro->id,
                    'descripcion' => 'Gestión del catálogo conservando sus relaciones e historial.',
                    'datos' => json_encode(['antes' => $antes, 'despues' => $registro->getAttributes()], JSON_THROW_ON_ERROR),
                    'direccion_ip' => $request->ip(), 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            return $registro;
        }, 3);
        return redirect()->route('administracion.academicos.edit', ['tipo' => $tipo, 'id' => $registro->id])->with('success', 'Registro guardado correctamente.');
    }

    private function catalogo(string $tipo): array { abort_unless(isset(self::CATALOGOS[$tipo]), 404); return self::CATALOGOS[$tipo]; }
    private function rechazar(string $campo, string $mensaje): never { throw ValidationException::withMessages([$campo => $mensaje]); }
}

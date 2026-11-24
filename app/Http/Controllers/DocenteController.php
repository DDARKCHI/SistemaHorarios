<?php

namespace App\Http\Controllers;

use App\Models;
use App\Services\CorreccionRutDocente;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\{Rule, ValidationException};

class DocenteController extends Controller
{
    public function index(Request $request)
    {
        $filtros = $request->validate(['q' => ['nullable', 'string', 'max:100'], 'universidad_id' => ['nullable', 'integer', 'exists:universidades,id'],
            'estado' => ['nullable', Rule::in(['activos', 'inactivos', 'todos'])], 'page' => ['nullable', 'integer', 'min:1']]);
        $q = trim($filtros['q'] ?? ''); $estado = $filtros['estado'] ?? 'activos';
        $consulta = Models\Profesor::with('universidad');
        if ($estado !== 'todos') $consulta->where('estado', $estado === 'activos');
        if (! empty($filtros['universidad_id'])) $consulta->where('universidad_id', $filtros['universidad_id']);
        if ($q !== '') {
            $literal = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $q).'%';
            $consulta->where(function ($p) use ($literal, $q) {
                foreach (['nombre', 'apellido', 'correo_institucional', 'rut', 'unidad_academica'] as $campo) $p->orWhereRaw("COALESCE($campo, '') LIKE ? ESCAPE '!'", [$literal]);
                if ($normalizado = $this->rut($q)) $p->orWhereRaw("UPPER(REPLACE(REPLACE(REPLACE(COALESCE(rut, ''), '.', ''), '-', ''), ' ', '')) = ?", [$normalizado]);
                $palabras = preg_split('/\s+/', $q, -1, PREG_SPLIT_NO_EMPTY);
                if (count($palabras) > 1) $p->orWhere(function ($n) use ($palabras) { foreach ($palabras as $palabra) {
                    $parte = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $palabra).'%';
                    $n->where(fn ($c) => $c->whereRaw("nombre LIKE ? ESCAPE '!'", [$parte])->orWhereRaw("apellido LIKE ? ESCAPE '!'", [$parte]));
                } });
            });
        }
        $docentes = $consulta->orderBy('apellido')->orderBy('nombre')->orderBy('id')->paginate(12)->withQueryString();
        if ($docentes->currentPage() > $docentes->lastPage()) return redirect()->to($docentes->url($docentes->lastPage()));
        $universidades = Models\Universidad::orderBy('nombre')->get();
        return view('administracion.docentes.index', compact('docentes', 'universidades', 'filtros', 'q', 'estado'));
    }
    public function create() { return $this->formulario(new Models\Profesor(['estado' => true])); }
    public function edit(Models\Profesor $profesor) { return $this->formulario($profesor); }
    private function formulario(Models\Profesor $profesor)
    {
        $universidades = Models\Universidad::where('estado', true)->when($profesor->exists, fn ($u) => $u->orWhere('id', $profesor->universidad_id))->orderBy('nombre')->get();
        return view('administracion.docentes.formulario', compact('profesor', 'universidades'));
    }
    public function store(Request $request) { return $this->guardar($request); }
    public function update(Request $request, Models\Profesor $profesor) { return $this->guardar($request, $profesor->id); }

    private function guardar(Request $request, ?int $id = null)
    {
        foreach (['nombre', 'apellido', 'rut', 'correo_institucional', 'correo_personal', 'tipo_contrato', 'tipo_profesor', 'unidad_academica'] as $campo) if (is_string($request->input($campo))) {
            $texto = Str::squish($request->input($campo));
            $request->merge([$campo => $texto === '' ? null : (str_starts_with($campo, 'correo_') ? mb_strtolower($texto) : $texto)]);
        }
        $datos = $request->validate(['universidad_id' => ['required', 'integer', 'exists:universidades,id'], 'nombre' => ['required', 'string', 'max:100'], 'apellido' => ['required', 'string', 'max:100'],
            'rut' => ['nullable', 'required_without:correo_institucional', 'string', 'max:12'], 'correo_institucional' => ['nullable', 'required_without:rut', 'email', 'max:150'],
            'correo_personal' => ['nullable', 'email', 'max:150'], 'tipo_contrato' => ['nullable', 'string', 'max:50'], 'tipo_profesor' => ['nullable', 'string', 'max:50'],
            'unidad_academica' => ['nullable', 'string', 'max:150'], 'estado' => ['required', 'boolean'], 'revision' => [$id ? 'required' : 'nullable', 'string', 'size:64']],
            ['rut.required_without' => 'Indica el RUT o un correo institucional.', 'correo_institucional.required_without' => 'Indica un correo institucional o el RUT.']);
        $profesor = DB::transaction(function () use ($request, $datos, $id) {
            $universidad = Models\Universidad::lockForUpdate()->findOrFail($datos['universidad_id']);
            // Respetar el orden período → docente de los flujos de disponibilidad y asignación.
            Models\PeriodoAcademico::where('universidad_id', $universidad->id)->orderBy('id')->lockForUpdate()->get();
            $profesor = $id ? Models\Profesor::lockForUpdate()->findOrFail($id) : new Models\Profesor;
            if ($id && ! hash_equals(self::firma($profesor), $datos['revision'])) $this->rechazar('revision', 'El docente cambió desde que abriste el formulario. Actualiza antes de guardar.');
            if ($id && (int) $profesor->universidad_id !== (int) $universidad->id) $this->rechazar('universidad_id', 'La institución de un docente existente no puede cambiarse.');
            if ((! $id || ($datos['estado'] && ! $profesor->estado)) && ! $universidad->estado) $this->rechazar('universidad_id', 'Activa primero la institución.');
            $rutNuevo = $this->rut($datos['rut'] ?? null); $rutAnterior = $this->rut($profesor->rut);
            $otros = fn () => Models\Profesor::where('universidad_id', $universidad->id)->when($id, fn ($p) => $p->whereKeyNot($id));
            if ($rutNuevo && $rutNuevo !== $rutAnterior && $otros()->whereRaw("UPPER(REPLACE(REPLACE(REPLACE(COALESCE(rut, ''), '.', ''), '-', ''), ' ', '')) = ?", [$rutNuevo])->exists()) $this->rechazar('rut', 'Ya existe este RUT en la institución, incluso entre los docentes inactivos.');
            $correo = $datos['correo_institucional'] ?? null;
            if (! $rutNuevo && ! $correo) $this->rechazar('rut', 'Indica un RUT o correo institucional que identifique al docente.');
            if ($correo && mb_strtolower($profesor->correo_institucional ?? '') !== $correo && $otros()->whereRaw('LOWER(TRIM(correo_institucional)) = ?', [$correo])->exists()) $this->rechazar('correo_institucional', 'Este correo institucional ya pertenece a otro docente de la institución.');
            $vinculado = $id && ($profesor->horarios()->exists() || $profesor->grupos()->exists() || $profesor->secciones()->exists() || $profesor->versiones()->exists()
                || $profesor->asignaturasDocentes()->exists() || $profesor->disponibilidades()->exists() || $profesor->respuestasDisponibilidad()->exists()
                || Models\SolicitudDisponibilidad::where('profesor_id', $id)->exists() || $profesor->solicitudesCambioHorario()->exists()
                || Models\RespuestaSolicitudCambio::where('profesor_id', $id)->exists() || $profesor->candidatosReemplazo()->exists());
            if ($vinculado && $rutAnterior && ! $rutNuevo) $this->rechazar('rut', 'Indica el RUT corregido para conservar la identificación de sus registros.');
            if ($vinculado && ! $rutAnterior && ! $rutNuevo && $profesor->correo_institucional && $correo !== mb_strtolower($profesor->correo_institucional)) $this->rechazar('correo_institucional', 'Este correo identifica a un docente con registros vinculados. Conserva el identificador o completa primero su RUT.');
            if ($id && ! $datos['estado']) {
                if ($this->tieneClasesVigentes($profesor->id)) $this->rechazar('estado', 'El docente tiene clases en una programación semestral actual de un período activo. Revisa sus asignaciones o reemplazos antes de desactivarlo.');
                if (Models\SolicitudDisponibilidad::where('profesor_id', $id)->where(fn ($s) => $s->where('estado', 'En revisión')->orWhere(fn ($p) => $p->where('estado', 'Pendiente')->where(fn ($f) => $f->whereNull('fecha_limite')->orWhereDate('fecha_limite', '>=', today()))))->exists()) $this->rechazar('estado', 'Resuelve las solicitudes de disponibilidad pendientes o en revisión antes de desactivar al docente.');
                if (Models\SolicitudCambioHorario::where('estado_activo', true)->whereIn('estado', ['Pendiente de respuesta', 'Respondida'])->where('fecha_limite', '>', now())
                    ->where(fn ($s) => $s->where('profesor_id', $id)->orWhereHas('respuestasDocentes', fn ($r) => $r->where('profesor_id', $id)))->exists()) $this->rechazar('estado', 'Hay solicitudes de cambio vigentes para el docente. Resuélvelas antes de desactivarlo.');
            }
            $antes = $id ? $profesor->getAttributes() : null;
            $correccionRut = $id && $rutNuevo !== $rutAnterior
                ? app(CorreccionRutDocente::class)->corregir($profesor, $rutNuevo)
                : [];
            $valores = $datos; unset($valores['revision']);
            if (array_key_exists('rut', $valores)) $valores['rut'] = $id && $rutNuevo === $rutAnterior ? $profesor->rut : $rutNuevo;
            $profesor->fill($valores);
            if ($profesor->isDirty() || ! $id) {
                $profesor->save();
                DB::table('historial_acciones')->insert(['usuario_id' => $request->user()->id, 'accion' => $id ? 'Actualizar docente' : 'Crear docente', 'modulo' => 'Docentes',
                    'tabla_afectada' => 'profesores', 'registro_id' => $profesor->id, 'descripcion' => 'Gestión docente conservando asignaciones, respuestas y versiones.',
                    'datos' => json_encode(['antes' => $antes, 'despues' => $profesor->getAttributes(), 'correccion_rut' => $correccionRut], JSON_THROW_ON_ERROR), 'direccion_ip' => $request->ip(), 'created_at' => now(), 'updated_at' => now()]);
            }
            return $profesor;
        }, 3);
        return redirect()->route('administracion.docentes.edit', $profesor)->with('success', 'Docente guardado. Sus asignaciones e historial se conservan.');
    }
    private function tieneClasesVigentes(int $id): bool
    {
        $activo = fn ($p) => $p->where('estado', true);
        $delDocente = fn ($p) => $p->where('estado', true)->where('profesor_id', $id);
        return Models\Horario::where('estado', true)->whereHas('versionPlanificacion', fn ($v) => $v->where('tipo', 'semestral')->where('es_actual', true)
                ->whereHas('periodoAcademico', fn ($p) => $p->where('estado', true)))
            ->where(fn ($h) => $h->whereHas('profesores', $delDocente)
                ->orWhere(fn ($g) => $g->whereDoesntHave('profesores', $activo)->whereHas('grupoSeccion.profesores', $delDocente))
                ->orWhere(fn ($s) => $s->whereDoesntHave('profesores', $activo)->whereDoesntHave('grupoSeccion.profesores', $activo)->whereHas('actividadSeccion.seccion.profesores', $delDocente)))->exists();
    }
    private function rut(?string $valor): ?string { $valor = mb_strtoupper(str_replace(['.', '-', ' '], '', trim($valor ?? ''))); return $valor === '' ? null : $valor; }
    public static function firma(Models\Profesor $profesor): string { $datos = $profesor->getAttributes(); ksort($datos); return hash('sha256', json_encode($datos, JSON_THROW_ON_ERROR)); }
    private function rechazar(string $campo, string $mensaje): never { throw ValidationException::withMessages([$campo => $mensaje]); }
}

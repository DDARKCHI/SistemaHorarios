<?php

namespace App\Http\Controllers;

use App\Models;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ConfiguracionController extends Controller
{
    private const DIAS = [1 => ['Lunes', 'LU'], 2 => ['Martes', 'MA'], 3 => ['Miércoles', 'MI'], 4 => ['Jueves', 'JU'], 5 => ['Viernes', 'VI'], 6 => ['Sábado', 'SA'], 7 => ['Domingo', 'DO']];

    public function index(Request $request)
    {
        $datos = $request->validate(['universidad_id' => ['nullable', 'integer', 'exists:universidades,id'], 'nueva' => ['nullable', 'boolean']]);
        $universidades = Models\Universidad::orderBy('nombre')->get();
        $institucion = isset($datos['universidad_id']) ? $universidades->firstWhere('id', $datos['universidad_id']) : ($universidades->firstWhere('estado', true) ?? $universidades->first());
        if ($datos['nueva'] ?? false) { abort_unless($request->user()->can('administrar usuarios'), 403); $institucion = new Models\Universidad(['estado' => true]); }
        return view('configuracion.index', compact('universidades', 'institucion'));
    }

    public function guardarInstitucion(Request $request, ?int $id = null)
    {
        foreach (['nombre', 'sigla'] as $campo) if (is_string($request->input($campo))) $request->merge([$campo => Str::squish($request->input($campo))]);
        $datos = $request->validate(['nombre' => ['required', 'string', 'max:150'], 'sigla' => ['required', 'string', 'max:20']]);
        $institucion = DB::transaction(function () use ($request, $datos, $id) {
            $instituciones = Models\Universidad::orderBy('id')->lockForUpdate()->get();
            $registro = $id ? $instituciones->firstWhere('id', $id) : new Models\Universidad(['estado' => true]);
            abort_unless($registro, 404);
            foreach (['nombre', 'sigla'] as $campo) if ((! $id || $datos[$campo] !== $registro->$campo) && $instituciones->contains(fn ($u) => (int) $u->id !== $id && mb_strtolower(trim($u->$campo)) === mb_strtolower($datos[$campo]))) $this->rechazar($campo, 'Ya existe una institución con este '.($campo === 'nombre' ? 'nombre' : 'sigla').'.');
            $antes = $id ? $registro->getAttributes() : null;
            $registro->fill($datos);
            $cambio = $registro->isDirty() || ! $id;
            if ($cambio) $registro->save();
            $configuracion = Models\ConfiguracionInstitucion::where('universidad_id', $registro->id)->orderBy('id')->lockForUpdate()->first()
                ?? new Models\ConfiguracionInstitucion(['universidad_id' => $registro->id, 'nombre_sistema' => 'PROSEC', 'color_principal' => '#D12421', 'color_secundario' => '#1f2937', 'estado' => true]);
            $configuracion->fill(['nombre_institucion' => $registro->nombre, 'sigla' => $registro->sigla]);
            $configAntes = $configuracion->exists ? $configuracion->getRawOriginal() : null;
            if ($cambio || $configuracion->isDirty()) {
                $configuracion->save();
                $this->auditar($request, $registro, $id ? 'Actualizar institución' : 'Crear institución', $antes, ['configuracion_anterior' => $configAntes, 'configuracion_actual' => $configuracion->getAttributes()]);
            }
            return $registro;
        }, 3);
        return redirect()->route('configuracion.index', ['universidad_id' => $institucion->id])->with('success', 'Datos institucionales guardados.');
    }

    public function calendario()
    {
        $dias = Models\Dia::orderBy('numero')->get(); $bloques = Models\BloqueHorario::orderBy('hora_inicio')->orderBy('id')->get();
        return view('configuracion.calendario', compact('dias', 'bloques'));
    }

    public function editarCalendario(string $tipo, ?int $id = null)
    {
        $modelo = $this->modeloCalendario($tipo);
        $registro = $id ? $modelo::findOrFail($id) : new $modelo(['estado' => true, 'es_almuerzo' => false]);
        $diasSemana = self::DIAS;
        return view('configuracion.calendario-formulario', compact('tipo', 'registro', 'diasSemana'));
    }

    public function guardarCalendario(Request $request, string $tipo, ?int $id = null)
    {
        $modelo = $this->modeloCalendario($tipo);
        $reglas = ['numero' => ['required', 'integer', 'between:1,'.($tipo === 'dias' ? 7 : 255)], 'estado' => ['required', 'boolean']];
        if ($tipo === 'bloques') $reglas += ['hora_inicio' => ['required', 'date_format:H:i'], 'hora_fin' => ['required', 'date_format:H:i', 'after:hora_inicio'], 'es_almuerzo' => ['required', 'boolean']];
        $datos = $request->validate($reglas, ['hora_fin.after' => 'La hora de término debe ser posterior al inicio.']);
        $registro = DB::transaction(function () use ($request, $tipo, $modelo, $id, $datos) {
            // El calendario es compartido: serializar con los flujos que asignan y aprueban horarios.
            Models\Universidad::orderBy('id')->lockForUpdate()->get();
            Models\PeriodoAcademico::orderBy('id')->lockForUpdate()->get();
            Models\Profesor::orderBy('id')->lockForUpdate()->get();
            Models\Dia::orderBy('id')->lockForUpdate()->get();
            Models\BloqueHorario::orderBy('id')->lockForUpdate()->get();
            $registro = $id ? $modelo::findOrFail($id) : new $modelo;
            if ((! $id || (int) $datos['numero'] !== (int) $registro->numero) && $modelo::where('numero', $datos['numero'])->when($id, fn ($q) => $q->whereKeyNot($id))->exists()) $this->rechazar('numero', 'Ya existe este número, incluso entre los registros inactivos. Edita el registro existente.');
            if ($tipo === 'dias') {
                if (! $id || (int) $datos['numero'] !== (int) $registro->numero) [$datos['nombre'], $datos['abreviatura']] = self::DIAS[$datos['numero']];
            } else {
                $datos['hora_inicio'] .= ':00'; $datos['hora_fin'] .= ':00';
            }
            $antes = $id ? $registro->getAttributes() : null;
            $registro->fill($datos);
            if ($registro->isDirty() && Models\SolicitudDisponibilidad::where(fn ($s) => $s->where('estado', 'En revisión')->orWhere(fn ($p) => $p->where('estado', 'Pendiente')->where(fn ($f) => $f->whereNull('fecha_limite')->orWhereDate('fecha_limite', '>=', today()))))->exists()) {
                $this->rechazar('calendario', 'Hay solicitudes de disponibilidad pendientes o en revisión. Resuélvelas antes de cambiar el calendario compartido.');
            }
            $camposIdentidad = $tipo === 'dias' ? ['numero', 'nombre', 'abreviatura'] : ['numero', 'hora_inicio', 'hora_fin', 'es_almuerzo'];
            $cambiaIdentidad = $registro->isDirty($camposIdentidad);
            if ($id && $cambiaIdentidad && ($registro->horarios()->exists() || $registro->disponibilidadesProfesores()->exists() || $registro->opcionesCambioHorario()->exists())) $this->rechazar('numero', 'Este registro tiene horarios, disponibilidad u opciones de cambio vinculadas. Conserva su definición para proteger el historial.');
            if ($id && ! $datos['estado']) {
                $horariosVigentes = $registro->horarios()->where('estado', true)->whereHas('versionPlanificacion', fn ($v) => $v->where('es_actual', true)->where('tipo', 'semestral')->whereHas('periodoAcademico', fn ($p) => $p->where('estado', true)))->exists();
                $disponibilidadVigente = $registro->disponibilidadesProfesores()->where('estado', true)->whereHas('periodoAcademico', fn ($p) => $p->where('estado', true))->exists();
                if ($horariosVigentes || $disponibilidadVigente) $this->rechazar('estado', 'Hay horarios actuales o disponibilidad vigente que utilizan este registro. No puede desactivarse.');
            }
            if ($tipo === 'bloques' && $datos['estado'] && ($cambiaIdentidad || ! $id || $registro->isDirty('estado'))
                && Models\BloqueHorario::where('estado', true)->when($id, fn ($b) => $b->whereKeyNot($id))->where('hora_inicio', '<', $datos['hora_fin'])->where('hora_fin', '>', $datos['hora_inicio'])->exists()) $this->rechazar('hora_inicio', 'El intervalo se superpone con otro módulo activo. Revisa el calendario.');
            if ($id && ! $datos['estado'] && $modelo::where('estado', true)->whereKeyNot($id)->doesntExist()) $this->rechazar('estado', 'Debe quedar al menos un '.($tipo === 'dias' ? 'día' : 'módulo').' activo.');
            if ($registro->isDirty() || ! $id) {
                $registro->save();
                $this->auditar($request, $registro, ($id ? 'Actualizar ' : 'Crear ').($tipo === 'dias' ? 'día académico' : 'módulo horario'), $antes);
            }
            return $registro;
        }, 3);
        return redirect()->route('configuracion.calendario.edit', ['tipo' => $tipo, 'id' => $registro->id])->with('success', 'Calendario actualizado. Revisa las solicitudes de disponibilidad si cambió el calendario activo.');
    }

    private function modeloCalendario(string $tipo): string { return match ($tipo) { 'dias' => Models\Dia::class, 'bloques' => Models\BloqueHorario::class, default => abort(404) }; }
    private function auditar(Request $request, $registro, string $accion, ?array $antes, array $extras = []): void
    {
        DB::table('historial_acciones')->insert(['usuario_id' => $request->user()->id, 'accion' => $accion, 'modulo' => 'Configuración', 'tabla_afectada' => $registro->getTable(), 'registro_id' => $registro->id,
            'descripcion' => 'Gestión de configuración conservando identidad y relaciones.', 'datos' => json_encode(['antes' => $antes, 'despues' => $registro->getAttributes()] + $extras, JSON_THROW_ON_ERROR),
            'direccion_ip' => $request->ip(), 'created_at' => now(), 'updated_at' => now()]);
    }
    private function rechazar(string $campo, string $mensaje): never { throw ValidationException::withMessages([$campo => $mensaje]); }
}

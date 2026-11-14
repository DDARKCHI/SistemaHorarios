<?php

namespace App\Http\Controllers;

use App\Models\{BloqueHorario, Dia, DisponibilidadProfesor, PeriodoAcademico, Profesor, RespuestaDisponibilidad, SolicitudDisponibilidad};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, URL};
use Illuminate\Validation\{Rule, ValidationException};

class DisponibilidadDocenteController extends Controller
{
    public function index(Request $request)
    {
        $datos = $request->validate(['periodo_id' => ['nullable', 'integer', 'exists:periodos_academicos,id'],
            'estado' => ['nullable', Rule::in(['Pendiente', 'En revisión', 'Aprobada', 'Rechazada', 'Cancelada'])]]);
        $periodos = PeriodoAcademico::orderByDesc('anio')->orderByDesc('numero_semestre')->get();
        $periodo = isset($datos['periodo_id']) ? $periodos->firstWhere('id', $datos['periodo_id']) : $periodos->firstWhere('estado', true);
        $profesores = Profesor::where('estado', true)->when($periodo, fn ($q) => $q->where('universidad_id', $periodo->universidad_id))->orderBy('apellido')->orderBy('nombre')->get();
        $solicitudes = SolicitudDisponibilidad::with(['profesor', 'periodoAcademico'])->when($periodo, fn ($q) => $q->where('periodo_academico_id', $periodo->id))
            ->when($datos['estado'] ?? null, fn ($q, $estado) => $q->where('estado', $estado))->latest('id')->paginate(15)->withQueryString();
        return view('disponibilidad.index', compact('periodos', 'periodo', 'profesores', 'solicitudes'));
    }

    public function store(Request $request)
    {
        $datos = $request->validate(['profesor_id' => ['required', 'integer', 'exists:profesores,id'],
            'periodo_academico_id' => ['required', 'integer', 'exists:periodos_academicos,id'],
            'fecha_limite' => ['required', 'date_format:Y-m-d', 'after_or_equal:today', 'before_or_equal:'.now()->addYear()->toDateString()],
            'observaciones' => ['nullable', 'string', 'max:2000']]);
        $solicitud = DB::transaction(function () use ($request, $datos) {
            $profesor = Profesor::lockForUpdate()->findOrFail($datos['profesor_id']);
            $periodo = PeriodoAcademico::findOrFail($datos['periodo_academico_id']);
            if (! $profesor->estado || ! $periodo->estado || $profesor->universidad_id !== $periodo->universidad_id) {
                throw ValidationException::withMessages(['profesor_id' => 'Selecciona un docente activo de la institución del período activo.']);
            }
            if (! $this->claves($this->calendario())) throw ValidationException::withMessages(['profesor_id' => 'Primero configura los días y módulos horarios activos.']);
            $abierta = SolicitudDisponibilidad::where('profesor_id', $profesor->id)->where('periodo_academico_id', $periodo->id)
                ->where(fn ($q) => $q->where('estado', 'En revisión')->orWhere(fn ($q) => $q->where('estado', 'Pendiente')->whereDate('fecha_limite', '>=', today())))->exists();
            if ($abierta) throw ValidationException::withMessages(['profesor_id' => 'Este docente ya tiene una solicitud pendiente o una respuesta en revisión para este período.']);
            $solicitud = SolicitudDisponibilidad::create($datos + ['fecha_solicitud' => today(), 'estado' => 'Pendiente']);
            $this->auditar($request, $solicitud, 'Crear solicitud de disponibilidad');
            return $solicitud;
        }, 3);
        return redirect()->route('disponibilidad.show', $solicitud)->with('success', 'Solicitud creada. Comparte el enlace privado con el docente.');
    }

    public function show(SolicitudDisponibilidad $solicitud)
    {
        $solicitud->load(['profesor', 'periodoAcademico', 'respuestas']);
        $respuesta = $solicitud->respuestas->sortByDesc('id')->first();
        $enlace = $this->abierta($solicitud) ? URL::temporarySignedRoute('disponibilidad.publica', $solicitud->fecha_limite->endOfDay(), ['solicitud' => $solicitud->id]) : null;
        return view('disponibilidad.detalle', compact('solicitud', 'respuesta', 'enlace') + $this->calendario());
    }

    public function publica(Request $request, SolicitudDisponibilidad $solicitud)
    {
        $solicitud->load(['profesor', 'periodoAcademico']);
        abort_unless($solicitud->profesor->estado && $solicitud->periodoAcademico->estado, 410, 'Esta solicitud ya no está disponible.');
        if ($request->isMethod('post')) {
            $datos = $request->validate(['bloques' => ['nullable', 'array', 'max:1000'], 'bloques.*' => ['required', 'string', 'distinct', 'regex:/^\d+:\d+$/'],
                'sin_disponibilidad' => ['nullable', 'boolean'], 'observaciones' => ['nullable', 'string', 'max:2000']]);
            DB::transaction(function () use ($request, $solicitud, $datos) {
                $profesor = Profesor::lockForUpdate()->findOrFail($solicitud->profesor_id);
                $registro = SolicitudDisponibilidad::lockForUpdate()->findOrFail($solicitud->id);
                abort_unless($profesor->estado && $registro->periodoAcademico->estado && $this->abierta($registro), 410, 'La solicitud venció o ya fue respondida.');
                $calendario = $this->calendario();
                $validos = $this->claves($calendario);
                $elegidos = $datos['bloques'] ?? [];
                if ((! $elegidos && ! ($datos['sin_disponibilidad'] ?? false)) || ($elegidos && ($datos['sin_disponibilidad'] ?? false)) || array_diff($elegidos, $validos)) {
                    throw ValidationException::withMessages(['bloques' => 'Selecciona bloques válidos o marca que no tienes disponibilidad.']);
                }
                RespuestaDisponibilidad::create(['solicitud_disponibilidad_id' => $registro->id, 'profesor_id' => $profesor->id,
                    'respuesta' => 'Pendiente de revisión', 'bloques' => ['disponibles' => $elegidos, 'calendario' => $validos, 'firma' => $this->firmaCalendario($calendario)],
                    'observaciones' => $datos['observaciones'] ?? null, 'fecha_respuesta' => now()]);
                $registro->update(['estado' => 'En revisión']);
                $this->auditar($request, $registro, 'Recibir respuesta de disponibilidad');
            }, 3);
            return redirect()->to($request->fullUrl());
        }
        return response()->view('disponibilidad.publica', compact('solicitud') + $this->calendario() + ['abierta' => $this->abierta($solicitud)])
            ->header('Cache-Control', 'no-store, private')->header('Referrer-Policy', 'no-referrer')->header('X-Robots-Tag', 'noindex, nofollow');
    }

    public function revisar(Request $request, SolicitudDisponibilidad $solicitud)
    {
        $datos = $request->validate(['decision' => ['required', Rule::in(['aprobar', 'rechazar'])],
            'motivo' => ['required_if:decision,rechazar', 'nullable', 'string', 'max:2000']]);
        DB::transaction(function () use ($request, $solicitud, $datos) {
            $profesor = Profesor::lockForUpdate()->findOrFail($solicitud->profesor_id);
            $registro = SolicitudDisponibilidad::lockForUpdate()->findOrFail($solicitud->id);
            $respuesta = $registro->respuestas()->latest('id')->lockForUpdate()->first();
            if ($registro->estado !== 'En revisión' || ! $respuesta || $respuesta->respuesta !== 'Pendiente de revisión') {
                throw ValidationException::withMessages(['decision' => 'Esta respuesta ya fue revisada o no está pendiente.']);
            }
            if ($datos['decision'] === 'aprobar') {
                if (! $profesor->estado || ! $registro->periodoAcademico->estado) throw ValidationException::withMessages(['decision' => 'El docente y el período deben estar activos.']);
                $actuales = $this->claves($this->calendario());
                $enviados = $respuesta->bloques['calendario'] ?? [];
                sort($actuales); sort($enviados);
                if (! $actuales || $actuales !== $enviados || ($respuesta->bloques['firma'] ?? '') !== $this->firmaCalendario($this->calendario())) throw ValidationException::withMessages(['decision' => 'El calendario cambió desde la respuesta. Recházala y crea una nueva solicitud.']);
                $antes = DisponibilidadProfesor::where('profesor_id', $profesor->id)->where('periodo_academico_id', $registro->periodo_academico_id)->get()->toArray();
                DisponibilidadProfesor::where('profesor_id', $profesor->id)->where('periodo_academico_id', $registro->periodo_academico_id)->update(['estado' => false]);
                foreach ($actuales as $clave) {
                    [$dia, $bloque] = explode(':', $clave);
                    DisponibilidadProfesor::updateOrCreate(['profesor_id' => $profesor->id, 'periodo_academico_id' => $registro->periodo_academico_id,
                        'dia_id' => $dia, 'bloque_horario_id' => $bloque], ['tipo_disponibilidad' => in_array($clave, $respuesta->bloques['disponibles'], true) ? 'Disponible' : 'No disponible',
                        'estado' => true, 'observaciones' => 'Disponibilidad aprobada. Solicitud #'.$registro->id]);
                }
            }
            $estado = $datos['decision'] === 'aprobar' ? 'Aprobada' : 'Rechazada';
            $respuesta->update(['respuesta' => $estado, 'revisado_por' => $request->user()->id, 'revisado_en' => now(), 'motivo_revision' => $datos['motivo'] ?? null]);
            $registro->update(['estado' => $estado]);
            $this->auditar($request, $registro, $estado === 'Aprobada' ? 'Aprobar disponibilidad' : 'Rechazar disponibilidad', ['respuesta_id' => $respuesta->id, 'disponibilidad_anterior' => $antes ?? null]);
        }, 3);
        return back()->with('success', 'Respuesta revisada correctamente.');
    }

    public function cancelar(Request $request, SolicitudDisponibilidad $solicitud)
    {
        DB::transaction(function () use ($request, $solicitud) {
            Profesor::lockForUpdate()->findOrFail($solicitud->profesor_id);
            $registro = SolicitudDisponibilidad::lockForUpdate()->findOrFail($solicitud->id);
            if ($registro->estado !== 'Pendiente') throw ValidationException::withMessages(['solicitud' => 'Solo puedes cancelar una solicitud pendiente de respuesta.']);
            $registro->update(['estado' => 'Cancelada']);
            $this->auditar($request, $registro, 'Cancelar solicitud de disponibilidad');
        }, 3);
        return back()->with('success', 'Solicitud cancelada. El enlace dejó de estar disponible.');
    }

    private function abierta(SolicitudDisponibilidad $solicitud): bool
    {
        return $solicitud->estado === 'Pendiente' && $solicitud->fecha_limite && $solicitud->fecha_limite->copy()->endOfDay()->isFuture();
    }

    private function calendario(): array
    {
        return ['dias' => Dia::where('estado', true)->orderBy('numero')->get(),
            'bloques' => BloqueHorario::where('estado', true)->orderBy('hora_inicio')->get()];
    }

    private function claves(array $calendario): array
    {
        $claves = [];
        foreach ($calendario['dias'] as $dia) foreach ($calendario['bloques'] as $bloque) $claves[] = $dia->id.':'.$bloque->id;
        return $claves;
    }

    private function firmaCalendario(array $calendario): string
    {
        return hash('sha256', json_encode([
            $calendario['dias']->map(fn ($dia) => [$dia->id, $dia->numero, $dia->nombre])->values()->all(),
            $calendario['bloques']->map(fn ($bloque) => [$bloque->id, $bloque->hora_inicio, $bloque->hora_fin, $bloque->es_almuerzo])->values()->all(),
        ], JSON_THROW_ON_ERROR));
    }

    private function auditar(Request $request, SolicitudDisponibilidad $solicitud, string $accion, array $detalle = []): void
    {
        DB::table('historial_acciones')->insert(['usuario_id' => $request->user()?->id, 'accion' => $accion, 'modulo' => 'Disponibilidad docente',
            'tabla_afectada' => 'solicitudes_disponibilidad', 'registro_id' => $solicitud->id,
            'descripcion' => $accion.'. Docente #'.$solicitud->profesor_id.', período #'.$solicitud->periodo_academico_id.'.',
            'datos' => json_encode(['estado' => $solicitud->estado] + $detalle, JSON_THROW_ON_ERROR), 'direccion_ip' => $request->ip(), 'created_at' => now(), 'updated_at' => now()]);
    }
}

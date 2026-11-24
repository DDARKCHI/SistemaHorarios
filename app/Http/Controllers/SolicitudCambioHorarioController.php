<?php

namespace App\Http\Controllers;

use App\Models;
use App\Services\{AlternativasCambioHorario, SolicitudesCambio};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;

class SolicitudCambioHorarioController extends Controller
{
    public function index(Request $request)
    {
        $datos = $request->validate(['periodo_id' => ['nullable', 'integer', 'exists:periodos_academicos,id'], 'q' => ['nullable', 'string', 'max:100'],
            'estado' => ['nullable', Rule::in(['Pendiente de respuesta', 'Respondida', 'Aplicada', 'Rechazada', 'Cancelada'])], 'page' => ['nullable', 'integer', 'min:1']]);
        $periodos = Models\PeriodoAcademico::orderByDesc('anio')->orderByDesc('numero_semestre')->get();
        $periodo = isset($datos['periodo_id']) ? $periodos->firstWhere('id', $datos['periodo_id']) : ($periodos->firstWhere('estado', true) ?? $periodos->first());
        $solicitudes = Models\SolicitudCambioHorario::whereHas('horario.versionPlanificacion', fn ($q) => $q->where('periodo_academico_id', $periodo?->id))
            ->when($datos['estado'] ?? null, fn ($q, $estado) => $q->where('estado', $estado))->with('profesor')->latest('id')->paginate(12)->withQueryString();
        $version = Models\VersionPlanificacion::where('periodo_academico_id', $periodo?->id)->where('tipo', 'semestral')->where('es_actual', true)->latest('id')->first();
        $q = trim($datos['q'] ?? '');
        $horarios = collect();
        if ($q !== '' && $version?->estado === 'Borrador') {
            $literal = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $q).'%';
            $horarios = Models\Horario::where('version_planificacion_id', $version->id)->where('estado', true)->whereHas('actividadSeccion.seccion.asignatura',
                fn ($a) => $a->where(fn ($a) => $a->whereRaw("nombre LIKE ? ESCAPE '!'", [$literal])->orWhereRaw("codigo LIKE ? ESCAPE '!'", [$literal])))
                ->with('actividadSeccion.seccion.asignatura', 'grupoSeccion', 'dia', 'bloqueHorario')->orderBy('id')->limit(30)->get();
        }
        return view('programacion.cambios.index', compact('periodos', 'periodo', 'solicitudes', 'version', 'q', 'horarios'));
    }
    public function create(Request $request, Models\Horario $horario, AlternativasCambioHorario $alternativas)
    {
        $datos = $request->validate(['espacio' => ['nullable', 'string', 'regex:/^(actual|sala:\d+|laboratorio:\d+)$/']]);
        return response()->view('programacion.cambios.crear', $alternativas->preparar($horario, $datos['espacio'] ?? 'actual'))->header('Cache-Control', 'no-store, private');
    }
    public function store(Request $request, Models\Horario $horario, SolicitudesCambio $servicio)
    {
        $datos = $request->validate(['espacio' => ['nullable', 'string', 'regex:/^(actual|sala:\d+|laboratorio:\d+)$/'], 'firma' => ['required', 'regex:/^[a-f0-9]{64}$/'], 'motivo' => ['required', 'string', 'max:150'], 'descripcion' => ['nullable', 'string', 'max:2000'],
            'fecha_limite' => ['required', 'date_format:Y-m-d', 'after_or_equal:today', 'before_or_equal:'.today()->addDays(30)->toDateString()],
            'opciones' => ['required', 'array', 'min:1', 'max:5'], 'opciones.*' => ['required', 'string', 'distinct', 'regex:/^\d+:\d+$/']],
            ['opciones.required' => 'Selecciona al menos una alternativa.', 'opciones.max' => 'Puedes proponer como máximo cinco alternativas.',
                'opciones.*.distinct' => 'No repitas una alternativa.', 'firma.*' => 'Actualiza la búsqueda de alternativas antes de enviar.',
                'fecha_limite.*' => 'Selecciona una fecha límite entre hoy y los próximos treinta días.', 'motivo.required' => 'Indica el motivo del cambio.']);
        $solicitud = $servicio->crear($horario, $datos, $request->user()->id, $request->ip());
        return redirect()->route('programacion.cambios.show', $solicitud)->with('success', 'Solicitud creada. Comparte el enlace correspondiente con cada docente.');
    }
    public function show(Models\SolicitudCambioHorario $solicitud, SolicitudesCambio $servicio)
    {
        $solicitud->load('opciones', 'respuestasDocentes.profesor', 'respuestasDocentes.opcion', 'horario.versionPlanificacion.periodoAcademico');
        $abierta = $servicio->abierta($solicitud);
        $enlaces = [];
        if ($abierta && $solicitud->estado === 'Pendiente de respuesta') foreach ($solicitud->respuestasDocentes->where('estado', 'Pendiente') as $respuesta) {
            $enlaces[$respuesta->id] = URL::temporarySignedRoute('programacion.cambios.responder', $solicitud->fecha_limite, ['respuesta' => $respuesta->id]);
        }
        $acuerdo = $solicitud->estado === 'Respondida' && $solicitud->respuestasDocentes->isNotEmpty()
            && $solicitud->respuestasDocentes->every(fn ($r) => $r->estado === 'Aceptada' && $r->opcion_id) && $solicitud->respuestasDocentes->pluck('opcion_id')->unique()->count() === 1;
        return response()->view('programacion.cambios.detalle', compact('solicitud', 'abierta', 'enlaces', 'acuerdo'))->header('Cache-Control', 'no-store, private');
    }
    public function revisar(Request $request, Models\SolicitudCambioHorario $solicitud, SolicitudesCambio $servicio)
    {
        $datos = $request->validate(['decision' => ['required', Rule::in(['aplicar', 'rechazar', 'cancelar'])], 'motivo' => ['required_unless:decision,aplicar', 'nullable', 'string', 'max:2000']]);
        $servicio->revisar($solicitud, $datos, $request->user()->id, $request->ip());
        return back()->with('success', $datos['decision'] === 'aplicar' ? 'Cambio aplicado tras comprobar de nuevo disponibilidad, topes y recursos.' : 'Solicitud cerrada sin modificar el horario.');
    }
    public function responder(Request $request, Models\RespuestaSolicitudCambio $respuesta, SolicitudesCambio $servicio)
    {
        $respuesta->load('profesor', 'solicitud.opciones', 'solicitud.horario.versionPlanificacion.periodoAcademico');
        $solicitud = $respuesta->solicitud;
        abort_unless($respuesta->profesor?->estado, 410, 'Esta solicitud ya no está disponible.');
        if ($request->isMethod('post')) {
            $datos = $request->validate(['decision' => ['required', Rule::in(['aceptar', 'rechazar'])], 'opcion_id' => ['required_if:decision,aceptar', 'nullable', 'integer', 'min:1'],
                'observaciones' => ['required_if:decision,rechazar', 'nullable', 'string', 'max:2000']]);
            $servicio->responder($respuesta, $datos, $request->ip());
            return redirect()->to($request->fullUrl());
        }
        $abierta = $servicio->abierta($solicitud) && $respuesta->estado === 'Pendiente' && $solicitud->estado === 'Pendiente de respuesta';
        return response()->view('programacion.cambios.responder', compact('solicitud', 'respuesta', 'abierta'))->header('Cache-Control', 'no-store, private')->header('Referrer-Policy', 'no-referrer')->header('X-Robots-Tag', 'noindex, nofollow');
    }
}

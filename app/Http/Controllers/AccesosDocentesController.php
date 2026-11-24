<?php

namespace App\Http\Controllers;

use App\Models;
use App\Services\CuentasDocentes;
use App\Services\DocentesPorSede;
use App\Services\EnviosAccesosDocentes;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AccesosDocentesController extends Controller
{
    public const FILTROS = ['periodo_id', 'sede_id', 'q', 'estado', 'fecha_limite', 'page', 'historial_page'];

    private const ESTADOS_SEGUIMIENTO = [
        'sin_enviar' => 'Sin invitación',
        'en_cola' => 'Correo pendiente o en proceso',
        'enviados' => 'Correo enviado',
        'fallidos' => 'Error de envío',
        'ingresaron' => 'Ingresaron al sistema',
        'sin_ingreso' => 'Aún no ingresan',
    ];

    private const ESTADOS_DISPONIBILIDAD = [
        'sin_solicitud' => 'Sin solicitud',
        'Pendiente' => 'Pendiente de respuesta',
        'respondieron' => 'Con respuesta',
        'En revisión' => 'En revisión',
        'Aprobada' => 'Aprobada',
        'Rechazada' => 'Rechazada',
        'Vencida' => 'Vencida',
        'Cancelada' => 'Cancelada',
    ];

    public function redirigir(Request $request)
    {
        return redirect()->route('disponibilidad.index', $request->only([...self::FILTROS, 'solicitud']));
    }

    public function index(Request $request, EnviosAccesosDocentes $envios, DisponibilidadDocenteController $disponibilidad)
    {
        $datos = $request->validate([
            'periodo_id' => ['nullable', 'integer', 'exists:periodos_academicos,id'],
            'sede_id' => ['nullable', 'integer', 'exists:sedes,id'],
            'q' => ['nullable', 'string', 'max:100'],
            'estado' => ['nullable', Rule::in(['todos', ...array_keys(self::ESTADOS_SEGUIMIENTO), ...array_keys(self::ESTADOS_DISPONIBILIDAD)])],
            'fecha_limite' => ['nullable', 'date_format:Y-m-d'],
            'page' => ['nullable', 'integer', 'min:1'],
            'historial_page' => ['nullable', 'integer', 'min:1'],
            'solicitud' => ['nullable', 'integer', 'min:1'],
        ]);
        $solicitudConsulta = isset($datos['solicitud']) ? Models\SolicitudDisponibilidad::findOrFail($datos['solicitud']) : null;
        $periodos = Models\PeriodoAcademico::with('universidad')->orderByDesc('anio')
            ->orderByDesc('numero_semestre')->orderByDesc('id')->get();
        $periodoId = $datos['periodo_id'] ?? $solicitudConsulta?->periodo_academico_id;
        $periodo = $periodoId
            ? $periodos->firstWhere('id', $periodoId)
            : ($periodos->firstWhere('estado', true) ?? $periodos->first());
        abort_if($solicitudConsulta && (int) $solicitudConsulta->periodo_academico_id !== (int) $periodo?->id, 404);
        $sedes = Models\Sede::where('universidad_id', $periodo?->universidad_id)->orderBy('nombre')->get();
        $sedeId = filled($datos['sede_id'] ?? null) ? (int) $datos['sede_id'] : null;
        $sedeSeleccionada = $sedes->firstWhere('id', $sedeId);
        if ($sedeId && ! $sedeSeleccionada) {
            throw ValidationException::withMessages(['sede_id' => 'Selecciona una sede de la institución del período.']);
        }
        $docentesPorSede = app(DocentesPorSede::class);
        $versionSede = $sedeId && $periodo ? $docentesPorSede->versionReferencia($periodo) : null;
        $idsSede = $sedeId && $periodo ? $docentesPorSede->ids($periodo, $sedeId) : null;
        $filtros = $request->only(self::FILTROS) + ['periodo_id' => $periodo?->id];
        $q = trim($datos['q'] ?? '');
        $estado = $datos['estado'] ?? 'todos';
        $fechaLimite = $datos['fecha_limite'] ?? null;
        $filtraSolicitud = $fechaLimite || array_key_exists($estado, self::ESTADOS_DISPONIBILIDAD);
        $ultimosEnvios = Models\EnvioAccesoDocente::where('periodo_academico_id', $periodo?->id)
            ->whereIn('id', Models\EnvioAccesoDocente::selectRaw('MAX(id)')
                ->where('periodo_academico_id', $periodo?->id)->groupBy('profesor_id'))
            ->get()->keyBy('profesor_id');
        $solicitudes = Models\SolicitudDisponibilidad::where('periodo_academico_id', $periodo?->id)
            ->with([
                'respuestas' => fn ($respuestas) => $respuestas->latest('id'),
                'recordatorios',
                'enviosAcceso' => fn ($envios) => $envios->where('estado', 'enviado')->whereNotNull('enviado_en')
                    ->latest('enviado_en')->latest('id'),
            ])
            ->latest('id')->get()->groupBy('profesor_id');
        $idsConHistorial = $solicitudes->keys()->merge($ultimosEnvios->keys())->unique();
        $profesores = Models\Profesor::where(fn ($query) => $query
            ->where(fn ($activos) => $activos->where('universidad_id', $periodo?->universidad_id)->where('estado', true))
            ->orWhereIn('id', $idsConHistorial))
            ->when($idsSede !== null, fn ($query) => $query->whereIn('id', $idsSede))
            ->orderBy('apellido')->orderBy('nombre')->orderBy('id')->get();
        $solicitudes = $solicitudes->toBase()->only($profesores->modelKeys());
        $usuarios = Models\User::whereIn('profesor_id', $profesores->modelKeys())->get()->keyBy('profesor_id');

        $filas = $profesores->map(function (Models\Profesor $profesor) use ($usuarios, $ultimosEnvios, $solicitudes, $periodo, $estado, $fechaLimite, $filtraSolicitud) {
            $usuario = $usuarios->get($profesor->id);
            $correo = collect([$profesor->correo_institucional, $profesor->correo_personal, $usuario?->email])
                ->first(fn ($valor) => filter_var($valor, FILTER_VALIDATE_EMAIL) !== false) ?? '';
            $solicitudesDocente = $solicitudes->get($profesor->id, collect());
            $solicitud = $solicitudesDocente->first(function (Models\SolicitudDisponibilidad $registro) use ($estado, $fechaLimite) {
                if ($fechaLimite && $registro->fecha_limite?->toDateString() !== $fechaLimite) return false;

                return match ($estado) {
                    'respondieron' => $registro->respuestas->isNotEmpty(),
                    'sin_solicitud' => false,
                    default => ! array_key_exists($estado, self::ESTADOS_DISPONIBILIDAD)
                        || $this->estadoSolicitud($registro) === $estado,
                };
            });
            $rutAcceso = CuentasDocentes::rutParaAcceso($profesor->rut);
            $motivoEnvio = match (true) {
                ! $profesor->estado || (int) $profesor->universidad_id !== (int) $periodo?->universidad_id => 'Docente fuera del padrón activo del período.',
                $rutAcceso === null => 'Completa el RUT en la ficha docente.',
                $correo === '' => 'Completa un correo válido para enviar el acceso.',
                default => null,
            };
            $invitacion = $solicitud?->enviosAcceso->first(fn ($envio) => (int) $envio->profesor_id === $profesor->id
                && (int) $envio->periodo_academico_id === (int) $periodo?->id);
            $recordatoriosPrevistos = $motivoEnvio === null && $usuario && $invitacion
                && (int) $invitacion->user_id === $usuario->id
                && mb_strtolower(trim($invitacion->correo ?? '')) === mb_strtolower(trim($correo));

            return [
                'profesor' => $profesor,
                'correo' => $correo,
                'rut_acceso' => $rutAcceso,
                'seleccionable' => $motivoEnvio === null,
                'motivo_envio' => $motivoEnvio,
                'usuario' => $usuario,
                'envio' => $ultimosEnvios->get($profesor->id),
                'solicitud' => $solicitud,
                'estado_solicitud' => $solicitud ? $this->estadoSolicitud($solicitud) : null,
                'respuesta' => $solicitud?->respuestas->first(),
                'invitacion_confirmada' => $recordatoriosPrevistos ? $invitacion : null,
                'otras_solicitudes' => $solicitudesDocente->reject(fn ($anterior) => $anterior->is($solicitud)),
                'coincide_solicitud' => ! $filtraSolicitud || $solicitud !== null
                    || ($estado === 'sin_solicitud' && ! $fechaLimite && $solicitudesDocente->isEmpty()),
            ];
        });
        $resumen = [
            'enviados' => $filas->where('envio.estado', 'enviado')->count(),
            'ingresaron' => $filas->filter(fn ($fila) => $fila['usuario']?->primer_ingreso_en !== null)->count(),
            'pendientes' => $solicitudes->filter(fn ($historial) => $this->estadoSolicitud($historial->first()) === 'Pendiente')->count(),
            'respondieron' => $solicitudes->filter(fn ($historial) => $historial->contains(fn ($registro) => $registro->respuestas->isNotEmpty()))->count(),
        ];
        if ($q !== '') {
            $busqueda = Str::lower(Str::ascii($q));
            $rutBuscado = CuentasDocentes::rutParaAcceso($q);
            $filas = $filas->filter(fn ($fila) => ($rutBuscado !== null && $fila['rut_acceso'] === $rutBuscado)
                || Str::contains(Str::lower(Str::ascii(implode(' ', [
                $fila['profesor']->nombre, $fila['profesor']->apellido, $fila['profesor']->rut, $fila['rut_acceso'], $fila['correo'],
            ]))), $busqueda));
        }
        $filas = $filas->where('coincide_solicitud', true)->filter(fn ($fila) => match ($estado) {
            'sin_enviar' => ! $fila['envio'],
            'en_cola' => in_array($fila['envio']?->estado, ['pendiente', 'en_proceso'], true),
            'enviados' => $fila['envio']?->estado === 'enviado',
            'fallidos' => $fila['envio']?->estado === 'fallido',
            'ingresaron' => $fila['usuario']?->primer_ingreso_en !== null,
            'sin_ingreso' => $fila['usuario']?->primer_ingreso_en === null,
            default => true,
        })->values();
        $pagina = $datos['page'] ?? 1;
        $docentes = new LengthAwarePaginator($filas->forPage($pagina, 25)->values(), $filas->count(), 25, $pagina, [
            'path' => route('disponibilidad.index'),
            'query' => $datos + ['periodo_id' => $periodo?->id],
        ]);
        if ($pagina > $docentes->lastPage()) return redirect()->to($docentes->url($docentes->lastPage()));
        $seleccionables = $filas->where('seleccionable', true)->pluck('profesor.id')->all();
        $seleccionToken = (string) Str::uuid();
        $selecciones = collect($request->session()->get('selecciones_accesos_docentes', []))
            ->filter(fn ($seleccion) => $seleccion['creada'] >= now()->subMinutes(20)->timestamp)->take(-4)->all();
        $selecciones[$seleccionToken] = [
            'usuario_id' => $request->user()->id, 'periodo_id' => $periodo?->id,
            'profesores' => $seleccionables, 'sede_id' => $sedeId, 'creada' => now()->timestamp,
        ];
        $request->session()->put('selecciones_accesos_docentes', $selecciones);
        $historial = Models\EnvioAccesoDocente::where('periodo_academico_id', $periodo?->id)
            ->when($idsSede !== null, fn ($query) => $query->whereIn('profesor_id', $idsSede))
            ->with('profesor')
            ->latest('id')->paginate(15, ['*'], 'historial_page')->withQueryString();
        if ($historial->currentPage() > $historial->lastPage()) return redirect()->to($historial->url($historial->lastPage()));
        $correoDisponible = $envios->correoDisponible();

        $consulta = $solicitudConsulta ? $disponibilidad->datosDetalle($solicitudConsulta) : null;
        return view('disponibilidad.accesos', compact(
            'periodos', 'periodo', 'q', 'estado', 'resumen', 'docentes', 'historial',
            'seleccionables', 'seleccionToken', 'correoDisponible', 'fechaLimite', 'filtros', 'consulta',
            'sedes', 'sedeId', 'sedeSeleccionada', 'versionSede'
        ) + [
            'estadosSeguimiento' => self::ESTADOS_SEGUIMIENTO,
            'estadosDisponibilidad' => self::ESTADOS_DISPONIBILIDAD,
        ]);
    }

    public function enviar(Request $request, EnviosAccesosDocentes $envios)
    {
        $datos = $request->validate([
            'periodo_id' => ['required', 'integer', 'exists:periodos_academicos,id'],
            'sede_id' => ['nullable', 'integer', 'exists:sedes,id'],
            'profesores' => ['nullable', 'array', 'max:2000'],
            'profesores.*' => ['required', 'integer', 'distinct', 'exists:profesores,id'],
            'todos' => ['nullable', 'boolean'],
            'excluir' => ['nullable', 'array', 'max:2000'],
            'excluir.*' => ['required', 'integer', 'distinct'],
            'seleccion_token' => ['required_if:todos,1', 'nullable', 'uuid'],
            'lote' => ['required', 'uuid'],
            'confirmar_envio' => ['required', 'accepted'],
            'cantidad_confirmada' => ['required', 'integer', 'between:1,2000'],
            'fecha_limite' => ['required', 'date_format:Y-m-d', 'after_or_equal:today', 'before_or_equal:'.today()->addYear()->toDateString()],
        ], [
            'confirmar_envio.required' => 'Revisa la cantidad de docentes y confirma el envío antes de continuar.',
            'confirmar_envio.accepted' => 'Confirma el envío de las solicitudes antes de continuar.',
            'cantidad_confirmada.*' => 'Revisa la selección y confirma cuántas solicitudes deseas enviar.',
        ]);
        $periodo = Models\PeriodoAcademico::with('universidad')->findOrFail($datos['periodo_id']);
        if (! $periodo->estado || ! $periodo->universidad->estado) {
            throw ValidationException::withMessages(['periodo_id' => 'Selecciona un período de una institución activa.']);
        }
        $sedeId = filled($datos['sede_id'] ?? null) ? (int) $datos['sede_id'] : null;
        if ($sedeId && ! Models\Sede::whereKey($sedeId)->where('universidad_id', $periodo->universidad_id)->exists()) {
            throw ValidationException::withMessages(['sede_id' => 'Selecciona una sede de la institución del período.']);
        }
        $profesores = array_map('intval', $datos['profesores'] ?? []);
        if ($request->boolean('todos')) {
            $seleccion = $request->session()->get('selecciones_accesos_docentes.'.$datos['seleccion_token']);
            if (! is_array($seleccion) || (int) $seleccion['usuario_id'] !== (int) $request->user()->id
                || (int) $seleccion['periodo_id'] !== (int) $periodo->id
                || (int) ($seleccion['sede_id'] ?? 0) !== (int) $sedeId
                || $seleccion['creada'] < now()->subMinutes(20)->timestamp) {
                throw ValidationException::withMessages(['profesores' => 'La selección venció o corresponde a otro período o sede. Actualiza el listado y selecciona los destinatarios.']);
            }
            $profesores = array_values(array_diff($seleccion['profesores'], array_map('intval', $datos['excluir'] ?? [])));
        }
        if (! $profesores) {
            throw ValidationException::withMessages(['profesores' => 'Selecciona al menos un docente para enviar sus accesos.']);
        }
        $validos = Models\Profesor::whereIn('id', $profesores)->where('estado', true)
            ->where('universidad_id', $periodo->universidad_id)->count();
        if ($validos !== count($profesores)) {
            throw ValidationException::withMessages(['profesores' => 'Hay docentes inactivos o de otra institución en la selección. Actualiza el listado.']);
        }
        if ($sedeId && array_diff($profesores, app(DocentesPorSede::class)->ids($periodo, $sedeId))) {
            throw ValidationException::withMessages([
                'profesores' => 'Hay docentes que ya no tienen clases en la sede elegida. Actualiza el listado y confirma nuevamente.',
            ]);
        }
        if ((int) $datos['cantidad_confirmada'] !== count($profesores)) {
            throw ValidationException::withMessages([
                'cantidad_confirmada' => 'La cantidad de docentes cambió. Revisa los destinatarios y confirma nuevamente el envío.',
            ]);
        }
        $correoDisponible = $envios->correoDisponible();
        $resultado = $envios->encolar($periodo, $profesores, $request->user()->id, $request->ip(), $datos['lote'], $datos['fecha_limite']);
        $mensajes = [];
        if ($resultado['enviados']) {
            $mensajes[] = $resultado['enviados'].' '.($resultado['enviados'] === 1 ? 'correo enviado' : 'correos enviados').'.';
        }
        if ($resultado['pendientes']) {
            $cantidad = $resultado['pendientes'];
            $mensajes[] = $correoDisponible
                ? $cantidad.' '.($cantidad === 1 ? 'envío programado.' : 'envíos programados.').' Actualiza el seguimiento para consultar su entrega.'
                : $cantidad.' '.($cantidad === 1 ? 'envío pendiente' : 'envíos pendientes').' de configuración de correo.';
        }
        if ($resultado['fallidos']) $mensajes[] = $resultado['fallidos'].' no se pudieron enviar. Revisa el historial.';
        $mensaje = implode(' ', $mensajes);
        $respuesta = redirect()->route('disponibilidad.index', array_filter(['periodo_id' => $periodo->id, 'sede_id' => $sedeId]));
        if ($resultado['enviados'] || ($correoDisponible && $resultado['pendientes'])) return $respuesta->with('success', $mensaje);

        return $respuesta->with('aviso_accesos', $mensaje);
    }

    private function estadoSolicitud(Models\SolicitudDisponibilidad $solicitud): string
    {
        return $solicitud->estado === 'Pendiente' && $solicitud->fecha_limite?->copy()->endOfDay()->isPast()
            ? 'Vencida'
            : $solicitud->estado;
    }
}

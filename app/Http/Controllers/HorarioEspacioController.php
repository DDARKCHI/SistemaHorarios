<?php

namespace App\Http\Controllers;

use App\Models\BloqueHorario;
use App\Models\Dia;
use App\Models\Horario;
use App\Models\Laboratorio;
use App\Models\PeriodoAcademico;
use App\Models\ProfesorVersion;
use App\Models\Sala;
use App\Models\VersionPlanificacion;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class HorarioEspacioController extends Controller
{
    private const PARAMETROS = ['espacio', 'periodo_id', 'version_id', 'dia_id', 'vista'];

    public function index(Request $request)
    {
        $validador = Validator::make($request->query(), [
            'espacio' => ['bail', 'nullable', 'string', 'max:64', 'regex:/\A(sala|laboratorio):[1-9][0-9]*\z/'],
            'periodo_id' => ['bail', 'nullable', 'integer', 'min:1', Rule::exists('periodos_academicos', 'id')],
            'version_id' => ['bail', 'nullable', 'integer', 'min:1', Rule::exists('versiones_planificacion', 'id')],
            'dia_id' => ['bail', 'nullable', 'integer', 'min:1', Rule::exists('dias', 'id')],
            'vista' => ['bail', 'nullable', Rule::in(['dia', 'semana'])],
        ], [
            'espacio.*' => 'Selecciona una sala o un laboratorio válido.',
            'periodo_id.*' => 'Selecciona un período académico válido.',
            'version_id.*' => 'Selecciona una versión válida.',
            'dia_id.*' => 'Selecciona un día válido.',
            'vista.*' => 'Selecciona la vista diaria o semanal.',
        ]);

        if ($validador->fails()) {
            $datos = Arr::only($validador->valid(), self::PARAMETROS);
            if ($validador->errors()->has('periodo_id')) {
                unset($datos['version_id']);
            }

            return redirect()->route('salas.horario', $datos)->withErrors($validador);
        }

        $datos = Arr::only($validador->validated(), self::PARAMETROS);
        $espacios = $this->espacios();
        $espacioSeleccionado = $espacios->firstWhere('clave', $datos['espacio'] ?? null);

        if (! empty($datos['espacio']) && ! $espacioSeleccionado) {
            return $this->seleccionInvalida($datos, 'espacio', 'El espacio seleccionado ya no está registrado.');
        }

        $periodos = PeriodoAcademico::query()
            ->when($espacioSeleccionado, fn ($query) => $query
                ->where('universidad_id', $espacioSeleccionado['universidad_id']))
            ->orderByDesc('anio')->orderByDesc('numero_semestre')->orderByDesc('id')->get();

        if (! empty($datos['periodo_id']) && ! $periodos->contains('id', (int) $datos['periodo_id'])) {
            return $this->seleccionInvalida($datos, 'periodo_id', 'El período no pertenece a la institución del espacio seleccionado.');
        }

        $periodoSeleccionado = ! empty($datos['periodo_id'])
            ? $periodos->firstWhere('id', (int) $datos['periodo_id'])
            : $periodos->firstWhere('estado', true);
        $versiones = $periodoSeleccionado
            ? VersionPlanificacion::where('periodo_academico_id', $periodoSeleccionado->id)
                ->whereIn('tipo', ['semestral', 'anterior'])->orderByDesc('id')->get()
            : collect();

        if (! empty($datos['version_id']) && ! $versiones->contains('id', (int) $datos['version_id'])) {
            return $this->seleccionInvalida($datos, 'version_id', 'La versión no corresponde al período seleccionado. Vuelve a elegirla.');
        }

        $versionSeleccionada = ! empty($datos['version_id'])
            ? $versiones->firstWhere('id', (int) $datos['version_id'])
            : $versiones->first(fn ($version) => $version->tipo === 'semestral' && $version->es_actual);
        $horarios = collect();

        if ($espacioSeleccionado && $versionSeleccionada) {
            $horarios = Horario::query()
                ->where('estado', true)
                ->where('version_planificacion_id', $versionSeleccionada->id)
                ->whereHas('actividadSeccion.seccion', fn ($query) => $query
                    ->where('periodo_academico_id', $periodoSeleccionado->id))
                ->where(function ($query) use ($espacioSeleccionado) {
                    if ($espacioSeleccionado['tipo'] === 'laboratorio') {
                        $query->whereHas('asignacionesLaboratorios', fn ($asignacion) => $asignacion
                            ->where('estado', true)->where('laboratorio_id', $espacioSeleccionado['id']));
                    } else {
                        // La sala física incluye también las clases de sus laboratorios.
                        $query->whereHas('asignacionesSalas', fn ($asignacion) => $asignacion
                            ->where('estado', true)->where('sala_id', $espacioSeleccionado['id']))
                            ->orWhereHas('asignacionesLaboratorios', fn ($asignacion) => $asignacion
                                ->where('estado', true)
                                ->whereHas('laboratorio', fn ($laboratorio) => $laboratorio
                                    ->where('sala_id', $espacioSeleccionado['id'])));
                    }
                })
                ->with([
                    'dia', 'bloqueHorario', 'actividadSeccion.seccion.asignatura', 'grupoSeccion',
                    'profesores' => fn ($query) => $query->where('estado', true)->orderBy('id'),
                    'profesores.profesor:id,nombre,apellido',
                ])
                ->orderBy('id')->get();
        }

        $dias = Dia::query()->where(fn ($query) => $query->where('estado', true)
            ->orWhereIn('id', $horarios->pluck('dia_id'))
            ->when(! empty($datos['dia_id']), fn ($query) => $query->orWhere('id', $datos['dia_id'])))
            ->orderBy('numero')->orderBy('id')->get();
        $bloques = BloqueHorario::query()->where(fn ($query) => $query->where('estado', true)
            ->orWhereIn('id', $horarios->pluck('bloque_horario_id')))
            ->orderBy('hora_inicio')->orderBy('numero')->orderBy('id')->get();
        $diaSeleccionado = ! empty($datos['dia_id'])
            ? $dias->firstWhere('id', (int) $datos['dia_id'])
            : ($dias->firstWhere('estado', true) ?? $dias->first());
        $sesiones = $this->sesiones($horarios, $versionSeleccionada);
        $celdas = $sesiones->groupBy(fn ($sesion) => $sesion['dia_id'].'-'.$sesion['bloque_id']);
        $resumen = [
            'bloques' => $celdas->count(),
            'asignaturas' => $sesiones->pluck('asignatura_id')->filter()->unique()->count(),
            'coincidencias' => $celdas->filter(fn ($celda) => $celda->count() > 1)->count(),
        ];
        $vista = $datos['vista'] ?? null;
        $parametros = array_filter([
            'espacio' => $espacioSeleccionado['clave'] ?? null,
            'periodo_id' => $periodoSeleccionado?->id,
            'version_id' => $versionSeleccionada?->id,
            'dia_id' => $diaSeleccionado?->id,
            'vista' => $vista,
        ], fn ($valor) => $valor !== null && $valor !== '');

        return view('salas.horario', compact('espacios', 'espacioSeleccionado', 'periodos', 'periodoSeleccionado',
            'versiones', 'versionSeleccionada', 'dias', 'bloques', 'diaSeleccionado', 'sesiones', 'celdas', 'resumen', 'vista', 'parametros'));
    }

    private function espacios(): Collection
    {
        $columnasSala = ['id', 'universidad_id', 'sede_id', 'codigo', 'nombre', 'capacidad', 'edificio', 'piso', 'disponible', 'estado'];
        $salas = Sala::with('sede:id,nombre')->orderBy('codigo')->orderBy('id')->get($columnasSala)
            ->map(fn ($sala) => $this->datosEspacio($sala, $sala, 'sala'));
        $laboratorios = Laboratorio::with(['sala' => fn ($query) => $query->select($columnasSala)->with('sede:id,nombre')])
            ->orderBy('codigo')->orderBy('nombre')->orderBy('id')->get(['id', 'sala_id', 'codigo', 'nombre', 'capacidad', 'disponible', 'estado'])
            ->filter(fn ($laboratorio) => $laboratorio->sala !== null)
            ->map(fn ($laboratorio) => $this->datosEspacio($laboratorio, $laboratorio->sala, 'laboratorio'));

        return $salas->concat($laboratorios)->values();
    }

    private function datosEspacio(Sala|Laboratorio $modelo, Sala $sala, string $tipo): array
    {
        $codigo = $modelo->codigo ?: 'Sin código';
        $nombre = $modelo->nombre ?: 'Sin nombre registrado';
        $sede = $sala->sede?->nombre ?? 'Sin sede registrada';
        $etiqueta = $codigo.' — '.$nombre.($tipo === 'laboratorio' ? ' · Sala '.$sala->codigo : '').' · '.$sede;

        return [
            'clave' => $tipo.':'.$modelo->id, 'tipo' => $tipo, 'id' => $modelo->id,
            'etiqueta' => $etiqueta.($modelo->estado ? '' : ' (registro inactivo)'),
            'codigo' => $codigo, 'nombre' => $nombre, 'sede' => $sede,
            'edificio' => $sala->edificio, 'piso' => $sala->piso, 'capacidad' => $modelo->capacidad,
            'estado' => (bool) $modelo->estado, 'disponible' => (bool) $modelo->disponible,
            'sala_id' => $sala->id, 'sala_codigo' => $sala->codigo, 'universidad_id' => $sala->universidad_id,
        ];
    }

    private function sesiones(Collection $horarios, ?VersionPlanificacion $version): Collection
    {
        $profesoresIds = $horarios->flatMap(fn ($horario) => $horario->profesores->pluck('profesor_id'))->unique();
        $nombres = $version && $profesoresIds->isNotEmpty()
            ? ProfesorVersion::where('version_planificacion_id', $version->id)->whereIn('profesor_id', $profesoresIds)
                ->get(['profesor_id', 'nombre', 'apellido'])->keyBy('profesor_id')
            : collect();

        return $horarios->map(function ($horario) use ($nombres) {
            $seccion = $horario->actividadSeccion?->seccion;
            $asignatura = $seccion?->asignatura;
            $profesores = $horario->profesores->unique('profesor_id')->map(function ($participacion) use ($nombres) {
                $historico = $nombres->get($participacion->profesor_id);
                $nombre = trim(($historico?->nombre ?? '').' '.($historico?->apellido ?? ''));

                return $nombre !== '' ? $nombre : trim(($participacion->profesor?->nombre ?? '').' '.($participacion->profesor?->apellido ?? ''));
            })->filter()->values()->all();

            return [
                'id' => $horario->id, 'dia_id' => $horario->dia_id, 'bloque_id' => $horario->bloque_horario_id,
                'asignatura_id' => $asignatura?->id,
                'codigo_asignatura' => $asignatura?->codigo ?? 'Sin código',
                'nombre_asignatura' => $asignatura?->nombre ?? 'Asignatura no registrada',
                'actividad' => $horario->actividadSeccion?->tipo ?? 'Actividad sin definir',
                'seccion' => $seccion?->codigo ?: ($seccion?->nombre ?: 'Sin sección'),
                'grupo' => $horario->grupoSeccion?->codigo ?: ($horario->grupoSeccion?->nombre ?: 'Sin grupo'),
                'profesores' => $profesores, 'observaciones' => $horario->observaciones,
            ];
        })->values();
    }

    private function seleccionInvalida(array $datos, string $campo, string $mensaje)
    {
        unset($datos[$campo]);
        if ($campo === 'periodo_id') {
            unset($datos['version_id']);
        }

        return redirect()->route('salas.horario', $datos)->withErrors([$campo => $mensaje]);
    }
}

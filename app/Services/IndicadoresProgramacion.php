<?php

namespace App\Services;

use App\Models\ActividadSeccion;
use App\Models\Horario;
use App\Models\Laboratorio;
use App\Models\LaboratorioMovil;
use App\Models\PeriodoAcademico;
use App\Models\Sala;
use App\Models\VersionPlanificacion;
use Illuminate\Support\Facades\DB;

class IndicadoresProgramacion
{
    public function calcular(?PeriodoAcademico $periodo, ?VersionPlanificacion $version): array
    {
        $datos = [
            'horario' => $this->indicador('Carga horaria cubierta', 'Módulos requeridos cubiertos en cada grupo.', 'Cubiertos', 'Pendientes', 'Sin carga horaria definida.'),
            'docentes' => $this->indicador('Clases con docente', 'Módulos programados con docente asignado.', 'Con docente', 'Sin docente', 'No hay módulos programados.'),
            'espacios' => $this->indicador('Clases con espacio', 'Módulos programados con sala o laboratorio fijo asignado.', 'Con espacio', 'Sin espacio', 'No hay módulos programados.'),
            'salas' => $this->indicador('Salas utilizadas', 'Salas activas con clases en esta versión.', 'Con clases', 'Sin clases', 'No hay salas activas registradas.'),
            'laboratorios' => $this->indicador('Laboratorios utilizados', 'Laboratorios fijos activos con clases en esta versión.', 'Con clases', 'Sin clases', 'No hay laboratorios fijos activos registrados.'),
            'moviles' => $this->indicador('Laboratorios móviles', 'Laboratorios móviles activos asignados en esta versión.', 'Asignados', 'Sin asignaciones', 'No hay laboratorios móviles activos registrados.'),
        ];
        $pendientes = ['actividades_sin_grupos' => 0, 'grupos_sin_carga' => 0, 'modulos_sin_grupo' => 0, 'excedentes' => 0];
        if (! $periodo || ! $version || (int) $version->periodo_academico_id !== (int) $periodo->id || $version->tipo !== 'semestral') {
            return ['graficos' => $datos, 'pendientes' => $pendientes];
        }

        $universidad = $periodo->universidad_id;
        $modulos = Horario::query()->where('estado', true)->where('version_planificacion_id', $version->id)
            ->whereHas('actividadSeccion', fn ($query) => $query->where('estado', true)
                ->whereHas('seccion', fn ($query) => $query->where('periodo_academico_id', $periodo->id)->where('estado_activo', true)))
            ->where(fn ($query) => $query->whereNull('grupo_seccion_id')->orWhereHas('grupoSeccion', fn ($grupo) => $grupo
                ->where('estado_activo', true)->whereColumn('grupos_seccion.actividad_seccion_id', 'horarios.actividad_seccion_id')));
        $cantidad = (clone $modulos)->count();
        $porGrupo = (clone $modulos)->select('grupo_seccion_id')->selectRaw('COUNT(*) AS cantidad')->groupBy('grupo_seccion_id');
        $grupos = DB::table('grupos_seccion as g')->join('actividades_seccion as a', 'a.id', '=', 'g.actividad_seccion_id')
            ->join('secciones as s', 's.id', '=', 'a.seccion_id')
            ->leftJoinSub($porGrupo, 'm', 'm.grupo_seccion_id', '=', 'g.id')
            ->where('g.estado_activo', true)->where('a.estado', true)->where('s.estado_activo', true)
            ->where('s.periodo_academico_id', $periodo->id)
            ->get(['a.cantidad_horas', DB::raw('COALESCE(m.cantidad, 0) AS asignados')]);
        $requeridos = $cubiertos = 0;
        foreach ($grupos as $grupo) {
            $carga = max(0, (int) $grupo->cantidad_horas);
            $asignados = (int) $grupo->asignados;
            $requeridos += $carga;
            $cubiertos += min($carga, $asignados);
            $pendientes['excedentes'] += $carga > 0 ? max(0, $asignados - $carga) : 0;
            $pendientes['grupos_sin_carga'] += (int) ($carga === 0);
        }
        $pendientes['actividades_sin_grupos'] = ActividadSeccion::where('estado', true)
            ->whereHas('seccion', fn ($query) => $query->where('periodo_academico_id', $periodo->id)->where('estado_activo', true))
            ->whereDoesntHave('grupos', fn ($query) => $query->where('estado_activo', true))->count();
        $pendientes['modulos_sin_grupo'] = (clone $modulos)->whereNull('grupo_seccion_id')->count();
        $datos['horario'] = $this->medir($datos['horario'], $cubiertos, $requeridos);
        $conDocente = (clone $modulos)->whereHas('profesores', fn ($query) => $query->where('estado', true)
            ->whereHas('profesor', fn ($query) => $query->where('universidad_id', $universidad)))->count();
        $datos['docentes'] = $this->medir($datos['docentes'], $conDocente, $cantidad);
        $conEspacio = (clone $modulos)->where(fn ($query) => $query
            ->whereHas('asignacionesSalas', fn ($query) => $query->where('estado', true)
                ->whereHas('sala', fn ($query) => $query->where('universidad_id', $universidad)))
            ->orWhereHas('asignacionesLaboratorios', fn ($query) => $query->where('estado', true)
                ->whereHas('laboratorio.sala', fn ($query) => $query->where('universidad_id', $universidad))))->count();
        $datos['espacios'] = $this->medir($datos['espacios'], $conEspacio, $cantidad);

        $asignados = fn (string $tabla, string $columna) => DB::table($tabla)->where('estado', true)
            ->whereIn('horario_id', (clone $modulos)->select('horarios.id'))->select($columna);
        $salas = Sala::where('universidad_id', $universidad)->where('estado', true);
        $laboratorios = Laboratorio::where('estado', true)->whereIn('sala_id', (clone $salas)->select('id'));
        $salasConClases = (clone $salas)->where(fn ($query) => $query
            ->whereIn('id', $asignados('asignaciones_salas', 'sala_id'))
            ->orWhereIn('id', Laboratorio::whereIn('id', $asignados('asignaciones_laboratorios', 'laboratorio_id'))->select('sala_id')))->count();
        $datos['salas'] = $this->medir($datos['salas'], $salasConClases, (clone $salas)->count());
        $datos['laboratorios'] = $this->medir($datos['laboratorios'], (clone $laboratorios)
            ->whereIn('id', $asignados('asignaciones_laboratorios', 'laboratorio_id'))->count(), (clone $laboratorios)->count());
        $moviles = LaboratorioMovil::where('universidad_id', $universidad)->where('estado', true);
        $datos['moviles'] = $this->medir($datos['moviles'], (clone $moviles)
            ->whereIn('id', $asignados('asignaciones_laboratorios_moviles', 'laboratorio_movil_id'))->count(), (clone $moviles)->count());

        return ['graficos' => $datos, 'pendientes' => $pendientes];
    }

    private function indicador(string $titulo, string $descripcion, string $asignados, string $restantes, string $sinRegistros): array
    {
        return compact('titulo', 'descripcion') + ['asignados' => 0, 'total' => 0, 'restantes' => 0,
            'porcentaje' => null, 'etiqueta_asignados' => $asignados, 'etiqueta_restantes' => $restantes,
            'sin_registros' => $sinRegistros, 'motivo_sin_datos' => 'Sin programación semestral actual.'];
    }

    private function medir(array $indicador, int $asignados, int $total): array
    {
        $porcentaje = $total > 0 ? round(100 * $asignados / $total, 1) : null;
        if ($asignados < $total && $porcentaje === 100.0) $porcentaje = 99.9;

        return array_replace($indicador, compact('asignados', 'total', 'porcentaje'), [
            'restantes' => max(0, $total - $asignados), 'motivo_sin_datos' => $indicador['sin_registros'],
        ]);
    }
}

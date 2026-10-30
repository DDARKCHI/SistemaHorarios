<?php

namespace App\Services;

use App\Models\PeriodoAcademico;
use App\Models\VersionPlanificacion;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class FiltrosProgramacionAnterior
{
    public const ACADEMICOS = ['sede_id', 'carrera_id', 'semestre', 'seccion_numero', 'asignatura_id', 'profesor_id'];

    public const CAMPOS = ['periodo_id', 'version_id', ...self::ACADEMICOS];

    private const COLUMNAS = [
        'periodo_id' => 'v.periodo_academico_id', 'version_id' => 'v.id',
        'sede_id' => 'sd.id', 'carrera_id' => 'c.id', 'semestre' => 's.semestre',
        'seccion_numero' => 's.codigo', 'asignatura_id' => 's.asignatura_id', 'profesor_id' => 'p.id',
    ];

    public function contexto(array $entrada): array
    {
        $seleccion = array_replace(array_fill_keys(self::CAMPOS, null), Arr::only($entrada, self::CAMPOS));
        foreach ($seleccion as $campo => $valor) {
            $seleccion[$campo] = filled($valor) ? ($campo === 'seccion_numero' ? (string) $valor : (int) $valor) : null;
        }
        $periodos = PeriodoAcademico::orderByDesc('anio')->orderByDesc('numero_semestre')->orderByDesc('id')->get();
        $seleccion['periodo_id'] = filled($seleccion['periodo_id'])
            ? $periodos->firstWhere('id', $seleccion['periodo_id'])?->id : $periodos->first()?->id;
        $versiones = VersionPlanificacion::where('periodo_academico_id', $seleccion['periodo_id'])
            ->where('tipo', 'anterior')->orderByDesc('id')->get();
        $seleccion['version_id'] = $versiones->firstWhere('id', $seleccion['version_id'])?->id ?? $versiones->first()?->id;

        return $seleccion;
    }

    public function reconciliar(array $seleccion, ?string $cambiado, array $prioridad): array
    {
        if ($cambiado === 'periodo_id') {
            // Al cambiar de período, preferir una versión que conserve la combinación elegida.
            $version = $this->consulta($seleccion, ['version_id'])->orderByDesc('v.id')->value('v.id');
            $seleccion['version_id'] = $version ?? $seleccion['version_id'];
        }
        if (! $cambiado || $this->consulta($seleccion)->exists()) {
            return $seleccion;
        }

        $compatibles = array_replace($seleccion, array_fill_keys(self::ACADEMICOS, null));
        $orden = array_unique(array_merge([$cambiado], $prioridad, self::ACADEMICOS));
        foreach ($orden as $campo) {
            if (! in_array($campo, self::ACADEMICOS, true) || ! filled($seleccion[$campo])) {
                continue;
            }
            $candidato = array_replace($compatibles, [$campo => $seleccion[$campo]]);
            if ($this->consulta($candidato)->exists()) {
                $compatibles = $candidato;
            }
        }

        return $compatibles;
    }

    public function consulta(array $seleccion, array $excluir = []): Builder
    {
        $query = DB::table('horarios as h')
            ->join('versiones_planificacion as v', 'v.id', '=', 'h.version_planificacion_id')
            ->join('actividades_seccion as a', 'a.id', '=', 'h.actividad_seccion_id')
            ->join('secciones as s', function ($join) {
                $join->on('s.id', '=', 'a.seccion_id')->on('s.periodo_academico_id', '=', 'v.periodo_academico_id');
            })
            ->join('asignaturas as asig', 'asig.id', '=', 's.asignatura_id')
            ->leftJoin('secciones_carreras as sc', 'sc.seccion_id', '=', 's.id')
            ->leftJoin('carreras as c', 'c.id', '=', 'sc.carrera_id')
            ->leftJoin('carreras_sedes as cs', 'cs.carrera_id', '=', 'c.id')
            ->leftJoin('sedes as sd', 'sd.id', '=', 'cs.sede_id')
            ->leftJoin('horarios_profesores as hp', function ($join) {
                $join->on('hp.horario_id', '=', 'h.id')->where('hp.estado', true);
            })
            ->leftJoin('secciones_profesores as sp', function ($join) {
                $join->on('sp.seccion_id', '=', 's.id')->where('sp.estado', true)->whereNull('hp.id');
            })
            ->leftJoin('profesores as p', 'p.id', '=', DB::raw('COALESCE(hp.profesor_id, sp.profesor_id)'))
            ->where('h.estado', true)->where('v.tipo', 'anterior');

        foreach (self::COLUMNAS as $campo => $columna) {
            if (in_array($campo, $excluir, true)) {
                continue;
            }
            if (filled($seleccion[$campo] ?? null)) {
                $query->where($columna, $seleccion[$campo]);
            } elseif (in_array($campo, ['periodo_id', 'version_id'], true)) {
                $query->whereRaw('1 = 0');
            }
        }

        return $query;
    }

    public function opciones(array $seleccion): array
    {
        $hayAcademicos = collect(Arr::only($seleccion, self::ACADEMICOS))->contains(fn ($valor) => filled($valor));
        $periodos = PeriodoAcademico::query();
        $versiones = VersionPlanificacion::where('periodo_academico_id', $seleccion['periodo_id'])->where('tipo', 'anterior');
        if ($hayAcademicos) {
            $periodos->whereIn('id', $this->consulta($seleccion, ['periodo_id', 'version_id'])->select('v.periodo_academico_id'));
            $versiones->whereIn('id', $this->consulta($seleccion, ['version_id'])->select('v.id'));
        }

        // Cada selector respeta todos los demás, pero permite cambiar su propio valor.
        $sedes = $this->consulta($seleccion, ['sede_id'])->whereNotNull('sd.id')
            ->select('sd.id', 'sd.nombre')->distinct()->orderBy('sd.nombre')->get();
        $carreras = $this->consulta($seleccion, ['carrera_id'])->whereNotNull('c.id')
            ->select('c.id', 'c.nombre')->distinct()->orderBy('c.nombre')->get();
        $semestres = $this->consulta($seleccion, ['semestre'])->whereNotNull('s.semestre')
            ->select('s.semestre')->distinct()->orderBy('s.semestre')->pluck('semestre')->map(fn ($valor) => (int) $valor);
        $secciones = $this->consulta($seleccion, ['seccion_numero'])
            ->select('s.codigo')->distinct()->orderBy('s.codigo')->get();
        $asignaturas = $this->consulta($seleccion, ['asignatura_id'])
            ->select('asig.id', 'asig.codigo', 'asig.nombre')->distinct()->orderBy('asig.codigo')->get();
        $profesores = $this->consulta($seleccion, ['profesor_id'])->whereNotNull('p.id')
            ->leftJoin('profesores_versiones as pv', function ($join) {
                $join->on('pv.profesor_id', '=', 'p.id')->on('pv.version_planificacion_id', '=', 'v.id');
            })
            ->select('p.id')
            ->selectRaw('CASE WHEN pv.id IS NOT NULL THEN pv.nombre ELSE p.nombre END AS nombre')
            ->selectRaw('CASE WHEN pv.id IS NOT NULL THEN pv.apellido ELSE p.apellido END AS apellido')
            ->distinct()->get()->sortBy(fn ($profesor) => mb_strtoupper(trim($profesor->apellido.' '.$profesor->nombre)))->values();

        return [
            'periodos' => $periodos->orderByDesc('anio')->orderByDesc('numero_semestre')->orderByDesc('id')->get(),
            'versiones' => $versiones->orderByDesc('id')->get(),
            ...compact('sedes', 'carreras', 'semestres', 'secciones', 'asignaturas', 'profesores'),
        ];
    }
}

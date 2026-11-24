<?php

namespace App\Services;

use App\Models\PeriodoAcademico;
use App\Models\VersionPlanificacion;
use Illuminate\Support\Facades\DB;

class DocentesPorSede
{
    public function versionReferencia(PeriodoAcademico $periodo): ?VersionPlanificacion
    {
        $versiones = VersionPlanificacion::where('periodo_academico_id', $periodo->id);

        return (clone $versiones)->where('tipo', 'semestral')->where('es_actual', true)->latest('id')->first()
            ?? (clone $versiones)->where('tipo', 'anterior')->latest('id')->first();
    }

    public function ids(PeriodoAcademico $periodo, int $sedeId): array
    {
        $version = $this->versionReferencia($periodo);
        if (! $version) return [];

        // La sede corresponde al espacio de la clase. Una carrera puede impartirse en varias sedes.
        return DB::table('horarios as h')
            ->join('actividades_seccion as a', 'a.id', '=', 'h.actividad_seccion_id')
            ->join('secciones as s', 's.id', '=', 'a.seccion_id')
            ->leftJoin('horarios_profesores as hp', function ($join) {
                $join->on('hp.horario_id', '=', 'h.id')->where('hp.estado', true);
            })
            ->leftJoin('grupos_profesores as gp', function ($join) {
                $join->on('gp.grupo_seccion_id', '=', 'h.grupo_seccion_id')->where('gp.estado', true)->whereNull('hp.id');
            })
            ->leftJoin('secciones_profesores as sp', function ($join) {
                $join->on('sp.seccion_id', '=', 's.id')->where('sp.estado', true)->whereNull('hp.id')->whereNull('gp.id');
            })
            ->where('h.version_planificacion_id', $version->id)->where('h.estado', true)
            ->where('s.periodo_academico_id', $periodo->id)
            ->where(function ($query) use ($sedeId, $periodo) {
                $query->whereExists(function ($salas) use ($sedeId, $periodo) {
                    $salas->selectRaw('1')->from('asignaciones_salas as als')
                        ->join('salas as sa', 'sa.id', '=', 'als.sala_id')
                        ->whereColumn('als.horario_id', 'h.id')->where('als.estado', true)
                        ->where('sa.sede_id', $sedeId)->where('sa.universidad_id', $periodo->universidad_id);
                })->orWhereExists(function ($laboratorios) use ($sedeId, $periodo) {
                    $laboratorios->selectRaw('1')->from('asignaciones_laboratorios as al')
                        ->join('laboratorios as l', 'l.id', '=', 'al.laboratorio_id')
                        ->join('salas as sl', 'sl.id', '=', 'l.sala_id')
                        ->whereColumn('al.horario_id', 'h.id')->where('al.estado', true)
                        ->where('sl.sede_id', $sedeId)->where('sl.universidad_id', $periodo->universidad_id);
                });
            })
            ->selectRaw('COALESCE(hp.profesor_id, gp.profesor_id, sp.profesor_id) as profesor_id')
            ->distinct()->pluck('profesor_id')->filter()->map(fn ($id) => (int) $id)->unique()->values()->all();
    }
}

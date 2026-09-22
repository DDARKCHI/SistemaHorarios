<?php

namespace App\Services;

use App\Models\AsignacionLaboratorio;
use App\Models\AsignacionLaboratorioMovil;
use App\Models\AsignacionSala;
use App\Models\Horario;
use App\Models\HorarioProfesor;
use App\Models\ProfesorVersion;
use App\Models\VersionPlanificacion;
use Illuminate\Support\Facades\DB;

class ProgramacionSemestralService
{
    public function generar(
        int $versionAnteriorId,
        int $usuarioId
    ): VersionPlanificacion {
        return DB::transaction(function () use (
            $versionAnteriorId,
            $usuarioId
        ) {
            $versionAnterior = VersionPlanificacion::query()
                ->where('id', $versionAnteriorId)
                ->where('tipo', 'anterior')
                ->firstOrFail();

            $versionSemestral = VersionPlanificacion::create([
                'periodo_academico_id' => $versionAnterior->periodo_academico_id,
                'nombre' => 'Programación semestral',
                'version' => $this->siguienteVersion(
                    $versionAnterior->periodo_academico_id
                ),
                'tipo' => 'semestral',
                'descripcion' => 'Generada desde la versión V'
                    . number_format((float) $versionAnterior->version, 1)
                    . ' de la programación anterior.',
                'estado' => 'Borrador',
                'usuario_id' => $usuarioId,
                'es_actual' => true,
            ]);

            VersionPlanificacion::where(
                'periodo_academico_id',
                $versionAnterior->periodo_academico_id
            )
                ->where('tipo', 'semestral')
                ->where('id', '!=', $versionSemestral->id)
                ->update([
                    'es_actual' => false,
                ]);

            $this->copiarProfesoresVersion(
                $versionAnterior,
                $versionSemestral
            );

            $horarios = Horario::with([
                'profesores',
                'asignacionesSalas',
                'asignacionesLaboratorios',
                'asignacionesLaboratoriosMoviles',
            ])
                ->where(
                    'version_planificacion_id',
                    $versionAnterior->id
                )
                ->get();

            foreach ($horarios as $horarioAnterior) {
                $horarioNuevo = Horario::create([
                    'actividad_seccion_id' => $horarioAnterior->actividad_seccion_id,
                    'grupo_seccion_id' => $horarioAnterior->grupo_seccion_id,
                    'version_planificacion_id' => $versionSemestral->id,
                    'dia_id' => $horarioAnterior->dia_id,
                    'bloque_horario_id' => $horarioAnterior->bloque_horario_id,
                    'observaciones' => $horarioAnterior->observaciones,
                    'estado' => $horarioAnterior->estado,
                ]);

                $this->copiarProfesores(
                    $horarioAnterior,
                    $horarioNuevo
                );

                $this->copiarSalas(
                    $horarioAnterior,
                    $horarioNuevo
                );

                $this->copiarLaboratorios(
                    $horarioAnterior,
                    $horarioNuevo
                );

                $this->copiarLaboratoriosMoviles(
                    $horarioAnterior,
                    $horarioNuevo
                );
            }

            return $versionSemestral;
        });
    }

    private function copiarProfesoresVersion(
        VersionPlanificacion $versionAnterior,
        VersionPlanificacion $versionSemestral
    ): void {
        $profesores = ProfesorVersion::where(
            'version_planificacion_id',
            $versionAnterior->id
        )->get();

        foreach ($profesores as $profesor) {
            ProfesorVersion::create([
                'version_planificacion_id' => $versionSemestral->id,
                'profesor_id' => $profesor->profesor_id,
                'rut' => $profesor->rut,
                'nombre' => $profesor->nombre,
                'apellido' => $profesor->apellido,
                'correo_institucional' => $profesor->correo_institucional,
                'correo_personal' => $profesor->correo_personal,
                'tipo_contrato' => $profesor->tipo_contrato,
                'tipo_profesor' => $profesor->tipo_profesor,
                'unidad_academica' => $profesor->unidad_academica,
            ]);
        }
    }

    private function copiarProfesores(
        Horario $horarioAnterior,
        Horario $horarioNuevo
    ): void {
        foreach ($horarioAnterior->profesores as $asignacion) {
            HorarioProfesor::create([
                'horario_id' => $horarioNuevo->id,
                'profesor_id' => $asignacion->profesor_id,
                'tipo_participacion' => $asignacion->tipo_participacion,
                'es_principal' => $asignacion->es_principal,
                'observaciones' => $asignacion->observaciones,
                'estado' => $asignacion->estado,
            ]);
        }
    }

    private function copiarSalas(
        Horario $horarioAnterior,
        Horario $horarioNuevo
    ): void {
        foreach ($horarioAnterior->asignacionesSalas as $asignacion) {
            AsignacionSala::create([
                'horario_id' => $horarioNuevo->id,
                'sala_id' => $asignacion->sala_id,
                'capacidad_asignada' => $asignacion->capacidad_asignada,
                'observaciones' => $asignacion->observaciones,
                'es_principal' => $asignacion->es_principal,
                'estado' => $asignacion->estado,
            ]);
        }
    }

    private function copiarLaboratorios(
        Horario $horarioAnterior,
        Horario $horarioNuevo
    ): void {
        foreach ($horarioAnterior->asignacionesLaboratorios as $asignacion) {
            AsignacionLaboratorio::create([
                'horario_id' => $horarioNuevo->id,
                'laboratorio_id' => $asignacion->laboratorio_id,
                'capacidad_asignada' => $asignacion->capacidad_asignada,
                'observaciones' => $asignacion->observaciones,
                'es_principal' => $asignacion->es_principal,
                'estado' => $asignacion->estado,
            ]);
        }
    }

    private function copiarLaboratoriosMoviles(
        Horario $horarioAnterior,
        Horario $horarioNuevo
    ): void {
        foreach (
            $horarioAnterior->asignacionesLaboratoriosMoviles
            as $asignacion
        ) {
            AsignacionLaboratorioMovil::create([
                'horario_id' => $horarioNuevo->id,
                'laboratorio_movil_id' => $asignacion->laboratorio_movil_id,
                'capacidad_asignada' => $asignacion->capacidad_asignada,
                'observaciones' => $asignacion->observaciones,
                'es_principal' => $asignacion->es_principal,
                'estado' => $asignacion->estado,
            ]);
        }
    }

    private function siguienteVersion(int $periodoId): float
    {
        $ultima = VersionPlanificacion::where(
            'periodo_academico_id',
            $periodoId
        )
            ->where('tipo', 'semestral')
            ->orderByDesc('version')
            ->value('version');

        if ($ultima === null) {
            return 0.1;
        }

        return (round((float) $ultima * 10) + 1) / 10;
    }
}
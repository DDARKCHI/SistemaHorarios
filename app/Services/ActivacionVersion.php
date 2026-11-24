<?php

namespace App\Services;

use App\Models;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ActivacionVersion
{
    public function revisar(Models\VersionPlanificacion $version, string $accion = 'activar'): array
    {
        $version->load('periodoAcademico');
        $reporte = app(RevisionConflictosProgramacion::class)->revisar($version);
        $actuales = Models\VersionPlanificacion::where('periodo_academico_id', $version->periodo_academico_id)->where('tipo', 'semestral')->where('es_actual', true)->orderBy('id')->get();
        $horarios = Models\Horario::where('version_planificacion_id', $version->id)->orderBy('id')->with([
            'profesores' => fn ($q) => $q->orderBy('id'), 'asignacionesSalas' => fn ($q) => $q->orderBy('id'),
            'asignacionesLaboratorios' => fn ($q) => $q->orderBy('id'), 'asignacionesLaboratoriosMoviles' => fn ($q) => $q->orderBy('id'),
        ])->get();
        $bloqueos = [];
        if ($version->tipo !== 'semestral') $bloqueos[] = 'Solo puedes activar una versión semestral.';
        if (! $version->periodoAcademico->estado) $bloqueos[] = 'El período académico está inactivo.';
        if ($accion === 'activar' && $version->estado !== 'Borrador') $bloqueos[] = 'Solo puedes activar una versión en estado Borrador.';
        if ($accion === 'activar' && $version->es_actual) $bloqueos[] = 'Esta versión ya es la programación actual.';
        if (! $reporte['modulos']) $bloqueos[] = 'La versión no contiene módulos activos.';
        foreach (['conflictos' => 'topes horarios', 'asignaciones' => 'asignaciones incompletas', 'capacidad' => 'capacidades por revisar',
            'disponibilidad' => 'disponibilidades por revisar', 'datos' => 'datos por revisar', 'recursos' => 'recursos incompatibles', 'carga_observaciones' => 'observaciones de carga'] as $clave => $etiqueta) {
            if ($reporte[$clave]) $bloqueos[] = $reporte[$clave].' '.$etiqueta.'.';
        }
        $firma = hash('sha256', json_encode([$version->getAttributes(), $version->periodoAcademico->getAttributes(), $horarios->toArray(),
            $actuales->map(fn ($v) => $v->getAttributes())->all(), $reporte], JSON_THROW_ON_ERROR));
        return compact('reporte', 'actuales', 'bloqueos', 'firma');
    }

    public function activar(Models\VersionPlanificacion $referencia, string $firma, int $usuarioId, ?string $ip): Models\VersionPlanificacion
    {
        return DB::transaction(function () use ($referencia, $firma, $usuarioId, $ip) {
            $periodo = Models\PeriodoAcademico::lockForUpdate()->findOrFail($referencia->periodo_academico_id);
            $versiones = Models\VersionPlanificacion::where('periodo_academico_id', $periodo->id)->orderBy('id')->lockForUpdate()->get();
            $version = $versiones->firstWhere('id', $referencia->id);
            abort_unless($version, 404);
            $this->bloquearDatos($periodo, $version);
            $revision = $this->revisar($version);
            if (! hash_equals($revision['firma'], $firma)) throw ValidationException::withMessages(['version' => 'La versión o la programación actual cambiaron. Revisa de nuevo antes de activar.']);
            if ($revision['bloqueos']) throw ValidationException::withMessages(['version' => 'No se puede activar esta versión: '.implode(' ', $revision['bloqueos'])]);
            $antes = $revision['actuales']->pluck('id')->all();
            Models\VersionPlanificacion::where('periodo_academico_id', $periodo->id)->where('tipo', 'semestral')->where('es_actual', true)->update(['es_actual' => false]);
            $version->update(['es_actual' => true]);
            DB::table('historial_acciones')->insert(['usuario_id' => $usuarioId, 'accion' => 'Activar versión semestral', 'modulo' => 'Programación semestral',
                'tabla_afectada' => 'versiones_planificacion', 'registro_id' => $version->id, 'descripcion' => 'Versión activada tras revisar carga, conflictos, disponibilidad y recursos. Se conservan las versiones anteriores.',
                'datos' => json_encode(['versiones_anteriores' => $antes, 'version_actual' => $version->id, 'firma_revision' => $firma, 'modulos' => $revision['reporte']['modulos']], JSON_THROW_ON_ERROR),
                'direccion_ip' => $ip, 'created_at' => now(), 'updated_at' => now()]);
            return $version;
        }, 3);
    }

    public function bloquearDatos(Models\PeriodoAcademico $periodo, Models\VersionPlanificacion $version): void
    {
            Models\Profesor::where('universidad_id', $periodo->universidad_id)->orderBy('id')->lockForUpdate()->get();
            Models\Dia::orderBy('id')->lockForUpdate()->get();
            Models\BloqueHorario::orderBy('id')->lockForUpdate()->get();
            Models\TipoSala::orderBy('id')->lockForUpdate()->get();
            Models\Sala::where('universidad_id', $periodo->universidad_id)->orderBy('id')->lockForUpdate()->get();
            Models\Laboratorio::whereHas('sala', fn ($q) => $q->where('universidad_id', $periodo->universidad_id))->orderBy('id')->lockForUpdate()->get();
            Models\LaboratorioMovil::where('universidad_id', $periodo->universidad_id)->orderBy('id')->lockForUpdate()->get();
            $equipos = Models\Equipamiento::where('universidad_id', $periodo->universidad_id)->orderBy('id')->lockForUpdate()->get();
            Models\EquipamientoEspacio::whereIn('equipamiento_id', $equipos->pluck('id'))->orderBy('id')->lockForUpdate()->get();
            Models\GrupoSeccion::whereHas('actividadSeccion.seccion', fn ($q) => $q->where('periodo_academico_id', $periodo->id))->orderBy('id')->lockForUpdate()->get();
            Models\ActividadSeccion::whereHas('seccion', fn ($q) => $q->where('periodo_academico_id', $periodo->id))->orderBy('id')->lockForUpdate()->get();
            Models\Seccion::where('periodo_academico_id', $periodo->id)->orderBy('id')->lockForUpdate()->get();
            Models\RequerimientoActividad::whereHas('actividadSeccion.seccion', fn ($q) => $q->where('periodo_academico_id', $periodo->id))->orderBy('id')->lockForUpdate()->get();
            Models\Horario::where('version_planificacion_id', $version->id)->orderBy('id')->lockForUpdate()->get();
    }
}

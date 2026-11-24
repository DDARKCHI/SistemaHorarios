<?php

namespace App\Services;

use App\Models;
use App\Support\IntervalosHorario;
use Illuminate\Validation\ValidationException;

class AlternativasCambioHorario
{
    public function preparar(Models\Horario $referencia, string $espacio = 'actual'): array
    {
        $horario = Models\Horario::with($this->relaciones())->findOrFail($referencia->id);
        $version = $horario->versionPlanificacion;
        $periodo = $version->periodoAcademico;
        $actividad = $horario->actividadSeccion;
        $grupo = $horario->grupoSeccion;
        if (! $periodo->estado || $version->tipo !== 'semestral' || ! $version->es_actual || $version->estado !== 'Borrador') $this->rechazar('Los cambios se solicitan sobre la versión semestral actual en borrador. Crea un borrador para ajustar una publicación.');
        if (! $horario->estado || ! $actividad?->estado || ! $grupo?->estado_activo || ! $actividad->seccion?->estado_activo
            || (int) $grupo->actividad_seccion_id !== (int) $actividad->id || (int) $actividad->seccion->periodo_academico_id !== (int) $periodo->id) $this->rechazar('El horario, grupo y actividad deben estar activos en este período.');
        $docentes = $this->docentes($horario);
        if ($docentes->isEmpty() || $docentes->contains(fn ($d) => ! $d->profesor?->estado || (int) $d->profesor->universidad_id !== (int) $periodo->universidad_id)) $this->rechazar('La clase necesita docentes activos de la institución antes de solicitar un cambio.');
        $intervalo = IntervalosHorario::deBloque($horario->bloqueHorario);
        if (! $intervalo) $this->rechazar('Corrige el intervalo de la clase antes de buscar alternativas.');
        $revision = (new RevisionAsignacionesProgramacion)->revisar($horario, $docentes);
        if ($revision['asignaciones'] || $revision['capacidad']) $this->rechazar('Completa las asignaciones y revisa la capacidad de esta clase antes de solicitar un cambio.');
        $inventario = Models\Equipamiento::where('universidad_id', $periodo->universidad_id)->with('ubicaciones')->get()->keyBy('id');
        if ((new RevisionRecursosProgramacion((int) $periodo->universidad_id, new RevisionEquipamientoProgramacion($inventario)))->revisar($horario)) $this->rechazar('Los recursos de esta clase tienen condiciones por revisar. Corrígelas antes de solicitar el cambio.');
        $disponibilidades = Models\DisponibilidadProfesor::where('periodo_academico_id', $periodo->id)->whereIn('profesor_id', $docentes->pluck('profesor_id'))->where('estado', true)->with('bloqueHorario')->get()->groupBy('profesor_id');
        $sinDeclaracion = $docentes->filter(fn ($d) => ! $disponibilidades->has($d->profesor_id))->pluck('profesor_id')->all();
        $destino = app(EspaciosCambioHorario::class)->preparar($horario, $docentes, $inventario, $espacio);
        $propios = $this->recursos($destino['candidato']);
        $ocupados = [];
        $otros = Models\Horario::where('version_planificacion_id', $version->id)->where('estado', true)->whereKeyNot($horario->id)->with($this->relaciones())->get();
        foreach ($otros as $otro) {
            if (! array_intersect($propios, $this->recursos($otro))) continue;
            $ocupados[$otro->dia_id][] = IntervalosHorario::deBloque($otro->bloqueHorario);
        }
        $opciones = [];
        // La clave única de horarios también incluye registros inactivos del mismo grupo.
        $reservadas = Models\Horario::where('version_planificacion_id', $version->id)->where('actividad_seccion_id', $horario->actividad_seccion_id)
            ->where('grupo_seccion_id', $horario->grupo_seccion_id)->whereKeyNot($horario->id)->get(['dia_id', 'bloque_horario_id'])
            ->mapWithKeys(fn ($h) => [$h->dia_id.':'.$h->bloque_horario_id => true])->all();
        $dias = Models\Dia::where('estado', true)->orderBy('numero')->get();
        $bloques = Models\BloqueHorario::where('estado', true)->orderBy('hora_inicio')->get();
        foreach ($dias as $dia) foreach ($bloques as $bloque) {
            if (isset($reservadas[$dia->id.':'.$bloque->id])) continue;
            if ($espacio === 'actual' && (int) $dia->id === (int) $horario->dia_id && (int) $bloque->id === (int) $horario->bloque_horario_id) continue;
            $rango = IntervalosHorario::deBloque($bloque);
            if ($espacio === 'actual' && (int) $dia->id === (int) $horario->dia_id && $rango === $intervalo) continue;
            if (! $rango || $rango[1] - $rango[0] !== $intervalo[1] - $intervalo[0] || $bloque->es_almuerzo || IntervalosHorario::seSuperponen($rango, [13 * 3600, 14 * 3600])) continue;
            $apta = true;
            foreach ($ocupados[$dia->id] ?? [] as $ocupado) if (! $ocupado || IntervalosHorario::seSuperponen($rango, $ocupado)) { $apta = false; break; }
            if (! $apta) continue;
            foreach ($docentes as $docente) {
                $declaradas = $disponibilidades->get($docente->profesor_id, collect());
                if ($declaradas->isNotEmpty() && IntervalosHorario::coberturaDisponibilidad($rango, $declaradas->where('dia_id', $dia->id)) !== 'cubierto') { $apta = false; break; }
            }
            if ($apta) $opciones[$dia->id.':'.$bloque->id] = ['dia_id' => $dia->id, 'bloque_horario_id' => $bloque->id,
                'nombre' => $dia->nombre.' · Módulo '.$bloque->numero.' · '.substr($bloque->hora_inicio, 0, 5).'–'.substr($bloque->hora_fin, 0, 5),
                'hora_inicio' => $bloque->hora_inicio, 'hora_fin' => $bloque->hora_fin, 'dia_nombre' => $dia->nombre];
        }
        if ($espacio !== 'actual') foreach ($opciones as &$opcion) { $opcion['nombre'] .= ' · '.$destino['destino']['nombre']; $opcion['destino'] = $destino['destino']; }
        unset($opcion);
        $origen = ['horario' => $horario->getAttributes(), 'docentes' => $docentes->map(fn ($d) => $d->only(['profesor_id', 'tipo_participacion', 'es_principal', 'observaciones']))->sortBy('profesor_id')->values()->all(),
            'asignaciones' => collect(['asignacionesSalas', 'asignacionesLaboratorios', 'asignacionesLaboratoriosMoviles'])->mapWithKeys(fn ($r) => [$r => $horario->$r->map(fn ($a) => $a->getAttributes())->sortBy('id')->values()->all()])->all(),
            'bloque' => $horario->bloqueHorario->getAttributes()];
        $firma = hash('sha256', json_encode($origen, JSON_THROW_ON_ERROR));
        if ($espacio !== 'actual') $firma = hash('sha256', $firma.$destino['destino']['firma']);
        $resumen = ($actividad->seccion->asignatura?->codigo ?? '').' · '.$actividad->seccion->asignatura?->nombre.' · Sección '.$actividad->seccion->codigo.' · '.$actividad->tipo.' · '.$grupo->codigo;
        $actual = $horario->dia?->nombre.' · '.substr($horario->bloqueHorario->hora_inicio, 0, 5).'–'.substr($horario->bloqueHorario->hora_fin, 0, 5);
        $espaciosActuales = $horario->asignacionesSalas->map(fn ($a) => $a->sala?->codigo)->concat($horario->asignacionesLaboratorios->map(fn ($a) => $a->laboratorio?->nombre.' ('.$a->laboratorio?->sala?->codigo.')'))
            ->concat($horario->asignacionesLaboratoriosMoviles->map(fn ($a) => $a->laboratorioMovil?->nombre))->filter()->unique()->implode(' / ');
        $actual .= ' · '.$espaciosActuales;
        return compact('horario', 'version', 'periodo', 'docentes', 'opciones', 'sinDeclaracion', 'firma', 'resumen', 'actual') + $destino;
    }

    public function docentes(Models\Horario $horario)
    {
        $docentes = $horario->profesores;
        if ($docentes->isEmpty()) $docentes = $horario->grupoSeccion?->profesores ?? collect();
        if ($docentes->isEmpty()) $docentes = $horario->actividadSeccion?->seccion?->profesores ?? collect();
        return $docentes->unique('profesor_id')->values();
    }

    private function recursos(Models\Horario $horario): array
    {
        $recursos = ['curso:'.$horario->actividadSeccion?->seccion_id];
        foreach ($this->docentes($horario) as $d) $recursos[] = 'profesor:'.$d->profesor_id;
        foreach ($horario->asignacionesSalas as $a) $recursos[] = 'sala:'.$a->sala_id;
        foreach ($horario->asignacionesLaboratorios as $a) { $recursos[] = 'laboratorio:'.$a->laboratorio_id; $recursos[] = 'sala:'.$a->laboratorio?->sala_id; }
        foreach ($horario->asignacionesLaboratoriosMoviles as $a) $recursos[] = 'movil:'.$a->laboratorio_movil_id;
        return $recursos;
    }

    private function relaciones(): array
    {
        return ['dia', 'bloqueHorario', 'versionPlanificacion.periodoAcademico', 'actividadSeccion.seccion.asignatura', 'grupoSeccion',
            'actividadSeccion.requerimientos' => fn ($q) => $q->where('estado', true)->where('obligatorio', true), 'actividadSeccion.requerimientos.tipoSala', 'actividadSeccion.requerimientos.equipamiento',
            'profesores' => fn ($q) => $q->where('estado', true)->orderBy('id'), 'profesores.profesor',
            'grupoSeccion.profesores' => fn ($q) => $q->where('estado', true)->orderBy('id'), 'grupoSeccion.profesores.profesor',
            'actividadSeccion.seccion.profesores' => fn ($q) => $q->where('estado', true)->orderBy('id'), 'actividadSeccion.seccion.profesores.profesor',
            'asignacionesSalas' => fn ($q) => $q->where('estado', true)->orderBy('id'), 'asignacionesSalas.sala.tipoSala',
            'asignacionesLaboratorios' => fn ($q) => $q->where('estado', true)->orderBy('id'), 'asignacionesLaboratorios.laboratorio.sala.tipoSala',
            'asignacionesLaboratoriosMoviles' => fn ($q) => $q->where('estado', true)->orderBy('id'), 'asignacionesLaboratoriosMoviles.laboratorioMovil'];
    }
    private function rechazar(string $mensaje): never { throw ValidationException::withMessages(['cambio' => $mensaje]); }
}

<?php

namespace App\Services;

use App\Models;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CierreProgramacion
{
    public function revisar(Models\VersionPlanificacion $version): array
    {
        $revision = app(ActivacionVersion::class)->revisar($version, 'cierre');
        $evidencia = $this->evidencia($version);
        $etapa = $version->estado === 'Aprobado' ? 'publicar' : 'aprobar';
        $bloqueos = $revision['bloqueos'];
        if (! $version->es_actual) $bloqueos[] = 'Activa esta versión antes de aprobarla o publicarla.';
        if (! in_array($version->estado, ['Borrador', 'Aprobado'], true)) $bloqueos[] = 'Esta versión ya está publicada o no admite este paso.';
        $aprobacion = Models\CierreProgramacion::where('version_planificacion_id', $version->id)->where('etapa', 'Aprobado')->first();
        if ($version->estado === 'Aprobado' && (! $aprobacion || ! hash_equals($aprobacion->firma, $evidencia['firma']))) {
            $bloqueos[] = 'Los datos cambiaron desde la aprobación. Conserva esta versión como antecedente y crea un borrador nuevo para revisar los cambios.';
        }
        $firma = hash('sha256', $revision['firma'].$evidencia['firma']);
        $cierres = Models\CierreProgramacion::where('version_planificacion_id', $version->id)->with('usuario')->orderBy('id')->get();
        return ['reporte' => $revision['reporte'], 'bloqueos' => $bloqueos, 'firma' => $firma, 'etapa' => $etapa, 'cierres' => $cierres];
    }

    public function cambiar(Models\VersionPlanificacion $referencia, string $etapa, string $firma, int $usuarioId, ?string $ip): Models\VersionPlanificacion
    {
        return DB::transaction(function () use ($referencia, $etapa, $firma, $usuarioId, $ip) {
            $periodo = Models\PeriodoAcademico::lockForUpdate()->findOrFail($referencia->periodo_academico_id);
            $version = Models\VersionPlanificacion::where('periodo_academico_id', $periodo->id)->whereKey($referencia->id)->lockForUpdate()->firstOrFail();
            app(ActivacionVersion::class)->bloquearDatos($periodo, $version);
            $revision = $this->revisar($version);
            if ($etapa !== $revision['etapa'] || ! hash_equals($revision['firma'], $firma)) $this->rechazar('La versión cambió desde la revisión. Actualiza la página antes de continuar.');
            if ($revision['bloqueos']) $this->rechazar(implode(' ', $revision['bloqueos']));
            if ($etapa === 'aprobar') {
                // Fijar los docentes inferidos evita que cambios posteriores del grupo alteren la versión cerrada.
                $horarios = Models\Horario::where('version_planificacion_id', $version->id)->where('estado', true)->with([
                    'profesores' => fn ($q) => $q->where('estado', true), 'grupoSeccion.profesores' => fn ($q) => $q->where('estado', true),
                    'actividadSeccion.seccion.profesores' => fn ($q) => $q->where('estado', true),
                ])->get();
                foreach ($horarios as $h) {
                    if ($h->profesores->isNotEmpty()) continue;
                    $docentes = $h->grupoSeccion?->profesores ?? collect();
                    if ($docentes->isEmpty()) $docentes = $h->actividadSeccion->seccion->profesores;
                    foreach ($docentes->unique('profesor_id') as $d) Models\HorarioProfesor::updateOrCreate(['horario_id' => $h->id, 'profesor_id' => $d->profesor_id],
                        ['tipo_participacion' => $d->tipo_participacion, 'es_principal' => $d->es_principal, 'observaciones' => $d->observaciones, 'estado' => true]);
                }
            }
            $evidencia = $this->evidencia($version);
            $estado = $etapa === 'aprobar' ? 'Aprobado' : 'Publicado';
            Models\CierreProgramacion::create(['version_planificacion_id' => $version->id, 'etapa' => $estado, 'usuario_id' => $usuarioId,
                'firma' => $evidencia['firma'], 'datos' => $evidencia['datos']]);
            $version->update(['estado' => $estado, 'fecha_publicacion' => $estado === 'Publicado' ? now() : null]);
            DB::table('historial_acciones')->insert(['usuario_id' => $usuarioId, 'accion' => $estado === 'Publicado' ? 'Publicar programación' : 'Aprobar programación',
                'modulo' => 'Programación semestral', 'tabla_afectada' => 'versiones_planificacion', 'registro_id' => $version->id,
                'descripcion' => 'Cierre de versión con revisión global y copia de consulta conservada.',
                'datos' => json_encode(['estado' => $estado, 'firma' => $evidencia['firma']], JSON_THROW_ON_ERROR),
                'direccion_ip' => $ip, 'created_at' => now(), 'updated_at' => now()]);
            return $version;
        }, 3);
    }

    private function evidencia(Models\VersionPlanificacion $version): array
    {
        $horarios = Models\Horario::where('version_planificacion_id', $version->id)->where('estado', true)->orderBy('id')->with([
            'dia', 'bloqueHorario', 'grupoSeccion', 'actividadSeccion.seccion.asignatura', 'actividadSeccion.seccion.carreras',
            'actividadSeccion.requerimientos' => fn ($q) => $q->orderBy('id'),
            'profesores' => fn ($q) => $q->where('estado', true)->orderBy('id'), 'profesores.profesor',
            'grupoSeccion.profesores' => fn ($q) => $q->where('estado', true)->orderBy('id'), 'grupoSeccion.profesores.profesor',
            'actividadSeccion.seccion.profesores' => fn ($q) => $q->where('estado', true)->orderBy('id'), 'actividadSeccion.seccion.profesores.profesor',
            'asignacionesSalas' => fn ($q) => $q->where('estado', true)->orderBy('id'), 'asignacionesSalas.sala.sede',
            'asignacionesLaboratorios' => fn ($q) => $q->where('estado', true)->orderBy('id'), 'asignacionesLaboratorios.laboratorio.sala.sede',
            'asignacionesLaboratoriosMoviles' => fn ($q) => $q->where('estado', true)->orderBy('id'), 'asignacionesLaboratoriosMoviles.laboratorioMovil.sede',
        ])->get();
        $filas = [];
        $firmaHorarios = [];
        foreach ($horarios as $h) {
            $docentes = $h->profesores;
            if ($docentes->isEmpty()) $docentes = $h->grupoSeccion?->profesores ?? collect();
            if ($docentes->isEmpty()) $docentes = $h->actividadSeccion->seccion->profesores;
            $s = $h->actividadSeccion->seccion;
            $espacios = $h->asignacionesSalas->map(fn ($a) => $a->sala?->codigo)
                ->concat($h->asignacionesLaboratorios->map(fn ($a) => $a->laboratorio?->nombre.' ('.$a->laboratorio?->sala?->codigo.')'))
                ->concat($h->asignacionesLaboratoriosMoviles->map(fn ($a) => $a->laboratorioMovil?->nombre))->filter()->unique()->implode(' / ');
            $sedes = $h->asignacionesSalas->map(fn ($a) => $a->sala?->sede?->nombre)->concat($h->asignacionesLaboratorios->map(fn ($a) => $a->laboratorio?->sala?->sede?->nombre))->concat($h->asignacionesLaboratoriosMoviles->map(fn ($a) => $a->laboratorioMovil?->sede?->nombre))->filter()->unique()->implode(' / ');
            $codigos = $h->asignacionesSalas->map(fn ($a) => $a->sala?->codigo)->concat($h->asignacionesLaboratorios->map(fn ($a) => $a->laboratorio?->sala?->codigo))->concat($h->asignacionesLaboratoriosMoviles->map(fn ($a) => $a->laboratorioMovil?->codigo))->filter()->unique()->implode(' / ');
            $filas[] = ['codigo_espacios' => $codigos, 'carreras' => $s->carreras->pluck('nombre')->sort()->implode(' / '), 'nivel' => $s->semestre, 'sedes' => $sedes, 'curso' => $s->asignatura?->codigo.' · '.$s->asignatura?->nombre, 'seccion' => $s->codigo, 'actividad' => $h->actividadSeccion->tipo,
                'grupo' => $h->grupoSeccion?->codigo, 'dia' => $h->dia?->nombre, 'hora' => substr($h->bloqueHorario?->hora_inicio ?? '', 0, 5).'–'.substr($h->bloqueHorario?->hora_fin ?? '', 0, 5),
                'modulo' => $h->bloqueHorario?->numero, 'espacios' => $espacios, 'docentes' => $docentes->map(fn ($d) => $d->profesor?->apellido.', '.$d->profesor?->nombre)->unique()->implode(' / ')];
            $datos = $h->toArray();
            // La versión cerrada utiliza docentes del módulo; no depende de cambios de plantilla del grupo o sección.
            unset($datos['grupo_seccion']['profesores'], $datos['actividad_seccion']['seccion']['profesores']);
            $firmaHorarios[] = $datos;
        }
        $datos = ['periodo' => $version->periodoAcademico->nombre, 'version' => (string) $version->version, 'filas' => $filas];
        $docentesIds = $horarios->flatMap(fn ($h) => $h->profesores->pluck('profesor_id')->concat($h->grupoSeccion?->profesores?->pluck('profesor_id') ?? collect())
            ->concat($h->actividadSeccion->seccion->profesores->pluck('profesor_id')))->unique();
        $disponibilidades = Models\DisponibilidadProfesor::where('periodo_academico_id', $version->periodo_academico_id)->whereIn('profesor_id', $docentesIds)->where('estado', true)->orderBy('id')->get()->toArray();
        $inventario = Models\Equipamiento::where('universidad_id', $version->periodoAcademico->universidad_id)->orderBy('id')->with(['ubicaciones' => fn ($q) => $q->orderBy('id')])->get()->toArray();
        return ['firma' => hash('sha256', json_encode([$firmaHorarios, $datos, $disponibilidades, $inventario], JSON_THROW_ON_ERROR)), 'datos' => $datos];
    }

    private function rechazar(string $mensaje): never { throw ValidationException::withMessages(['cierre' => $mensaje]); }
}

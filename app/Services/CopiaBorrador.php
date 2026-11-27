<?php

namespace App\Services;

use App\Models;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CopiaBorrador
{
    public function revisar(Models\VersionPlanificacion $version): array
    {
        $revision = app(ActivacionVersion::class)->revisar($version, 'copia');
        $bloqueos = [];
        if ($version->tipo !== 'semestral') $bloqueos[] = 'Selecciona una versión semestral.';
        if (! $version->periodoAcademico->estado) $bloqueos[] = 'El período debe estar activo para preparar ajustes.';
        if (! in_array($version->estado, ['Borrador', 'Aprobado', 'Publicado'], true)) $bloqueos[] = 'El estado de esta versión no admite crear una copia.';
        if (! $revision['reporte']['modulos']) $bloqueos[] = 'La versión no tiene módulos activos para copiar.';
        $docentes = Models\Horario::where('version_planificacion_id', $version->id)->where('estado', true)->orderBy('id')->with([
            'grupoSeccion.profesores' => fn ($q) => $q->orderBy('id'), 'actividadSeccion.seccion.profesores' => fn ($q) => $q->orderBy('id'),
        ])->get()->toArray();
        $confirmaciones = DB::table('confirmaciones_grupo_programacion')->where('version_planificacion_id', $version->id)
            ->orderBy('grupo_seccion_id')->get(['grupo_seccion_id', 'profesor_id', 'usuario_id']);

        return ['firma' => hash('sha256', $revision['firma'].json_encode([$docentes, $confirmaciones], JSON_THROW_ON_ERROR)),
            'bloqueos' => $bloqueos, 'reporte' => $revision['reporte'], 'confirmaciones' => $confirmaciones];
    }

    public function crear(Models\VersionPlanificacion $referencia, string $firma, bool $activar, string $solicitud, int $usuarioId, ?string $ip, ?string $nombre = null): Models\VersionPlanificacion
    {
        return DB::transaction(function () use ($referencia, $firma, $activar, $solicitud, $usuarioId, $ip, $nombre) {
            $periodo = Models\PeriodoAcademico::lockForUpdate()->findOrFail($referencia->periodo_academico_id);
            Models\VersionPlanificacion::where('periodo_academico_id', $periodo->id)->orderBy('id')->lockForUpdate()->get();
            $fuente = Models\VersionPlanificacion::findOrFail($referencia->id);
            $yaCreada = DB::table('historial_acciones')->where('accion', 'Crear borrador para ajustes')->where('usuario_id', $usuarioId)->where('datos->solicitud', $solicitud)->value('registro_id');
            if ($yaCreada) return Models\VersionPlanificacion::findOrFail($yaCreada);
            app(ActivacionVersion::class)->bloquearDatos($periodo, $fuente);
            $revision = $this->revisar($fuente);
            if (! hash_equals($revision['firma'], $firma)) $this->rechazar('La fuente o la versión actual cambiaron. Revisa de nuevo antes de crear el borrador.');
            if ($revision['bloqueos']) $this->rechazar(implode(' ', $revision['bloqueos']));
            $maximo = Models\VersionPlanificacion::where('periodo_academico_id', $periodo->id)->where('tipo', 'semestral')->pluck('version')->map(fn ($v) => (float) $v)->max() ?? 0;
            $nueva = Models\VersionPlanificacion::create(['periodo_academico_id' => $periodo->id, 'tipo' => 'semestral', 'nombre' => $nombre ?: 'Programación semestral',
                'version' => (round($maximo * 10) + 1) / 10, 'estado' => 'Borrador', 'es_actual' => $activar, 'usuario_id' => $usuarioId,
                'descripcion' => 'Borrador para ajustes desde V'.$fuente->version.' (#'.$fuente->id.'). La fuente se conserva sin modificar sus horarios.']);
            $horarios = Models\Horario::where('version_planificacion_id', $fuente->id)->where('estado', true)->orderBy('id')->with([
                'profesores' => fn ($q) => $q->where('estado', true), 'grupoSeccion.profesores' => fn ($q) => $q->where('estado', true),
                'actividadSeccion.seccion.profesores' => fn ($q) => $q->where('estado', true),
                'asignacionesSalas' => fn ($q) => $q->where('estado', true), 'asignacionesLaboratorios' => fn ($q) => $q->where('estado', true),
                'asignacionesLaboratoriosMoviles' => fn ($q) => $q->where('estado', true),
            ])->get();
            $docentesIds = [];
            foreach ($horarios as $h) {
                $copia = $h->replicate(); $copia->version_planificacion_id = $nueva->id; $copia->save();
                $docentes = $h->profesores;
                if ($docentes->isEmpty()) $docentes = $h->grupoSeccion?->profesores ?? collect();
                if ($docentes->isEmpty()) $docentes = $h->actividadSeccion?->seccion?->profesores ?? collect();
                foreach ($docentes->unique('profesor_id') as $d) {
                    Models\HorarioProfesor::create(['horario_id' => $copia->id, 'profesor_id' => $d->profesor_id, 'tipo_participacion' => $d->tipo_participacion,
                        'es_principal' => $d->es_principal, 'observaciones' => $d->observaciones, 'estado' => true]);
                    $docentesIds[] = $d->profesor_id;
                }
                foreach (['asignacionesSalas', 'asignacionesLaboratorios', 'asignacionesLaboratoriosMoviles'] as $relacion) foreach ($h->$relacion as $a) {
                    $registro = $a->replicate(); $registro->horario_id = $copia->id; $registro->save();
                }
            }
            $historicos = Models\ProfesorVersion::where('version_planificacion_id', $fuente->id)->get()->keyBy('profesor_id');
            foreach (Models\Profesor::whereIn('id', array_unique($docentesIds))->get() as $profesor) {
                $original = $historicos->get($profesor->id) ?? $profesor;
                Models\ProfesorVersion::create($original->only(['rut', 'nombre', 'apellido', 'correo_institucional', 'correo_personal', 'tipo_contrato', 'tipo_profesor', 'unidad_academica'])
                    + ['version_planificacion_id' => $nueva->id, 'profesor_id' => $profesor->id]);
            }
            foreach ($revision['confirmaciones']->chunk(300) as $parte) {
                DB::table('confirmaciones_grupo_programacion')->insert($parte->map(fn ($c) => (array) $c + [
                    'version_planificacion_id' => $nueva->id, 'created_at' => now(), 'updated_at' => now(),
                ])->all());
            }
            $antes = Models\VersionPlanificacion::where('periodo_academico_id', $periodo->id)->where('tipo', 'semestral')->where('es_actual', true)->whereKeyNot($nueva->id)->pluck('id')->all();
            if ($activar) Models\VersionPlanificacion::whereIn('id', $antes)->update(['es_actual' => false]);
            DB::table('historial_acciones')->insert(['usuario_id' => $usuarioId, 'accion' => 'Crear borrador para ajustes', 'modulo' => 'Programación semestral',
                'tabla_afectada' => 'versiones_planificacion', 'registro_id' => $nueva->id, 'descripcion' => 'Copia de trabajo independiente; los horarios de la fuente se conservan.',
                'datos' => json_encode(['solicitud' => $solicitud, 'fuente' => $fuente->id, 'actual' => $activar, 'actuales_anteriores' => $antes, 'firma' => $firma, 'modulos' => $horarios->count()], JSON_THROW_ON_ERROR),
                'direccion_ip' => $ip, 'created_at' => now(), 'updated_at' => now()]);
            return $nueva;
        }, 3);
    }
    private function rechazar(string $mensaje): never { throw ValidationException::withMessages(['copia' => $mensaje]); }
}

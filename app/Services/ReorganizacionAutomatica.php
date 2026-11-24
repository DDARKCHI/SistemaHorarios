<?php

namespace App\Services;

use App\Models;
use App\Support\IntervalosHorario;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReorganizacionAutomatica
{
    public function preparar(Models\VersionPlanificacion $version): array
    {
        if (! in_array($version->tipo, ['anterior', 'semestral'], true)) $this->rechazar('Selecciona una versión anterior o semestral.');
        $periodo = $version->periodoAcademico;
        $reporte = app(PreparacionGeneracion::class)->revisar($periodo, $version);
        if ($reporte['bloqueos']) $this->rechazar('Completa los '.$reporte['bloqueos'].' bloqueos del diagnóstico antes de reorganizar.');
        $horarios = Models\Horario::where('version_planificacion_id', $version->id)->where('estado', true)->orderBy('id')->with([
            'bloqueHorario',
            'actividadSeccion.seccion.asignatura', 'actividadSeccion.seccion.profesores' => fn ($q) => $q->where('estado', true)->orderBy('id'),
            'actividadSeccion.seccion.profesores.profesor', 'actividadSeccion.requerimientos', 'grupoSeccion',
            'grupoSeccion.profesores' => fn ($q) => $q->where('estado', true)->orderBy('id'), 'grupoSeccion.profesores.profesor',
            'profesores' => fn ($q) => $q->where('estado', true)->orderBy('id'), 'profesores.profesor',
            'asignacionesSalas' => fn ($q) => $q->where('estado', true)->orderBy('id'), 'asignacionesSalas.sala.tipoSala',
            'asignacionesLaboratorios' => fn ($q) => $q->where('estado', true)->orderBy('id'), 'asignacionesLaboratorios.laboratorio.sala.tipoSala',
            'asignacionesLaboratoriosMoviles' => fn ($q) => $q->where('estado', true)->orderBy('id'), 'asignacionesLaboratoriosMoviles.laboratorioMovil',
        ])->get()->keyBy('id');
        if ($horarios->isEmpty() || $horarios->count() > 4000) $this->rechazar('Esta modalidad requiere una base completa de entre 1 y 4.000 módulos activos.');
        $dias = Models\Dia::where('estado', true)->orderBy('numero')->get();
        $bloques = Models\BloqueHorario::where('estado', true)->orderBy('hora_inicio')->get();
        $opciones = [];
        foreach ($dias as $dia) foreach ($bloques as $bloque) {
            $rango = IntervalosHorario::deBloque($bloque);
            if (! $rango || $bloque->es_almuerzo || IntervalosHorario::seSuperponen($rango, [13 * 3600, 14 * 3600])) continue;
            $opciones[$dia->id.':'.$bloque->id] = ['dia_id' => (int) $dia->id, 'bloque_id' => (int) $bloque->id, 'numero_dia' => (int) $dia->numero,
                'inicio' => $rango[0], 'fin' => $rango[1], 'nombre' => $dia->nombre.' · Módulo '.$bloque->numero.' · '.substr($bloque->hora_inicio, 0, 5).'–'.substr($bloque->hora_fin, 0, 5)];
        }
        $inventario = Models\Equipamiento::where('universidad_id', $periodo->universidad_id)->orderBy('id')->with('ubicaciones')->get()->keyBy('id');
        $reglas = Models\PrioridadRecurso::where('estado', true)->whereIn('asignatura_id', $horarios->pluck('actividadSeccion.seccion.asignatura_id')->unique())->orderBy('id')->with('tipoSala')->get();
        $prioridades = new PrioridadUsoRecursos($reglas, $inventario);
        $declaradas = Models\DisponibilidadProfesor::where('periodo_academico_id', $periodo->id)->where('estado', true)->orderBy('id')->with('bloqueHorario')->get();
        $porDocente = $declaradas->groupBy('profesor_id');
        $cobertura = [];
        $trabajos = [];
        foreach ($horarios as $h) {
            $docentes = $h->profesores;
            if ($docentes->isEmpty()) $docentes = $h->grupoSeccion->profesores;
            if ($docentes->isEmpty()) $docentes = $h->actividadSeccion->seccion->profesores;
            $h->setRelation('docentesEfectivos', $docentes->unique('profesor_id')->values());
            $recursos = ['curso:'.$h->actividadSeccion->seccion_id, 'grupo:'.$h->grupo_seccion_id];
            foreach ($docentes as $d) $recursos[] = 'profesor:'.$d->profesor_id;
            $espacios = [];
            foreach ($h->asignacionesSalas as $a) $espacios[] = ['tipo' => 'sala', 'id' => $a->sala_id];
            foreach ($h->asignacionesLaboratorios as $a) {
                $espacios[] = ['tipo' => 'laboratorio', 'id' => $a->laboratorio_id];
                $espacios[] = ['tipo' => 'sala', 'id' => $a->laboratorio->sala_id];
            }
            foreach ($h->asignacionesLaboratoriosMoviles as $a) $espacios[] = ['tipo' => 'movil', 'id' => $a->laboratorio_movil_id];
            $niveles = [];
            foreach ($espacios as $espacio) {
                $recursos[] = $espacio['tipo'].':'.$espacio['id'];
                $nivel = $prioridades->nivel($h, $espacio);
                if ($nivel !== null) $niveles[] = $nivel;
            }
            $admitidas = [];
            $duracion = IntervalosHorario::deBloque($h->bloqueHorario);
            foreach ($opciones as $id => $o) {
                if ($o['fin'] - $o['inicio'] !== $duracion[1] - $duracion[0]) continue;
                $apta = true;
                foreach ($docentes as $d) {
                    if (! isset($cobertura[$d->profesor_id][$id])) $cobertura[$d->profesor_id][$id] = IntervalosHorario::coberturaDisponibilidad([$o['inicio'], $o['fin']], $porDocente->get($d->profesor_id, collect())->where('dia_id', $o['dia_id'])) === 'cubierto';
                    if (! $cobertura[$d->profesor_id][$id]) { $apta = false; break; }
                }
                if ($apta) $admitidas[] = $id;
            }
            $trabajos[] = ['id' => $h->id, 'recursos' => array_values(array_unique($recursos)), 'opciones' => $admitidas,
                'original' => $h->dia_id.':'.$h->bloque_horario_id, 'prioridad' => $niveles ? min($niveles) : 256,
                'grupo_id' => $h->grupo_seccion_id, 'carga' => (int) $h->actividadSeccion->cantidad_horas];
        }
        $firmables = [$version->getAttributes(), $periodo->getAttributes(), $horarios->toArray(), $declaradas->toArray(), $dias->toArray(), $bloques->toArray(), $inventario->toArray(), $reglas->toArray(), $opciones, $trabajos];
        $firma = hash('sha256', json_encode($firmables, JSON_THROW_ON_ERROR));
        return compact('firma', 'trabajos', 'opciones', 'horarios');
    }

    public function guardar(Models\VersionPlanificacion $referencia, array $propuesta, int $usuarioId, ?string $ip): Models\VersionPlanificacion
    {
        return DB::transaction(function () use ($referencia, $propuesta, $usuarioId, $ip) {
            $periodo = Models\PeriodoAcademico::lockForUpdate()->findOrFail($referencia->periodo_academico_id);
            $version = Models\VersionPlanificacion::lockForUpdate()->findOrFail($referencia->id);
            $anterior = DB::table('historial_acciones')->where('accion', 'Reorganizar programación')->where('usuario_id', $usuarioId)->where('datos->propuesta', $propuesta['token'])->value('registro_id');
            if ($anterior) return Models\VersionPlanificacion::findOrFail($anterior);
            // Compartir bloqueo docente con la aprobación de disponibilidad y la asignación manual.
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
            Models\PrioridadRecurso::orderBy('id')->lockForUpdate()->get();
            Models\Horario::where('version_planificacion_id', $version->id)->orderBy('id')->lockForUpdate()->get();
            $version->unsetRelation('periodoAcademico');
            $datos = $this->preparar($version);
            if (! hash_equals($datos['firma'], $propuesta['firma'])) $this->rechazar('Los datos cambiaron desde la vista previa. Genera una propuesta nueva antes de guardar.');
            if (! app(DistribuidorHorarios::class)->valido($propuesta['plan'], $datos['trabajos'], $datos['opciones'])) $this->rechazar('La propuesta no supera la comprobación final. Genera una nueva.');
            $mayor = Models\VersionPlanificacion::where('periodo_academico_id', $periodo->id)->where('tipo', 'semestral')->pluck('version')->map(fn ($v) => (float) $v)->max() ?? 0;
            $nueva = Models\VersionPlanificacion::create(['periodo_academico_id' => $periodo->id, 'nombre' => 'Programación semestral', 'tipo' => 'semestral',
                'version' => (round($mayor * 10) + 1) / 10, 'estado' => 'Borrador', 'es_actual' => false, 'usuario_id' => $usuarioId,
                'descripcion' => 'Reorganización automática de la versión #'.$version->id.'. Mantiene docentes y recursos. No sustituye la versión actual.']);
            foreach ($datos['horarios'] as $h) {
                $o = $datos['opciones'][$propuesta['plan'][$h->id]];
                $copia = Models\Horario::create(['actividad_seccion_id' => $h->actividad_seccion_id, 'grupo_seccion_id' => $h->grupo_seccion_id,
                    'version_planificacion_id' => $nueva->id, 'dia_id' => $o['dia_id'], 'bloque_horario_id' => $o['bloque_id'], 'observaciones' => $h->observaciones, 'estado' => true]);
                foreach ($h->docentesEfectivos as $d) {
                    Models\HorarioProfesor::create(['horario_id' => $copia->id, 'profesor_id' => $d->profesor_id, 'tipo_participacion' => $d->tipo_participacion,
                        'es_principal' => $d->es_principal, 'observaciones' => $d->observaciones, 'estado' => true]);
                }
                foreach (['asignacionesSalas', 'asignacionesLaboratorios', 'asignacionesLaboratoriosMoviles'] as $relacion) foreach ($h->$relacion as $a) {
                    $registro = $a->replicate(); $registro->horario_id = $copia->id; $registro->save();
                }
            }
            $docentes = $datos['horarios']->flatMap(fn ($h) => $h->docentesEfectivos)->unique('profesor_id');
            $historicos = Models\ProfesorVersion::where('version_planificacion_id', $version->id)->get()->keyBy('profesor_id');
            foreach ($docentes as $d) {
                $fuente = $historicos->get($d->profesor_id) ?? $d->profesor;
                Models\ProfesorVersion::create($fuente->only(['rut', 'nombre', 'apellido', 'correo_institucional', 'correo_personal', 'tipo_contrato', 'tipo_profesor', 'unidad_academica'])
                    + ['version_planificacion_id' => $nueva->id, 'profesor_id' => $d->profesor_id]);
            }
            $revision = app(RevisionConflictosProgramacion::class)->revisar($nueva);
            foreach (['conflictos', 'asignaciones', 'capacidad', 'disponibilidad', 'datos', 'recursos', 'carga_observaciones'] as $clave) if ($revision[$clave]) $this->rechazar('La revisión final encontró observaciones. No se guardó la propuesta; revisa los datos y vuelve a generar.');
            DB::table('historial_acciones')->insert(['usuario_id' => $usuarioId, 'accion' => 'Reorganizar programación', 'modulo' => 'Programación semestral',
                'tabla_afectada' => 'versiones_planificacion', 'registro_id' => $nueva->id, 'descripcion' => 'Borrador reorganizado sin sustituir la versión actual.',
                'datos' => json_encode(['propuesta' => $propuesta['token'], 'version_base' => $version->id, 'modulos' => count($propuesta['plan']), 'firma' => $datos['firma']], JSON_THROW_ON_ERROR),
                'direccion_ip' => $ip, 'created_at' => now(), 'updated_at' => now()]);
            return $nueva;
        }, 3);
    }

    public function presentar(array $datos, array $plan): array
    {
        $filas = [];
        foreach ($datos['horarios'] as $h) {
            $curso = $h->actividadSeccion->seccion;
            $original = $h->dia_id.':'.$h->bloque_horario_id;
            $filas[] = ['id' => $h->id, 'curso' => $curso->asignatura->codigo.' · '.$curso->asignatura->nombre, 'seccion' => $curso->codigo,
                'actividad' => $h->actividadSeccion->tipo, 'grupo' => $h->grupoSeccion->codigo,
                'docentes' => $h->docentesEfectivos->map(fn ($d) => $d->profesor->apellido.', '.$d->profesor->nombre)->implode(' / '),
                'antes' => $datos['opciones'][$original]['nombre'] ?? 'Fuera de los módulos automáticos',
                'despues' => $datos['opciones'][$plan[$h->id]]['nombre'], 'cambio' => $original !== $plan[$h->id]];
        }
        return $filas;
    }

    private function rechazar(string $mensaje): never
    {
        throw ValidationException::withMessages(['generacion' => $mensaje]);
    }
}

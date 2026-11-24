<?php

namespace App\Services;

use App\Models;
use App\Support\IntervalosHorario;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GeneracionCargaPendiente
{
    public function catalogo(Models\VersionPlanificacion $version): array
    {
        $version->load('periodoAcademico.universidad');
        $cuentas = Models\Horario::where('version_planificacion_id', $version->id)->where('estado', true)->selectRaw('grupo_seccion_id, COUNT(*) as total')->groupBy('grupo_seccion_id')->pluck('total', 'grupo_seccion_id');
        $grupos = $this->grupos($version)->get();
        $sedes = Models\Sede::where('universidad_id', $version->periodoAcademico->universidad_id)->where('estado', true)->orderBy('nombre')->orderBy('id')->get();
        $filas = $grupos->map(function ($g) use ($cuentas, $sedes) {
            $asignados = (int) $cuentas->get($g->id, 0); $pendientes = max(0, (int) $g->actividadSeccion->cantidad_horas - $asignados);
            return ['grupo' => $g, 'asignados' => $asignados, 'pendientes' => $pendientes, 'sedes' => $this->sedesPermitidas($g, $sedes)];
        })->where('pendientes', '>', 0)->values();
        $duraciones = Models\BloqueHorario::where('estado', true)->orderBy('hora_inicio')->get()->filter(fn ($b) => $this->utilizable($b))
            ->map(fn ($b) => IntervalosHorario::deBloque($b)[1] - IntervalosHorario::deBloque($b)[0])->unique()->sort()->values();
        return compact('filas', 'duraciones');
    }

    public function preparar(Models\VersionPlanificacion $version, array $seleccion, array $sedesElegidas, int $duracion): array
    {
        $version->unsetRelation('periodoAcademico'); $periodo = $version->periodoAcademico;
        if ($version->tipo !== 'semestral' || $version->estado !== 'Borrador' || ! $periodo->estado || ! $periodo->universidad->estado) $this->rechazar('Selecciona un borrador semestral de un período e institución activos. La propuesta se guarda aparte.');
        $ids = collect($seleccion)->map(fn ($v) => (int) $v)->unique()->sort()->values()->all();
        if (! $ids || count($ids) > 40 || count($ids) !== count($seleccion)) $this->rechazar('Selecciona entre 1 y 40 grupos distintos para esta etapa.');
        $grupos = $this->grupos($version)->whereIn('grupos_seccion.id', $ids)->get()->keyBy('id');
        if ($grupos->count() !== count($ids)) $this->rechazar('La selección contiene grupos ajenos al período, inactivos o de una actividad inactiva.');
        $revision = app(RevisionConflictosProgramacion::class)->revisar($version);
        foreach (['conflictos', 'datos', 'asignaciones', 'capacidad', 'recursos'] as $tipo) if ($revision[$tipo]) $this->rechazar('La base tiene observaciones de topes, asignaciones, datos, capacidad o recursos. Corrígelas antes de conservar sus módulos como fijos.');
        if ($revision['carga']['excedentes'] || $revision['carga']['modulos_sin_grupo']) $this->rechazar('La base tiene módulos en exceso o sin grupo válido. Revisa su carga antes de completar.');
        $base = $this->horarios($version);
        if ($base->count() > 4000) $this->rechazar('Esta modalidad admite hasta 4.000 módulos activos incluyendo los nuevos. Reduce la base antes de continuar.');
        $porGrupo = $base->groupBy('grupo_seccion_id');
        $dias = Models\Dia::where('estado', true)->orderBy('numero')->orderBy('id')->get();
        $bloques = Models\BloqueHorario::where('estado', true)->orderBy('hora_inicio')->orderBy('id')->get();
        $opciones = []; $orden = 0;
        foreach ($dias as $d) foreach ($bloques as $b) {
            if (! $this->utilizable($b)) continue;
            $r = IntervalosHorario::deBloque($b);
            if ($r[1] - $r[0] !== $duracion) continue;
            $opciones[$d->id.':'.$b->id] = ['dia_id' => (int) $d->id, 'bloque_id' => (int) $b->id, 'inicio' => $r[0], 'fin' => $r[1], 'orden' => ++$orden,
                'nombre' => $d->nombre.' · Módulo '.$b->numero.' · '.substr($b->hora_inicio, 0, 5).'–'.substr($b->hora_fin, 0, 5)];
        }
        if (! $opciones) $this->rechazar('No hay módulos activos de esa duración fuera del almuerzo. Revisa el calendario.');
        $salas = Models\Sala::where('universidad_id', $periodo->universidad_id)->orderBy('id')->with('sede', 'tipoSala', 'laboratorios.sala.tipoSala')->get();
        $moviles = Models\LaboratorioMovil::where('universidad_id', $periodo->universidad_id)->orderBy('id')->get();
        $sedes = Models\Sede::where('universidad_id', $periodo->universidad_id)->where('estado', true)->orderBy('id')->get();
        $inventario = Models\Equipamiento::where('universidad_id', $periodo->universidad_id)->orderBy('id')->with('ubicaciones')->get()->keyBy('id');
        $reglas = Models\PrioridadRecurso::whereHas('asignatura', fn ($a) => $a->where('universidad_id', $periodo->universidad_id))->orderBy('id')->with('tipoSala')->get();
        $recursos = new RevisionRecursosProgramacion($periodo->universidad_id, new RevisionEquipamientoProgramacion($inventario));
        $prioridades = new PrioridadUsoRecursos($reglas, $inventario);
        $docentes = collect(); $docentesGrupo = collect();
        $pendientes = []; $sedesSeleccionadas = [];
        foreach ($grupos as $g) {
            $a = $g->actividadSeccion; $s = $a->seccion;
            if (! $s->asignatura?->estado || (int) $s->asignatura->universidad_id !== (int) $periodo->universidad_id) $this->rechazar('Revisa el estado y la institución del ramo del grupo '.$g->codigo.'.');
            $faltan = (int) $a->cantidad_horas - $porGrupo->get($g->id, collect())->count();
            if ($faltan <= 0) $this->rechazar('El grupo '.$g->codigo.' ya no tiene carga pendiente o necesita definir sus módulos. Actualiza la selección.');
            $sede = (int) ($sedesElegidas[$g->id] ?? 0);
            if (! $this->sedesPermitidas($g, $sedes)->contains('id', $sede)) $this->rechazar('Selecciona una sede activa común a las carreras del grupo '.$g->codigo.'. Si no hay carreras vinculadas, completa su oferta.');
            foreach ($porGrupo->get($g->id, collect()) as $h) {
                $r = IntervalosHorario::deBloque($h->bloqueHorario);
                if ($r[1] - $r[0] !== $duracion) $this->rechazar('El grupo '.$g->codigo.' tiene módulos de otra duración. Completa grupos de una misma duración por etapa.');
                foreach ($this->salasAsignadas($h) as $sala) if ((int) $sala->sede_id !== $sede) $this->rechazar('La sede elegida para el grupo '.$g->codigo.' difiere de sus módulos ya asignados. Conserva su sede.');
            }
            $asignados = $g->profesores->isNotEmpty() ? $g->profesores : $s->profesores;
            if ($asignados->isEmpty()) $this->rechazar('Asigna docentes al grupo '.$g->codigo.' o a su sección antes de completar sus módulos. Los docentes exclusivos de módulos anteriores se conservan en esos módulos.');
            foreach ($asignados as $d) {
                if (! $d->profesor?->estado || (int) $d->profesor->universidad_id !== (int) $periodo->universidad_id) $this->rechazar('Un docente del grupo '.$g->codigo.' está inactivo o pertenece a otra institución.');
                $docentes->put($d->profesor_id, $d->profesor);
            }
            $docentesGrupo->put($g->id, $asignados->unique('profesor_id')->values());
            $pendientes[$g->id] = $faltan; $sedesSeleccionadas[$g->id] = $sede;
        }
        if (array_sum($pendientes) > 300 || array_sum($pendientes) + $base->count() > 4000) $this->rechazar('Selecciona hasta 300 módulos nuevos y una base total de hasta 4.000. Puedes continuar con otro borrador por etapas.');
        $disponibilidad = $this->disponibilidadAprobada($periodo, $docentes, $dias, $bloques);
        $paquetes = $trabajos = []; $ensayos = 0;
        foreach ($grupos as $g) {
            $ds = $docentesGrupo->get($g->id);
            $paquetes[$g->id] = app(EspaciosCargaPendiente::class)->candidatos($g, $sedesSeleccionadas[$g->id], $ds, $salas, $moviles, $recursos, $prioridades, $inventario, $ensayos);
            if (! $paquetes[$g->id]) $this->rechazar('El grupo '.$g->codigo.' de '.$g->actividadSeccion->seccion->asignatura->codigo.' no tiene un espacio compatible en la sede elegida. Revisa cupos, laboratorios, equipamiento y requerimientos.');
            if (array_sum(array_map('count', $paquetes)) > 2000) $this->rechazar('La selección supera 2.000 combinaciones de espacios. Reduce los grupos o precisa sus requerimientos.');
            $admitidas = [];
            foreach ($opciones as $clave => $o) if ($ds->every(fn ($d) => IntervalosHorario::coberturaDisponibilidad([$o['inicio'], $o['fin']], $disponibilidad['porDocente']->get($d->profesor_id, collect())->where('dia_id', $o['dia_id'])) === 'cubierto')) $admitidas[] = $clave;
            $r = ['curso:'.$g->actividadSeccion->seccion_id, 'grupo:'.$g->id, ...$ds->pluck('profesor_id')->map(fn ($id) => 'profesor:'.$id)->all()];
            for ($n = 1; $n <= $pendientes[$g->id]; $n++) $trabajos[] = ['id' => 'nuevo:'.$g->id.':'.$n, 'orden' => $n, 'grupo_id' => (int) $g->id,
                'recursos' => array_values(array_unique($r)), 'opciones' => $admitidas, 'carga' => (int) $g->actividadSeccion->cantidad_horas,
                'prioridad' => min(array_column($paquetes[$g->id], 'prioridad'))];
        }
        $fijos = [];
        foreach ($base as $h) {
            $ds = $this->docentesEfectivos($h); $h->setRelation('docentesEfectivos', $ds);
            if ($ds->contains(fn ($d) => ! $d->profesor?->estado || (int) $d->profesor->universidad_id !== (int) $periodo->universidad_id)) $this->rechazar('La base contiene un docente inactivo o de otra institución. Revisa sus módulos antes de completar.');
            $r = ['curso:'.$h->actividadSeccion->seccion_id, 'grupo:'.$h->grupo_seccion_id, ...$ds->pluck('profesor_id')->map(fn ($id) => 'profesor:'.$id)->all()];
            foreach ($this->salasAsignadas($h) as $s) $r[] = 'sala:'.$s->id;
            foreach ($h->asignacionesLaboratorios as $a) $r[] = 'laboratorio:'.$a->laboratorio_id;
            foreach ($h->asignacionesLaboratoriosMoviles as $a) $r[] = 'movil:'.$a->laboratorio_movil_id;
            $rango = IntervalosHorario::deBloque($h->bloqueHorario);
            $fijos[] = ['id' => $h->id, 'dia_id' => (int) $h->dia_id, 'inicio' => $rango[0], 'fin' => $rango[1], 'recursos' => array_values(array_unique($r))];
        }
        $historicos = Models\ProfesorVersion::where('version_planificacion_id', $version->id)->orderBy('id')->get();
        $actuales = Models\VersionPlanificacion::where('periodo_academico_id', $periodo->id)->where('es_actual', true)->orderBy('id')->get();
        $firma = hash('sha256', json_encode([$version->getAttributes(), $periodo->toArray(), $grupos->toArray(), $base->toArray(), $salas->toArray(), $moviles->toArray(), $sedes->toArray(),
            $inventario->toArray(), $reglas->toArray(), $dias->toArray(), $bloques->toArray(), $disponibilidad['firma'], $historicos->toArray(), $actuales->toArray(), $revision['carga'], $revision['disponibilidad'], $trabajos, $paquetes, $sedesSeleccionadas, $duracion], JSON_THROW_ON_ERROR));
        return compact('firma', 'version', 'grupos', 'base', 'trabajos', 'opciones', 'paquetes', 'fijos', 'docentesGrupo', 'historicos', 'revision', 'pendientes');
    }

    public function guardar(Models\VersionPlanificacion $referencia, array $propuesta, int $usuario, ?string $ip): Models\VersionPlanificacion
    {
        return DB::transaction(function () use ($referencia, $propuesta, $usuario, $ip) {
            $periodo = Models\PeriodoAcademico::lockForUpdate()->findOrFail($referencia->periodo_academico_id);
            $version = Models\VersionPlanificacion::lockForUpdate()->findOrFail($referencia->id);
            $yaGuardada = DB::table('historial_acciones')->where('accion', 'Completar carga pendiente')->where('usuario_id', $usuario)->where('datos->propuesta', $propuesta['token'])->value('registro_id');
            if ($yaGuardada) return Models\VersionPlanificacion::findOrFail($yaGuardada);
            app(ActivacionVersion::class)->bloquearDatos($periodo, $version);
            Models\Sede::where('universidad_id', $periodo->universidad_id)->orderBy('id')->lockForUpdate()->get();
            Models\Carrera::where('universidad_id', $periodo->universidad_id)->orderBy('id')->lockForUpdate()->get();
            Models\PrioridadRecurso::orderBy('id')->lockForUpdate()->get();
            $datos = $this->preparar($version, $propuesta['seleccion'], $propuesta['sedes'], $propuesta['duracion']);
            if (! hash_equals($datos['firma'], $propuesta['firma'])) $this->rechazar('Los datos cambiaron desde la vista previa. Prepara otra propuesta para guardar los datos actuales.');
            if (! app(DistribuidorCargaPendiente::class)->valido($propuesta['plan'], $datos['trabajos'], $datos['opciones'], $datos['paquetes'], $datos['fijos'])) $this->rechazar('La propuesta no supera la comprobación de carga, docentes o espacios. Genera otra.');
            $ultima = $periodo->versionesPlanificacion()->where('tipo', 'semestral')->pluck('version')->map(fn ($v) => (float) $v)->max() ?? 0;
            $nueva = $periodo->versionesPlanificacion()->create(['nombre' => 'Programación semestral', 'tipo' => 'semestral', 'version' => (round($ultima * 10) + 1) / 10,
                'estado' => 'Borrador', 'es_actual' => false, 'usuario_id' => $usuario, 'descripcion' => 'Completa '.count($datos['trabajos']).' módulos pendientes desde V'.$version->version.'. Conserva los módulos activos de la base.']);
            foreach ($datos['historicos'] as $p) { $c = $p->replicate(); $c->version_planificacion_id = $nueva->id; $c->save(); }
            foreach ($datos['base'] as $h) {
                $c = $h->replicate(); $c->version_planificacion_id = $nueva->id; $c->setRelations([]); $c->save();
                foreach ($h->docentesEfectivos as $d) $c->profesores()->create(['profesor_id' => $d->profesor_id, 'tipo_participacion' => $d->tipo_participacion, 'es_principal' => $d->es_principal, 'observaciones' => $d->observaciones, 'estado' => true]);
                foreach (['asignacionesSalas', 'asignacionesLaboratorios', 'asignacionesLaboratoriosMoviles'] as $relacion) foreach ($h->$relacion as $a) { $copia = $a->replicate(); $copia->horario_id = $c->id; $copia->setRelations([]); $copia->save(); }
            }
            foreach ($datos['trabajos'] as $t) {
                $e = $propuesta['plan'][$t['id']]; $o = $datos['opciones'][$e['slot']]; $p = $datos['paquetes'][$t['grupo_id']][$e['paquete']]; $g = $datos['grupos']->get($t['grupo_id']);
                $h = $nueva->horarios()->create(['actividad_seccion_id' => $g->actividad_seccion_id, 'grupo_seccion_id' => $g->id, 'dia_id' => $o['dia_id'], 'bloque_horario_id' => $o['bloque_id'], 'estado' => true]);
                foreach ($datos['docentesGrupo']->get($g->id) as $d) $h->profesores()->create(['profesor_id' => $d->profesor_id, 'tipo_participacion' => $d->tipo_participacion, 'es_principal' => $d->es_principal, 'observaciones' => $d->observaciones, 'estado' => true]);
                $valores = ['capacidad_asignada' => $p['cantidad'], 'es_principal' => true, 'estado' => true];
                $h->asignacionesSalas()->create(['sala_id' => $p['sala_id']] + $valores);
                if ($p['laboratorio_id']) $h->asignacionesLaboratorios()->create(['laboratorio_id' => $p['laboratorio_id']] + $valores);
                if ($p['movil_id']) $h->asignacionesLaboratoriosMoviles()->create(['laboratorio_movil_id' => $p['movil_id']] + $valores);
            }
            $revision = app(RevisionConflictosProgramacion::class)->revisar($nueva);
            foreach (['conflictos', 'datos', 'asignaciones', 'capacidad', 'recursos'] as $clave) if ($revision[$clave]) $this->rechazar('El borrador no superó la revisión final. No se guardó ningún cambio; revisa la oferta y sus recursos.');
            foreach ($datos['grupos'] as $g) if ($nueva->horarios()->where('grupo_seccion_id', $g->id)->where('estado', true)->count() !== (int) $g->actividadSeccion->cantidad_horas) $this->rechazar('La carga de un grupo no quedó completa. No se guardó la propuesta.');
            DB::table('historial_acciones')->insert(['usuario_id' => $usuario, 'accion' => 'Completar carga pendiente', 'modulo' => 'Programación semestral', 'tabla_afectada' => 'versiones_planificacion', 'registro_id' => $nueva->id,
                'descripcion' => 'Borrador independiente que conserva la base y completa grupos seleccionados.', 'datos' => json_encode(['propuesta' => $propuesta['token'], 'version_base' => $version->id,
                    'firma' => $datos['firma'], 'grupos' => $propuesta['seleccion'], 'sedes' => $propuesta['sedes'], 'duracion' => $propuesta['duracion'], 'nuevos' => count($datos['trabajos']), 'conservados' => $datos['base']->count(), 'pendientes_globales' => $revision['carga']['pendientes']], JSON_THROW_ON_ERROR),
                'direccion_ip' => $ip, 'created_at' => now(), 'updated_at' => now()]);
            return $nueva;
        }, 3);
    }

    public function presentar(array $datos, array $plan): Collection
    {
        return collect($datos['trabajos'])->map(function ($t) use ($datos, $plan) {
            $g = $datos['grupos']->get($t['grupo_id']); $a = $g->actividadSeccion; $e = $plan[$t['id']];
            return ['curso' => $a->seccion->asignatura->codigo.' · '.$a->seccion->asignatura->nombre, 'seccion' => $a->seccion->codigo, 'grupo' => $g->codigo,
                'actividad' => $a->tipo, 'horario' => $datos['opciones'][$e['slot']]['nombre'], 'espacio' => $datos['paquetes'][$g->id][$e['paquete']]['nombre'],
                'docentes' => $datos['docentesGrupo']->get($g->id)->map(fn ($d) => $d->profesor->apellido.', '.$d->profesor->nombre)->implode(' / ')];
        });
    }

    public function grupos(Models\VersionPlanificacion $version)
    {
        return Models\GrupoSeccion::where('estado_activo', true)->whereHas('actividadSeccion', fn ($a) => $a->where('estado', true)->whereHas('seccion', fn ($s) => $s->where('periodo_academico_id', $version->periodo_academico_id)->where('estado_activo', true)))
            ->with(['profesores' => fn ($p) => $p->where('estado', true)->orderBy('id'), 'profesores.profesor', 'actividadSeccion.grupos' => fn ($g) => $g->where('estado_activo', true)->orderBy('id'), 'actividadSeccion.seccion.asignatura',
                'actividadSeccion.seccion.carreras.sedes', 'actividadSeccion.seccion.profesores' => fn ($p) => $p->where('estado', true)->orderBy('id'), 'actividadSeccion.seccion.profesores.profesor',
                'actividadSeccion.requerimientos' => fn ($r) => $r->where('estado', true)->where('obligatorio', true)->orderBy('id'), 'actividadSeccion.requerimientos.tipoSala', 'actividadSeccion.requerimientos.equipamiento'])->orderBy('id');
    }
    public function horarios(Models\VersionPlanificacion $v): Collection
    {
        return Models\Horario::where('version_planificacion_id', $v->id)->where('estado', true)->orderBy('id')->with(['bloqueHorario', 'grupoSeccion.profesores.profesor', 'actividadSeccion.seccion.profesores.profesor',
            'profesores' => fn ($p) => $p->where('estado', true)->orderBy('id'), 'profesores.profesor', 'asignacionesSalas' => fn ($a) => $a->where('estado', true)->orderBy('id'), 'asignacionesSalas.sala',
            'asignacionesLaboratorios' => fn ($a) => $a->where('estado', true)->orderBy('id'), 'asignacionesLaboratorios.laboratorio.sala',
            'asignacionesLaboratoriosMoviles' => fn ($a) => $a->where('estado', true)->orderBy('id')])->get();
    }
    public function docentesEfectivos(Models\Horario $h): Collection
    {
        $ds = $h->profesores->where('estado', true);
        if ($ds->isEmpty()) $ds = $h->grupoSeccion?->profesores->where('estado', true) ?? collect();
        if ($ds->isEmpty()) $ds = $h->actividadSeccion->seccion->profesores->where('estado', true);
        return $ds->unique('profesor_id')->values();
    }
    public function salasAsignadas(Models\Horario $h): Collection { return $h->asignacionesSalas->pluck('sala')->concat($h->asignacionesLaboratorios->pluck('laboratorio.sala'))->filter()->unique('id'); }
    public function sedesPermitidas(Models\GrupoSeccion $g, Collection $sedes): Collection
    {
        $carreras = $g->actividadSeccion->seccion->carreras;
        if ($carreras->isEmpty()) return collect();
        return $sedes->filter(fn ($s) => $carreras->every(fn ($c) => $c->estado && (int) $c->universidad_id === (int) $s->universidad_id && ((int) $c->sede_id === (int) $s->id || $c->sedes->contains('id', $s->id))))->values();
    }
    public function utilizable(Models\BloqueHorario $b): bool { $r = IntervalosHorario::deBloque($b); return $r && ! $b->es_almuerzo && ! IntervalosHorario::seSuperponen($r, [13 * 3600, 14 * 3600]); }
    public function disponibilidadAprobada(Models\PeriodoAcademico $p, Collection $docentes, Collection $dias, Collection $bloques): array
    {
        $calendario = hash('sha256', json_encode([$dias->map(fn ($d) => [$d->id, $d->numero, $d->nombre])->values()->all(), $bloques->map(fn ($b) => [$b->id, $b->hora_inicio, $b->hora_fin, $b->es_almuerzo])->values()->all()], JSON_THROW_ON_ERROR));
        $respuestas = Models\RespuestaDisponibilidad::where('respuesta', 'Aprobada')->whereIn('profesor_id', $docentes->keys())->whereHas('solicitudDisponibilidad', fn ($s) => $s->where('periodo_academico_id', $p->id)->where('estado', 'Aprobada'))->orderByDesc('id')->get()->unique('profesor_id')->keyBy('profesor_id');
        $declaradas = Models\DisponibilidadProfesor::where('periodo_academico_id', $p->id)->whereIn('profesor_id', $docentes->keys())->where('estado', true)->orderBy('id')->with('bloqueHorario')->get();
        $porDocente = $declaradas->groupBy('profesor_id');
        foreach ($docentes as $id => $d) {
            $r = $respuestas->get($id);
            if (! $r?->bloques || ($r->bloques['firma'] ?? null) !== $calendario) $this->rechazar('Falta disponibilidad aprobada con el calendario actual para '.$d->apellido.', '.$d->nombre.'.');
            $esperadas = collect($r->bloques['calendario'] ?? [])->mapWithKeys(fn ($c) => [$c => in_array($c, $r->bloques['disponibles'] ?? [], true) ? 'Disponible' : 'No disponible'])->sortKeys()->all();
            $actuales = $porDocente->get($id, collect())->mapWithKeys(fn ($a) => [$a->dia_id.':'.$a->bloque_horario_id => $a->tipo_disponibilidad])->sortKeys()->all();
            if ($actuales !== $esperadas) $this->rechazar('La disponibilidad de '.$d->apellido.', '.$d->nombre.' cambió tras su aprobación. Solicita una nueva respuesta.');
        }
        return ['porDocente' => $porDocente, 'firma' => [$calendario, $respuestas->toArray(), $declaradas->toArray()]];
    }
    private function rechazar(string $mensaje): never { throw ValidationException::withMessages(['generacion' => $mensaje]); }
}

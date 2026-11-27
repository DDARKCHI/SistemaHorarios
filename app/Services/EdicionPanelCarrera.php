<?php

namespace App\Services;

use App\Models;
use App\Support\IntervalosHorario;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EdicionPanelCarrera
{
    public function conGrupo(Models\VersionPlanificacion $version, Models\GrupoSeccion $grupo, int $carreraId, callable $operacion): mixed
    {
        return DB::transaction(function () use ($version, $grupo, $carreraId, $operacion) {
            $periodo = Models\PeriodoAcademico::lockForUpdate()->findOrFail($version->periodo_academico_id);
            $version = Models\VersionPlanificacion::lockForUpdate()->findOrFail($version->id);
            $grupo = Models\GrupoSeccion::lockForUpdate()->findOrFail($grupo->id);
            $actividad = Models\ActividadSeccion::lockForUpdate()->findOrFail($grupo->actividad_seccion_id);
            $seccion = Models\Seccion::lockForUpdate()->findOrFail($actividad->seccion_id);
            $actividad->setRelation('seccion', $seccion);
            $grupo->setRelation('actividadSeccion', $actividad);
            abort_unless((int) $seccion->periodo_academico_id === (int) $periodo->id
                && $seccion->carreras()->whereKey($carreraId)->where('carreras.universidad_id', $periodo->universidad_id)->exists(), 404);
            if (! $periodo->estado || ! $periodo->universidad?->estado || ! $version->es_actual
                || $version->estado !== 'Borrador' || $version->tipo !== 'semestral'
                || ! $grupo->estado_activo || ! $actividad->estado || ! $seccion->estado_activo) {
                $this->rechazar('programacion', 'Los cambios se guardan en el borrador actual de un período activo y en grupos habilitados.');
            }

            return $operacion($grupo, $version, $periodo);
        }, 3);
    }

    public function docentesPlantilla(Models\GrupoSeccion $grupo): Collection
    {
        $docentes = $grupo->profesores()->where('estado', true)->orderByDesc('es_principal')->orderBy('id')->with('profesor')->get();

        return $docentes->isNotEmpty() ? $docentes : $grupo->actividadSeccion->seccion->profesores()
            ->where('estado', true)->orderByDesc('es_principal')->orderBy('id')->with('profesor')->get();
    }

    public function asignarDocente(Models\GrupoSeccion $grupo, Models\VersionPlanificacion $version, int $profesorId, Models\User $usuario): void
    {
        $profesor = Models\Profesor::lockForUpdate()->find($profesorId);
        if (! $profesor?->estado || (int) $profesor->universidad_id !== (int) $version->periodoAcademico->universidad_id) {
            $this->rechazar('profesor_id', 'Elige un docente activo de la institución del período.');
        }
        $previa = $this->docentesPlantilla($grupo);
        $principal = $previa->first();
        if ((int) $principal?->profesor_id === $profesorId && $grupo->profesores()->where('estado', true)->where('es_principal', true)->where('profesor_id', $profesorId)->exists()) return;
        $responsableAnterior = $principal ? (int) $principal->profesor_id : $this->responsableEnModulos($grupo, $version);
        // Al declarar una plantilla de grupo, conservar los docentes que hasta entonces heredaba de la sección.
        foreach ($previa as $docente) {
            $grupo->profesores()->updateOrCreate(['profesor_id' => $docente->profesor_id],
                $docente->only(['tipo_participacion', 'observaciones']) + ['estado' => true]);
        }
        $grupo->profesores()->where('es_principal', true)->update(['es_principal' => false]);
        $grupo->profesores()->updateOrCreate(['profesor_id' => $profesorId],
            ['tipo_participacion' => 'PROFESOR RESPONSABLE', 'es_principal' => true, 'estado' => true]);
        if ($responsableAnterior !== $profesorId) {
            $this->invalidarConfirmacion($grupo, $version);
        }
        $this->auditar('Asignar responsable desde Panel de carrera', 'grupos_seccion', $grupo->id, $usuario, [
            'version_id' => $version->id, 'anterior' => $principal?->profesor_id, 'profesor_id' => $profesorId,
        ]);
    }

    public function responsableGrupo(Models\GrupoSeccion $grupo, Models\VersionPlanificacion $version): ?int
    {
        $responsable = $this->docentesPlantilla($grupo)->first()?->profesor_id;

        return $responsable ? (int) $responsable : $this->responsableEnModulos($grupo, $version);
    }

    private function responsableEnModulos(Models\GrupoSeccion $grupo, Models\VersionPlanificacion $version): ?int
    {
        $directos = $grupo->horarios()->where('version_planificacion_id', $version->id)->where('estado', true)
            ->with(['profesores' => fn ($query) => $query->where('estado', true)->orderByDesc('es_principal')->orderBy('id')])->get()
            ->map(fn ($horario) => $horario->profesores->first()?->profesor_id)->unique();

        return $directos->count() === 1 && $directos->first() ? (int) $directos->first() : null;
    }

    public function invalidarConfirmacion(Models\GrupoSeccion $grupo, Models\VersionPlanificacion $version): void
    {
        DB::table('confirmaciones_grupo_programacion')->where('version_planificacion_id', $version->id)
            ->where('grupo_seccion_id', $grupo->id)->delete();
    }

    public function confirmar(Models\GrupoSeccion $grupo, Models\VersionPlanificacion $version, int $profesorId, bool $confirmado, Models\User $usuario): void
    {
        $responsable = $this->responsableGrupo($grupo, $version);
        $docente = Models\Profesor::find($profesorId);
        if (! $docente?->estado || (int) $responsable !== $profesorId
            || (int) $docente->universidad_id !== (int) $version->periodoAcademico->universidad_id) {
            $this->rechazar('profesor_id', 'El responsable del grupo cambió. Revisa quién dicta el grupo antes de confirmar.');
        }
        $clave = ['version_planificacion_id' => $version->id, 'grupo_seccion_id' => $grupo->id];
        if ($confirmado) {
            $anterior = DB::table('confirmaciones_grupo_programacion')->where($clave)->value('profesor_id');
            if ((int) $anterior === $profesorId) return;
            DB::table('confirmaciones_grupo_programacion')->upsert([
                $clave + ['profesor_id' => $profesorId, 'usuario_id' => $usuario->id, 'created_at' => now(), 'updated_at' => now()],
            ], array_keys($clave), ['profesor_id', 'usuario_id', 'updated_at']);
        } elseif (! DB::table('confirmaciones_grupo_programacion')->where($clave)->where('profesor_id', $profesorId)->delete()) {
            return;
        }
        $this->auditar($confirmado ? 'Confirmar docente del grupo' : 'Retirar confirmación del docente', 'grupos_seccion', $grupo->id, $usuario,
            ['version_id' => $version->id, 'profesor_id' => $profesorId]);
    }

    public function espacio(Models\Horario $referencia, Models\GrupoSeccion $grupo, Models\VersionPlanificacion $version, Models\PeriodoAcademico $periodo, string $seleccion, Models\User $usuario): void
    {
        $horario = Models\Horario::lockForUpdate()->findOrFail($referencia->id);
        abort_unless((int) $horario->version_planificacion_id === (int) $version->id
            && (int) $horario->grupo_seccion_id === (int) $grupo->id
            && (int) $horario->actividad_seccion_id === (int) $grupo->actividad_seccion_id && $horario->estado, 404);
        $actual = $horario->load(['bloqueHorario', 'asignacionesSalas', 'asignacionesLaboratorios', 'asignacionesLaboratoriosMoviles']);
        $rango = IntervalosHorario::deBloque($actual->bloqueHorario);
        if (! $rango) $this->rechazar('espacio', 'Corrige las horas del módulo antes de asignar un espacio.');
        if (Models\SolicitudCambioHorario::where('horario_id', $horario->id)->where('estado_activo', true)
            ->whereIn('estado', ['Pendiente de respuesta', 'Respondida'])->exists()) {
            $this->rechazar('espacio', 'La clase tiene una solicitud de cambio abierta. Resuélvela antes de reasignar sus espacios.');
        }
        foreach (['asignacionesSalas', 'asignacionesLaboratorios', 'asignacionesLaboratoriosMoviles'] as $relacion) {
            if ($actual->$relacion->where('estado', true)->count() > 1) {
                $this->rechazar('espacio', 'La clase reparte su capacidad entre varios espacios. Conserva esa distribución y revisa sus requerimientos.');
            }
        }
        $carga = app(GeneracionCargaPendiente::class);
        $docentes = $carga->docentesEfectivos($horario)->loadMissing('profesor');
        $grupo->loadMissing(['actividadSeccion.grupos', 'actividadSeccion.requerimientos' => fn ($q) => $q->where('estado', true)->where('obligatorio', true),
            'actividadSeccion.requerimientos.tipoSala', 'actividadSeccion.requerimientos.equipamiento', 'actividadSeccion.seccion.carreras.sedes', 'actividadSeccion.seccion.asignatura']);
        $sedes = $carga->sedesPermitidas($grupo, Models\Sede::where('universidad_id', $periodo->universidad_id)->where('estado', true)->get());
        $salas = Models\Sala::where('universidad_id', $periodo->universidad_id)->whereIn('sede_id', $sedes->modelKeys())
            ->where('estado', true)->where('disponible', true)->with('laboratorios', 'tipoSala', 'sede')->orderBy('id')->lockForUpdate()->get();
        $moviles = Models\LaboratorioMovil::where('universidad_id', $periodo->universidad_id)->whereIn('sede_id', $sedes->modelKeys())
            ->where('estado', true)->where('disponible', true)->orderBy('id')->lockForUpdate()->get();
        $inventario = Models\Equipamiento::where('universidad_id', $periodo->universidad_id)->with('ubicaciones')->get()->keyBy('id');
        $reglas = Models\PrioridadRecurso::where('estado', true)->where('asignatura_id', $grupo->actividadSeccion->seccion->asignatura_id)->with('tipoSala')->get();
        $revision = new RevisionRecursosProgramacion((int) $periodo->universidad_id, new RevisionEquipamientoProgramacion($inventario));
        $prioridades = new PrioridadUsoRecursos($reglas, $inventario);
        $intentos = 0;
        $paquetes = [];
        foreach ($sedes as $sede) {
            $paquetes += app(EspaciosCargaPendiente::class)->candidatos($grupo, $sede->id, $docentes, $salas, $moviles, $revision, $prioridades, $inventario, $intentos);
        }
        $ocupados = $this->espaciosOcupados($horario, $rango);
        [$tipo, $id] = array_pad(explode(':', $seleccion, 2), 2, null);
        $campo = ['sala' => 'sala_id', 'laboratorio' => 'laboratorio_id', 'movil' => 'movil_id'][$tipo] ?? null;
        $libres = collect($paquetes)->filter(fn ($p) => ! array_intersect($p['recursos'], $ocupados));
        $opcion = $libres->filter(fn ($p) => ! $campo || (int) $p[$campo] === (int) $id)
            ->sortBy(fn ($p) => [$p['prioridad'], $p['preferencia'], $p['nombre']])->first();
        if (! $opcion) $this->rechazar('espacio', 'No hay un espacio libre para este módulo que cumpla sede, aforo y requerimientos. Prueba otro espacio o módulo.');

        $motivoMovil = match (true) {
            ! $opcion['movil_id'] => null,
            $campo === 'movil_id' => 'Laboratorio móvil elegido para este módulo.',
            $opcion['laboratorio_id'] || $libres->contains(fn ($p) => $p['laboratorio_id'] && ! $p['movil_id']) => 'Asignado según los requerimientos y prioridades del grupo.',
            default => 'Alternativa compatible para el módulo según los requerimientos y los espacios libres.',
        };
        $previos = [];
        foreach (['asignacionesSalas' => 'sala_id', 'asignacionesLaboratorios' => 'laboratorio_id', 'asignacionesLaboratoriosMoviles' => 'movil_id'] as $relacion => $atributo) {
            $previos[$relacion] = $actual->$relacion->where('estado', true)->pluck($atributo === 'movil_id' ? 'laboratorio_movil_id' : $atributo)->all();
            $fk = $atributo === 'movil_id' ? 'laboratorio_movil_id' : $atributo;
            $actual->$relacion()->where('estado', true)->when($opcion[$atributo], fn ($q, $id) => $q->where($fk, '!=', $id))->update(['estado' => false]);
            if ($opcion[$atributo]) {
                $valores = ['capacidad_asignada' => $opcion['cantidad'], 'es_principal' => true, 'estado' => true];
                if ($atributo === 'movil_id') {
                    $valores['observaciones'] = $actual->$relacion->firstWhere($fk, $opcion[$atributo])?->observaciones ?: $motivoMovil;
                }
                $actual->$relacion()->updateOrCreate([$fk => $opcion[$atributo]],
                    $valores);
            }
        }
        $this->auditar('Asignar espacio desde Panel de carrera', 'horarios', $horario->id, $usuario,
            ['version_id' => $version->id, 'antes' => $previos, 'despues' => $opcion]);
    }

    private function espaciosOcupados(Models\Horario $horario, array $rango): array
    {
        $clases = Models\Horario::where('version_planificacion_id', $horario->version_planificacion_id)->where('estado', true)
            ->whereKeyNot($horario->id)->where('dia_id', $horario->dia_id)->with([
                'bloqueHorario', 'asignacionesSalas' => fn ($q) => $q->where('estado', true),
                'asignacionesLaboratorios' => fn ($q) => $q->where('estado', true), 'asignacionesLaboratorios.laboratorio',
                'asignacionesLaboratoriosMoviles' => fn ($q) => $q->where('estado', true),
            ])->get();
        $ocupados = [];
        foreach ($clases as $clase) {
            $intervalo = IntervalosHorario::deBloque($clase->bloqueHorario);
            if ($intervalo && ! IntervalosHorario::seSuperponen($intervalo, $rango)) continue;
            foreach ($clase->asignacionesSalas as $sala) $ocupados[] = 'sala:'.$sala->sala_id;
            foreach ($clase->asignacionesLaboratorios as $lab) {
                $ocupados[] = 'laboratorio:'.$lab->laboratorio_id;
                $ocupados[] = 'sala:'.$lab->laboratorio?->sala_id;
            }
            foreach ($clase->asignacionesLaboratoriosMoviles as $movil) $ocupados[] = 'movil:'.$movil->laboratorio_movil_id;
        }

        return array_unique($ocupados);
    }

    private function auditar(string $accion, string $tabla, int $id, Models\User $usuario, array $datos): void
    {
        DB::table('historial_acciones')->insert([
            'usuario_id' => $usuario->id, 'accion' => $accion, 'modulo' => 'Panel de carrera',
            'tabla_afectada' => $tabla, 'registro_id' => $id, 'datos' => json_encode($datos, JSON_THROW_ON_ERROR),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function rechazar(string $campo, string $mensaje): never
    {
        throw ValidationException::withMessages([$campo => $mensaje]);
    }
}

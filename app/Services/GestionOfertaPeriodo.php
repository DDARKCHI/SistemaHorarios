<?php

namespace App\Services;

use App\Models;
use Illuminate\Support\Facades\{DB, Validator};
use Illuminate\Support\Str;
use Illuminate\Validation\{Rule, ValidationException};

class GestionOfertaPeriodo
{
    public const ACTIVIDADES = ['Cátedra' => 'horas_catedra', 'Laboratorio' => 'horas_laboratorio', 'Ayudantía' => 'horas_ayudantia',
        'Taller' => 'horas_taller', 'Clínica' => 'horas_clinica', 'Centro de práctica' => 'horas_practica', 'Otro' => 'horas_otros'];

    public function guardarSeccion(array $entrada, int $usuario, ?string $ip, ?int $id = null): Models\Seccion
    {
        $datos = $this->validar($entrada, [
            'periodo_academico_id' => ['required', 'integer', 'exists:periodos_academicos,id'], 'asignatura_id' => ['required', 'integer', 'exists:asignaturas,id'],
            'codigo' => [$id ? 'nullable' : 'required', 'string', 'max:30'], 'nombre' => ['nullable', 'string', 'max:100'],
            'semestre' => [$id ? 'nullable' : 'required', 'integer', 'between:1,255'], 'cupos' => ['nullable', 'integer', 'between:0,65535'],
            'inscritos' => ['required', 'integer', 'between:0,65535'], 'estado_activo' => ['required', 'boolean'],
            'carreras' => ['required', 'array', 'min:1', 'max:100'], 'carreras.*' => ['required', 'integer', 'distinct', 'exists:carreras,id'],
            'modalidad' => ['nullable', 'string', 'max:50'], 'categoria' => ['nullable', 'string', 'max:50'], 'tipo_dictacion' => ['nullable', 'string', 'max:50'],
            'usar_referencia' => ['nullable', 'boolean'], 'revision' => [$id ? 'required' : 'nullable', 'string', 'size:64'],
        ]);
        return DB::transaction(function () use ($datos, $id, $usuario, $ip) {
            $periodo = $this->periodoEditable((int) $datos['periodo_academico_id']);
            $ramo = Models\Asignatura::lockForUpdate()->findOrFail($datos['asignatura_id']);
            $seccion = $id ? Models\Seccion::lockForUpdate()->findOrFail($id) : new Models\Seccion(['estado' => 'Pendiente']);
            if ($id) $this->comprobarRevision($seccion, $datos['revision']);
            if ($id && ((int) $seccion->periodo_academico_id !== (int) $periodo->id || (int) $seccion->asignatura_id !== (int) $ramo->id)) $this->rechazar('seccion', 'El período y el ramo de una sección existente deben conservarse.');
            if ((int) $ramo->universidad_id !== (int) $periodo->universidad_id || (! $ramo->estado && (! $id || (! $seccion->estado_activo && $datos['estado_activo'])))) $this->rechazar('asignatura_id', 'Selecciona un ramo activo de la institución del período.');
            $carrerasAntes = $id ? $seccion->carreras()->pluck('carreras.id')->map(fn ($v) => (int) $v)->all() : [];
            $carrerasNuevas = collect($datos['carreras'])->map(fn ($v) => (int) $v)->sort()->values()->all();
            $nivelPropuesto = array_key_exists('semestre', $datos) ? $datos['semestre'] : $seccion->semestre;
            foreach (Models\Carrera::whereIn('id', $carrerasNuevas)->orderBy('id')->lockForUpdate()->get() as $c) {
                if ((int) $c->universidad_id !== (int) $periodo->universidad_id || (! $c->estado && ! in_array((int) $c->id, $carrerasAntes, true))) $this->rechazar('carreras', 'Las carreras deben pertenecer a la institución y estar activas; puedes conservar los vínculos inactivos anteriores.');
                $conservaNivel = $id && in_array((int) $c->id, $carrerasAntes, true) && (int) $nivelPropuesto === (int) $seccion->semestre;
                if (! $conservaNivel && $c->nivelesCarrera()->exists() && ! $c->nivelesCarrera()->where('numero', $nivelPropuesto)->where('estado', true)->exists()) $this->rechazar('semestre', 'El semestre debe estar activo en cada carrera seleccionada. Contacta con administración para revisar su configuración.');
            }
            $antes = $id ? $seccion->getAttributes() : null;
            $seccion->fill(collect($datos)->except(['carreras', 'usar_referencia', 'revision'])->all());
            if ((! $id || $seccion->isDirty('codigo')) && filled($seccion->codigo) && Models\Seccion::where('periodo_academico_id', $periodo->id)->where('asignatura_id', $ramo->id)
                ->when($id, fn ($q) => $q->whereKeyNot($id))->whereRaw('LOWER(TRIM(codigo)) = ?', [mb_strtolower($seccion->codigo)])->exists()) $this->rechazar('codigo', 'Ya existe esta sección para el ramo y período, incluso entre las inactivas.');
            $this->comprobarCupos($seccion, ! $id);
            if ($id) {
                $horarios = Models\Horario::whereHas('actividadSeccion', fn ($a) => $a->where('seccion_id', $id));
                $tieneUso = $horarios->exists() || $seccion->profesores()->exists() || Models\GrupoProfesor::whereHas('grupoSeccion.actividadSeccion', fn ($a) => $a->where('seccion_id', $id))->exists();
                if ($tieneUso && ($seccion->isDirty(['codigo', 'semestre']) || array_diff($carrerasAntes, $carrerasNuevas))) $this->rechazar('seccion', 'La sección ya tiene horarios o docencia. Conserva código, semestre y carreras anteriores; puedes añadir carreras.');
                if (! $seccion->estado_activo && $this->horariosVigentes(clone $horarios)->exists()) $this->rechazar('estado_activo', 'Retira primero los horarios activos de la sección en la versión actual.');
                if (($seccion->isDirty() || array_diff($carrerasAntes, $carrerasNuevas) || array_diff($carrerasNuevas, $carrerasAntes)) && $this->horariosCerrados(clone $horarios)->exists()) $this->rechazar('seccion', 'La sección participa en una versión aprobada o publicada. Su definición se conserva para proteger ese cierre.');
                if ($seccion->isDirty(['cupos', 'inscritos']) && (int) $seccion->cupos > 0 && $seccion->grupos()->where('estado_activo', true)->where(fn ($g) => $g->where('cupos', '>', $seccion->cupos)->orWhere('inscritos', '>', $seccion->cupos))->exists()) $this->rechazar('cupos', 'Los cupos no pueden quedar por debajo de un grupo activo de la sección.');
                if ($seccion->isDirty('inscritos') && (int) $seccion->inscritos > 0 && $seccion->grupos()->where('estado_activo', true)->where('inscritos', '>', $seccion->inscritos)->exists()) $this->rechazar('inscritos', 'La matrícula de la sección no puede quedar por debajo de los inscritos de un grupo activo.');
            }
            $sucio = $seccion->isDirty() || ! $id;
            if ($sucio) $seccion->save();
            $sincronizacion = $seccion->carreras()->sync($carrerasNuevas);
            $cambiaCarreras = count($sincronizacion['attached']) + count($sincronizacion['detached']) > 0;
            $actividadesCreadas = [];
            if (! $id && ($datos['usar_referencia'] ?? false)) foreach (self::ACTIVIDADES as $tipo => $campo) {
                if ((int) $ramo->$campo <= 0) continue;
                $a = $seccion->actividades()->create(['tipo' => $tipo, 'cantidad_horas' => $ramo->$campo, 'cantidad_estudiantes' => $seccion->inscritos ?: $seccion->cupos,
                    'modalidad' => $seccion->modalidad, 'estado' => true]);
                $a->grupos()->create(['codigo' => '1', 'numero' => 1, 'cupos' => $seccion->cupos, 'inscritos' => $seccion->inscritos, 'estado' => 'Activo', 'estado_activo' => true]);
                $actividadesCreadas[] = $a->id;
            }
            if ($sucio || $cambiaCarreras) $this->auditar($seccion, $id ? 'Actualizar sección' : 'Crear sección', $antes, $usuario, $ip,
                ['carreras_anteriores' => $carrerasAntes, 'carreras_actuales' => $carrerasNuevas, 'actividades_creadas' => $actividadesCreadas]);
            return $seccion;
        }, 3);
    }

    public function guardarActividad(int $seccionId, array $entrada, int $usuario, ?string $ip, ?int $id = null): Models\ActividadSeccion
    {
        $tipos = array_keys(self::ACTIVIDADES);
        if ($id) { $previa = Models\ActividadSeccion::findOrFail($id); abort_unless((int) $previa->seccion_id === $seccionId, 404); $tipos[] = $previa->tipo; }
        $datos = $this->validar($entrada, ['tipo' => ['required', 'string', Rule::in($tipos)], 'cantidad_horas' => ['required', 'integer', 'between:1,255'],
            'cantidad_estudiantes' => ['nullable', 'integer', 'between:0,65535'], 'modalidad' => ['nullable', 'string', 'max:50'],
            'observaciones' => ['nullable', 'string', 'max:2000'], 'estado' => ['required', 'boolean'], 'revision' => [$id ? 'required' : 'nullable', 'string', 'size:64']]);
        $referencia = Models\Seccion::findOrFail($seccionId);
        return DB::transaction(function () use ($referencia, $datos, $id, $usuario, $ip) {
            $periodo = $this->periodoEditable($referencia->periodo_academico_id);
            $seccion = Models\Seccion::lockForUpdate()->findOrFail($referencia->id);
            if (! $seccion->estado_activo) $this->rechazar('actividad', 'Activa primero la sección.');
            $actividad = $id ? $seccion->actividades()->lockForUpdate()->findOrFail($id) : new Models\ActividadSeccion(['seccion_id' => $seccion->id]);
            if ($id) $this->comprobarRevision($actividad, $datos['revision']);
            $antes = $id ? $actividad->getAttributes() : null;
            $actividad->fill(collect($datos)->except('revision')->all());
            if ((! $id || $actividad->isDirty('tipo')) && $seccion->actividades()->when($id, fn ($a) => $a->whereKeyNot($id))->whereRaw('LOWER(TRIM(tipo)) = ?', [mb_strtolower($actividad->tipo)])->exists()) $this->rechazar('tipo', 'Esta actividad ya existe en la sección, incluso entre las inactivas.');
            if ($actividad->isDirty('cantidad_estudiantes') && (int) $seccion->cupos > 0 && (int) $actividad->cantidad_estudiantes > (int) $seccion->cupos) $this->rechazar('cantidad_estudiantes', 'La cantidad no puede superar los cupos conocidos de la sección.');
            if ($id) {
                $horarios = $actividad->horarios();
                if ($actividad->isDirty() && $this->horariosCerrados(clone $horarios)->exists()) $this->rechazar('actividad', 'La actividad participa en una versión aprobada o publicada; conserva su definición.');
                if ($actividad->isDirty('tipo') && ($horarios->exists() || $actividad->grupos()->exists() || $actividad->requerimientos()->exists())) $this->rechazar('tipo', 'La actividad tiene grupos, horarios o requerimientos. Conserva su tipo.');
                if (! $actividad->estado && $this->horariosVigentes(clone $horarios)->exists()) $this->rechazar('estado', 'Retira primero los horarios activos de esta actividad en la versión actual.');
                if ($actividad->isDirty('cantidad_horas') && $this->horariosVigentes(clone $horarios)->select('grupo_seccion_id')->groupBy('grupo_seccion_id')->havingRaw('COUNT(*) > ?', [(int) $actividad->cantidad_horas])->exists()) $this->rechazar('cantidad_horas', 'Hay un grupo con más módulos asignados que la carga propuesta. Retira primero esos módulos o conserva la carga.');
            }
            if ($actividad->isDirty() || ! $id) { $actividad->save(); $this->auditar($actividad, $id ? 'Actualizar actividad' : 'Crear actividad', $antes, $usuario, $ip); }
            return $actividad;
        }, 3);
    }

    public function guardarGrupo(int $actividadId, array $entrada, int $usuario, ?string $ip, ?int $id = null): Models\GrupoSeccion
    {
        $datos = $this->validar($entrada, ['codigo' => ['required', 'string', 'max:20'], 'nombre' => ['nullable', 'string', 'max:255'],
            'numero' => ['required', 'integer', 'between:1,65535'], 'cupos' => ['nullable', 'integer', 'between:0,65535'], 'inscritos' => ['required', 'integer', 'between:0,65535'],
            'estado_activo' => ['required', 'boolean'], 'revision' => [$id ? 'required' : 'nullable', 'string', 'size:64']]);
        $referencia = Models\ActividadSeccion::with('seccion')->findOrFail($actividadId);
        return DB::transaction(function () use ($referencia, $datos, $id, $usuario, $ip) {
            $this->periodoEditable($referencia->seccion->periodo_academico_id);
            $seccion = Models\Seccion::lockForUpdate()->findOrFail($referencia->seccion_id);
            $actividad = Models\ActividadSeccion::lockForUpdate()->findOrFail($referencia->id);
            if (! $actividad->estado || ! $seccion->estado_activo) $this->rechazar('grupo', 'Activa primero la sección y la actividad.');
            $grupo = $id ? $actividad->grupos()->lockForUpdate()->findOrFail($id) : new Models\GrupoSeccion(['actividad_seccion_id' => $actividad->id, 'estado' => 'Activo']);
            if ($id) $this->comprobarRevision($grupo, $datos['revision']);
            $antes = $id ? $grupo->getAttributes() : null;
            $grupo->fill(collect($datos)->except('revision')->all());
            $this->comprobarCupos($grupo, ! $id);
            foreach (['codigo', 'numero'] as $campo) if ((! $id || $grupo->isDirty($campo)) && $actividad->grupos()->when($id, fn ($g) => $g->whereKeyNot($id))->whereRaw('LOWER(TRIM('.$campo.')) = ?', [mb_strtolower((string) $grupo->$campo)])->exists()) $this->rechazar($campo, 'Ya existe un grupo con este '.($campo === 'codigo' ? 'código' : 'número').' en la actividad, incluso entre los inactivos.');
            if (($grupo->isDirty(['cupos', 'inscritos']) || ! $id) && (int) $seccion->cupos > 0 && max((int) $grupo->cupos, (int) $grupo->inscritos) > (int) $seccion->cupos) $this->rechazar('cupos', 'Los cupos e inscritos del grupo no pueden superar los cupos conocidos de la sección.');
            if (($grupo->isDirty('inscritos') || ! $id) && (int) $seccion->inscritos > 0 && (int) $grupo->inscritos > (int) $seccion->inscritos) $this->rechazar('inscritos', 'Los inscritos del grupo no pueden superar la matrícula informada de la sección.');
            if ($id) {
                $horarios = $grupo->horarios();
                if ($grupo->isDirty() && $this->horariosCerrados(clone $horarios)->exists()) $this->rechazar('grupo', 'El grupo participa en una versión aprobada o publicada; conserva su definición.');
                if ($grupo->isDirty(['codigo', 'numero']) && ($horarios->exists() || $grupo->profesores()->exists())) $this->rechazar('codigo', 'Este grupo tiene horarios o docentes vinculados. Conserva su código y número.');
                if (! $grupo->estado_activo && $this->horariosVigentes(clone $horarios)->exists()) $this->rechazar('estado_activo', 'Retira primero los horarios activos del grupo en la versión actual.');
            }
            if ($grupo->isDirty() || ! $id) { $grupo->save(); $this->auditar($grupo, $id ? 'Actualizar grupo' : 'Crear grupo', $antes, $usuario, $ip); }
            return $grupo;
        }, 3);
    }

    public function iniciarBorrador(int $periodoId, int $usuario, ?string $ip): Models\VersionPlanificacion
    {
        return DB::transaction(function () use ($periodoId, $usuario, $ip) {
            $periodo = $this->periodoEditable($periodoId);
            if ($periodo->versionesPlanificacion()->where('tipo', 'semestral')->where('es_actual', true)->exists()) $this->rechazar('version', 'Ya existe una programación semestral actual. Continúa con ella o utiliza la copia para ajustes.');
            if (! $periodo->secciones()->where('estado_activo', true)->whereHas('actividades', fn ($a) => $a->where('estado', true)->whereHas('grupos', fn ($g) => $g->where('estado_activo', true)))->exists()) $this->rechazar('version', 'Prepara al menos una sección con actividad y grupo activos antes de iniciar el borrador.');
            $ultima = $periodo->versionesPlanificacion()->where('tipo', 'semestral')->pluck('version')->map(fn ($v) => (float) $v)->max() ?? 0;
            $nueva = $periodo->versionesPlanificacion()->create(['nombre' => 'Programación semestral', 'tipo' => 'semestral', 'version' => (round($ultima * 10) + 1) / 10,
                'descripcion' => 'Borrador iniciado desde la oferta académica del período, sin horarios asignados.', 'estado' => 'Borrador', 'es_actual' => true, 'usuario_id' => $usuario]);
            $this->auditar($nueva, 'Iniciar borrador desde oferta', null, $usuario, $ip);
            return $nueva;
        }, 3);
    }

    public function firma($registro): string
    {
        $datos = $registro->getAttributes(); ksort($datos);
        if ($registro instanceof Models\Seccion) $datos['carreras'] = $registro->carreras()->orderBy('carreras.id')->pluck('carreras.id')->map(fn ($v) => (int) $v)->all();
        return hash('sha256', json_encode($datos, JSON_THROW_ON_ERROR));
    }
    private function comprobarRevision($registro, string $firma): void { if (! hash_equals($this->firma($registro), $firma)) $this->rechazar('revision', 'Los datos cambiaron desde que abriste el formulario. Actualiza la página antes de guardar.'); }
    private function comprobarCupos($registro, bool $nuevo): void
    {
        if (($nuevo || $registro->isDirty(['cupos', 'inscritos'])) && (int) $registro->cupos > 0 && (int) $registro->inscritos > (int) $registro->cupos) $this->rechazar('inscritos', 'Los inscritos no pueden superar los cupos informados.');
    }
    private function periodoEditable(int $id): Models\PeriodoAcademico
    {
        $periodo = Models\PeriodoAcademico::lockForUpdate()->findOrFail($id);
        $actual = $periodo->versionesPlanificacion()->where('tipo', 'semestral')->where('es_actual', true)->latest('id')->lockForUpdate()->first();
        if (! $periodo->estado || ! $periodo->universidad->estado || ($actual && $actual->estado !== 'Borrador')) $this->rechazar('periodo', 'La oferta se prepara en un período activo con programación actual en borrador, o todavía sin versión. Para ajustar una publicación, crea primero un borrador.');
        return $periodo;
    }
    private function horariosVigentes($consulta) { return $consulta->where('estado', true)->whereHas('versionPlanificacion', fn ($v) => $v->where('tipo', 'semestral')->where('es_actual', true)); }
    private function horariosCerrados($consulta) { return $consulta->whereHas('versionPlanificacion', fn ($v) => $v->whereIn('estado', ['Aprobado', 'Publicado'])); }
    private function validar(array $datos, array $reglas): array
    {
        foreach ($datos as $campo => $valor) if (is_string($valor) && ! in_array($campo, ['revision', 'observaciones'], true)) {
            $normalizado = Str::squish($valor);
            $datos[$campo] = $normalizado === '' ? null : $normalizado;
        }
        return Validator::make($datos, $reglas)->validate();
    }
    private function auditar($registro, string $accion, ?array $antes, int $usuario, ?string $ip, array $extras = []): void
    {
        DB::table('historial_acciones')->insert(['usuario_id' => $usuario, 'accion' => $accion, 'modulo' => 'Oferta por período', 'tabla_afectada' => $registro->getTable(), 'registro_id' => $registro->id,
            'descripcion' => 'Gestión de oferta sin eliminar asignaciones ni versiones.', 'datos' => json_encode(['antes' => $antes, 'despues' => $registro->getAttributes()] + $extras, JSON_THROW_ON_ERROR),
            'direccion_ip' => $ip, 'created_at' => now(), 'updated_at' => now()]);
    }
    private function rechazar(string $campo, string $mensaje): never { throw ValidationException::withMessages([$campo => $mensaje]); }
}

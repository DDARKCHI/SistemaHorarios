<?php

namespace App\Services;

use App\Models\DisponibilidadProfesor;
use App\Models\GrupoProfesor;
use App\Models\GrupoSeccion;
use App\Models\Horario;
use App\Models\HorarioProfesor;
use App\Models\PeriodoAcademico;
use App\Models\Profesor;
use App\Models\ProfesorVersion;
use App\Models\SeccionProfesor;
use App\Models\VersionPlanificacion;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class EvaluacionReemplazoDocente
{
    public function contexto(bool $bloquear = false): array
    {
        $periodo = PeriodoAcademico::where('estado', true)->orderByDesc('anio')->orderByDesc('numero_semestre')->orderByDesc('id')
            ->when($bloquear, fn ($query) => $query->lockForUpdate())->first();
        $version = $periodo ? VersionPlanificacion::where('periodo_academico_id', $periodo->id)->where('tipo', 'semestral')
            ->where('es_actual', true)->orderByDesc('id')->when($bloquear, fn ($query) => $query->lockForUpdate())->first() : null;

        return [$periodo, $version];
    }

    public function modulos(PeriodoAcademico $periodo, VersionPlanificacion $version): Builder
    {
        return Horario::where('estado', true)->where('version_planificacion_id', $version->id)
            ->whereHas('actividadSeccion', fn ($query) => $query->where('estado', true)->whereHas('seccion', fn ($query) => $query
                ->where('periodo_academico_id', $periodo->id)->where('estado_activo', true)));
    }

    public function relaciones(): array
    {
        return ['dia', 'bloqueHorario', 'grupoSeccion', 'actividadSeccion.seccion.asignatura'];
    }

    public function docentes(PeriodoAcademico $periodo, VersionPlanificacion $version): Builder
    {
        return Profesor::where('universidad_id', $periodo->universidad_id)->where('estado', true)
            ->whereHas('horarios', fn ($query) => $query->where('estado', true)->whereIn('horario_id', $this->modulos($periodo, $version)->select('horarios.id')))
            ->orderBy('apellido')->orderBy('nombre')->orderBy('id');
    }

    public function carga(Profesor $profesor, PeriodoAcademico $periodo, VersionPlanificacion $version): Collection
    {
        return $this->modulos($periodo, $version)->whereHas('profesores', fn ($query) => $query->where('profesor_id', $profesor->id)->where('estado', true))
            ->with($this->relaciones())->orderBy('dia_id')->orderBy('bloque_horario_id')->orderBy('id')->get();
    }

    public function buscar(string $texto, Profesor $origen, PeriodoAcademico $periodo): Builder
    {
        $consulta = Profesor::where('universidad_id', $periodo->universidad_id)->where('estado', true)->whereKeyNot($origen->id);
        $texto = trim($texto);
        $literal = fn ($valor) => '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $valor).'%';
        if (preg_match('/^[\d.kK\s-]+$/u', $texto) && preg_match('/\d/', $texto)) {
            $rut = mb_strtolower(preg_replace('/[.\s-]+/u', '', $texto));
            $consulta->whereRaw("LOWER(REPLACE(REPLACE(REPLACE(rut, '.', ''), '-', ''), ' ', '')) LIKE ? ESCAPE '!'", [$literal($rut)]);
        } else {
            foreach (preg_split('/\s+/u', mb_strtolower(Str::ascii($texto)), -1, PREG_SPLIT_NO_EMPTY) as $palabra) {
                $consulta->where(function ($query) use ($literal, $palabra) {
                    foreach (['nombre', 'apellido'] as $columna) {
                        $expresion = $columna;
                        foreach (['Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ü' => 'U', 'Ñ' => 'N', 'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n'] as $de => $a) {
                            $expresion = "REPLACE($expresion, '$de', '$a')";
                        }
                        $query->orWhereRaw("LOWER($expresion) LIKE ? ESCAPE '!'", [$literal($palabra)]);
                    }
                });
            }
        }

        return $consulta->orderBy('apellido')->orderBy('nombre')->orderBy('id');
    }

    public function evaluar(Profesor $candidato, Collection $objetivo, PeriodoAcademico $periodo, VersionPlanificacion $version): array
    {
        $carga = $this->carga($candidato, $periodo, $version);
        $disponibilidad = DisponibilidadProfesor::where('profesor_id', $candidato->id)->where('periodo_academico_id', $periodo->id)->where('estado', true)
            ->with('bloqueHorario')->orderBy('dia_id')->get();
        $filas = $objetivo->map(function ($modulo) use ($carga, $disponibilidad) {
            $rango = $this->rango($modulo);
            $conflictos = $carga->filter(fn ($otro) => (int) $otro->id !== (int) $modulo->id && $this->coincide($modulo, $otro))->values();
            $declarada = $disponibilidad->where('dia_id', $modulo->dia_id);
            $disponible = $disponibilidad->isEmpty() ? null : ($rango !== null && $this->cubre($rango, $declarada));

            return ['horario' => $modulo, 'conflictos' => $conflictos, 'disponible' => $disponible, 'hora_valida' => $rango !== null];
        });
        $topes = $filas->filter(fn ($fila) => $fila['conflictos']->isNotEmpty())->count();
        $fuera = $filas->filter(fn ($fila) => $fila['disponible'] === false)->count();
        $incompletos = $filas->filter(fn ($fila) => ! $fila['hora_valida'])->count();
        $horasCargaIncompletas = $carga->contains(fn ($modulo) => $this->rango($modulo) === null);
        $compatible = $objetivo->isNotEmpty() && $topes === 0 && $fuera === 0 && $incompletos === 0 && ! $horasCargaIncompletas;

        return compact('candidato', 'carga', 'disponibilidad', 'filas', 'topes', 'fuera', 'compatible')
            + ['sin_disponibilidad' => $disponibilidad->isEmpty(), 'horas_incompletas' => $incompletos > 0 || $horasCargaIncompletas,
                'cubiertos' => $filas->filter(fn ($fila) => $fila['disponible'] === true)->count()];
    }

    public function huella(Collection $objetivo): string
    {
        return hash('sha256', json_encode($objetivo->sortBy('id')->map(fn ($h) => [
            $h->id, $h->actividad_seccion_id, $h->grupo_seccion_id, $h->dia_id, $h->bloque_horario_id,
            $h->bloqueHorario?->hora_inicio, $h->bloqueHorario?->hora_fin,
        ])->values()->all(), JSON_THROW_ON_ERROR));
    }

    public function aplicar(array $datos, int $usuarioId, ?string $ip): int
    {
        return DB::transaction(function () use ($datos, $usuarioId, $ip) {
            [$periodo, $version] = $this->contexto(true);
            if (! $version || (int) $version->id !== (int) $datos['version_id'] || (int) $periodo->id !== (int) $datos['periodo_id'] || $version->estado !== 'Borrador') {
                $this->error('La programación cambió o ya no está en borrador. Actualiza la comparación.');
            }
            $profesores = Profesor::whereIn('id', [$datos['docente_id'], $datos['candidato_id']])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $origen = $profesores->get($datos['docente_id']);
            $candidato = $profesores->get($datos['candidato_id']);
            if (! $origen || ! $candidato || $origen->id === $candidato->id || ! $origen->estado || ! $candidato->estado
                || (int) $origen->universidad_id !== (int) $periodo->universidad_id || (int) $candidato->universidad_id !== (int) $periodo->universidad_id) {
                $this->error('Los docentes deben estar activos y pertenecer a la institución del período.');
            }
            $grupo = GrupoSeccion::whereKey($datos['grupo_id'])->where('estado_activo', true)->lockForUpdate()->first();
            if (! $grupo) {
                $this->error('La clase seleccionada ya no está activa.');
            }
            HorarioProfesor::whereIn('profesor_id', [$origen->id, $candidato->id])->orderBy('id')->lockForUpdate()->get();
            $objetivo = $this->carga($origen, $periodo, $version)->where('grupo_seccion_id', $grupo->id)->values();
            if ($objetivo->isEmpty() || ! hash_equals($this->huella($objetivo), $datos['huella'])) {
                $this->error('La clase cambió desde la comparación. Revisa sus módulos nuevamente.');
            }
            if ($objetivo->contains(fn ($h) => (int) $h->actividad_seccion_id !== (int) $grupo->actividad_seccion_id)) {
                $this->error('La actividad y el grupo de la clase no coinciden. Revisa la programación.');
            }
            $evaluacion = $this->evaluar($candidato, $objetivo, $periodo, $version);
            if (! $evaluacion['compatible']) {
                $this->error('El candidato ya no es compatible con los módulos de esta clase. Revisa la comparación.');
            }
            if ($evaluacion['sin_disponibilidad'] && empty($datos['confirmar_disponibilidad'])) {
                $this->error('Confirma la disponibilidad con el docente antes de aplicar el reemplazo.');
            }
            $vinculos = HorarioProfesor::whereIn('horario_id', $objetivo->pluck('id'))->where('profesor_id', $origen->id)->where('estado', true)->get();
            foreach ($vinculos as $vinculo) {
                HorarioProfesor::updateOrCreate(['horario_id' => $vinculo->horario_id, 'profesor_id' => $candidato->id], [
                    'tipo_participacion' => $vinculo->tipo_participacion, 'es_principal' => $vinculo->es_principal, 'estado' => true,
                ]);
                $vinculo->update(['estado' => false]);
            }
            $seccionId = $grupo->actividadSeccion->seccion_id;
            foreach ([
                [GrupoProfesor::class, 'grupo_seccion_id', $grupo->id],
                [SeccionProfesor::class, 'seccion_id', $seccionId],
            ] as [$modelo, $campo, $id]) {
                $anterior = $modelo::where($campo, $id)->where('profesor_id', $origen->id)->first();
                $nuevo = $modelo::firstOrCreate([$campo => $id, 'profesor_id' => $candidato->id], [
                    'tipo_participacion' => $anterior?->tipo_participacion ?: $vinculos->first()->tipo_participacion,
                    'es_principal' => (bool) ($anterior?->es_principal ?? $vinculos->first()->es_principal), 'estado' => true,
                ]);
                $principal = (bool) $nuevo->es_principal || (bool) ($anterior?->es_principal ?? $vinculos->first()->es_principal);
                $nuevo->update(['estado' => true, 'es_principal' => $principal]);
                $restante = $this->modulos($periodo, $version)->whereHas('profesores', fn ($query) => $query->where('profesor_id', $origen->id)->where('estado', true));
                $restante = $campo === 'grupo_seccion_id' ? $restante->where('grupo_seccion_id', $id)
                    : $restante->whereHas('actividadSeccion', fn ($query) => $query->where('seccion_id', $id));
                if ($anterior && ! $restante->exists()) {
                    $anterior->update(['estado' => false]);
                }
            }
            ProfesorVersion::firstOrCreate(['version_planificacion_id' => $version->id, 'profesor_id' => $candidato->id],
                $candidato->only(['rut', 'nombre', 'apellido', 'correo_institucional', 'correo_personal', 'tipo_contrato', 'tipo_profesor', 'unidad_academica']));
            DB::table('historial_acciones')->insert([
                'usuario_id' => $usuarioId, 'accion' => 'Reemplazo docente', 'modulo' => 'Intercambio docente',
                'tabla_afectada' => 'grupos_seccion', 'registro_id' => $grupo->id,
                'descripcion' => 'Sustitución del docente en los módulos activos seleccionados de la versión semestral.',
                'datos' => json_encode(['periodo_id' => $periodo->id, 'version_id' => $version->id, 'origen_id' => $origen->id,
                    'candidato_id' => $candidato->id, 'horarios_ids' => $objetivo->pluck('id')->all(),
                    'disponibilidad_confirmada' => (bool) ($datos['confirmar_disponibilidad'] ?? false)], JSON_THROW_ON_ERROR),
                'direccion_ip' => $ip, 'created_at' => now(), 'updated_at' => now(),
            ]);

            return $objetivo->count();
        }, 3);
    }

    private function rango($registro): ?array
    {
        $inicio = $this->hora($registro->bloqueHorario?->hora_inicio);
        $fin = $this->hora($registro->bloqueHorario?->hora_fin);
        if (! $inicio || ! $fin || $inicio >= $fin) {
            return null;
        }

        return [substr((string) $inicio, 0, 8), substr((string) $fin, 0, 8)];
    }

    private function hora(mixed $valor): ?string
    {
        if (! preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/D', (string) $valor, $partes)) {
            return null;
        }
        $hora = (int) $partes[1];
        $minuto = (int) $partes[2];
        $segundo = (int) ($partes[3] ?? 0);
        if ($hora > 23 || $minuto > 59 || $segundo > 59) {
            return null;
        }

        return sprintf('%02d:%02d:%02d', $hora, $minuto, $segundo);
    }

    private function coincide($primero, $segundo): bool
    {
        if ((int) $primero->dia_id !== (int) $segundo->dia_id) {
            return false;
        }
        $a = $this->rango($primero);
        $b = $this->rango($segundo);

        return ! $a || ! $b || ($a[0] < $b[1] && $b[0] < $a[1]);
    }

    private function cubre(array $objetivo, Collection $declaradas): bool
    {
        $rangos = [];
        foreach ($declaradas as $declarada) {
            $rango = $this->rango($declarada);
            if (! $rango) {
                continue;
            }
            if (mb_strtolower(trim((string) $declarada->tipo_disponibilidad)) !== 'disponible') {
                if ($rango[0] < $objetivo[1] && $objetivo[0] < $rango[1]) {
                    return false;
                }
            } else {
                $rangos[] = $rango;
            }
        }
        usort($rangos, fn ($a, $b) => strcmp($a[0], $b[0]));
        $cursor = $objetivo[0];
        foreach ($rangos as [$inicio, $fin]) {
            if ($inicio <= $cursor && $fin > $cursor) {
                $cursor = $fin;
            }
            if ($cursor >= $objetivo[1]) {
                return true;
            }
        }

        return false;
    }

    private function error(string $mensaje): never
    {
        throw ValidationException::withMessages(['reemplazo' => $mensaje]);
    }
}

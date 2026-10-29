<?php

namespace App\Services;

use App\Models\Horario;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class HistorialReemplazos
{
    public function consulta(): Builder
    {
        $consulta = DB::table('historial_acciones as h')
            ->where('h.accion', 'Reemplazo docente')->where('h.modulo', 'Intercambio docente')->where('h.tabla_afectada', 'grupos_seccion')
            ->leftJoin('users as u', 'u.id', '=', 'h.usuario_id')
            ->leftJoin('periodos_academicos as p', 'p.id', '=', DB::raw($this->jsonId('periodo_id')))
            ->leftJoin('versiones_planificacion as v', 'v.id', '=', DB::raw($this->jsonId('version_id')))
            ->leftJoin('profesores as po', 'po.id', '=', DB::raw($this->jsonId('origen_id')))
            ->leftJoin('profesores as pc', 'pc.id', '=', DB::raw($this->jsonId('candidato_id')))
            ->leftJoin('profesores_versiones as pvo', fn ($join) => $join
                ->on('pvo.profesor_id', '=', 'po.id')->on('pvo.version_planificacion_id', '=', DB::raw($this->jsonId('version_id'))))
            ->leftJoin('profesores_versiones as pvc', fn ($join) => $join
                ->on('pvc.profesor_id', '=', 'pc.id')->on('pvc.version_planificacion_id', '=', DB::raw($this->jsonId('version_id'))))
            ->leftJoin('grupos_seccion as g', 'g.id', '=', 'h.registro_id')
            ->leftJoin('actividades_seccion as a', 'a.id', '=', 'g.actividad_seccion_id')
            ->leftJoin('secciones as s', 's.id', '=', 'a.seccion_id')
            ->leftJoin('asignaturas as ramo', 'ramo.id', '=', 's.asignatura_id')
            ->select('h.*');
        foreach ($this->campos() as $alias => $expresion) {
            $consulta->selectRaw($expresion.' as '.$alias);
        }

        return $consulta;
    }

    public function filtrar(Builder $consulta, array $filtros): Builder
    {
        foreach (['periodo_id', 'version_id'] as $campo) {
            if (! empty($filtros[$campo])) {
                $consulta->whereRaw($this->jsonId($campo).' = ?', [(int) $filtros[$campo]]);
            }
        }
        if (! empty($filtros['usuario_id'])) {
            $consulta->where('h.usuario_id', $filtros['usuario_id']);
        }
        if (! empty($filtros['desde'])) {
            $consulta->where('h.created_at', '>=', $filtros['desde'].' 00:00:00');
        }
        if (! empty($filtros['hasta'])) {
            $consulta->where('h.created_at', '<', CarbonImmutable::createFromFormat('!Y-m-d', $filtros['hasta'])->addDay()->format('Y-m-d H:i:s'));
        }
        $texto = trim($filtros['q'] ?? '');
        if ($texto !== '') {
            $campos = $this->campos();
            if (preg_match('/^[\d.kK\s-]+$/u', $texto) && preg_match('/\d/', $texto)) {
                $rut = mb_strtolower(preg_replace('/[.\s-]+/u', '', $texto));
                $consulta->where(function ($query) use ($campos, $rut) {
                    foreach (['origen_rut', 'candidato_rut'] as $campo) {
                        $query->orWhereRaw("LOWER(REPLACE(REPLACE(REPLACE($campos[$campo], '.', ''), '-', ''), ' ', '')) LIKE ? ESCAPE '!'", [$this->literal($rut)]);
                    }
                });
            } else {
                foreach (preg_split('/\s+/u', mb_strtolower(Str::ascii($texto)), -1, PREG_SPLIT_NO_EMPTY) as $palabra) {
                    $consulta->where(function ($query) use ($campos, $palabra) {
                        foreach (['origen_nombre', 'origen_apellido', 'candidato_nombre', 'candidato_apellido',
                            'asignatura_codigo', 'asignatura_nombre', 'responsable'] as $campo) {
                            $expresion = $campos[$campo];
                            foreach (['Á' => 'a', 'É' => 'e', 'Í' => 'i', 'Ó' => 'o', 'Ú' => 'u', 'Ü' => 'u', 'Ñ' => 'n',
                                'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n'] as $de => $a) {
                                $expresion = "REPLACE($expresion, '$de', '$a')";
                            }
                            $query->orWhereRaw("LOWER($expresion) LIKE ? ESCAPE '!'", [$this->literal($palabra)]);
                        }
                    });
                }
            }
        }

        return $consulta;
    }

    public function presentar(object $fila, bool $conModulos = false): array
    {
        $datos = is_string($fila->datos) ? json_decode($fila->datos, true) : null;
        $datos = is_array($datos) ? $datos : [];
        $detalle = is_array($datos['detalle'] ?? null) ? $datos['detalle'] : [];
        $ids = array_values(array_unique(array_filter(is_array($datos['horarios_ids'] ?? null) ? $datos['horarios_ids'] : [],
            fn ($id) => is_int($id) && $id > 0)));
        $capturado = ($detalle['version_esquema'] ?? null) === 1;
        $completo = $capturado && is_array($detalle['origen'] ?? null) && is_array($detalle['candidato'] ?? null)
            && is_array($detalle['clase'] ?? null) && is_array($detalle['modulos'] ?? null) && $ids !== []
            && is_string($detalle['origen']['nombre'] ?? null) && is_string($detalle['candidato']['nombre'] ?? null)
            && is_string($detalle['clase']['asignatura_nombre'] ?? null)
            && Arr::has($detalle, ['periodo.nombre', 'version.nombre', 'version.version', 'responsable',
                'origen.apellido', 'origen.rut', 'candidato.apellido', 'candidato.rut',
                'clase.asignatura_codigo', 'clase.seccion', 'clase.actividad', 'clase.grupo', 'disponibilidad']);
        $idsConDetalle = [];
        foreach (is_array($detalle['modulos'] ?? null) ? $detalle['modulos'] : [] as $modulo) {
            if (is_array($modulo) && in_array($modulo['id'] ?? null, $ids, true)
                && $this->texto($modulo['dia'] ?? null) && $this->hora($modulo['hora_inicio'] ?? null) && $this->hora($modulo['hora_fin'] ?? null)) {
                $idsConDetalle[] = $modulo['id'];
            }
        }
        $completo = $completo && count(array_unique($idsConDetalle)) === count($ids);
        $nombre = fn ($prefijo) => trim(($fila->{$prefijo.'_nombre'} ?? '').' '.($fila->{$prefijo.'_apellido'} ?? ''));
        $modulos = [];
        $fuenteModulos = 'sin_detalle';
        if ($conModulos && $capturado && is_array($detalle['modulos'] ?? null)) {
            foreach ($detalle['modulos'] as $modulo) {
                if (! is_array($modulo) || ! in_array($modulo['id'] ?? null, $ids, true)) {
                    continue;
                }
                $modulos[$modulo['id']] = ['id' => $modulo['id'], 'dia' => $this->texto($modulo['dia'] ?? null),
                    'bloque' => $this->texto($modulo['bloque'] ?? null), 'hora_inicio' => $this->hora($modulo['hora_inicio'] ?? null),
                    'hora_fin' => $this->hora($modulo['hora_fin'] ?? null)];
            }
            foreach ($ids as $id) {
                $modulos[$id] ??= ['id' => $id, 'dia' => null, 'bloque' => null, 'hora_inicio' => null, 'hora_fin' => null];
            }
            $fuenteModulos = 'registrado';
        } elseif ($conModulos && $ids) {
            $actuales = Horario::with(['dia', 'bloqueHorario'])->whereIn('id', $ids)
                ->where('version_planificacion_id', $this->id($datos['version_id'] ?? null))
                ->where('grupo_seccion_id', $fila->registro_id)->get()->keyBy('id');
            foreach ($ids as $id) {
                $horario = $actuales->get($id);
                $modulos[$id] = ['id' => $id, 'dia' => $horario?->dia?->nombre,
                    'bloque' => $horario?->bloqueHorario?->numero, 'hora_inicio' => $this->hora($horario?->bloqueHorario?->hora_inicio),
                    'hora_fin' => $this->hora($horario?->bloqueHorario?->hora_fin)];
            }
            $fuenteModulos = 'referencias_actuales';
        }
        try {
            $fecha = filled($fila->created_at) ? CarbonImmutable::parse($fila->created_at)->format('d/m/Y H:i') : null;
        } catch (\Exception) {
            $fecha = null;
        }
        $disponibilidad = $capturado ? ($detalle['disponibilidad'] ?? null) : null;
        $comprobacion = match (true) {
            $disponibilidad === 'declarada' => 'Disponibilidad declarada compatible con los módulos al aplicar el reemplazo.',
            $disponibilidad === 'consulta_docente' => 'Se confirmó que el docente fue consultado porque no tenía disponibilidad declarada.',
            ($datos['disponibilidad_confirmada'] ?? null) === true => 'El registro conserva la confirmación de consulta al docente.',
            default => 'El registro anterior no conserva el tipo de comprobación de disponibilidad.',
        };

        return ['id' => $fila->id, 'fecha' => $fecha, 'responsable' => $fila->responsable ?: 'Usuario ya no disponible',
            'periodo' => $fila->periodo_nombre ?: 'Período ya no disponible',
            'version' => $fila->version_nombre ?: 'Versión ya no disponible', 'version_numero' => $fila->version_numero,
            'origen' => $nombre('origen') ?: 'Docente ya no disponible', 'origen_rut' => $fila->origen_rut,
            'candidato' => $nombre('candidato') ?: 'Docente ya no disponible', 'candidato_rut' => $fila->candidato_rut,
            'asignatura_codigo' => $fila->asignatura_codigo, 'asignatura_nombre' => $fila->asignatura_nombre ?: 'Asignatura ya no disponible',
            'seccion' => $fila->seccion_codigo, 'actividad' => $fila->actividad_tipo, 'grupo' => $fila->grupo_codigo,
            'cantidad_modulos' => $ids ? count($ids) : null, 'detalle_registrado' => $completo,
            'modulos' => array_values($modulos), 'fuente_modulos' => $fuenteModulos, 'comprobacion' => $comprobacion];
    }

    private function campos(): array
    {
        $campos = ['responsable' => ['responsable', 'u.name'], 'periodo_nombre' => ['periodo.nombre', 'p.nombre'],
            'version_nombre' => ['version.nombre', 'v.nombre'], 'version_numero' => ['version.version', 'v.version'],
            'asignatura_codigo' => ['clase.asignatura_codigo', 'ramo.codigo'], 'asignatura_nombre' => ['clase.asignatura_nombre', 'ramo.nombre'],
            'seccion_codigo' => ['clase.seccion', 's.codigo'], 'actividad_tipo' => ['clase.actividad', 'a.tipo'], 'grupo_codigo' => ['clase.grupo', 'g.codigo']];
        foreach (['origen' => ['pvo', 'po'], 'candidato' => ['pvc', 'pc']] as $tipo => [$historico, $actual]) {
            foreach (['nombre', 'apellido', 'rut'] as $campo) {
                $campos[$tipo.'_'.$campo] = [$tipo.'.'.$campo, "COALESCE($historico.$campo, $actual.$campo)"];
            }
        }

        return array_map(fn ($campo) => 'CASE WHEN '.$this->jsonId('detalle.version_esquema')." = 1 THEN COALESCE(NULLIF(".
            $this->jsonTexto('detalle.'.$campo[0]).", 'null'), '') ELSE COALESCE(".$campo[1].", '') END", $campos);
    }

    private function jsonTexto(string $ruta): string
    {
        // Las rutas son constantes internas; el CASE admite también registros antiguos con JSON incompleto o inválido.
        $json = "CASE WHEN JSON_VALID(h.datos) THEN JSON_EXTRACT(h.datos, '$.$ruta') END";

        return DB::connection()->getDriverName() === 'sqlite' ? $json : "JSON_UNQUOTE($json)";
    }

    private function jsonId(string $ruta): string
    {
        return 'CAST('.$this->jsonTexto($ruta).' AS '.(DB::connection()->getDriverName() === 'sqlite' ? 'INTEGER' : 'UNSIGNED').')';
    }

    private function literal(string $valor): string
    {
        return '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $valor).'%';
    }

    private function id(mixed $valor): ?int
    {
        return is_int($valor) && $valor > 0 ? $valor : null;
    }

    private function texto(mixed $valor): ?string
    {
        return is_string($valor) || is_int($valor) ? (string) $valor : null;
    }

    private function hora(mixed $valor): ?string
    {
        return is_string($valor) && preg_match('/^([01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/', $valor) ? substr($valor, 0, 5) : null;
    }
}

<?php

namespace App\Services;

use App\Models\Horario;
use Illuminate\Support\Str;

class RevisionRecursosProgramacion
{
    public function __construct(private int $universidadId) {}

    public function revisar(Horario $horario): ?array
    {
        $recursos = $problemas = $afectados = [];
        $agregar = function (string $tipo, $modelo) use (&$recursos): void {
            if (! $modelo) return;
            $recursos[$tipo.':'.$modelo->id] = ['tipo' => $tipo, 'id' => (int) $modelo->id,
                'nombre' => $modelo->codigo ?: ($modelo->nombre ?: '#'.$modelo->id), 'modelo' => $modelo, 'apto' => true];
        };
        foreach ($horario->asignacionesSalas as $a) $agregar('sala', $a->sala);
        foreach ($horario->asignacionesLaboratorios as $a) {
            $agregar('laboratorio', $a->laboratorio);
            $agregar('sala', $a->laboratorio?->sala);
        }
        foreach ($horario->asignacionesLaboratoriosMoviles as $a) $agregar('movil', $a->laboratorioMovil);

        $senalar = function (string $mensaje, array $claves) use (&$problemas, &$afectados, &$recursos): void {
            $problemas[] = $mensaje;
            foreach ($claves as $clave) if (isset($recursos[$clave])) $afectados[$clave] = $this->presentar($recursos[$clave]);
        };
        foreach ($recursos as $clave => &$recurso) {
            $modelo = $recurso['modelo'];
            $sala = $recurso['tipo'] === 'laboratorio' ? $modelo->sala : null;
            $motivos = [];
            if (! $modelo->estado) $motivos[] = 'registro inactivo';
            if (! $modelo->disponible) $motivos[] = 'uso no habilitado en el catálogo';
            if ($recurso['tipo'] === 'laboratorio' && ! $sala) $motivos[] = 'sin sala física registrada';
            $institucion = $recurso['tipo'] === 'laboratorio' ? $sala?->universidad_id : $modelo->universidad_id;
            if ((int) $institucion !== $this->universidadId) $motivos[] = 'no pertenece a la institución del período';
            if ($sala && (! $sala->estado || ! $sala->disponible)) $motivos[] = 'la sala del laboratorio no está activa y habilitada';
            $tipoSala = $recurso['tipo'] === 'sala' ? $modelo->tipoSala : $sala?->tipoSala;
            if ($recurso['tipo'] !== 'movil' && ! $tipoSala?->estado) $motivos[] = 'tipo de sala ausente o inactivo';
            if ($motivos) {
                $recurso['apto'] = false;
                $senalar($recurso['nombre'].': '.implode('; ', $motivos).'.', [$clave]);
            }
            if ($recurso['tipo'] === 'sala' && $tipoSala?->requiere_equipamiento) {
                $senalar($recurso['nombre'].': su tipo de sala exige equipamiento; falta vincular los equipos del inventario con este espacio para comprobarlo.', [$clave]);
            }
        }
        unset($recurso);

        $requerimientos = $horario->actividadSeccion?->requerimientos ?? collect();
        $salas = array_filter($recursos, fn ($r) => $r['tipo'] === 'sala');
        foreach ($recursos as $clave => &$recurso) {
            if ($recurso['tipo'] !== 'movil') continue;
            $habilitadas = array_filter($salas, fn ($r) => $r['apto']);
            $sedes = array_unique(array_map(fn ($r) => (int) $r['modelo']->sede_id, $habilitadas));
            if (! $habilitadas) $recurso['apto'] = false; // La ausencia de sala se detalla en asignaciones incompletas.
            elseif (count($sedes) > 1 || ! in_array((int) $recurso['modelo']->sede_id, $sedes, true)) {
                $recurso['apto'] = false;
                $senalar($recurso['nombre'].': revisa su sede y la distribución de salas; no se puede comprobar una ubicación común para el laboratorio móvil.', array_merge([$clave], array_keys($habilitadas)));
            }
            $tiposAdmitidos = $requerimientos->filter(fn ($r) => $r->tipo_sala_id && in_array($this->familia($r->tipo_requerimiento), ['sala', 'movil'], true))->pluck('tipo_sala_id')->map(fn ($id) => (int) $id)->all();
            if (! $tiposAdmitidos) $senalar($recurso['nombre'].': define un requerimiento obligatorio con el tipo de sala que necesita esta actividad para comprobar su compatibilidad.', [$clave]);
            else {
                $otrasSalas = array_filter($habilitadas, fn ($s) => ! in_array((int) $s['modelo']->tipo_sala_id, $tiposAdmitidos, true));
                if ($otrasSalas) {
                    $recurso['apto'] = false;
                    $senalar($recurso['nombre'].': hay salas de un tipo distinto al requerido; revisa qué espacio utilizará el laboratorio móvil.', array_merge([$clave], array_keys($otrasSalas)));
                }
            }
        }
        unset($recurso);

        $reglas = [];
        foreach ($requerimientos as $r) {
            $familia = $this->familia($r->tipo_requerimiento);
            $etiqueta = 'Requerimiento #'.$r->id.' ('.$r->tipo_requerimiento.(filled($r->nombre_recurso) ? ' · '.$r->nombre_recurso : '').')';
            if (! $familia || $familia === 'equipamiento') {
                $problemas[] = $etiqueta.($familia ? ': el equipamiento solicitado no está vinculado a las asignaciones; requiere revisión.' : ': tipo sin regla de comprobación; requiere revisión.');
                continue;
            }
            $candidatos = array_filter($recursos, fn ($recurso) => $recurso['tipo'] === $familia);
            if (filled($r->nombre_recurso)) $senalar($etiqueta.': verifica el recurso solicitado por nombre «'.$r->nombre_recurso.'»; no existe un vínculo que permita comprobar su identidad.', array_keys($candidatos));
            if ((int) $r->cantidad <= 0 || ($r->capacidad_minima !== null && (int) $r->capacidad_minima <= 0)
                || ($r->tipo_sala_id && ! $r->tipoSala?->estado)) {
                $senalar($etiqueta.': revisa la cantidad, la capacidad mínima y el tipo de sala configurados.', array_keys($candidatos));
                continue;
            }
            $compatibles = array_filter($candidatos, function ($recurso) use ($r, $familia, $salas) {
                if (! $recurso['apto'] || ($r->capacidad_minima !== null && (int) $recurso['modelo']->capacidad < (int) $r->capacidad_minima)) return false;
                if (! $r->tipo_sala_id) return true;
                if ($familia === 'movil') return $salas && collect($salas)->every(fn ($s) => $s['apto'] && (int) $s['modelo']->tipo_sala_id === (int) $r->tipo_sala_id);
                $sala = $familia === 'sala' ? $recurso['modelo'] : $recurso['modelo']->sala;
                return (int) $sala?->tipo_sala_id === (int) $r->tipo_sala_id;
            });
            $reglas[$r->id] = ['etiqueta' => $etiqueta, 'cantidad' => (int) $r->cantidad, 'candidatos' => array_keys($compatibles),
                'afectados' => array_keys($candidatos), 'tipo' => $r->tipoSala?->nombre, 'minima' => $r->capacidad_minima];
        }

        // Cada recurso satisface una sola unidad de los requerimientos de su familia.
        $propietarios = $unidades = [];
        $asignar = function (string $unidad, array &$visitados) use (&$asignar, &$propietarios, &$unidades, $reglas): bool {
            foreach ($reglas[$unidades[$unidad]]['candidatos'] as $clave) {
                if (isset($visitados[$clave])) continue;
                $visitados[$clave] = true;
                if (! isset($propietarios[$clave]) || $asignar($propietarios[$clave], $visitados)) {
                    $propietarios[$clave] = $unidad;
                    return true;
                }
            }
            return false;
        };
        foreach ($reglas as $id => $regla) for ($n = 0; $n < min($regla['cantidad'], count($regla['candidatos'])); $n++) {
            $unidad = $id.':'.$n; $unidades[$unidad] = $id; $visitados = []; $asignar($unidad, $visitados);
        }
        $cubiertos = array_count_values(array_map(fn ($unidad) => $unidades[$unidad], $propietarios));
        foreach ($reglas as $id => $regla) {
            $cantidad = $cubiertos[$id] ?? 0;
            if ($cantidad >= $regla['cantidad']) continue;
            $senalar($regla['etiqueta'].': exige '.$regla['cantidad'].($regla['cantidad'] === 1 ? ' recurso compatible' : ' recursos compatibles')
                .($regla['tipo'] ? ' de tipo '.$regla['tipo'] : '').($regla['minima'] ? ' y capacidad mínima '.$regla['minima'].' por recurso' : '')
                .'; se pueden cubrir '.$cantidad.' sin reutilizar recursos entre requerimientos.', $regla['afectados']);
        }
        if (! $problemas) return null;

        $seccion = $horario->actividadSeccion?->seccion;
        if ($requerimientos->isNotEmpty() && $seccion) $afectados['curso:'.$seccion->id] = ['tipo' => 'curso', 'id' => (int) $seccion->id,
            'nombre' => ($seccion->asignatura?->codigo ?: 'Curso').' · Sección '.$seccion->codigo];
        return ['mensaje' => implode(' ', array_unique($problemas)), 'recursos' => array_values($afectados)];
    }

    private function familia(string $tipo): ?string
    {
        return match (Str::lower(Str::ascii(Str::squish($tipo)))) {
            'sala', 'salas' => 'sala',
            'laboratorio', 'laboratorios', 'laboratorio fijo', 'laboratorios fijos' => 'laboratorio',
            'laboratorio movil', 'laboratorios moviles' => 'movil',
            'equipamiento' => 'equipamiento',
            default => null,
        };
    }

    private function presentar(array $recurso): array
    {
        return array_intersect_key($recurso, array_flip(['tipo', 'id', 'nombre']));
    }
}

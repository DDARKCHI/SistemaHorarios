<?php

namespace App\Services;

/** Búsqueda acotada con espacio variable por módulo y reservas fijas de la base. */
class DistribuidorCargaPendiente
{
    public function distribuir(array $trabajos, array $opciones, array $paquetes, array $fijos, int $maxIntentos = 150000, float $maxSegundos = 8): array
    {
        $inicio = microtime(true); $ocupados = $this->reservarFijos($fijos);
        $sedesAsignadas = $this->reservarSedes($fijos);
        foreach ($trabajos as &$trabajo) {
            $disponibles = array_keys($paquetes[$trabajo['grupo_id']] ?? []);
            $trabajo['paquetes'] = array_values(array_intersect($trabajo['paquetes'] ?? $disponibles, $disponibles));
        }
        unset($trabajo);
        $trabajosPorId = array_column($trabajos, null, 'id');
        usort($trabajos, fn ($a, $b) => [isset($a['horario_id']) ? 0 : 1, $a['prioridad'], count($a['opciones']) * count($a['paquetes']), $a['grupo_id'], $a['orden']]
            <=> [isset($b['horario_id']) ? 0 : 1, $b['prioridad'], count($b['opciones']) * count($b['paquetes']), $b['grupo_id'], $b['orden']]);
        if (! $trabajos) return ['estado' => 'vacio', 'plan' => [], 'intentos' => 0];
        foreach ($trabajos as $t) if (! $t['opciones'] || ! $t['paquetes']) return ['estado' => 'sin_opciones', 'plan' => [], 'intentos' => 0, 'afectado' => $t['id']];
        $plan = $marcos = $nuevosPorGrupo = []; $profundidad = $intentos = 0;
        $alternativasDescartadas = false;
        while ($profundidad < count($trabajos)) {
            if ($intentos >= $maxIntentos || microtime(true) - $inicio >= $maxSegundos) return ['estado' => 'limite', 'plan' => [], 'intentos' => $intentos];
            $t = $trabajos[$profundidad];
            if (! isset($marcos[$profundidad])) {
                $candidatos = new \SplPriorityQueue;
                $candidatos->setExtractFlags(\SplPriorityQueue::EXTR_BOTH);
                $anteriorGrupo = null;
                if (! isset($t['horario_id']) && ! empty($nuevosPorGrupo[$t['grupo_id']])) {
                    $anteriorId = array_key_last($nuevosPorGrupo[$t['grupo_id']]);
                    $anteriorTrabajo = $trabajosPorId[$anteriorId];
                    if ($anteriorTrabajo['opciones'] === $t['opciones'] && $anteriorTrabajo['paquetes'] === $t['paquetes']) $anteriorGrupo = $nuevosPorGrupo[$t['grupo_id']][$anteriorId];
                }
                $preferenciasPaquetes = [];
                foreach ($t['paquetes'] as $clave) {
                    $preferenciasPaquetes[$clave] = $paquetes[$t['grupo_id']][$clave]['preferencia']
                        + ($anteriorGrupo && $anteriorGrupo['paquete'] === $clave ? -0.5 : 0);
                }
                $clavesPaquetes = array_values(array_filter($t['paquetes'],
                    fn ($clave) => $this->sedeCompatible($sedesAsignadas[$t['grupo_id']] ?? [], $paquetes[$t['grupo_id']][$clave])));
                usort($clavesPaquetes, fn ($a, $b) => [$paquetes[$t['grupo_id']][$a]['prioridad'] ?? 256, $preferenciasPaquetes[$a], $a]
                    <=> [$paquetes[$t['grupo_id']][$b]['prioridad'] ?? 256, $preferenciasPaquetes[$b], $b]);
                foreach ($t['opciones'] as $slot) {
                    $o = $opciones[$slot];
                    // Los módulos nuevos de un grupo son intercambiables; evitar permutaciones equivalentes.
                    if ($anteriorGrupo && $o['orden'] <= $opciones[$anteriorGrupo['slot']]['orden']) continue;
                    if (! $this->libre($ocupados, $t['recursos'], $o)) continue;
                    $diasGrupo = $ocupados['grupo:'.$t['grupo_id']] ?? [];
                    $enDia = count($diasGrupo[$o['dia_id']] ?? []);
                    $distribucion = $t['carga'] >= 2 ? ($enDia >= (int) ceil($t['carga'] / 2) ? 60 : 0) : 0;
                    if (count($diasGrupo) >= 2 && ! isset($diasGrupo[$o['dia_id']])) $distribucion += 100;
                    $comodidadDocente = $this->costoDocente($ocupados, $t['recursos'], $o);
                    foreach ($clavesPaquetes as $clave) {
                        $p = $paquetes[$t['grupo_id']][$clave];
                        if (++$intentos >= $maxIntentos || microtime(true) - $inicio >= $maxSegundos) return ['estado' => 'limite', 'plan' => [], 'intentos' => $intentos];
                        $prioridad = [
                            $p['prioridad'] ?? 256,
                            $comodidadDocente + $distribucion,
                            $o['fin'],
                            $preferenciasPaquetes[$clave],
                            $o['orden'],
                            $clave,
                        ];
                        // El resto de esta lista ordenada tampoco puede mejorar las alternativas retenidas.
                        if ($candidatos->count() >= 128 && $prioridad >= $candidatos->top()['priority']) {
                            $alternativasDescartadas = true;
                            break;
                        }
                        if (! $this->libre($ocupados, $p['recursos'], $o)) continue;
                        $candidatos->insert(['slot' => $slot, 'paquete' => $clave, 'grupo_id' => $t['grupo_id']],
                            $prioridad);
                        // Acotar la memoria por módulo. Una búsqueda truncada nunca se declara imposible.
                        if ($candidatos->count() > 128) {
                            $candidatos->extract();
                            $alternativasDescartadas = true;
                        }
                    }
                }
                $marcos[$profundidad] = ['opciones' => array_reverse(array_column(iterator_to_array($candidatos, false), 'data')), 'indice' => 0];
            }
            $elegido = null;
            while ($marcos[$profundidad]['indice'] < count($marcos[$profundidad]['opciones'])) {
                if (++$intentos > $maxIntentos || microtime(true) - $inicio >= $maxSegundos) break;
                $c = $marcos[$profundidad]['opciones'][$marcos[$profundidad]['indice']++]; $p = $paquetes[$t['grupo_id']][$c['paquete']];
                if ($this->libre($ocupados, array_unique([...$t['recursos'], ...$p['recursos']]), $opciones[$c['slot']])) { $elegido = $c; break; }
            }
            if ($elegido) {
                $plan[$t['id']] = $elegido;
                if (! isset($t['horario_id'])) $nuevosPorGrupo[$t['grupo_id']][$t['id']] = $elegido;
                $paquete = $paquetes[$t['grupo_id']][$elegido['paquete']];
                if (isset($paquete['sede_id'])) $sedesAsignadas[$t['grupo_id']][$t['id']] = (int) $paquete['sede_id'];
                $o = $opciones[$elegido['slot']];
                foreach (array_unique([...$t['recursos'], ...$paquetes[$t['grupo_id']][$elegido['paquete']]['recursos']]) as $r) $ocupados[$r][$o['dia_id']][$t['id']] = $o;
                $profundidad++; unset($marcos[$profundidad]); continue;
            }
            if ($intentos >= $maxIntentos || microtime(true) - $inicio >= $maxSegundos) continue;
            unset($marcos[$profundidad]);
            if ($profundidad === 0) return ['estado' => $alternativasDescartadas ? 'limite' : 'sin_solucion', 'plan' => [], 'intentos' => $intentos];
            $previo = $trabajos[--$profundidad]; $elegida = $plan[$previo['id']]; $o = $opciones[$elegida['slot']];
            foreach (array_unique([...$previo['recursos'], ...$paquetes[$previo['grupo_id']][$elegida['paquete']]['recursos']]) as $r) {
                unset($ocupados[$r][$o['dia_id']][$previo['id']]);
                if (! $ocupados[$r][$o['dia_id']]) unset($ocupados[$r][$o['dia_id']]);
            }
            unset($plan[$previo['id']], $nuevosPorGrupo[$previo['grupo_id']][$previo['id']], $sedesAsignadas[$previo['grupo_id']][$previo['id']]);
        }
        return ['estado' => 'completo', 'plan' => $plan, 'intentos' => $intentos];
    }

    public function valido(array $plan, array $trabajos, array $opciones, array $paquetes, array $fijos): bool
    {
        if (! $trabajos || count($plan) !== count($trabajos)) return false;
        $ocupados = $this->reservarFijos($fijos);
        $sedesAsignadas = $this->reservarSedes($fijos);
        foreach ($trabajos as $t) {
            $e = $plan[$t['id']] ?? null;
            if (! is_array($e) || ($e['grupo_id'] ?? null) !== $t['grupo_id'] || ! is_string($e['slot'] ?? null) || ! is_string($e['paquete'] ?? null)
                || ! in_array($e['slot'], $t['opciones'], true) || ! isset($opciones[$e['slot']], $paquetes[$t['grupo_id']][$e['paquete']])
                || (isset($t['paquetes']) && ! in_array($e['paquete'], $t['paquetes'], true))) return false;
            $paquete = $paquetes[$t['grupo_id']][$e['paquete']];
            if (! $this->sedeCompatible($sedesAsignadas[$t['grupo_id']] ?? [], $paquete)) return false;
            if (isset($paquete['sede_id'])) $sedesAsignadas[$t['grupo_id']][$t['id']] = (int) $paquete['sede_id'];
            $o = $opciones[$e['slot']]; $recursos = array_unique([...$t['recursos'], ...$paquete['recursos']]);
            if (! $this->libre($ocupados, $recursos, $o)) return false;
            foreach ($recursos as $r) $ocupados[$r][$o['dia_id']][$t['id']] = $o;
        }
        return true;
    }
    private function reservarFijos(array $fijos): array
    {
        $reservas = [];
        foreach ($fijos as $f) foreach ($f['recursos'] as $r) $reservas[$r][$f['dia_id']]['fijo:'.$f['id']] = $f;
        return $reservas;
    }
    private function reservarSedes(array $fijos): array
    {
        $sedes = [];
        foreach ($fijos as $fijo) {
            if (isset($fijo['grupo_id'], $fijo['sede_id'])) $sedes[$fijo['grupo_id']]['fijo:'.$fijo['id']] = (int) $fijo['sede_id'];
        }
        return $sedes;
    }
    private function sedeCompatible(array $sedes, array $paquete): bool
    {
        if (! isset($paquete['sede_id'])) return true;
        foreach ($sedes as $sede) if ($sede !== (int) $paquete['sede_id']) return false;
        return true;
    }
    private function libre(array $ocupados, array $recursos, array $opcion): bool
    {
        foreach ($recursos as $r) foreach ($ocupados[$r][$opcion['dia_id']] ?? [] as $otra) if ($opcion['inicio'] < $otra['fin'] && $otra['inicio'] < $opcion['fin']) return false;
        return true;
    }

    private function costoDocente(array $ocupados, array $recursos, array $opcion): float
    {
        $costo = 0;
        foreach ($recursos as $recurso) {
            if (! str_starts_with($recurso, 'profesor:')) continue;
            $dias = $ocupados[$recurso] ?? [];
            $jornada = array_values($dias[$opcion['dia_id']] ?? []);
            if ($dias && ! $jornada) $costo += 180;
            [$ventanasAntes, $separacionesAntes] = $this->ventanasDocente($jornada);
            $jornada[] = $opcion;
            [$ventanasDespues, $separacionesDespues] = $this->ventanasDocente($jornada);
            $costo += ($ventanasDespues - $ventanasAntes) / 60
                + 180 * ($separacionesDespues - $separacionesAntes);
        }

        return $costo;
    }

    private function ventanasDocente(array $jornada): array
    {
        usort($jornada, fn ($a, $b) => $a['inicio'] <=> $b['inicio']);
        $segundos = $separaciones = 0;
        $finAnterior = null;
        foreach ($jornada as $modulo) {
            if ($finAnterior !== null) {
                $ventana = max(0, $modulo['inicio'] - $finAnterior);
                $segundos += $ventana;
                $separaciones += (int) ($ventana >= 3600);
            }
            $finAnterior = max($finAnterior ?? 0, $modulo['fin']);
        }

        return [$segundos, $separaciones];
    }
}

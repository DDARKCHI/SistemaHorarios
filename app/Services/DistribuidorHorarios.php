<?php

namespace App\Services;

/** Búsqueda acotada sobre módulos completos; no escribe en la base de datos. */
class DistribuidorHorarios
{
    public function distribuir(array $trabajos, array $opciones, int $maxIntentos = 200000, float $maxSegundos = 8): array
    {
        $inicio = microtime(true);
        $uso = [];
        foreach ($trabajos as $t) foreach ($t['recursos'] as $r) $uso[$r] = ($uso[$r] ?? 0) + 1;
        usort($trabajos, fn ($a, $b) => [count($a['opciones']), $a['prioridad'], -array_sum(array_intersect_key($uso, array_flip($a['recursos']))), $a['id']]
            <=> [count($b['opciones']), $b['prioridad'], -array_sum(array_intersect_key($uso, array_flip($b['recursos']))), $b['id']]);
        foreach ($trabajos as $t) if (! $t['opciones']) return ['estado' => 'sin_opciones', 'plan' => [], 'intentos' => 0, 'afectado' => $t['id']];
        if (! $trabajos) return ['estado' => 'vacio', 'plan' => [], 'intentos' => 0];
        $cruces = [];
        foreach ($opciones as $id => $a) foreach ($opciones as $otro => $b) {
            if ($a['dia_id'] === $b['dia_id'] && $a['inicio'] < $b['fin'] && $b['inicio'] < $a['fin']) $cruces[$id][$otro] = true;
        }
        $ocupados = $plan = $marcos = [];
        $profundidad = $intentos = 0;
        while ($profundidad < count($trabajos)) {
            if ($intentos >= $maxIntentos || microtime(true) - $inicio >= $maxSegundos) return ['estado' => 'limite', 'plan' => [], 'intentos' => $intentos];
            $trabajo = $trabajos[$profundidad];
            if (! isset($marcos[$profundidad])) {
                $candidatos = $trabajo['opciones'];
                $costos = [];
                foreach ($candidatos as $id) {
                    $opcion = $opciones[$id];
                    $hueco = 0;
                    foreach ($trabajo['recursos'] as $recurso) {
                        if (! str_starts_with($recurso, 'profesor:')) continue;
                        $distancias = [];
                        foreach ($ocupados[$recurso] ?? [] as $otro => $_) if ($opciones[$otro]['dia_id'] === $opcion['dia_id']) {
                            $distancias[] = min(abs($opcion['inicio'] - $opciones[$otro]['fin']), abs($opciones[$otro]['inicio'] - $opcion['fin']));
                        }
                        if ($distancias) $hueco += min($distancias) / 60;
                    }
                    $distribucion = 0;
                    if (isset($trabajo['grupo_id']) && $trabajo['carga'] >= 2) {
                        $diasUsados = [];
                        foreach ($ocupados['grupo:'.$trabajo['grupo_id']] ?? [] as $otro => $_) $diasUsados[$opciones[$otro]['dia_id']] = ($diasUsados[$opciones[$otro]['dia_id']] ?? 0) + 1;
                        $enDia = $diasUsados[$opcion['dia_id']] ?? 0;
                        $mitad = (int) ceil($trabajo['carga'] / 2);
                        if (count($diasUsados) === 1) $distribucion = $enDia >= $mitad ? 60 : ($enDia === 0 && max($diasUsados) < $mitad ? 15 : 0);
                        elseif (count($diasUsados) >= 2) $distribucion = $enDia === 0 ? 100 : max(0, $enDia - $mitad + 1) * 30;
                    }
                    $costos[$id] = $hueco + $distribucion + $opcion['fin'] / 3600 + ($id === $trabajo['original'] ? -0.25 : 0);
                }
                usort($candidatos, fn ($a, $b) => [$costos[$a], $opciones[$a]['numero_dia'], $opciones[$a]['inicio'], $a] <=> [$costos[$b], $opciones[$b]['numero_dia'], $opciones[$b]['inicio'], $b]);
                $marcos[$profundidad] = ['opciones' => $candidatos, 'indice' => 0];
            }
            $elegido = null;
            while ($marcos[$profundidad]['indice'] < count($marcos[$profundidad]['opciones'])) {
                if (++$intentos > $maxIntentos || microtime(true) - $inicio >= $maxSegundos) break;
                $id = $marcos[$profundidad]['opciones'][$marcos[$profundidad]['indice']++];
                $libre = true;
                foreach ($trabajo['recursos'] as $recurso) if (array_intersect_key($ocupados[$recurso] ?? [], $cruces[$id] ?? [])) { $libre = false; break; }
                if ($libre) { $elegido = $id; break; }
            }
            if ($elegido !== null) {
                $plan[$trabajo['id']] = $elegido;
                foreach ($trabajo['recursos'] as $recurso) $ocupados[$recurso][$elegido] = true;
                $profundidad++;
                unset($marcos[$profundidad]);
                continue;
            }
            if ($intentos >= $maxIntentos || microtime(true) - $inicio >= $maxSegundos) continue;
            unset($marcos[$profundidad]);
            if ($profundidad === 0) return ['estado' => 'sin_solucion', 'plan' => [], 'intentos' => $intentos];
            $anterior = $trabajos[--$profundidad];
            foreach ($anterior['recursos'] as $recurso) unset($ocupados[$recurso][$plan[$anterior['id']]]);
            unset($plan[$anterior['id']]);
        }
        return ['estado' => 'completo', 'plan' => $plan, 'intentos' => $intentos];
    }

    public function valido(array $plan, array $trabajos, array $opciones): bool
    {
        if (count($plan) !== count($trabajos) || ! $trabajos) return false;
        $reservas = [];
        foreach ($trabajos as $t) {
            $id = $plan[$t['id']] ?? null;
            if (! is_string($id) || ! in_array($id, $t['opciones'], true) || ! isset($opciones[$id])) return false;
            $a = $opciones[$id];
            foreach ($t['recursos'] as $recurso) {
                foreach ($reservas[$recurso][$a['dia_id']] ?? [] as $b) if ($a['inicio'] < $b['fin'] && $b['inicio'] < $a['fin']) return false;
                $reservas[$recurso][$a['dia_id']][] = $a;
            }
        }
        return true;
    }
}

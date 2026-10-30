<?php

namespace App\Services;

use App\Models\Equipamiento;
use App\Models\Horario;
use App\Models\RequerimientoActividad;
use Illuminate\Support\Collection;

class RevisionEquipamientoProgramacion
{
    public function __construct(private Collection $inventario) {}

    public function equipadoEnSala(int $salaId): bool
    {
        return $this->inventario->contains(fn ($e) => $this->utilizable($e) && $e->ubicaciones->contains(fn ($u) => (int) $u->sala_id === $salaId && $u->cantidad > 0));
    }

    public function revisar(Horario $horario, array $recursos): ?array
    {
        $problemas = $afectados = $restantes = [];
        foreach ($horario->actividadSeccion?->requerimientos ?? [] as $regla) {
            if (RequerimientoActividad::tipoNormalizado($regla->tipo_requerimiento) !== 'Equipamiento') continue;
            $etiqueta = 'Equipamiento requerido'.($regla->nombre_recurso ? ' «'.$regla->nombre_recurso.'»' : ' #'.$regla->id);
            if (! $regla->equipamiento_id) { $problemas[] = $etiqueta.': no está vinculado al inventario; requiere revisión.'; continue; }
            $equipo = $this->inventario->get($regla->equipamiento_id);
            $referencia = $equipo ?? $regla->equipamiento;
            if ($referencia) $afectados['equipamiento:'.$referencia->id] = ['tipo' => 'equipamiento', 'id' => (int) $referencia->id, 'nombre' => $referencia->nombre];
            if (! $equipo || ! $this->utilizable($equipo)) {
                $problemas[] = $etiqueta.': revisa la institución, el estado y la distribución del inventario; no se puede acreditar su disponibilidad.';
                continue;
            }
            if ($regla->cantidad <= 0 || $regla->capacidad_minima !== null || $regla->tipo_sala_id !== null) {
                $problemas[] = $etiqueta.': revisa sus condiciones. El equipo se comprueba por unidades; la capacidad y el tipo corresponden a los requerimientos de espacios.';
                continue;
            }
            if (! array_key_exists($equipo->id, $restantes)) {
                $restantes[$equipo->id] = (int) $equipo->ubicaciones->sum(function ($u) use ($recursos) {
                    $clave = $u->sala_id ? 'sala:'.$u->sala_id : 'movil:'.$u->laboratorio_movil_id;
                    return ($recursos[$clave]['apto'] ?? false) ? $u->cantidad : 0;
                });
            }
            $cubiertas = min($regla->cantidad, $restantes[$equipo->id]);
            $restantes[$equipo->id] -= $cubiertas;
            if ($cubiertas < $regla->cantidad) $problemas[] = $etiqueta.': se requieren '.$regla->cantidad.' unidades; se pueden cubrir '.$cubiertas.' con las ubicaciones de este módulo, sin reutilizarlas entre requerimientos.';
        }
        if (! $problemas) return null;
        $seccion = $horario->actividadSeccion?->seccion;
        if ($seccion) $afectados['curso:'.$seccion->id] = ['tipo' => 'curso', 'id' => (int) $seccion->id, 'nombre' => ($seccion->asignatura?->codigo ?: 'Curso').' · Sección '.$seccion->codigo];
        return ['mensaje' => implode(' ', $problemas), 'recursos' => array_values($afectados)];
    }

    private function utilizable(Equipamiento $equipo): bool
    {
        return $equipo->estado && $equipo->disponible && $equipo->cantidad > 0
            && $equipo->ubicaciones->every(fn ($u) => $u->cantidad > 0 && ((bool) $u->sala_id !== (bool) $u->laboratorio_movil_id))
            && (int) $equipo->ubicaciones->sum('cantidad') <= $equipo->cantidad;
    }
}

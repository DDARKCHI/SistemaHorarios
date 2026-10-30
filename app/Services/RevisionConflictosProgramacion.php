<?php

namespace App\Services;

use App\Models\Horario;
use App\Models\ProfesorVersion;
use App\Models\VersionPlanificacion;

class RevisionConflictosProgramacion
{
    public const TIPOS = ['todos' => 'Todas las revisiones', 'profesor' => 'Docentes', 'curso' => 'Cursos o secciones',
        'sala' => 'Salas', 'laboratorio' => 'Laboratorios', 'movil' => 'Laboratorios móviles',
        'asignaciones' => 'Asignaciones incompletas', 'capacidad' => 'Capacidad de los espacios', 'datos' => 'Datos por revisar'];

    public function revisar(VersionPlanificacion $version): array
    {
        $horarios = Horario::where('version_planificacion_id', $version->id)->where('estado', true)->with([
            'dia', 'bloqueHorario', 'actividadSeccion.seccion.asignatura', 'grupoSeccion',
            'actividadSeccion.grupos' => fn ($query) => $query->where('estado_activo', true),
            'profesores' => fn ($query) => $query->where('estado', true), 'profesores.profesor',
            'grupoSeccion.profesores' => fn ($query) => $query->where('estado', true), 'grupoSeccion.profesores.profesor',
            'actividadSeccion.seccion.profesores' => fn ($query) => $query->where('estado', true),
            'actividadSeccion.seccion.profesores.profesor',
            'asignacionesSalas' => fn ($query) => $query->where('estado', true), 'asignacionesSalas.sala',
            'asignacionesLaboratorios' => fn ($query) => $query->where('estado', true), 'asignacionesLaboratorios.laboratorio.sala',
            'asignacionesLaboratoriosMoviles' => fn ($query) => $query->where('estado', true), 'asignacionesLaboratoriosMoviles.laboratorioMovil',
        ])->orderBy('id')->get();
        $docentesVersion = ProfesorVersion::where('version_planificacion_id', $version->id)->get()->keyBy('profesor_id');
        $grupos = $cruces = $datos = $asignaciones = $capacidades = [];
        $revisionAsignaciones = new RevisionAsignacionesProgramacion;

        foreach ($horarios as $horario) {
            $seccion = $horario->actividadSeccion?->seccion;
            $inicio = $this->segundos($horario->bloqueHorario?->hora_inicio);
            $fin = $this->segundos($horario->bloqueHorario?->hora_fin);
            $valido = $inicio !== null && $fin !== null && $inicio < $fin && $horario->dia;
            $presentacion = [
                'id' => $horario->id, 'asignatura' => implode(' · ', array_filter(
                    [$seccion?->asignatura?->codigo, $seccion?->asignatura?->nombre], fn ($dato) => filled($dato)
                )),
                'seccion' => $seccion?->codigo ?? 'Sin sección', 'actividad' => $horario->actividadSeccion?->tipo ?? 'Sin actividad',
                'grupo' => $horario->grupoSeccion?->codigo, 'bloque' => $horario->bloqueHorario?->numero,
                'hora' => $valido ? $this->hora($inicio).'–'.$this->hora($fin) : 'Horario por revisar',
            ];
            $base = ['dia' => $horario->dia?->nombre ?? 'Día sin información', 'horarios' => [$presentacion],
                'orden' => [(int) ($horario->dia?->numero ?? 99), $inicio ?? 0, $horario->id]];
            $problemas = [];
            if (! $valido) $problemas[] = 'El día o las horas del bloque están incompletos o el intervalo no es válido.';
            if (! $seccion || (int) $seccion->periodo_academico_id !== (int) $version->periodo_academico_id) {
                $problemas[] = 'La sección no corresponde al período de esta versión.';
            }
            if ($problemas) $datos[] = $base + ['tipo' => 'datos', 'mensaje' => implode(' ', $problemas), 'recursos' => [], 'intervalo' => $presentacion['hora']];
            $docentes = $horario->profesores;
            if ($docentes->isEmpty()) $docentes = $horario->grupoSeccion?->profesores ?? collect();
            if ($docentes->isEmpty()) $docentes = $seccion?->profesores ?? collect();
            $revision = $revisionAsignaciones->revisar($horario, $docentes);
            if ($revision['asignaciones']) $asignaciones[] = $base + ['tipo' => 'asignaciones', 'intervalo' => $presentacion['hora']] + $revision['asignaciones'];
            if ($revision['capacidad']) $capacidades[] = $base + ['tipo' => 'capacidad', 'intervalo' => $presentacion['hora']] + $revision['capacidad'];
            if (! $valido) continue;

            $recursos = [];
            $agregar = function (string $tipo, $id, string $nombre) use (&$recursos) {
                if ($id) $recursos[$tipo.':'.$id] = ['tipo' => $tipo, 'id' => (int) $id, 'nombre' => $nombre];
            };
            if ($seccion) $agregar('curso', $seccion->id, ($seccion->asignatura?->codigo ?: 'Curso').' · Sección '.$seccion->codigo);
            foreach ($docentes as $asignacion) {
                $profesor = $docentesVersion->get($asignacion->profesor_id) ?? $asignacion->profesor;
                $nombre = trim(($profesor?->nombre ?? '').' '.($profesor?->apellido ?? ''));
                $agregar('profesor', $asignacion->profesor_id, $nombre ?: 'Docente #'.$asignacion->profesor_id);
            }
            foreach ($horario->asignacionesSalas as $asignacion) {
                $agregar('sala', $asignacion->sala_id, $asignacion->sala?->codigo ?: 'Sala #'.$asignacion->sala_id);
            }
            foreach ($horario->asignacionesLaboratorios as $asignacion) {
                $lab = $asignacion->laboratorio;
                $agregar('laboratorio', $asignacion->laboratorio_id, $lab?->codigo ?: ($lab?->nombre ?: 'Laboratorio #'.$asignacion->laboratorio_id));
                if ($lab?->sala) $agregar('sala', $lab->sala_id, $lab->sala->codigo ?: 'Sala #'.$lab->sala_id);
            }
            foreach ($horario->asignacionesLaboratoriosMoviles as $asignacion) {
                $lab = $asignacion->laboratorioMovil;
                $agregar('movil', $asignacion->laboratorio_movil_id, $lab?->codigo ?: ($lab?->nombre ?: 'Laboratorio móvil #'.$asignacion->laboratorio_movil_id));
            }
            foreach ($recursos as $clave => $recurso) {
                $grupos[$horario->dia_id.':'.$clave][] = $base + ['inicio' => $inicio, 'fin' => $fin, 'recurso' => $recurso];
            }
        }

        foreach ($grupos as $eventos) {
            usort($eventos, fn ($a, $b) => [$a['inicio'], $a['fin'], $a['horarios'][0]['id']] <=> [$b['inicio'], $b['fin'], $b['horarios'][0]['id']]);
            $activos = [];
            foreach ($eventos as $actual) {
                $activos = array_filter($activos, fn ($previo) => $previo['fin'] > $actual['inicio']);
                foreach ($activos as $previo) {
                    $modulos = [$previo['horarios'][0], $actual['horarios'][0]];
                    usort($modulos, fn ($a, $b) => $a['id'] <=> $b['id']);
                    $clave = $modulos[0]['id'].'-'.$modulos[1]['id'];
                    $cruces[$clave] ??= ['tipo' => 'conflicto', 'dia' => $actual['dia'], 'horarios' => $modulos,
                        'intervalo' => $this->hora(max($previo['inicio'], $actual['inicio'])).'–'.$this->hora(min($previo['fin'], $actual['fin'])),
                        'mensaje' => 'Estos módulos coinciden en el tiempo. Revisa el horario o la asignación compartida de uno de ellos.', 'recursos' => [], 'orden' => $actual['orden']];
                    $recurso = $actual['recurso'];
                    $cruces[$clave]['recursos'][$recurso['tipo'].':'.$recurso['id']] = $recurso;
                }
                $activos[] = $actual;
            }
        }
        $observaciones = collect(array_merge(array_values($cruces), $asignaciones, $capacidades, $datos))
            ->sortBy('orden')->map(function ($fila) { $fila['recursos'] = array_values($fila['recursos']); return $fila; })->values();

        return ['modulos' => $horarios->count(), 'conflictos' => count($cruces), 'asignaciones' => count($asignaciones),
            'capacidad' => count($capacidades), 'datos' => count($datos), 'observaciones' => $observaciones];
    }

    private function segundos(?string $hora): ?int
    {
        if (! preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/', trim($hora ?? ''), $partes)) return null;
        [$h, $m, $s] = [(int) $partes[1], (int) $partes[2], (int) ($partes[3] ?? 0)];
        return $h < 24 && $m < 60 && $s < 60 ? $h * 3600 + $m * 60 + $s : null;
    }

    private function hora(int $segundos): string
    {
        return gmdate($segundos % 60 === 0 ? 'H:i' : 'H:i:s', $segundos);
    }
}

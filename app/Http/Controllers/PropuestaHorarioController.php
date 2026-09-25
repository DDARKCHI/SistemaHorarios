<?php

namespace App\Http\Controllers;

use App\Models\BloqueHorario;
use App\Models\Dia;
use App\Models\DisponibilidadProfesor;
use App\Models\PeriodoAcademico;
use App\Models\Profesor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PropuestaHorarioController extends Controller
{
    public function index()
    {
        $profesor = $this->obtenerProfesor();

        if (!$profesor) {
            return redirect()
                ->route('programacion.semestral')
                ->withErrors([
                    'profesor' => 'No se encontró un profesor asociado a tu usuario.',
                ]);
        }

        $periodo = $this->obtenerPeriodoActivo($profesor);

        if (!$periodo) {
            return redirect()
                ->route('programacion.semestral')
                ->withErrors([
                    'periodo' => 'No existe un período académico activo.',
                ]);
        }

        $dias = Dia::where('estado', true)
            ->orderBy('numero')
            ->get();

        $bloques = BloqueHorario::where('estado', true)
            ->orderBy('numero')
            ->get();

        $disponibilidades = DisponibilidadProfesor::where('profesor_id', $profesor->id)
            ->where('periodo_academico_id', $periodo->id)
            ->where('estado', true)
            ->get();

        $seleccionados = $disponibilidades
            ->mapWithKeys(function ($disponibilidad) {
                $clave = $disponibilidad->dia_id . '-' . $disponibilidad->bloque_horario_id;

                return [$clave => true];
            });

        return view('programacion.propuesta-horario', compact(
            'profesor',
            'periodo',
            'dias',
            'bloques',
            'seleccionados'
        ));
    }

    public function guardar(Request $request)
    {
        $profesor = $this->obtenerProfesor();

        if (!$profesor) {
            return redirect()
                ->route('programacion.semestral')
                ->withErrors([
                    'profesor' => 'No se encontró un profesor asociado a tu usuario.',
                ]);
        }

        $periodo = $this->obtenerPeriodoActivo($profesor);

        if (!$periodo) {
            return redirect()
                ->route('programacion.semestral')
                ->withErrors([
                    'periodo' => 'No existe un período académico activo.',
                ]);
        }

        $datos = $request->validate([
            'disponibilidades' => ['nullable', 'array'],
            'disponibilidades.*' => [
                'string',
                'regex:/^\d+-\d+$/',
            ],
        ], [
            'disponibilidades.array' => 'La disponibilidad enviada no es válida.',
            'disponibilidades.*.regex' => 'Uno de los bloques seleccionados no es válido.',
        ]);

        $diasValidos = Dia::where('estado', true)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $bloquesValidos = BloqueHorario::where('estado', true)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $disponibilidades = collect($datos['disponibilidades'] ?? [])
            ->map(function ($valor) {
                [$diaId, $bloqueId] = array_map('intval', explode('-', $valor));

                return [
                    'dia_id' => $diaId,
                    'bloque_horario_id' => $bloqueId,
                ];
            })
            ->filter(function ($item) use ($diasValidos, $bloquesValidos) {
                return in_array($item['dia_id'], $diasValidos, true)
                    && in_array($item['bloque_horario_id'], $bloquesValidos, true);
            })
            ->unique(function ($item) {
                return $item['dia_id'] . '-' . $item['bloque_horario_id'];
            })
            ->values();

        DB::transaction(function () use ($profesor, $periodo, $disponibilidades) {
            DisponibilidadProfesor::where('profesor_id', $profesor->id)
                ->where('periodo_academico_id', $periodo->id)
                ->delete();

            foreach ($disponibilidades as $disponibilidad) {
                DisponibilidadProfesor::create([
                    'profesor_id' => $profesor->id,
                    'periodo_academico_id' => $periodo->id,
                    'dia_id' => $disponibilidad['dia_id'],
                    'bloque_horario_id' => $disponibilidad['bloque_horario_id'],
                    'tipo_disponibilidad' => 'Disponible',
                    'estado' => true,
                ]);
            }
        });

        return redirect()
            ->route('programacion.propuesta')
            ->with('success', 'Propuesta de horario guardada correctamente.');
    }

    private function obtenerProfesor(): ?Profesor
    {
        $usuario = Auth::user();

        return Profesor::where('rut', $usuario->rut)
            ->where('estado', true)
            ->first();
    }

    private function obtenerPeriodoActivo(Profesor $profesor): ?PeriodoAcademico
    {
        return PeriodoAcademico::where('universidad_id', $profesor->universidad_id)
            ->where('estado', true)
            ->orderByDesc('anio')
            ->orderByDesc('numero_semestre')
            ->first();
    }
}
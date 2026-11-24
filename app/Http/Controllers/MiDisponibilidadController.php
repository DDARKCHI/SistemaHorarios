<?php

namespace App\Http\Controllers;

use App\Models;
use Illuminate\Http\Request;

class MiDisponibilidadController extends Controller
{
    public function index(Request $request)
    {
        $profesor = $request->user()->profesor;
        abort_unless($profesor?->estado, 403, 'Tu cuenta necesita una ficha docente activa. Contacta con coordinación.');
        $solicitudes = Models\SolicitudDisponibilidad::where('profesor_id', $profesor->id)
            ->with('periodoAcademico', 'respuestas')->latest('id')->paginate(12);
        $pendiente = Models\SolicitudDisponibilidad::where('profesor_id', $profesor->id)
            ->where('estado', 'Pendiente')->whereDate('fecha_limite', '>=', today())
            ->whereHas('periodoAcademico', fn ($query) => $query->where('estado', true))
            ->latest('id')->first();

        return view('disponibilidad.mi-disponibilidad', compact('profesor', 'solicitudes', 'pendiente'));
    }

    public function show(Request $request, Models\SolicitudDisponibilidad $solicitud)
    {
        $this->comprobarTitular($request, $solicitud);
        $solicitud->load('profesor', 'periodoAcademico', 'respuestas');
        $abierta = $solicitud->estado === 'Pendiente'
            && $solicitud->fecha_limite?->copy()->endOfDay()->isFuture()
            && $solicitud->periodoAcademico->estado;
        $dias = Models\Dia::where('estado', true)->orderBy('numero')->get();
        $bloques = Models\BloqueHorario::where('estado', true)->orderBy('hora_inicio')->get();
        $respuesta = $solicitud->respuestas->sortByDesc('id')->first();

        return response()->view('disponibilidad.mi-respuesta', compact(
            'solicitud', 'abierta', 'dias', 'bloques', 'respuesta'
        ))->header('Cache-Control', 'no-store, private');
    }

    public function guardar(Request $request, Models\SolicitudDisponibilidad $solicitud)
    {
        $this->comprobarTitular($request, $solicitud);
        app(DisponibilidadDocenteController::class)->publica($request, $solicitud);

        return redirect()->route('mi.disponibilidad.show', $solicitud)
            ->with('success', 'Disponibilidad enviada. La coordinación revisará tu respuesta.');
    }

    private function comprobarTitular(Request $request, Models\SolicitudDisponibilidad $solicitud): void
    {
        abort_unless($request->user()->profesor_id
            && (int) $request->user()->profesor_id === (int) $solicitud->profesor_id
            && $request->user()->profesor?->estado, 403, 'Esta solicitud pertenece a otro docente.');
    }
}

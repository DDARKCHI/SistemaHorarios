@extends('layouts.app')
@section('title', 'Revisar disponibilidad | PROSEC')
@section('content')
<div class="space-y-6">
    <header class="flex flex-wrap items-start justify-between gap-3"><div><h1 class="text-2xl font-semibold">Solicitud de disponibilidad</h1><p class="mt-1 text-sm text-texto-secundario">{{ $solicitud->profesor->apellido }}, {{ $solicitud->profesor->nombre }} · {{ $solicitud->periodoAcademico->nombre }}</p></div><a href="{{ route('disponibilidad.index', ['periodo_id' => $solicitud->periodo_academico_id]) }}" class="rounded-lg border border-borde bg-superficie px-4 py-2 text-sm font-semibold">Volver a solicitudes</a></header>
    @include('disponibilidad.avisos')
    <section class="space-y-4 rounded-xl border border-borde bg-superficie p-5 shadow-sm">
        <div class="flex flex-wrap justify-between gap-3"><h2 class="font-semibold">{{ $solicitud->estado }}</h2><p class="text-sm text-texto-secundario">Fecha límite: {{ $solicitud->fecha_limite?->format('d/m/Y') ?? 'Sin fecha' }}</p></div>
        @if ($solicitud->observaciones)<p class="whitespace-pre-line text-sm">{{ $solicitud->observaciones }}</p>@endif
        @if ($enlace)
            <div><label for="enlace-docente" class="mb-2 block text-sm font-semibold">Enlace privado para el docente</label><div class="flex flex-col gap-2 sm:flex-row"><input id="enlace-docente" value="{{ $enlace }}" readonly class="h-11 min-w-0 flex-1 rounded-lg border border-borde bg-fondo px-3 text-sm"><button type="button" id="copiar-enlace" class="h-11 shrink-0 rounded-lg border border-borde px-4 text-sm font-semibold">Copiar enlace</button></div><p id="estado-copia" role="status" class="mt-2 text-xs text-texto-secundario">Permite responder hasta la fecha límite. Compártelo únicamente con este docente.</p></div>
        @elseif ($solicitud->estado === 'Pendiente')<p class="text-sm text-amber-800">El enlace ha vencido. Puedes crear una nueva solicitud desde el listado.</p>@endif
        @if ($solicitud->estado === 'Pendiente')<form method="post" action="{{ route('disponibilidad.cancelar', $solicitud) }}">@csrf<button class="rounded-lg border border-borde px-4 py-2 text-sm font-semibold">Cancelar solicitud e invalidar enlace</button></form>@endif
    </section>
    @if ($respuesta)
        <section class="space-y-4 rounded-xl border border-borde bg-superficie p-5 shadow-sm"><div class="flex flex-wrap justify-between gap-2"><h2 class="font-semibold">Respuesta del docente</h2><p class="text-xs text-texto-secundario">Enviada el {{ $respuesta->fecha_respuesta?->format('d/m/Y H:i') }}</p></div>
        @if ($respuesta->bloques)
            @if (empty($respuesta->bloques['disponibles']))<p class="text-sm text-texto-secundario">El docente declaró que no tiene disponibilidad en este período.</p>@endif
            @include('disponibilidad.calendario', ['editable' => false])
        @else<p class="text-sm text-texto-secundario">Esta respuesta anterior no contiene un calendario para revisión.</p>@endif
        @if ($respuesta->observaciones)<p class="whitespace-pre-line text-sm">{{ $respuesta->observaciones }}</p>@endif
        @if ($respuesta->revisado_en)<p class="text-sm text-texto-secundario">{{ $respuesta->respuesta }} el {{ $respuesta->revisado_en->format('d/m/Y H:i') }}.</p>@if ($respuesta->motivo_revision)<p class="whitespace-pre-line text-sm">{{ $respuesta->motivo_revision }}</p>@endif @endif
        </section>
        @if ($solicitud->estado === 'En revisión')
            <form method="post" action="{{ route('disponibilidad.revisar', $solicitud) }}" class="space-y-4 rounded-xl border border-borde bg-superficie p-5 shadow-sm">@csrf<h2 class="font-semibold">Revisar respuesta</h2><p class="text-sm text-texto-secundario">Al aprobar, esta respuesta reemplazará la disponibilidad vigente del docente para este período. Los horarios existentes se conservan y deberán volver a revisarse por posibles conflictos.</p><div><label for="motivo" class="mb-2 block text-sm font-semibold">Motivo o comentario de revisión</label><textarea id="motivo" name="motivo" rows="2" maxlength="2000" class="w-full rounded-lg border border-borde p-3">{{ old('motivo') }}</textarea><p class="mt-1 text-xs text-texto-secundario">Obligatorio si rechazas la respuesta.</p></div><div class="flex flex-wrap justify-end gap-3"><button name="decision" value="rechazar" class="h-11 rounded-lg border border-borde px-5 text-sm font-semibold">Rechazar respuesta</button><button name="decision" value="aprobar" class="h-11 rounded-lg bg-institucional px-5 text-sm font-semibold text-white">Aprobar disponibilidad</button></div></form>
        @endif
    @endif
</div>
<script>
document.getElementById('copiar-enlace')?.addEventListener('click', async () => {
    const input = document.getElementById('enlace-docente');
    try { await navigator.clipboard.writeText(input.value); document.getElementById('estado-copia').textContent = 'Enlace copiado.'; }
    catch { input.select(); document.getElementById('estado-copia').textContent = 'Seleccionamos el enlace. Usa Copiar para compartirlo.'; }
});
</script>
@endsection

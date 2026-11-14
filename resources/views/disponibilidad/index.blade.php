@extends('layouts.app')
@section('title', 'Disponibilidad docente | PROSEC')
@section('content')
<div class="space-y-6">
    <header><h1 class="text-2xl font-semibold">Disponibilidad docente</h1><p class="mt-1 text-sm text-texto-secundario">Solicita disponibilidad para el período y revisa las respuestas antes de incorporarlas a la planificación.</p></header>
    @include('disponibilidad.avisos')
    <form method="get" class="flex flex-wrap items-end gap-4 rounded-xl border border-borde bg-superficie p-5 shadow-sm">
        <div class="min-w-48 flex-1"><label for="periodo_id" class="mb-2 block text-sm font-semibold">Período académico</label><select id="periodo_id" name="periodo_id" class="h-11 w-full rounded-lg border border-borde px-3">@foreach ($periodos as $p)<option value="{{ $p->id }}" @selected($periodo?->id === $p->id)>{{ $p->nombre }}{{ $p->estado ? '' : ' · Inactivo' }}</option>@endforeach</select></div>
        <div class="min-w-48 flex-1"><label for="estado" class="mb-2 block text-sm font-semibold">Estado de la solicitud</label><select id="estado" name="estado" class="h-11 w-full rounded-lg border border-borde px-3"><option value="">Todos los estados</option>@foreach (['Pendiente', 'En revisión', 'Aprobada', 'Rechazada', 'Cancelada'] as $e)<option @selected(request('estado') === $e)>{{ $e }}</option>@endforeach</select></div>
        <button class="h-11 rounded-lg border border-borde px-5 text-sm font-semibold hover:bg-fondo">Filtrar</button>
    </form>
    @if ($periodo?->estado)
    <section class="rounded-xl border border-borde bg-superficie p-5 shadow-sm" aria-labelledby="nueva-solicitud">
        <h2 id="nueva-solicitud" class="font-semibold">Nueva solicitud · {{ $periodo->nombre }}</h2>
        <form method="post" action="{{ route('disponibilidad.store') }}" class="mt-4 grid items-end gap-4 md:grid-cols-2 xl:grid-cols-[2fr_1fr_auto]">
            @csrf <input type="hidden" name="periodo_academico_id" value="{{ $periodo->id }}">
            <div><label for="profesor_id" class="mb-2 block text-sm font-semibold">Docente *</label><select id="profesor_id" name="profesor_id" required class="h-11 w-full rounded-lg border border-borde px-3"><option value="">Seleccionar docente</option>@foreach ($profesores as $profesor)<option value="{{ $profesor->id }}" @selected(old('profesor_id') == $profesor->id)>{{ $profesor->apellido }}, {{ $profesor->nombre }}</option>@endforeach</select></div>
            <div><label for="fecha_limite" class="mb-2 block text-sm font-semibold">Fecha límite *</label><input id="fecha_limite" name="fecha_limite" type="date" required min="{{ today()->toDateString() }}" max="{{ today()->addYear()->toDateString() }}" value="{{ old('fecha_limite', today()->addDays(7)->toDateString()) }}" class="h-11 w-full rounded-lg border border-borde px-3"></div>
            <button class="h-11 rounded-lg bg-institucional px-5 text-sm font-semibold text-white hover:bg-institucional/90">Crear solicitud</button>
            <div class="md:col-span-2 xl:col-span-3"><label for="observaciones" class="mb-2 block text-sm font-semibold">Indicaciones para el docente</label><input id="observaciones" name="observaciones" maxlength="2000" value="{{ old('observaciones') }}" placeholder="Opcional" class="h-11 w-full rounded-lg border border-borde px-3"></div>
            <p class="text-xs text-texto-secundario md:col-span-2 xl:col-span-3">Los campos con * son obligatorios. Podrás copiar un enlace privado para compartirlo con el docente.</p>
        </form>
    </section>
    @endif
    <section class="overflow-hidden rounded-xl border border-borde bg-superficie shadow-sm" aria-labelledby="solicitudes">
        <h2 id="solicitudes" class="border-b border-borde p-5 font-semibold">Seguimiento de solicitudes</h2>
        <div class="overflow-x-auto"><table class="w-full text-left text-sm"><thead class="bg-fondo text-xs uppercase text-texto-secundario"><tr><th class="p-4">Docente</th><th class="p-4">Período</th><th class="p-4">Fecha límite</th><th class="p-4">Estado</th><th class="p-4 text-right">Acciones</th></tr></thead><tbody class="divide-y divide-borde">
        @forelse ($solicitudes as $solicitud)<tr><td class="p-4 font-medium">{{ $solicitud->profesor->apellido }}, {{ $solicitud->profesor->nombre }}</td><td class="p-4">{{ $solicitud->periodoAcademico->nombre }}</td><td class="p-4 whitespace-nowrap">{{ $solicitud->fecha_limite?->format('d/m/Y') ?? 'Sin fecha' }}</td><td class="p-4"><span class="inline-flex whitespace-nowrap rounded-full px-3 py-1 text-xs font-semibold {{ $solicitud->estado === 'Aprobada' ? 'bg-green-50 text-green-800' : ($solicitud->estado === 'En revisión' ? 'bg-sky-50 text-sky-800' : 'bg-fondo text-texto-secundario') }}">{{ $solicitud->estado === 'Pendiente' && $solicitud->fecha_limite?->copy()->endOfDay()->isPast() ? 'Vencida' : $solicitud->estado }}</span></td><td class="p-4 text-right"><a href="{{ route('disponibilidad.show', $solicitud) }}" class="inline-flex h-10 items-center whitespace-nowrap rounded-lg border border-borde px-4 font-semibold hover:bg-fondo">Ver solicitud</a></td></tr>
        @empty<tr><td colspan="5" class="p-8 text-center text-texto-secundario">No hay solicitudes para esta selección.</td></tr>@endforelse
        </tbody></table></div><div class="border-t border-borde p-4">{{ $solicitudes->links() }}</div>
    </section>
</div>
@endsection

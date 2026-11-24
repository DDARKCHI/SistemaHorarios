@extends('layouts.app')
@section('title', 'Mi disponibilidad | '.$identidad['nombre'])
@section('content')
<div class="panel-editor">
    <x-encabezado-pagina titulo="Mi disponibilidad">
        Marca los horarios en los que puedes realizar clases en cada período.
        @if ($pendiente)
            <x-slot:acciones>
                <a class="boton boton-primario" href="{{ route('mi.disponibilidad.show', $pendiente) }}">Marcar disponibilidad</a>
            </x-slot:acciones>
        @endif
    </x-encabezado-pagina>
    @include('disponibilidad.avisos')
    <section class="overflow-hidden rounded-xl border border-borde bg-superficie shadow-sm" aria-label="Mis solicitudes">
        <div class="divide-y divide-borde">
            @forelse ($solicitudes as $solicitud)
                <article class="flex flex-wrap items-center justify-between gap-3 p-4 sm:p-5">
                    <div class="min-w-0">
                        <h2 class="font-semibold">{{ $solicitud->periodoAcademico->nombre }}</h2>
                        <p class="mt-1 text-sm text-texto-secundario">
                            {{ $solicitud->estado === 'Pendiente' && $solicitud->fecha_limite?->copy()->endOfDay()->isPast() ? 'Vencida' : $solicitud->estado }}
                            @if ($solicitud->fecha_limite) · Hasta el {{ $solicitud->fecha_limite->format('d/m/Y') }}@endif
                        </p>
                    </div>
                    <a class="boton boton-secundario" href="{{ route('mi.disponibilidad.show', $solicitud) }}">
                        {{ $solicitud->estado === 'Pendiente' ? 'Abrir solicitud' : 'Ver respuesta' }}
                    </a>
                </article>
            @empty
                <div class="space-y-2 p-5 text-sm">
                    <p class="font-semibold">Aún no tienes solicitudes de disponibilidad.</p>
                    <p class="text-texto-secundario">La coordinación habilitará el período para que puedas registrar tus horarios.</p>
                </div>
            @endforelse
        </div>
        @if ($solicitudes->hasPages())<div class="border-t border-borde p-4">{{ $solicitudes->links() }}</div>@endif
    </section>
</div>
@endsection

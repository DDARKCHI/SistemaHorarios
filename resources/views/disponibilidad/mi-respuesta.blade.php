@extends('layouts.app')
@section('title', 'Informar disponibilidad | '.$identidad['nombre'])
@section('content')
<div class="panel-editor">
    <x-encabezado-pagina :titulo="'Disponibilidad · '.$solicitud->periodoAcademico->nombre">
        {{ $solicitud->profesor->nombre }} {{ $solicitud->profesor->apellido }}
        <x-slot:acciones><a class="boton boton-secundario" href="{{ route('mi.disponibilidad.index') }}">Mis solicitudes</a></x-slot:acciones>
    </x-encabezado-pagina>
    @include('disponibilidad.avisos')
    @if ($abierta)
        <form method="post" action="{{ route('mi.disponibilidad.guardar', $solicitud) }}" class="panel-seccion space-y-5"
            data-confirmar="Los módulos seleccionados se enviarán a coordinación para su revisión."
            data-confirmar-titulo="Enviar disponibilidad" data-confirmar-accion="Enviar respuesta">
            @csrf
            <div>
                <h2 class="font-semibold">Selecciona tus módulos disponibles</h2>
                <p class="mt-1 text-sm text-texto-secundario">Los módulos sin marcar se registran como no disponibles. Responde hasta el {{ $solicitud->fecha_limite->format('d/m/Y') }}.</p>
            </div>
            @if ($solicitud->observaciones)<p class="whitespace-pre-line rounded-lg bg-fondo p-3 text-sm">{{ $solicitud->observaciones }}</p>@endif
            @include('disponibilidad.calendario', ['editable' => true])
            <label class="flex cursor-pointer items-start gap-3">
                <input type="checkbox" name="sin_disponibilidad" value="1" @checked(old('sin_disponibilidad')) class="mt-0.5 size-4 shrink-0 accent-institucional">
                No tengo disponibilidad para realizar clases en este período.
            </label>
            <div>
                <label for="observaciones" class="mb-1.5 block font-semibold">Comentarios</label>
                <textarea id="observaciones" name="observaciones" rows="2" maxlength="2000" placeholder="Opcional" class="w-full rounded-lg border border-borde bg-superficie p-3">{{ old('observaciones') }}</textarea>
            </div>
            <div class="flex flex-wrap items-center justify-between gap-3 border-t border-borde pt-4">
                <p class="text-xs text-texto-secundario">La coordinación revisará tu respuesta antes de planificar.</p>
                <button type="submit" class="boton boton-primario">Enviar disponibilidad</button>
            </div>
        </form>
    @else
        <section class="panel-seccion space-y-4">
            <div>
                <h2 class="font-semibold">{{ $respuesta ? 'Respuesta registrada' : 'Solicitud cerrada' }}</h2>
                <p class="mt-1 text-sm text-texto-secundario">Estado {{ $solicitud->estado }}.</p>
                @if ($respuesta?->motivo_revision)<p class="mt-2 text-sm">{{ $respuesta->motivo_revision }}</p>@endif
            </div>
            @if ($respuesta)
                @include('disponibilidad.calendario', ['editable' => false])
            @else
                <p class="text-sm text-texto-secundario">Contacta con coordinación si necesitas informar tu disponibilidad.</p>
            @endif
        </section>
    @endif
</div>
@endsection

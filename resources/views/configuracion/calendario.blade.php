@extends('layouts.app')
@section('title', 'Calendario académico | '.$identidad['nombre'])
@section('content')
<div class="space-y-6">
    <x-encabezado-pagina titulo="Calendario académico">
        Días y módulos compartidos por las instituciones y sedes. Los registros vinculados conservan su definición para proteger los horarios.
        <x-slot:acciones>
            <a class="boton boton-secundario" href="{{ route('configuracion.index') }}">Datos institucionales</a>
        </x-slot:acciones>
    </x-encabezado-pagina>
    @include('disponibilidad.avisos')
    <div class="@container/calendario">
        <div class="grid items-start gap-5 @min-[52rem]/calendario:grid-cols-[minmax(0,1fr)_minmax(0,1.5fr)]">
            <section class="min-w-0 rounded-xl border border-borde bg-superficie shadow-sm" aria-labelledby="titulo-dias">
                <header class="flex flex-wrap items-center gap-2 border-b border-borde px-4 py-3 sm:px-5">
                    <h2 id="titulo-dias" class="font-semibold">Días de la semana</h2>
                    @can('administrar usuarios')
                        <a class="boton boton-secundario ml-auto" href="{{ route('configuracion.calendario.create', 'dias') }}">Agregar día</a>
                    @endcan
                </header>
                <ul role="list" class="divide-y divide-borde">
                    @foreach ($dias as $dia)
                        <li class="flex items-center gap-3 px-4 py-2.5 sm:px-5">
                            <div class="flex min-w-0 flex-1 flex-wrap items-center justify-between gap-x-2 gap-y-1 text-sm">
                                <div class="min-w-0">
                                    <p class="font-semibold">{{ $dia->nombre }}</p>
                                    <p class="text-xs text-texto-secundario">{{ $dia->abreviatura }} · Día {{ $dia->numero }}</p>
                                </div>
                                <span class="rounded-md bg-fondo px-2 py-1 text-xs font-medium {{ $dia->estado ? 'text-texto' : 'text-texto-secundario' }}">
                                    {{ $dia->estado ? 'Activo' : 'Inactivo' }}
                                </span>
                            </div>
                            @can('administrar usuarios')
                                <a class="boton boton-secundario ml-auto shrink-0" href="{{ route('configuracion.calendario.edit', ['tipo' => 'dias', 'id' => $dia->id]) }}" aria-label="Editar {{ $dia->nombre }}">Editar</a>
                            @endcan
                        </li>
                    @endforeach
                </ul>
            </section>

            <section class="@container/modulos min-w-0 rounded-xl border border-borde bg-superficie shadow-sm" aria-labelledby="titulo-modulos">
                <header class="flex flex-wrap items-center gap-2 border-b border-borde px-4 py-3 sm:px-5">
                    <h2 id="titulo-modulos" class="font-semibold">Módulos horarios</h2>
                    @can('administrar usuarios')
                        <a class="boton boton-primario ml-auto" href="{{ route('configuracion.calendario.create', 'bloques') }}">Agregar módulo</a>
                    @endcan
                </header>
                <ul role="list" class="divide-y divide-borde">
                    @foreach ($bloques as $bloque)
                        <li class="flex items-center gap-3 px-4 py-2.5 last:rounded-b-xl sm:px-5 {{ $bloque->es_almuerzo ? 'bg-amber-50' : '' }}">
                            <div class="grid min-w-0 flex-1 grid-cols-[minmax(0,1fr)_auto] items-center gap-x-2 gap-y-0.5 text-sm
                                @min-[28rem]/modulos:grid-cols-[minmax(0,1fr)_minmax(6.5rem,auto)_minmax(4.5rem,auto)] @min-[28rem]/modulos:gap-x-4">
                                <div class="min-w-0">
                                    <p class="font-semibold">Módulo {{ $bloque->numero }}</p>
                                    @if ($bloque->es_almuerzo)
                                        <p class="text-xs text-amber-800">Almuerzo</p>
                                    @endif
                                </div>
                                <p class="col-start-1 row-start-2 tabular-nums text-texto-secundario @min-[28rem]/modulos:col-auto @min-[28rem]/modulos:row-auto">
                                    {{ substr($bloque->hora_inicio, 0, 5) }}–{{ substr($bloque->hora_fin, 0, 5) }}
                                </p>
                                <span class="col-start-2 row-span-2 row-start-1 justify-self-end rounded-md bg-fondo px-2 py-1 text-xs font-medium
                                    @min-[28rem]/modulos:col-auto @min-[28rem]/modulos:row-auto {{ $bloque->estado ? 'text-texto' : 'text-texto-secundario' }}">
                                    {{ $bloque->estado ? 'Activo' : 'Inactivo' }}
                                </span>
                            </div>
                            @can('administrar usuarios')
                                <a class="boton boton-secundario ml-auto shrink-0" href="{{ route('configuracion.calendario.edit', ['tipo' => 'bloques', 'id' => $bloque->id]) }}" aria-label="Editar módulo {{ $bloque->numero }}">Editar</a>
                            @endcan
                        </li>
                    @endforeach
                </ul>
            </section>
        </div>
    </div>
    <p class="rounded-xl border border-borde bg-superficie p-5 text-sm text-texto-secundario">Antes de cambiar el calendario, resuelve las solicitudes de disponibilidad pendientes o en revisión. Después del cambio se debe solicitar y aprobar la disponibilidad con el nuevo calendario. El generador excluye los módulos marcados como almuerzo y los intervalos que cruzan de 13:00 a 14:00.</p>
</div>
@endsection

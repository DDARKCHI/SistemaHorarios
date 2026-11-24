@extends('layouts.app')

@section('title', 'Inicio')

@section('page-title', 'Inicio')

@section('content')

<div class="space-y-6">

    <x-encabezado-pagina :titulo="'Bienvenido al sistema, '.auth()->user()->name" />

    <!-- Estado del período académico -->
    <div class="flex min-w-0 flex-wrap items-center justify-between gap-x-4 gap-y-2 rounded-xl border border-borde bg-superficie px-4 py-3 shadow-sm">
        <div class="flex min-w-0 flex-wrap items-baseline gap-x-3 gap-y-1">
            <span class="text-xs font-semibold uppercase tracking-wide text-texto-secundario">Período académico</span>
            <span class="text-base font-semibold text-texto">{{ $periodoActivo?->nombre ?? 'Sin período activo' }}</span>
            @if ($periodoActivo)
                <span class="text-sm text-texto-secundario">Semestre {{ $periodoActivo->numero_semestre }} de {{ $periodoActivo->anio }}</span>
            @endif
        </div>
        <span class="inline-flex w-fit items-center rounded-full bg-fondo px-3 py-1 text-xs font-medium text-texto-secundario">
            {{ $versionSemestral ? 'Semestral · ' . $versionSemestral->estado : 'Sin programación semestral' }}
        </span>
    </div>




    <!-- Tarjetas resumen  -->
    <div class="grid min-w-0 grid-cols-2 gap-3 sm:gap-4 xl:grid-cols-4">

        <!-- Carreras -->
        <div class="min-w-0 rounded-xl border border-borde bg-superficie p-4 shadow-sm sm:p-5">

            <div class="flex items-start justify-between">

                <div>

                    <p class="text-sm text-texto-secundario">
                        Carreras
                    </p>

                    <p class="mt-2 text-3xl font-bold text-texto">
                        {{ number_format($totales['carreras'], 0, ',', '.') }}
                    </p>

                    <p class="mt-1 text-xs text-texto-secundario">
                        Activas en el sistema
                    </p>

                </div>

                <div class="hidden h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-red-50 text-institucional sm:flex">

                    <svg class="h-5 w-5"
                         fill="none"
                         stroke="currentColor"
                         viewBox="0 0 24 24"
                         aria-hidden="true">

                        <path stroke-linecap="round"
                              stroke-linejoin="round"
                              stroke-width="2"
                              d="M12 14l9-5-9-5-9 5 9 5z" />

                        <path stroke-linecap="round"
                              stroke-linejoin="round"
                              stroke-width="2"
                              d="M5 12v5c3 2 11 2 14 0v-5" />

                    </svg>

                </div>

            </div>

        </div>


        <!-- Profesores  -->
        <div class="min-w-0 rounded-xl border border-borde bg-superficie p-4 shadow-sm sm:p-5">

            <div class="flex items-start justify-between">

                <div>

                    <p class="text-sm text-texto-secundario">
                        Docentes
                    </p>

                    <p class="mt-2 text-3xl font-bold text-texto">
                        {{ number_format($totales['profesores'], 0, ',', '.') }}
                    </p>

                    <p class="mt-1 text-xs text-texto-secundario">
                        Activos en el sistema
                    </p>

                </div>

                <div class="hidden h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-red-50 text-institucional sm:flex">

                    <svg class="h-5 w-5"
                         fill="none"
                         stroke="currentColor"
                         viewBox="0 0 24 24"
                         aria-hidden="true">

                        <path stroke-linecap="round"
                              stroke-linejoin="round"
                              stroke-width="2"
                              d="M16 21v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2" />

                        <circle cx="9" cy="7" r="4"
                                stroke-width="2" />

                        <path stroke-linecap="round"
                              stroke-linejoin="round"
                              stroke-width="2"
                              d="M22 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75" />

                    </svg>

                </div>

            </div>

        </div>


        <!-- Salas -->
        <div class="min-w-0 rounded-xl border border-borde bg-superficie p-4 shadow-sm sm:p-5">

            <div class="flex items-start justify-between">

                <div>

                    <p class="text-sm text-texto-secundario">
                        Salas
                    </p>

                    <p class="mt-2 text-3xl font-bold text-texto">
                        {{ number_format($totales['salas'], 0, ',', '.') }}
                    </p>

                    <p class="mt-1 text-xs text-texto-secundario">
                        Activas en el sistema
                    </p>

                </div>

                <div class="hidden h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-red-50 text-institucional sm:flex">

                    <svg class="h-5 w-5"
                         fill="none"
                         stroke="currentColor"
                         viewBox="0 0 24 24"
                         aria-hidden="true">

                        <path stroke-linecap="round"
                              stroke-linejoin="round"
                              stroke-width="2"
                              d="M3 21h18M5 21V5l7-3 7 3v16M9 21v-5h6v5M8 9h1m-1 4h1m6-4h1m-1 4h1" />

                    </svg>

                </div>

            </div>

        </div>


        <!-- Secciones del período -->
        <div class="min-w-0 rounded-xl border border-borde bg-superficie p-4 shadow-sm sm:p-5">

            <div class="flex items-start justify-between">

                <div>

                    <p class="text-sm text-texto-secundario">
                        Secciones
                    </p>

                    <p class="mt-2 text-3xl font-bold text-texto">
                        {{ number_format($totales['secciones'], 0, ',', '.') }}
                    </p>

                    <p class="mt-1 text-xs text-texto-secundario">
                        Activas en el período
                    </p>

                </div>

                <div class="hidden h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-red-50 text-institucional sm:flex">

                    <svg class="h-5 w-5"
                         fill="none"
                         stroke="currentColor"
                         viewBox="0 0 24 24"
                         aria-hidden="true">

                        <path stroke-linecap="round"
                              stroke-linejoin="round"
                              stroke-width="2"
                              d="M4 5a2 2 0 012-2h12a2 2 0 012 2v14a2 2 0 01-2 2H6a2 2 0 01-2-2V5z" />

                        <path stroke-linecap="round"
                              stroke-linejoin="round"
                              stroke-width="2"
                              d="M8 7h8M8 11h8M8 15h5" />

                    </svg>

                </div>

            </div>

        </div>

    </div>


    
    <section aria-labelledby="titulo-indicadores" class="min-w-0 space-y-3">
        <div class="flex min-w-0 flex-wrap items-center justify-between gap-2">
            <h2 id="titulo-indicadores" class="text-base font-bold text-texto">Avance de la programación</h2>
        </div>
        <div class="grid min-w-0 grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($indicadores['graficos'] as $indicador)
                <x-indicador-circular :indicador="$indicador" />
            @endforeach
        </div>
    </section>

    <div class="grid min-w-0 grid-cols-1 gap-6 xl:grid-cols-3">
        <section class="min-w-0 rounded-xl border border-borde bg-superficie shadow-sm xl:col-span-2" aria-labelledby="resumen-programacion">
            <div class="border-b border-borde px-5 py-4">
                <h2 id="resumen-programacion" class="font-semibold text-texto">Programación semestral</h2>
                @if ($versionSemestral)
                    <p class="mt-1 text-sm text-texto-secundario">Versión {{ $versionSemestral->version }} · {{ $versionSemestral->estado }}</p>
                @else
                    <p class="mt-1 text-sm text-texto-secundario">Resumen del período activo.</p>
                @endif
            </div>

            <div class="space-y-4 p-5">
                @if ($versionSemestral)
                    <dl class="grid min-w-0 grid-cols-1 gap-3 sm:grid-cols-3">
                        @foreach (['modulos' => 'Módulos programados', 'grupos' => 'Grupos con horarios', 'secciones' => 'Secciones con horarios'] as $clave => $etiqueta)
                            <div class="min-w-0 rounded-lg bg-fondo p-4">
                                <dt class="text-xs text-texto-secundario">{{ $etiqueta }}</dt>
                                <dd class="mt-1 text-2xl font-bold text-texto">{{ number_format($resumen[$clave], 0, ',', '.') }}</dd>
                            </div>
                        @endforeach
                    </dl>

                    @if ($resumen['modulos'] === 0)
                        <p class="rounded-lg border border-borde bg-fondo p-4 text-sm text-texto-secundario">Esta versión todavía no tiene módulos programados.</p>
                    @else
                        <p class="text-xs text-texto-secundario">Se cuentan los módulos activos de esta versión. Abre la programación y aplica un filtro para consultar sus horarios.</p>
                    @endif
                @else
                    <div class="rounded-lg border border-dashed border-borde bg-fondo p-5">
                        <p class="font-medium text-texto">{{ $periodoActivo ? 'Sin programación semestral actual' : 'Sin período activo' }}</p>
                        <p class="mt-1 text-sm text-texto-secundario">
                            {{ $periodoActivo ? 'Todavía no hay una programación semestral vigente para este período.' : 'Cuando haya un período activo, aquí verás el resumen de su programación semestral.' }}
                        </p>
                    </div>
                @endif

                @can('ver programacion semestral')
                    <a href="{{ $urlProgramacion }}" class="inline-flex min-h-11 w-full shrink-0 items-center justify-center gap-2 whitespace-nowrap rounded-lg bg-institucional px-4 py-2 text-center text-sm font-semibold text-white transition [overflow-wrap:normal] hover:bg-institucional-oscuro sm:w-auto">
                        Consultar programación
                        <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m9 5 7 7-7 7" /></svg>
                    </a>
                @endcan
            </div>
        </section>

        <section class="min-w-0 rounded-xl border border-borde bg-superficie shadow-sm" aria-labelledby="titulo-accesos">
            <div class="border-b border-borde px-5 py-4">
                <h2 id="titulo-accesos" class="font-semibold text-texto">Accesos rápidos</h2>
                <p class="mt-1 text-sm text-texto-secundario">Continúa con las tareas disponibles para tu cuenta.</p>
            </div>

            <nav class="p-5" aria-label="Accesos rápidos">
                <ul class="space-y-2">
                    @forelse ($accesos as $acceso)
                        <li>
                            <a href="{{ $acceso['url'] }}" class="flex min-h-11 min-w-0 items-center justify-between gap-3 rounded-lg border border-borde px-3 py-2.5 text-sm font-medium text-texto transition hover:border-institucional hover:bg-institucional/5">
                                <span class="min-w-0">{{ $acceso['titulo'] }}</span>
                                <svg class="h-4 w-4 shrink-0 text-texto-secundario" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m9 5 7 7-7 7" /></svg>
                            </a>
                        </li>
                    @empty
                        <li class="text-sm text-texto-secundario">No hay otras acciones disponibles para tu cuenta.</li>
                    @endforelse
                </ul>
            </nav>
        </section>
    </div>
</div>

@endsection

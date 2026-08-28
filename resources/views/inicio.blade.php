@extends('layouts.app')

@section('title', 'Inicio')

@section('page-title', 'Inicio')

@section('content')

<div class="space-y-6">

    {{-- Encabezado --}}
    <div>
        <h2 class="text-2xl font-bold text-texto">
            Bienvenido al sistema
        </h2>

        <p class="mt-1 text-sm text-texto-secundario">
            Gestión y planificación académica de horarios, salas y recursos.
        </p>
    </div>


    {{-- Estado del periodo --}}
    <div class="rounded-xl border border-borde bg-superficie p-5 shadow-sm">

        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">

            <div>

                <p class="text-xs font-semibold uppercase tracking-wide text-texto-secundario">
                    Periodo académico
                </p>

                <p class="mt-1 text-lg font-semibold text-texto">
                    No configurado
                </p>

            </div>

            <span class="inline-flex w-fit items-center rounded-full bg-fondo px-3 py-1 text-xs font-medium text-texto-secundario">
                Sin planificación activa
            </span>

        </div>

    </div>


    {{-- Tarjetas de resumen --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">

        {{-- Carreras --}}
        <div class="rounded-xl border border-borde bg-superficie p-5 shadow-sm">

            <div class="flex items-start justify-between">

                <div>

                    <p class="text-sm text-texto-secundario">
                        Carreras
                    </p>

                    <p class="mt-2 text-3xl font-bold text-texto">
                        0
                    </p>

                    <p class="mt-1 text-xs text-texto-secundario">
                        Registradas
                    </p>

                </div>

                <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-red-50 text-institucional">

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


        {{-- Profesores --}}
        <div class="rounded-xl border border-borde bg-superficie p-5 shadow-sm">

            <div class="flex items-start justify-between">

                <div>

                    <p class="text-sm text-texto-secundario">
                        Profesores
                    </p>

                    <p class="mt-2 text-3xl font-bold text-texto">
                        0
                    </p>

                    <p class="mt-1 text-xs text-texto-secundario">
                        Registrados
                    </p>

                </div>

                <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-red-50 text-institucional">

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


        {{-- Salas --}}
        <div class="rounded-xl border border-borde bg-superficie p-5 shadow-sm">

            <div class="flex items-start justify-between">

                <div>

                    <p class="text-sm text-texto-secundario">
                        Salas
                    </p>

                    <p class="mt-2 text-3xl font-bold text-texto">
                        0
                    </p>

                    <p class="mt-1 text-xs text-texto-secundario">
                        Registradas
                    </p>

                </div>

                <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-red-50 text-institucional">

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


        {{-- Cursos --}}
        <div class="rounded-xl border border-borde bg-superficie p-5 shadow-sm">

            <div class="flex items-start justify-between">

                <div>

                    <p class="text-sm text-texto-secundario">
                        Cursos
                    </p>

                    <p class="mt-2 text-3xl font-bold text-texto">
                        0
                    </p>

                    <p class="mt-1 text-xs text-texto-secundario">
                        Registrados
                    </p>

                </div>

                <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-red-50 text-institucional">

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


    {{-- Panel inferior --}}
    <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">

        {{-- Vista semanal --}}
        <div class="xl:col-span-2 rounded-xl border border-borde bg-superficie shadow-sm">

            <div class="border-b border-borde px-5 py-4">

                <h3 class="font-semibold text-texto">
                    Vista semanal
                </h3>

                <p class="mt-1 text-sm text-texto-secundario">
                    Resumen general de la planificación.
                </p>

            </div>

            <div class="p-5">

                <div class="flex min-h-56 items-center justify-center rounded-lg border border-dashed border-borde bg-fondo">

                    <div class="text-center">

                        <p class="font-medium text-texto">
                            No hay planificación disponible
                        </p>

                        <p class="mt-1 text-sm text-texto-secundario">
                            La vista semanal estará disponible cuando exista un horario generado.
                        </p>

                    </div>

                </div>

            </div>

        </div>


        {{-- Estado de validación --}}
        <div class="rounded-xl border border-borde bg-superficie shadow-sm">

            <div class="border-b border-borde px-5 py-4">

                <h3 class="font-semibold text-texto">
                    Estado de validación
                </h3>

                <p class="mt-1 text-sm text-texto-secundario">
                    Estado general de la planificación.
                </p>

            </div>

            <div class="space-y-4 p-5">

                <div class="flex items-center justify-between">

                    <span class="text-sm text-texto-secundario">
                        Válidas
                    </span>

                    <span class="font-semibold text-texto">
                        0
                    </span>

                </div>

                <div class="h-2 rounded-full bg-fondo">
                    <div class="h-2 w-0 rounded-full bg-institucional"></div>
                </div>


                <div class="flex items-center justify-between">

                    <span class="text-sm text-texto-secundario">
                        Con observaciones
                    </span>

                    <span class="font-semibold text-texto">
                        0
                    </span>

                </div>

                <div class="h-2 rounded-full bg-fondo">
                    <div class="h-2 w-0 rounded-full bg-institucional"></div>
                </div>


                <div class="flex items-center justify-between">

                    <span class="text-sm text-texto-secundario">
                        Inválidas
                    </span>

                    <span class="font-semibold text-texto">
                        0
                    </span>

                </div>

                <div class="h-2 rounded-full bg-fondo">
                    <div class="h-2 w-0 rounded-full bg-institucional"></div>
                </div>

            </div>

        </div>

    </div>

</div>

@endsection
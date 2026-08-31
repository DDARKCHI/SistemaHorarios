<aside class="fixed inset-y-0 left-0 z-40 hidden w-64 border-r border-borde bg-superficie lg:block">

    <div class="flex h-full flex-col">

       <!-- Logo y nombre del sistema -->
<div class="border-b border-borde px-6 py-5">

    <div class="flex justify-center">
        <img
            src="{{ asset('images/main-logo.svg') }}"
            alt="Universidad Católica de la Santísima Concepción"
            class="h-16 w-auto"
        >
    </div>

    <div class="mt-3">

        <p class="text-lg font-bold text-texto">
            Sistema de Gestión
        </p>

        <p class="text-lg font-bold text-texto">
            de Horarios y Salas
        </p>

        <p class="mt-1 text-sm text-texto-secundario">
            Gestión académica
        </p>

    </div>

</div>

        {{-- Navegación --}}
        <nav class="flex-1 overflow-y-auto px-4 py-6">

            <p class="mb-3 px-3 text-xs font-semibold uppercase tracking-wider text-texto-secundario">
                Menú principal
            </p>

            <div class="space-y-1">

    {{-- Inicio --}}
    <a href="#"
       class="group flex items-center gap-3 rounded-lg bg-institucional px-3 py-2.5 text-sm font-medium text-white transition-colors">

        <svg class="h-5 w-5 shrink-0"
             fill="none"
             stroke="currentColor"
             viewBox="0 0 24 24"
             aria-hidden="true">

            <path stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M3 12l9-9 9 9M5 10v10h14V10M9 20v-6h6v6" />

        </svg>

        <span>Inicio</span>

    </a>


    {{-- Planificación --}}
    <a href="#"
       class="group flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-texto transition-colors hover:bg-fondo">

        <svg class="h-5 w-5 shrink-0 text-texto-secundario group-hover:text-institucional"
             fill="none"
             stroke="currentColor"
             viewBox="0 0 24 24"
             aria-hidden="true">

            <path stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M8 7V3m8 4V3M4 11h16M5 5h14a1 1 0 011 1v14a1 1 0 01-1 1H5a1 1 0 01-1-1V6a1 1 0 011-1z" />

        </svg>

        <span>Planificación</span>

    </a>


    {{-- Horarios --}}
    <a href="#"
       class="group flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-texto transition-colors hover:bg-fondo">

        <svg class="h-5 w-5 shrink-0 text-texto-secundario group-hover:text-institucional"
             fill="none"
             stroke="currentColor"
             viewBox="0 0 24 24"
             aria-hidden="true">

            <path stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M12 8v4l3 2m6-2a9 9 0 11-18 0 9 9 0 0118 0z" />

        </svg>

        <span>Horarios</span>

    </a>


    {{-- Profesores --}}
    <a href="#"
       class="group flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-texto transition-colors hover:bg-fondo">

        <svg class="h-5 w-5 shrink-0 text-texto-secundario group-hover:text-institucional"
             fill="none"
             stroke="currentColor"
             viewBox="0 0 24 24"
             aria-hidden="true">

            <path stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M16 21v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2M9 11a4 4 0 100-8 4 4 0 000 8zm13 10v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75" />

        </svg>

        <span>Profesores</span>

    </a>


    {{-- Salas y recursos --}}
    <a href="#"
       class="group flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-texto transition-colors hover:bg-fondo">

        <svg class="h-5 w-5 shrink-0 text-texto-secundario group-hover:text-institucional"
             fill="none"
             stroke="currentColor"
             viewBox="0 0 24 24"
             aria-hidden="true">

            <path stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M3 21h18M5 21V5l7-3 7 3v16M9 21v-5h6v5M8 9h1m-1 4h1m6-4h1m-1 4h1" />

        </svg>

        <span>Salas y recursos</span>

    </a>


    {{-- Validación --}}
    <a href="#"
       class="group flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-texto transition-colors hover:bg-fondo">

        <svg class="h-5 w-5 shrink-0 text-texto-secundario group-hover:text-institucional"
             fill="none"
             stroke="currentColor"
             viewBox="0 0 24 24"
             aria-hidden="true">

            <path stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M9 12l2 2 4-4m5-2a9 9 0 11-18 0 9 9 0 0118 0z" />

        </svg>

        <span>Validación</span>

    </a>


    {{-- Reportes --}}
    <a href="#"
       class="group flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-texto transition-colors hover:bg-fondo">

        <svg class="h-5 w-5 shrink-0 text-texto-secundario group-hover:text-institucional"
             fill="none"
             stroke="currentColor"
             viewBox="0 0 24 24"
             aria-hidden="true">

            <path stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M4 19V5m0 14h16M8 16v-5m4 5V7m4 9v-8" />

        </svg>

        <span>Reportes</span>

    </a>

</div>

        </nav>

        {{-- Parte inferior --}}
        <div class="border-t border-borde p-4">

            <p class="text-xs text-texto-secundario">
                Sistema de Gestión de Horarios y Salas
            </p>

        </div>

    </div>

</aside>

<!-- Sidebar móvil -->
<div
    id="sidebar-movil"
    class="fixed inset-0 z-50 hidden lg:hidden"
    aria-hidden="true"
>

    <!-- Fondo oscuro -->
    <div
        id="fondo-sidebar-movil"
        class="absolute inset-0 bg-black/40"
    ></div>

    <!-- Panel lateral -->
    <aside
        class="absolute inset-y-0 left-0 w-72 max-w-[85vw] bg-superficie shadow-xl"
    >

        <div class="flex h-full flex-col">

        <!-- Encabezado del Sidebar móvil -->
<div class="relative border-b border-borde px-6 py-5">

    <!-- Botón cerrar -->
    <button
        type="button"
        id="cerrar-menu-movil"
        class="absolute right-5 top-5 flex h-9 w-9 items-center justify-center rounded-lg text-texto-secundario transition-colors hover:bg-fondo hover:text-texto"
        aria-label="Cerrar menú"
    >

        <svg
            class="h-5 w-5"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
            aria-hidden="true"
        >

            <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M6 18L18 6M6 6l12 12"
            />

        </svg>

    </button>


    <!-- Logo -->
    <div class="flex justify-center">

        <img
            src="{{ asset('images/main-logo.svg') }}"
            alt="Universidad Católica de la Santísima Concepción"
            class="h-14 w-auto -translate-x-2"
        >

    </div>


   <!-- Nombre del sistema -->
<div class="mt-3">

    <p class="text-lg font-bold text-texto">
        Sistema de Gestión
    </p>

    <p class="text-lg font-bold text-texto">
        de Horarios y Salas
    </p>

    <p class="mt-1 text-sm text-texto-secundario">
        Gestión académica
    </p>

</div>

</div>

            <!-- Navegación móvil -->
            <nav class="flex-1 overflow-y-auto px-4 py-6">

                <p class="mb-3 px-3 text-xs font-semibold uppercase tracking-wider text-texto-secundario">
                    Menú principal
                </p>

                <div class="space-y-1">

                    <!-- Inicio -->
                    <a
                        href="#"
                        class="flex items-center rounded-lg bg-institucional px-3 py-2.5 text-sm font-medium text-white"
                    >
                        Inicio
                    </a>

                    <!-- Planificación -->
                    <a
                        href="#"
                        class="flex items-center rounded-lg px-3 py-2.5 text-sm font-medium text-texto transition-colors hover:bg-fondo"
                    >
                        Planificación
                    </a>

                    <!-- Horarios -->
                    <a
                        href="#"
                        class="flex items-center rounded-lg px-3 py-2.5 text-sm font-medium text-texto transition-colors hover:bg-fondo"
                    >
                        Horarios
                    </a>

                    <!-- Profesores -->
                    <a
                        href="#"
                        class="flex items-center rounded-lg px-3 py-2.5 text-sm font-medium text-texto transition-colors hover:bg-fondo"
                    >
                        Profesores
                    </a>

                    <!-- Salas y recursos -->
                    <a
                        href="#"
                        class="flex items-center rounded-lg px-3 py-2.5 text-sm font-medium text-texto transition-colors hover:bg-fondo"
                    >
                        Salas y recursos
                    </a>

                    <!-- Validación -->
                    <a
                        href="#"
                        class="flex items-center rounded-lg px-3 py-2.5 text-sm font-medium text-texto transition-colors hover:bg-fondo"
                    >
                        Validación
                    </a>

                    <!-- Reportes -->
                    <a
                        href="#"
                        class="flex items-center rounded-lg px-3 py-2.5 text-sm font-medium text-texto transition-colors hover:bg-fondo"
                    >
                        Reportes
                    </a>

                </div>

            </nav>

        </div>

    </aside>

</div>
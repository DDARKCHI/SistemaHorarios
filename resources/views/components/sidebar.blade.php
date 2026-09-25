@php

    $inicioActivo = request()->routeIs('inicio');



    $programacionSemestralActiva =

        request()->routeIs('programacion.semestral*')

        || request()->routeIs('programacion.generar*')

        || request()->routeIs('programacion.asignacion*')

        || request()->routeIs('programacion.conflictos*')

        || request()->routeIs('programacion.visualizar*')
        || request()->routeIs('programacion.propuesta*');



    $intercambioActivo =

        request()->routeIs('programacion.intercambio*');



    $salasRecursosActiva =

        request()->routeIs('salas*')

        || request()->routeIs('laboratorios*')

        || request()->routeIs('recursos*');



    $programacionAnteriorActiva =

        request()->routeIs('programacion.importar*')

        || request()->routeIs('programacion.index');



    $administracionActiva =

        request()->routeIs('administracion*')

        || request()->routeIs('usuarios*')

        || request()->routeIs('roles*');



    $configuracionActiva =

        request()->routeIs('configuracion*');







@endphp



<aside

    id="sidebar-escritorio"

    class="fixed inset-y-0 left-0 z-40 hidden w-64 border-r border-borde bg-superficie transition-all duration-300 lg:block"

>

    <button

        type="button"

        id="boton-sidebar"

        class="absolute -right-4 top-7 z-50 flex h-9 w-9 items-center justify-center rounded-full border border-borde bg-superficie text-texto-secundario shadow-md transition-colors hover:bg-fondo hover:text-institucional"

        aria-label="Contraer menú lateral"

        aria-controls="sidebar-escritorio"

        aria-expanded="true"

    >

        <svg

            id="icono-sidebar"

            class="h-5 w-5"

            fill="none"

            stroke="currentColor"

            viewBox="0 0 24 24"

        >

            <path

                stroke-linecap="round"

                stroke-linejoin="round"

                stroke-width="2"

                d="M15 6l-6 6 6 6"

            />

        </svg>

    </button>



    <div class="flex h-full flex-col">



        <div

            id="contenedor-logo-prosec"

            class="flex justify-center border-b border-borde px-5 py-5 transition-all duration-300"

        >

            <img

                id="logo-prosec-completo"

                src="{{ asset('images/prosec-logo.svg') }}"

                alt="PROSEC"

                class="h-auto w-full max-w-[210px]"

            >



            <img

                id="logo-prosec-mini"

                src="{{ asset('images/prosec-mini.svg') }}"

                alt="PROSEC"

                class="hidden h-auto w-full max-w-[55px]"

            >

        </div>



        <nav class="flex-1 overflow-y-auto px-4 py-5">



            @can('ver panel inicio')

            <div class="mb-2">

                <a

                    href="{{ route('inicio') }}"

                    data-sidebar-row

                    data-sidebar-main

                    class="group flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-colors

                        {{ $inicioActivo

                            ? 'bg-institucional text-white'

                            : 'text-texto hover:bg-fondo' }}"

                >

                    <svg

                        class="h-5 w-5 shrink-0"

                        fill="none"

                        stroke="currentColor"

                        viewBox="0 0 24 24"

                    >

                        <path

                            stroke-linecap="round"

                            stroke-linejoin="round"

                            stroke-width="2"

                            d="M3 12l9-9 9 9M5 10v10h14V10M9 20v-6h6v6"

                        />

                    </svg>



                    <span>Inicio</span>

                </a>

            </div>

            @endcan



            @can('ver programacion semestral')

            <div class="mb-2">

                <button

                    type="button"

                    data-sidebar-toggle

                    data-sidebar-row

                    data-sidebar-main

                    data-sidebar-title="Programación semestral"

                    data-first-url="{{ route('programacion.semestral') }}"

                    data-target="submenu-programacion-semestral"

                    data-route-active="{{ $programacionSemestralActiva ? 'true' : 'false' }}"

                    aria-expanded="{{ $programacionSemestralActiva ? 'true' : 'false' }}"

                    class="group flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-left text-sm font-semibold transition-colors

                        {{ $programacionSemestralActiva

                            ? 'bg-institucional text-white'

                            : 'text-texto hover:bg-fondo' }}"

                >

                    <svg

                        class="h-5 w-5 shrink-0"

                        fill="none"

                        stroke="currentColor"

                        viewBox="0 0 24 24"

                    >

                        <path

                            stroke-linecap="round"

                            stroke-linejoin="round"

                            stroke-width="2"

                            d="M8 7V3m8 4V3M4 11h16M5 5h14a1 1 0 011 1v14a1 1 0 01-1 1H5a1 1 0 01-1-1V6a1 1 0 011-1z"

                        />

                    </svg>



                    <span class="flex-1">Programación semestral</span>



                    <svg

                        data-sidebar-chevron

                        class="h-4 w-4 shrink-0 transition-transform duration-200 {{ $programacionSemestralActiva ? 'rotate-180' : '' }}"

                        fill="none"

                        stroke="currentColor"

                        viewBox="0 0 24 24"

                    >

                        <path

                            stroke-linecap="round"

                            stroke-linejoin="round"

                            stroke-width="2"

                            d="M19 9l-7 7-7-7"

                        />

                    </svg>

                </button>



                <div

                    id="submenu-programacion-semestral"

                    data-sidebar-submenu

                    class="mt-1 space-y-1 pl-4 {{ $programacionSemestralActiva ? '' : 'hidden' }}"

                >

                    @can('ver programacion semestral')

                    <a

                        href="{{ route('programacion.semestral') }}"

                        class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-colors

                            {{ request()->routeIs('programacion.semestral')

                                ? 'bg-gray-100 text-gray-800'

                                : 'text-texto hover:bg-gray-50 hover:text-institucional' }}"

                    >

                        <span

                            class="h-1.5 w-1.5 rounded-full

                                {{ request()->routeIs('programacion.semestral')

                                    ? 'bg-institucional'

                                    : 'bg-gray-300' }}"

                        ></span>



                        <span>Visualizar programación</span>

                    </a>

                    @endcan



                    @can('generar programacion semestral')

                    <div class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm text-gray-400">

                        <span class="h-1.5 w-1.5 rounded-full bg-gray-300"></span>

                        <span class="flex-1">Generar programación</span>

                        <span class="text-[10px]">Próximamente</span>

                    </div>

                    @endcan



                    @can('asignar profesores')

                    <div class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm text-gray-400">

                        <span class="h-1.5 w-1.5 rounded-full bg-gray-300"></span>

                        <span class="flex-1">Asignación de profesores</span>

                        <span class="text-[10px]">Próximamente</span>

                    </div>

                    @endcan



                    @can('asignar horarios')

                    <div class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm text-gray-400">

                        <span class="h-1.5 w-1.5 rounded-full bg-gray-300"></span>

                        <span class="flex-1">Asignación de horarios</span>

                        <span class="text-[10px]">Próximamente</span>

                    </div>

                    @endcan



                    @can('gestionar conflictos')

                    <div class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm text-gray-400">

                        <span class="h-1.5 w-1.5 rounded-full bg-gray-300"></span>

                        <span class="flex-1">Gestión de conflictos</span>

                        <span class="text-[10px]">Próximamente</span>

                    </div>

                    @endcan



                    @can('enviar propuesta horario')

                    <a
                        href="{{ route('programacion.propuesta') }}"
                        class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-colors
                            {{ request()->routeIs('programacion.propuesta*')
                                ? 'bg-gray-100 text-gray-800'
                                : 'text-texto hover:bg-gray-50 hover:text-institucional' }}"
                    >
                        <span
                            class="h-1.5 w-1.5 rounded-full
                                {{ request()->routeIs('programacion.propuesta*')
                                    ? 'bg-institucional'
                                    : 'bg-gray-300' }}"
                        ></span>

                        <span>Propuesta de horario</span>
                    </a>

                    @endcan

                </div>

            </div>

            @endcan



            @can('ver intercambio docente')

            <div class="mb-2">

                <button

                    type="button"

                    data-sidebar-toggle

                    data-sidebar-row

                    data-sidebar-main

                    data-sidebar-title="Intercambio docente"

                    data-first-url="{{ route('programacion.intercambio') }}"

                    data-target="submenu-intercambio"

                    data-route-active="{{ $intercambioActivo ? 'true' : 'false' }}"

                    aria-expanded="{{ $intercambioActivo ? 'true' : 'false' }}"

                    class="group flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-left text-sm font-semibold transition-colors

                        {{ $intercambioActivo

                            ? 'bg-institucional text-white'

                            : 'text-texto hover:bg-fondo' }}"

                >

                    <svg

                        class="h-5 w-5 shrink-0"

                        fill="none"

                        stroke="currentColor"

                        viewBox="0 0 24 24"

                    >

                        <path

                            stroke-linecap="round"

                            stroke-linejoin="round"

                            stroke-width="2"

                            d="M17 8h4l-4-4m4 4H7a4 4 0 00-4 4m4 4H3l4 4m-4-4h14a4 4 0 004-4"

                        />

                    </svg>



                    <span class="flex-1">Intercambio docente</span>



                    <svg

                        data-sidebar-chevron

                        class="h-4 w-4 shrink-0 transition-transform duration-200 {{ $intercambioActivo ? 'rotate-180' : '' }}"

                        fill="none"

                        stroke="currentColor"

                        viewBox="0 0 24 24"

                    >

                        <path

                            stroke-linecap="round"

                            stroke-linejoin="round"

                            stroke-width="2"

                            d="M19 9l-7 7-7-7"

                        />

                    </svg>

                </button>



                <div

                    id="submenu-intercambio"

                    data-sidebar-submenu

                    class="mt-1 space-y-1 pl-4 {{ $intercambioActivo ? '' : 'hidden' }}"

                >

                    <a

                        href="{{ route('programacion.intercambio') }}"

                        class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-colors

                            {{ request()->routeIs('programacion.intercambio')

                                ? 'bg-gray-100 text-gray-800'

                                : 'text-texto hover:bg-gray-50 hover:text-institucional' }}"

                    >

                        <span

                            class="h-1.5 w-1.5 rounded-full

                                {{ request()->routeIs('programacion.intercambio')

                                    ? 'bg-institucional'

                                    : 'bg-gray-300' }}"

                        ></span>



                        <span>Consultar reemplazo</span>

                    </a>



                    <div class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm text-gray-400">

                        <span class="h-1.5 w-1.5 rounded-full bg-gray-300"></span>

                        <span class="flex-1">Historial de reemplazos</span>

                        <span class="text-[10px]">Próximamente</span>

                    </div>

                </div>

            </div>



            @endcan

            @can('gestionar salas y recursos')

            <div class="mb-2">

                <button

                    type="button"

                    data-sidebar-toggle

                    data-sidebar-row

                    data-sidebar-main

                    data-sidebar-title="Salas y recursos"

                    data-first-url="{{ route('salas.index') }}"

                    data-target="submenu-salas-recursos"

                    data-route-active="{{ $salasRecursosActiva ? 'true' : 'false' }}"

                    aria-expanded="{{ $salasRecursosActiva ? 'true' : 'false' }}"

                    class="group flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-left text-sm font-semibold transition-colors

                        {{ $salasRecursosActiva

                            ? 'bg-institucional text-white'

                            : 'text-texto hover:bg-fondo' }}"

                >

                    <svg

                        class="h-5 w-5 shrink-0"

                        fill="none"

                        stroke="currentColor"

                        viewBox="0 0 24 24"

                    >

                        <path

                            stroke-linecap="round"

                            stroke-linejoin="round"

                            stroke-width="2"

                            d="M4 21V10l8-5 8 5v11M9 21v-6h6v6M8 10h8"

                        />

                    </svg>



                    <span class="flex-1">Salas y recursos</span>



                    <svg

                        data-sidebar-chevron

                        class="h-4 w-4 shrink-0 transition-transform duration-200 {{ $salasRecursosActiva ? 'rotate-180' : '' }}"

                        fill="none"

                        stroke="currentColor"

                        viewBox="0 0 24 24"

                    >

                        <path

                            stroke-linecap="round"

                            stroke-linejoin="round"

                            stroke-width="2"

                            d="M19 9l-7 7-7-7"

                        />

                    </svg>

                </button>



                <div

                    id="submenu-salas-recursos"

                    data-sidebar-submenu

                    class="mt-1 space-y-1 pl-4 {{ $salasRecursosActiva ? '' : 'hidden' }}"

                >

                    <a

                        href="{{ route('salas.index') }}"

                        class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-colors

                            {{ request()->routeIs('salas.index')

                                ? 'bg-gray-100 text-gray-800'

                                : 'text-texto hover:bg-gray-50 hover:text-institucional' }}"

                    >

                        <span

                            class="h-1.5 w-1.5 rounded-full

                                {{ request()->routeIs('salas.index')

                                    ? 'bg-institucional'

                                    : 'bg-gray-300' }}"

                        ></span>



                        <span>Asignación de salas</span>

                    </a>



                    <div class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm text-gray-400">

                        <span class="h-1.5 w-1.5 rounded-full bg-gray-300"></span>

                        <span class="flex-1">Laboratorios</span>

                        <span class="text-[10px]">Próximamente</span>

                    </div>



                    <div class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm text-gray-400">

                        <span class="h-1.5 w-1.5 rounded-full bg-gray-300"></span>

                        <span class="flex-1">Equipamiento y recursos</span>

                        <span class="text-[10px]">Próximamente</span>

                    </div>

                </div>

            </div>



            @endcan

            @can('ver programacion anterior')

            <div class="mb-2">

                <button

                    type="button"

                    data-sidebar-toggle

                    data-sidebar-row

                    data-sidebar-main

                    data-sidebar-title="Programación anterior"

                    data-first-url="{{ route('programacion.index') }}"

                    data-target="submenu-programacion-anterior"

                    data-route-active="{{ $programacionAnteriorActiva ? 'true' : 'false' }}"

                    aria-expanded="{{ $programacionAnteriorActiva ? 'true' : 'false' }}"

                    class="group flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-left text-sm font-semibold transition-colors

                        {{ $programacionAnteriorActiva

                            ? 'bg-institucional text-white'

                            : 'text-texto hover:bg-fondo' }}"

                >

                    <svg

                        class="h-5 w-5 shrink-0"

                        fill="none"

                        stroke="currentColor"

                        viewBox="0 0 24 24"

                    >

                        <path

                            stroke-linecap="round"

                            stroke-linejoin="round"

                            stroke-width="2"

                            d="M12 8v4l3 2m6-2a9 9 0 11-18 0 9 9 0 0118 0z"

                        />

                    </svg>



                    <span class="flex-1">Programación anterior</span>



                    <svg

                        data-sidebar-chevron

                        class="h-4 w-4 shrink-0 transition-transform duration-200 {{ $programacionAnteriorActiva ? 'rotate-180' : '' }}"

                        fill="none"

                        stroke="currentColor"

                        viewBox="0 0 24 24"

                    >

                        <path

                            stroke-linecap="round"

                            stroke-linejoin="round"

                            stroke-width="2"

                            d="M19 9l-7 7-7-7"

                        />

                    </svg>

                </button>



                <div

                    id="submenu-programacion-anterior"

                    data-sidebar-submenu

                    class="mt-1 space-y-1 pl-4 {{ $programacionAnteriorActiva ? '' : 'hidden' }}"

                >

                    <a

                        href="{{ route('programacion.index') }}"

                        class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-colors

                            {{ request()->routeIs('programacion.index')

                                ? 'bg-gray-100 text-gray-800'

                                : 'text-texto hover:bg-gray-50 hover:text-institucional' }}"

                    >

                        <span

                            class="h-1.5 w-1.5 rounded-full

                                {{ request()->routeIs('programacion.index')

                                    ? 'bg-institucional'

                                    : 'bg-gray-300' }}"

                        ></span>



                        <span>Consultar programación anterior</span>

                    </a>



                    <a

                        href="{{ route('programacion.importar') }}"

                        class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-colors

                            {{ request()->routeIs('programacion.importar*')

                                ? 'bg-gray-100 text-gray-800'

                                : 'text-texto hover:bg-gray-50 hover:text-institucional' }}"

                    >

                        <span

                            class="h-1.5 w-1.5 rounded-full

                                {{ request()->routeIs('programacion.importar*')

                                    ? 'bg-institucional'

                                    : 'bg-gray-300' }}"

                        ></span>



                        <span>Cargar programación anterior</span>

                    </a>

                </div>

            </div>



            @endcan

            @can('administrar usuarios')

            <div class="mb-2">

                <button

                    type="button"

                    data-sidebar-toggle

                    data-sidebar-row

                    data-sidebar-main

                    data-sidebar-title="Administración"

                    data-first-url="{{ route('administracion.index') }}"

                    data-target="submenu-administracion"

                    data-route-active="{{ $administracionActiva ? 'true' : 'false' }}"

                    aria-expanded="{{ $administracionActiva ? 'true' : 'false' }}"

                    class="group flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-left text-sm font-semibold transition-colors

                        {{ $administracionActiva

                            ? 'bg-institucional text-white'

                            : 'text-texto hover:bg-fondo' }}"

                >

                    <svg

                        class="h-5 w-5 shrink-0"

                        fill="none"

                        stroke="currentColor"

                        viewBox="0 0 24 24"

                    >

                        <path

                            stroke-linecap="round"

                            stroke-linejoin="round"

                            stroke-width="2"

                            d="M16 21v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2M9 11a4 4 0 100-8 4 4 0 000 8zm13 10v-2a4 4 0 00-3-3.87"

                        />

                    </svg>



                    <span class="flex-1">Administración</span>



                    <svg

                        data-sidebar-chevron

                        class="h-4 w-4 shrink-0 transition-transform duration-200 {{ $administracionActiva ? 'rotate-180' : '' }}"

                        fill="none"

                        stroke="currentColor"

                        viewBox="0 0 24 24"

                    >

                        <path

                            stroke-linecap="round"

                            stroke-linejoin="round"

                            stroke-width="2"

                            d="M19 9l-7 7-7-7"

                        />

                    </svg>

                </button>



                <div

                    id="submenu-administracion"

                    data-sidebar-submenu

                    class="mt-1 space-y-1 pl-4 {{ $administracionActiva ? '' : 'hidden' }}"

                >

                    <a

                        href="{{ route('administracion.index') }}"

                        class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-colors

                            {{ request()->routeIs('administracion.index')

                                ? 'bg-gray-100 text-gray-800'

                                : 'text-texto hover:bg-gray-50 hover:text-institucional' }}"

                    >

                        <span

                            class="h-1.5 w-1.5 rounded-full

                                {{ request()->routeIs('administracion.index')

                                    ? 'bg-institucional'

                                    : 'bg-gray-300' }}"

                        ></span>



                        <span>Usuarios</span>

                    </a>



                    <div class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm text-gray-400">

                        <span class="h-1.5 w-1.5 rounded-full bg-gray-300"></span>

                        <span class="flex-1">Roles y permisos</span>

                        <span class="text-[10px]">Próximamente</span>

                    </div>

                </div>

            </div>



            @endcan

            @can('ver configuracion')

            <div>

                <button

                    type="button"

                    data-sidebar-toggle

                    data-sidebar-row

                    data-sidebar-main

                    data-sidebar-title="Configuración"

                    data-first-url="{{ route('configuracion.index') }}"

                    data-target="submenu-configuracion"

                    data-route-active="{{ $configuracionActiva ? 'true' : 'false' }}"

                    aria-expanded="{{ $configuracionActiva ? 'true' : 'false' }}"

                    class="group flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-left text-sm font-semibold transition-colors

                        {{ $configuracionActiva

                            ? 'bg-institucional text-white'

                            : 'text-texto hover:bg-fondo' }}"

                >

                    <svg

                        class="h-5 w-5 shrink-0"

                        fill="none"

                        stroke="currentColor"

                        viewBox="0 0 24 24"

                    >

                        <path

                            stroke-linecap="round"

                            stroke-linejoin="round"

                            stroke-width="2"

                            d="M12 15.5a3.5 3.5 0 100-7 3.5 3.5 0 000 7z"

                        />



                        <path

                            stroke-linecap="round"

                            stroke-linejoin="round"

                            stroke-width="2"

                            d="M19.4 15a1.7 1.7 0 00.34 1.88l.06.06a2 2 0 11-2.83 2.83l-.06-.06A1.7 1.7 0 0015 19.4a1.7 1.7 0 00-1 .6 1.7 1.7 0 00-.4 1.1V21a2 2 0 11-4 0v-.1a1.7 1.7 0 00-.4-1.1 1.7 1.7 0 00-1-.6 1.7 1.7 0 00-1.88.34l-.06.06a2 2 0 11-2.83-2.83l.06-.06A1.7 1.7 0 004.6 15a1.7 1.7 0 00-.6-1 1.7 1.7 0 00-1.1-.4H3a2 2 0 110-4h.1a1.7 1.7 0 001.1-.4 1.7 1.7 0 00.6-1 1.7 1.7 0 00-.34-1.88l-.06-.06a2 2 0 112.83-2.83l.06.06A1.7 1.7 0 009 4.6a1.7 1.7 0 001-.6 1.7 1.7 0 00.4-1.1V3a2 2 0 114 0v.1a1.7 1.7 0 00.4 1.1 1.7 1.7 0 001 .6 1.7 1.7 0 001.88-.34l.06-.06a2 2 0 112.83 2.83l-.06.06A1.7 1.7 0 0019.4 9c.4.23.72.58.9 1 .18.42.2.9.06 1.34-.14.44-.43.82-.82 1.08-.39.26-.86.38-1.32.34"

                        />

                    </svg>



                    <span class="flex-1">Configuración</span>



                    <svg

                        data-sidebar-chevron

                        class="h-4 w-4 shrink-0 transition-transform duration-200 {{ $configuracionActiva ? 'rotate-180' : '' }}"

                        fill="none"

                        stroke="currentColor"

                        viewBox="0 0 24 24"

                    >

                        <path

                            stroke-linecap="round"

                            stroke-linejoin="round"

                            stroke-width="2"

                            d="M19 9l-7 7-7-7"

                        />

                    </svg>

                </button>



                <div

                    id="submenu-configuracion"

                    data-sidebar-submenu

                    class="mt-1 space-y-1 pl-4 {{ $configuracionActiva ? '' : 'hidden' }}"

                >

                    <a

                        href="{{ route('configuracion.index') }}"

                        class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-colors

                            {{ request()->routeIs('configuracion.index')

                                ? 'bg-gray-100 text-gray-800'

                                : 'text-texto hover:bg-gray-50 hover:text-institucional' }}"

                    >

                        <span

                            class="h-1.5 w-1.5 rounded-full

                                {{ request()->routeIs('configuracion.index')

                                    ? 'bg-institucional'

                                    : 'bg-gray-300' }}"

                        ></span>



                        <span>Configuración general</span>

                    </a>

                </div>

            </div>

            @endcan



        </nav>



        <div class="flex shrink-0 justify-center border-t border-borde px-5 py-4">

            <img

                src="{{ asset('images/main-logo.svg') }}"

                alt="Universidad Católica de la Santísima Concepción"

                class="h-9 w-auto"

            >

        </div>



    </div>

</aside>



<div

    id="sidebar-flyout"

    class="fixed z-[70] hidden w-64 rounded-xl border border-gray-200 bg-white p-3 shadow-xl"

>

    <div

        id="sidebar-flyout-title"

        class="mb-2 border-b border-gray-200 px-2 pb-2.5 text-[15px] font-bold text-gray-800"

    ></div>



    <div

        id="sidebar-flyout-content"

        class="space-y-1"

    ></div>

</div>



<div

    id="sidebar-movil"

    class="fixed inset-0 z-50 hidden lg:hidden"

    aria-hidden="true"

>

    <div

        id="fondo-sidebar-movil"

        class="absolute inset-0 bg-black/40"

    ></div>



    <aside class="absolute inset-y-0 left-0 w-72 max-w-[85vw] bg-superficie shadow-xl">



        <div class="flex h-full flex-col">



            <div class="relative border-b border-borde px-5 py-5">



                <button

                    type="button"

                    id="cerrar-menu-movil"

                    class="absolute right-4 top-4 flex h-9 w-9 items-center justify-center rounded-lg text-texto-secundario hover:bg-fondo"

                >

                    <svg

                        class="h-5 w-5"

                        fill="none"

                        stroke="currentColor"

                        viewBox="0 0 24 24"

                    >

                        <path

                            stroke-linecap="round"

                            stroke-linejoin="round"

                            stroke-width="2"

                            d="M6 18L18 6M6 6l12 12"

                        />

                    </svg>

                </button>



                <div class="flex justify-center pr-4">

                    <img

                        src="{{ asset('images/prosec-logo.svg') }}"

                        alt="PROSEC"

                        class="h-auto w-full max-w-[220px]"

                    >

                </div>



            </div>



            <nav class="flex-1 overflow-y-auto px-4 py-5">



                @can('ver panel inicio')

                <a

                    href="{{ route('inicio') }}"

                    data-sidebar-main

                    class="mb-2 flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium

                        {{ $inicioActivo

                            ? 'bg-institucional text-white'

                            : 'text-texto hover:bg-fondo' }}"

                >

                    <span>Inicio</span>

                </a>

                @endcan



                @can('ver programacion semestral')

                <div class="mb-2">

                    <button

                        type="button"

                        data-sidebar-toggle

                        data-sidebar-main

                        data-first-url="{{ route('programacion.semestral') }}"

                        data-target="movil-programacion-semestral"

                        data-route-active="{{ $programacionSemestralActiva ? 'true' : 'false' }}"

                        aria-expanded="{{ $programacionSemestralActiva ? 'true' : 'false' }}"

                        class="flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-left text-sm font-semibold

                            {{ $programacionSemestralActiva

                                ? 'bg-institucional text-white'

                                : 'text-texto hover:bg-fondo' }}"

                    >

                        <span class="flex-1">Programación semestral</span>



                        <svg

                            data-sidebar-chevron

                            class="h-4 w-4 transition-transform {{ $programacionSemestralActiva ? 'rotate-180' : '' }}"

                            fill="none"

                            stroke="currentColor"

                            viewBox="0 0 24 24"

                        >

                            <path

                                stroke-linecap="round"

                                stroke-linejoin="round"

                                stroke-width="2"

                                d="M19 9l-7 7-7-7"

                            />

                        </svg>

                    </button>



                    <div

                        id="movil-programacion-semestral"

                        data-sidebar-submenu

                        class="mt-1 space-y-1 pl-4 {{ $programacionSemestralActiva ? '' : 'hidden' }}"

                    >

                        @can('ver programacion semestral')

                        <a

                            href="{{ route('programacion.semestral') }}"

                            class="block rounded-lg px-3 py-2 text-sm font-medium

                                {{ request()->routeIs('programacion.semestral')

                                    ? 'bg-gray-100 text-gray-800'

                                    : 'text-texto hover:bg-gray-50' }}"

                        >

                            Visualizar programación

                        </a>

                        @endcan



                        @can('generar programacion semestral')

                        <div class="px-3 py-2 text-sm text-gray-400">

                            Generar programación

                        </div>

                        @endcan



                        @can('asignar profesores')

                        <div class="px-3 py-2 text-sm text-gray-400">

                            Asignación de profesores

                        </div>

                        @endcan



                        @can('asignar horarios')

                        <div class="px-3 py-2 text-sm text-gray-400">

                            Asignación de horarios

                        </div>

                        @endcan



                        @can('gestionar conflictos')

                        <div class="px-3 py-2 text-sm text-gray-400">

                            Gestión de conflictos

                        </div>

                        @endcan



                        @can('enviar propuesta horario')

                        <a
                            href="{{ route('programacion.propuesta') }}"
                            class="block rounded-lg px-3 py-2 text-sm font-medium
                                {{ request()->routeIs('programacion.propuesta*')
                                    ? 'bg-gray-100 text-gray-800'
                                    : 'text-texto hover:bg-gray-50' }}"
                        >
                            Propuesta de horario
                        </a>

                        @endcan

                    </div>

                </div>

                @endcan



                @can('ver intercambio docente')

                <div class="mb-2">

                    <button

                        type="button"

                        data-sidebar-toggle

                        data-sidebar-main

                        data-first-url="{{ route('programacion.intercambio') }}"

                        data-target="movil-intercambio"

                        data-route-active="{{ $intercambioActivo ? 'true' : 'false' }}"

                        aria-expanded="{{ $intercambioActivo ? 'true' : 'false' }}"

                        class="flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-left text-sm font-semibold

                            {{ $intercambioActivo

                                ? 'bg-institucional text-white'

                                : 'text-texto hover:bg-fondo' }}"

                    >

                        <span class="flex-1">Intercambio docente</span>



                        <svg

                            data-sidebar-chevron

                            class="h-4 w-4 transition-transform {{ $intercambioActivo ? 'rotate-180' : '' }}"

                            fill="none"

                            stroke="currentColor"

                            viewBox="0 0 24 24"

                        >

                            <path

                                stroke-linecap="round"

                                stroke-linejoin="round"

                                stroke-width="2"

                                d="M19 9l-7 7-7-7"

                            />

                        </svg>

                    </button>



                    <div

                        id="movil-intercambio"

                        data-sidebar-submenu

                        class="mt-1 space-y-1 pl-4 {{ $intercambioActivo ? '' : 'hidden' }}"

                    >

                        <a

                            href="{{ route('programacion.intercambio') }}"

                            class="block rounded-lg px-3 py-2 text-sm font-medium

                                {{ request()->routeIs('programacion.intercambio')

                                    ? 'bg-gray-100 text-gray-800'

                                    : 'text-texto hover:bg-gray-50' }}"

                        >

                            Consultar reemplazo

                        </a>



                        <div class="px-3 py-2 text-sm text-gray-400">

                            Historial de reemplazos

                        </div>

                    </div>

                </div>



                @endcan

                @can('gestionar salas y recursos')

                <div class="mb-2">

                    <button

                        type="button"

                        data-sidebar-toggle

                        data-sidebar-main

                        data-first-url="{{ route('salas.index') }}"

                        data-target="movil-salas"

                        data-route-active="{{ $salasRecursosActiva ? 'true' : 'false' }}"

                        aria-expanded="{{ $salasRecursosActiva ? 'true' : 'false' }}"

                        class="flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-left text-sm font-semibold

                            {{ $salasRecursosActiva

                                ? 'bg-institucional text-white'

                                : 'text-texto hover:bg-fondo' }}"

                    >

                        <span class="flex-1">Salas y recursos</span>



                        <svg

                            data-sidebar-chevron

                            class="h-4 w-4 transition-transform {{ $salasRecursosActiva ? 'rotate-180' : '' }}"

                            fill="none"

                            stroke="currentColor"

                            viewBox="0 0 24 24"

                        >

                            <path

                                stroke-linecap="round"

                                stroke-linejoin="round"

                                stroke-width="2"

                                d="M19 9l-7 7-7-7"

                            />

                        </svg>

                    </button>



                    <div

                        id="movil-salas"

                        data-sidebar-submenu

                        class="mt-1 space-y-1 pl-4 {{ $salasRecursosActiva ? '' : 'hidden' }}"

                    >

                        <a

                            href="{{ route('salas.index') }}"

                            class="block rounded-lg px-3 py-2 text-sm font-medium

                                {{ request()->routeIs('salas.index')

                                    ? 'bg-gray-100 text-gray-800'

                                    : 'text-texto hover:bg-gray-50' }}"

                        >

                            Asignación de salas

                        </a>



                        <div class="px-3 py-2 text-sm text-gray-400">

                            Laboratorios

                        </div>



                        <div class="px-3 py-2 text-sm text-gray-400">

                            Equipamiento y recursos

                        </div>

                    </div>

                </div>



                @endcan

                @can('ver programacion anterior')

                <div class="mb-2">

                    <button

                        type="button"

                        data-sidebar-toggle

                        data-sidebar-main

                        data-first-url="{{ route('programacion.index') }}"

                        data-target="movil-anterior"

                        data-route-active="{{ $programacionAnteriorActiva ? 'true' : 'false' }}"

                        aria-expanded="{{ $programacionAnteriorActiva ? 'true' : 'false' }}"

                        class="flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-left text-sm font-semibold

                            {{ $programacionAnteriorActiva

                                ? 'bg-institucional text-white'

                                : 'text-texto hover:bg-fondo' }}"

                    >

                        <span class="flex-1">Programación anterior</span>



                        <svg

                            data-sidebar-chevron

                            class="h-4 w-4 transition-transform {{ $programacionAnteriorActiva ? 'rotate-180' : '' }}"

                            fill="none"

                            stroke="currentColor"

                            viewBox="0 0 24 24"

                        >

                            <path

                                stroke-linecap="round"

                                stroke-linejoin="round"

                                stroke-width="2"

                                d="M19 9l-7 7-7-7"

                            />

                        </svg>

                    </button>



                    <div

                        id="movil-anterior"

                        data-sidebar-submenu

                        class="mt-1 space-y-1 pl-4 {{ $programacionAnteriorActiva ? '' : 'hidden' }}"

                    >

                        <a

                            href="{{ route('programacion.index') }}"

                            class="block rounded-lg px-3 py-2 text-sm font-medium

                                {{ request()->routeIs('programacion.index')

                                    ? 'bg-gray-100 text-gray-800'

                                    : 'text-texto hover:bg-gray-50' }}"

                        >

                            Consultar programación anterior

                        </a>



                        <a

                            href="{{ route('programacion.importar') }}"

                            class="block rounded-lg px-3 py-2 text-sm font-medium

                                {{ request()->routeIs('programacion.importar*')

                                    ? 'bg-gray-100 text-gray-800'

                                    : 'text-texto hover:bg-gray-50' }}"

                        >

                            Cargar programación anterior

                        </a>

                    </div>

                </div>



                @endcan

                @can('administrar usuarios')

                <div class="mb-2">

                    <button

                        type="button"

                        data-sidebar-toggle

                        data-sidebar-main

                        data-first-url="{{ route('administracion.index') }}"

                        data-target="movil-administracion"

                        data-route-active="{{ $administracionActiva ? 'true' : 'false' }}"

                        aria-expanded="{{ $administracionActiva ? 'true' : 'false' }}"

                        class="flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-left text-sm font-semibold

                            {{ $administracionActiva

                                ? 'bg-institucional text-white'

                                : 'text-texto hover:bg-fondo' }}"

                    >

                        <span class="flex-1">Administración</span>



                        <svg

                            data-sidebar-chevron

                            class="h-4 w-4 transition-transform {{ $administracionActiva ? 'rotate-180' : '' }}"

                            fill="none"

                            stroke="currentColor"

                            viewBox="0 0 24 24"

                        >

                            <path

                                stroke-linecap="round"

                                stroke-linejoin="round"

                                stroke-width="2"

                                d="M19 9l-7 7-7-7"

                            />

                        </svg>

                    </button>



                    <div

                        id="movil-administracion"

                        data-sidebar-submenu

                        class="mt-1 space-y-1 pl-4 {{ $administracionActiva ? '' : 'hidden' }}"

                    >

                        <a

                            href="{{ route('administracion.index') }}"

                            class="block rounded-lg px-3 py-2 text-sm font-medium

                                {{ request()->routeIs('administracion.index')

                                    ? 'bg-gray-100 text-gray-800'

                                    : 'text-texto hover:bg-gray-50' }}"

                        >

                            Usuarios

                        </a>



                        <div class="px-3 py-2 text-sm text-gray-400">

                            Roles y permisos

                        </div>

                    </div>

                </div>



                @endcan

                @can('ver configuracion')

                <div>

                    <button

                        type="button"

                        data-sidebar-toggle

                        data-sidebar-main

                        data-first-url="{{ route('configuracion.index') }}"

                        data-target="movil-configuracion"

                        data-route-active="{{ $configuracionActiva ? 'true' : 'false' }}"

                        aria-expanded="{{ $configuracionActiva ? 'true' : 'false' }}"

                        class="flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-left text-sm font-semibold

                            {{ $configuracionActiva

                                ? 'bg-institucional text-white'

                                : 'text-texto hover:bg-fondo' }}"

                    >

                        <span class="flex-1">Configuración</span>



                        <svg

                            data-sidebar-chevron

                            class="h-4 w-4 transition-transform {{ $configuracionActiva ? 'rotate-180' : '' }}"

                            fill="none"

                            stroke="currentColor"

                            viewBox="0 0 24 24"

                        >

                            <path

                                stroke-linecap="round"

                                stroke-linejoin="round"

                                stroke-width="2"

                                d="M19 9l-7 7-7-7"

                            />

                        </svg>

                    </button>



                    <div

                        id="movil-configuracion"

                        data-sidebar-submenu

                        class="mt-1 space-y-1 pl-4 {{ $configuracionActiva ? '' : 'hidden' }}"

                    >

                        <a

                            href="{{ route('configuracion.index') }}"

                            class="block rounded-lg px-3 py-2 text-sm font-medium

                                {{ request()->routeIs('configuracion.index')

                                    ? 'bg-gray-100 text-gray-800'

                                    : 'text-texto hover:bg-gray-50' }}"

                        >

                            Configuración general

                        </a>

                    </div>

                </div>

                @endcan



            </nav>



            <div class="flex shrink-0 justify-center border-t border-borde px-5 py-4">

                <img

                    src="{{ asset('images/main-logo.svg') }}"

                    alt="Universidad Católica de la Santísima Concepción"

                    class="h-8 w-auto"

                >

            </div>



        </div>



    </aside>

</div>

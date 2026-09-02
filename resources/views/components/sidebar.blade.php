<!-- Sidebar escritorio -->
<aside
    id="sidebar-escritorio"
    class="fixed inset-y-0 left-0 z-40 hidden w-64 border-r border-borde bg-superficie transition-all duration-300 lg:block"
>

    <!-- Botón para contraer el sidebar -->
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
            aria-hidden="true"
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

        <!-- Logo PROSEC -->
        <div
            id="contenedor-logo-prosec"
            class="flex justify-center border-b border-borde px-5 py-5 transition-all duration-300"
        >

            <!-- Logo completo -->
            <img
                id="logo-prosec-completo"
                src="{{ asset('images/prosec-logo.svg') }}"
                alt="PROSEC - Programación Semestral de Cursos"
                class="h-auto w-full max-w-[210px] transition-all duration-300"
            >

            <!-- Logo reducido -->
            <img
                id="logo-prosec-mini"
                src="{{ asset('images/prosec-mini.svg') }}"
                alt="PROSEC"
                class="hidden h-auto w-full max-w-[55px] transition-all duration-300"
            >

        </div>


        <!-- Navegación -->
        <nav class="flex-1 overflow-y-auto px-4 py-5">

            <!-- Inicio -->
            <div class="mb-5">

                <a
                    href="{{ route('inicio') }}"
                    class="group flex items-center gap-3 rounded-lg bg-institucional px-3 py-2.5 text-sm font-medium text-white transition-colors"
                >

                    <svg
                        class="h-5 w-5 shrink-0"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                        aria-hidden="true"
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


            <!-- Programación -->
            <div class="mb-6">

                <p class="mb-3 px-3 text-xs font-semibold uppercase tracking-wider text-texto-secundario">
                    Programación
                </p>

                <div class="space-y-1">

                    <!-- Programación semestral -->
                    <a
                        href="#"
                        class="group flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-texto transition-colors hover:bg-fondo"
                    >

                        <svg
                            class="h-5 w-5 shrink-0 text-texto-secundario group-hover:text-institucional"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                            aria-hidden="true"
                        >

                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M8 7V3m8 4V3M4 11h16M5 5h14a1 1 0 011 1v14a1 1 0 01-1 1H5a1 1 0 01-1-1V6a1 1 0 011-1z"
                            />

                        </svg>

                        <span>Programación semestral</span>

                    </a>


                    <!-- Asignación de profesores -->
                    <a
                        href="#"
                        class="group flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-texto transition-colors hover:bg-fondo"
                    >

                        <svg
                            class="h-5 w-5 shrink-0 text-texto-secundario group-hover:text-institucional"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                            aria-hidden="true"
                        >

                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M16 21v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2M9 11a4 4 0 100-8 4 4 0 000 8zm13 10v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"
                            />

                        </svg>

                        <span>Asignación de profesores</span>

                    </a>


                    <!-- Intercambio docente -->
                    <a
                        href="#"
                        class="group flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-texto transition-colors hover:bg-fondo"
                    >

                        <svg
                            class="h-5 w-5 shrink-0 text-texto-secundario group-hover:text-institucional"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                            aria-hidden="true"
                        >

                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M7 7h10m0 0l-3-3m3 3l-3 3M17 17H7m0 0l3-3m-3 3l3 3"
                            />

                        </svg>

                        <span>Intercambio docente</span>

                    </a>


                    <!-- Asignación de salas -->
                    <a
                        href="#"
                        class="group flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-texto transition-colors hover:bg-fondo"
                    >

                        <svg
                            class="h-5 w-5 shrink-0 text-texto-secundario group-hover:text-institucional"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                            aria-hidden="true"
                        >

                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M3 21h18M5 21V5l7-3 7 3v16M9 21v-5h6v5M8 9h1m-1 4h1m6-4h1m-1 4h1"
                            />

                        </svg>

                        <span>Asignación de salas</span>

                    </a>


                    <!-- Asignación de horarios -->
                    <a
                        href="#"
                        class="group flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-texto transition-colors hover:bg-fondo"
                    >

                        <svg
                            class="h-5 w-5 shrink-0 text-texto-secundario group-hover:text-institucional"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                            aria-hidden="true"
                        >

                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M12 8v4l3 2m6-2a9 9 0 11-18 0 9 9 0 0118 0z"
                            />

                        </svg>

                        <span>Asignación de horarios</span>

                    </a>


                    <!-- Gestión de conflictos -->
                    <a
                        href="#"
                        class="group flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-texto transition-colors hover:bg-fondo"
                    >

                        <svg
                            class="h-5 w-5 shrink-0 text-texto-secundario group-hover:text-institucional"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                            aria-hidden="true"
                        >

                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M12 9v4m0 4h.01M10.29 3.86l-8.18 14A2 2 0 003.82 21h16.36a2 2 0 001.71-3.14l-8.18-14a2 2 0 00-3.42 0z"
                            />

                        </svg>

                        <span>Gestión de conflictos</span>

                    </a>


                    <!-- Visualización -->
                    <a
                        href="#"
                        class="group flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-texto transition-colors hover:bg-fondo"
                    >

                        <svg
                            class="h-5 w-5 shrink-0 text-texto-secundario group-hover:text-institucional"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                            aria-hidden="true"
                        >

                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M2.25 12s3.75-6 9.75-6 9.75 6 9.75 6-3.75 6-9.75 6-9.75-6-9.75-6z"
                            />

                            <circle
                                cx="12"
                                cy="12"
                                r="2.5"
                                stroke-width="2"
                            />

                        </svg>

                        <span>Visualización</span>

                    </a>

                </div>

            </div>


            <!-- Configuración -->
            <div class="mb-6">

                <p class="mb-3 px-3 text-xs font-semibold uppercase tracking-wider text-texto-secundario">
                    Configuración
                </p>

                <div class="space-y-1">

                    <!-- Cargar programación anterior -->
                    <a
                        href="#"
                        class="group flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-texto transition-colors hover:bg-fondo"
                    >

                        <svg
                            class="h-5 w-5 shrink-0 text-texto-secundario group-hover:text-institucional"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                            aria-hidden="true"
                        >

                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M12 3v12m0 0l-4-4m4 4l4-4M5 21h14"
                            />

                        </svg>

                        <span>Cargar programación anterior</span>

                    </a>


                    <!-- Carreras -->
                    <a
                        href="#"
                        class="group flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-texto transition-colors hover:bg-fondo"
                    >

                        <svg
                            class="h-5 w-5 shrink-0 text-texto-secundario group-hover:text-institucional"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                            aria-hidden="true"
                        >

                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M4 21h16M6 21V7l6-4 6 4v14M9 21v-4h6v4M9 10h1m4 0h1m-5 3h1m4 0h1"
                            />

                        </svg>

                        <span>Carreras</span>

                    </a>


                    <!-- Cursos -->
                    <a
                        href="#"
                        class="group flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-texto transition-colors hover:bg-fondo"
                    >

                        <svg
                            class="h-5 w-5 shrink-0 text-texto-secundario group-hover:text-institucional"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                            aria-hidden="true"
                        >

                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M4 5h16M4 5v14h16V5M8 9h8M8 13h5"
                            />

                        </svg>

                        <span>Cursos</span>

                    </a>


                    <!-- Profesores -->
                    <a
                        href="#"
                        class="group flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-texto transition-colors hover:bg-fondo"
                    >

                        <svg
                            class="h-5 w-5 shrink-0 text-texto-secundario group-hover:text-institucional"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                            aria-hidden="true"
                        >

                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M16 21v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2M9 11a4 4 0 100-8 4 4 0 000 8zm13 10v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"
                            />

                        </svg>

                        <span>Profesores</span>

                    </a>


                    <!-- Salas y recursos -->
                    <a
                        href="#"
                        class="group flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-texto transition-colors hover:bg-fondo"
                    >

                        <svg
                            class="h-5 w-5 shrink-0 text-texto-secundario group-hover:text-institucional"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                            aria-hidden="true"
                        >

                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M3 21h18M5 21V5l7-3 7 3v16M9 21v-5h6v5M8 9h1m-1 4h1m6-4h1m-1 4h1"
                            />

                        </svg>

                        <span>Salas y recursos</span>

                    </a>

                </div>

            </div>


            <!-- Administración -->
            <div>

                <p class="mb-3 px-3 text-xs font-semibold uppercase tracking-wider text-texto-secundario">
                    Administración
                </p>

                <div class="space-y-1">

                    <!-- Usuarios -->
                    <a
                        href="#"
                        class="group flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-texto transition-colors hover:bg-fondo"
                    >

                        <svg
                            class="h-5 w-5 shrink-0 text-texto-secundario group-hover:text-institucional"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                            aria-hidden="true"
                        >

                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M16 21v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2M9 11a4 4 0 100-8 4 4 0 000 8zm13 10v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"
                            />

                        </svg>

                        <span>Usuarios</span>

                    </a>


                    <!-- Roles y permisos -->
                    <a
                        href="#"
                        class="group flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-texto transition-colors hover:bg-fondo"
                    >

                        <svg
                            class="h-5 w-5 shrink-0 text-texto-secundario group-hover:text-institucional"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                            aria-hidden="true"
                        >

                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M12 3l7 4v5c0 4.5-3 7.5-7 9-4-1.5-7-4.5-7-9V7l7-4z"
                            />

                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M9 12l2 2 4-4"
                            />

                        </svg>

                        <span>Roles y permisos</span>

                    </a>

                </div>

            </div>

        </nav>


        <!-- Logo UCSC -->
        <div class="flex shrink-0 justify-center border-t border-borde px-5 py-4">

            <img
                src="{{ asset('images/main-logo.svg') }}"
                alt="Universidad Católica de la Santísima Concepción"
                class="h-9 w-auto"
            >

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


            <!-- Encabezado móvil -->
            <div class="relative border-b border-borde px-5 py-5">

                <!-- Botón cerrar -->
                <button
                    type="button"
                    id="cerrar-menu-movil"
                    class="absolute right-4 top-4 flex h-9 w-9 items-center justify-center rounded-lg text-texto-secundario transition-colors hover:bg-fondo hover:text-texto"
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


                <!-- Logo PROSEC -->
                <div class="flex justify-center pr-4">

                    <img
                        src="{{ asset('images/prosec-logo.svg') }}"
                        alt="PROSEC - Programación Semestral de Cursos"
                        class="h-auto w-full max-w-[220px]"
                    >

                </div>

            </div>


            <!-- Navegación móvil -->
            <nav class="flex-1 overflow-y-auto px-4 py-5">

                <!-- Inicio -->
                <div class="mb-5">

                    <a
                        href="{{ route('inicio') }}"
                        class="flex items-center gap-3 rounded-lg bg-institucional px-3 py-2.5 text-sm font-medium text-white"
                    >

                        <svg
                            class="h-5 w-5 shrink-0"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                            aria-hidden="true"
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


                <!-- Programación -->
                <div class="mb-6">

                    <p class="mb-3 px-3 text-xs font-semibold uppercase tracking-wider text-texto-secundario">
                        Programación
                    </p>

                    <div class="space-y-1">

                        <a href="#" class="flex items-center rounded-lg px-3 py-2.5 text-sm font-medium text-texto transition-colors hover:bg-fondo">
                            Programación semestral
                        </a>

                        <a href="#" class="flex items-center rounded-lg px-3 py-2.5 text-sm font-medium text-texto transition-colors hover:bg-fondo">
                            Asignación de profesores
                        </a>

                        <a href="#" class="flex items-center rounded-lg px-3 py-2.5 text-sm font-medium text-texto transition-colors hover:bg-fondo">
                            Intercambio docente
                        </a>

                        <a href="#" class="flex items-center rounded-lg px-3 py-2.5 text-sm font-medium text-texto transition-colors hover:bg-fondo">
                            Asignación de salas
                        </a>

                        <a href="#" class="flex items-center rounded-lg px-3 py-2.5 text-sm font-medium text-texto transition-colors hover:bg-fondo">
                            Asignación de horarios
                        </a>

                        <a href="#" class="flex items-center rounded-lg px-3 py-2.5 text-sm font-medium text-texto transition-colors hover:bg-fondo">
                            Gestión de conflictos
                        </a>

                        <a href="#" class="flex items-center rounded-lg px-3 py-2.5 text-sm font-medium text-texto transition-colors hover:bg-fondo">
                            Visualización
                        </a>

                    </div>

                </div>


                <!-- Configuración -->
                <div class="mb-6">

                    <p class="mb-3 px-3 text-xs font-semibold uppercase tracking-wider text-texto-secundario">
                        Configuración
                    </p>

                    <div class="space-y-1">

                        <a href="#" class="flex items-center rounded-lg px-3 py-2.5 text-sm font-medium text-texto transition-colors hover:bg-fondo">
                            Cargar programación anterior
                        </a>

                        <a href="#" class="flex items-center rounded-lg px-3 py-2.5 text-sm font-medium text-texto transition-colors hover:bg-fondo">
                            Carreras
                        </a>

                        <a href="#" class="flex items-center rounded-lg px-3 py-2.5 text-sm font-medium text-texto transition-colors hover:bg-fondo">
                            Cursos
                        </a>

                        <a href="#" class="flex items-center rounded-lg px-3 py-2.5 text-sm font-medium text-texto transition-colors hover:bg-fondo">
                            Profesores
                        </a>

                        <a href="#" class="flex items-center rounded-lg px-3 py-2.5 text-sm font-medium text-texto transition-colors hover:bg-fondo">
                            Salas y recursos
                        </a>

                    </div>

                </div>


                <!-- Administración -->
                <div>

                    <p class="mb-3 px-3 text-xs font-semibold uppercase tracking-wider text-texto-secundario">
                        Administración
                    </p>

                    <div class="space-y-1">

                        <a href="#" class="flex items-center rounded-lg px-3 py-2.5 text-sm font-medium text-texto transition-colors hover:bg-fondo">
                            Usuarios
                        </a>

                        <a href="#" class="flex items-center rounded-lg px-3 py-2.5 text-sm font-medium text-texto transition-colors hover:bg-fondo">
                            Roles y permisos
                        </a>

                    </div>

                </div>

            </nav>


            <!-- Logo UCSC -->
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
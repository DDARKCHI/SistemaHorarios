@php
    $usuarioActual = auth()->user();

    $nombreUsuario = $usuarioActual?->name ?? 'Usuario';
    $rolUsuario = $usuarioActual?->getRoleNames()->first() ?? 'Sin rol';

    $inicialUsuario = strtoupper(
        mb_substr($nombreUsuario, 0, 1)
    );

    $seccionActual = null;
    $paginaActual = 'Inicio';

    if (request()->routeIs('inicio')) {
        $paginaActual = 'Inicio';
    } elseif (request()->routeIs('programacion.asignacion.profesores*')) {
        $seccionActual = 'Programación semestral';
        $paginaActual = 'Asignación de profesores';
    } elseif (request()->routeIs('programacion.asignacion.horarios*')) {
        $seccionActual = 'Programación semestral';
        $paginaActual = 'Asignación de horarios';
    } elseif (request()->routeIs('programacion.propuesta*')) {
        $seccionActual = 'Programación semestral';
        $paginaActual = 'Propuesta de horario';
    } elseif (request()->routeIs('programacion.semestral*')) {
        $seccionActual = 'Programación semestral';
        $paginaActual = 'Visualizar programación';
    } elseif (request()->routeIs('programacion.intercambio*')) {
        $seccionActual = 'Intercambio docente';
        $paginaActual = 'Consultar reemplazo';
    } elseif (request()->routeIs('salas.*')) {
        $seccionActual = 'Salas y recursos';
        $paginaActual = 'Asignación de salas';
    } elseif (request()->routeIs('programacion.importar*')) {
        $seccionActual = 'Programación anterior';
        $paginaActual = 'Cargar programación anterior';
    } elseif (request()->routeIs('programacion.index', 'programacion.filtros')) {
        $seccionActual = 'Programación anterior';
        $paginaActual = 'Consultar programación anterior';
    } elseif (request()->routeIs('usuarios.create')) {
        $seccionActual = 'Administración';
        $paginaActual = 'Crear usuario';
    } elseif (request()->routeIs('usuarios.edit')) {
        $seccionActual = 'Administración';
        $paginaActual = 'Editar usuario';
    } elseif (request()->routeIs('administracion.*', 'usuarios.*')) {
        $seccionActual = 'Administración';
        $paginaActual = 'Usuarios';
    } elseif (request()->routeIs('configuracion.*')) {
        $seccionActual = 'Configuración';
        $paginaActual = 'Configuración general';
    }
@endphp

<header class="sticky top-0 z-30 border-b border-borde bg-superficie">

    <div class="relative flex h-20 items-center justify-between px-6">

        <div class="flex min-w-0 flex-1 items-center">

            <button
                type="button"
                id="boton-menu-movil"
                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg border border-borde text-texto transition-colors hover:bg-fondo lg:hidden"
                aria-label="Abrir menú"
                aria-controls="sidebar-movil"
                aria-expanded="false"
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
                        d="M4 6h16M4 12h16M4 18h16"
                    />
                </svg>
            </button>

            <div class="absolute left-1/2 flex -translate-x-1/2 items-center xl:hidden">
                <img
                    src="{{ asset('images/main-logo.svg') }}"
                    alt="Universidad Católica de la Santísima Concepción"
                    class="h-10 w-auto"
                >
            </div>

            <div class="hidden min-w-0 xl:flex xl:items-center xl:gap-3">

                @if ($seccionActual)

                    <div class="shrink-0 rounded-full border border-borde/70 bg-fondo/60 px-3.5 py-1.5">
                        <span class="block text-[10px] font-semibold uppercase tracking-[0.16em] text-texto-secundario">
                            {{ $seccionActual }}
                        </span>
                    </div>

                    <svg
                        class="h-3.5 w-3.5 shrink-0 text-texto-secundario/30"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                        aria-hidden="true"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M9 5l7 7-7 7"
                        />
                    </svg>

                @endif

                <div class="min-w-0 rounded-full border border-borde/50 bg-white px-3.5 py-1.5">
                    <span class="block truncate text-[10px] font-semibold uppercase tracking-[0.16em] text-texto-secundario">
                        {{ $paginaActual }}
                    </span>
                </div>

            </div>

        </div>

        <div
            class="relative shrink-0"
            id="contenedor-usuario"
        >

            <button
                type="button"
                id="boton-usuario"
                class="flex items-center gap-3 rounded-lg px-2 py-1.5 transition-colors hover:bg-fondo"
                aria-expanded="false"
                aria-controls="menu-usuario"
            >

                <div class="hidden text-right sm:block">

                    <p class="text-sm font-semibold text-texto">
                        {{ $nombreUsuario }}
                    </p>

                    <p class="text-xs text-texto-secundario">
                        {{ $rolUsuario }}
                    </p>

                </div>

                <div class="flex h-10 w-10 items-center justify-center rounded-full bg-institucional text-base font-semibold text-white">
                    {{ $inicialUsuario }}
                </div>

                <svg
                    class="hidden h-4 w-4 text-texto-secundario transition-transform sm:block"
                    id="icono-usuario"
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
                id="menu-usuario"
                class="absolute right-0 top-full z-50 mt-2 hidden w-44 overflow-hidden rounded-lg border border-borde bg-superficie shadow-lg"
            >
                <form
                    method="POST"
                    action="{{ route('logout') }}"
                >
                    @csrf

                    <button
                        type="submit"
                        class="flex w-full items-center gap-2 px-4 py-3 text-left text-sm text-texto transition-colors hover:bg-fondo"
                    >
                        <svg
                            class="h-4 w-4 text-texto-secundario"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h6a2 2 0 012 2v1"
                            />
                        </svg>

                        Cerrar sesión

                    </button>
                </form>

            </div>

        </div>

    </div>

</header>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const botonUsuario = document.getElementById('boton-usuario');
        const menuUsuario = document.getElementById('menu-usuario');
        const contenedorUsuario = document.getElementById('contenedor-usuario');
        const iconoUsuario = document.getElementById('icono-usuario');

        if (!botonUsuario || !menuUsuario) {
            return;
        }

        botonUsuario.addEventListener('click', function () {
            const abierto = !menuUsuario.classList.contains('hidden');

            menuUsuario.classList.toggle('hidden');

            botonUsuario.setAttribute(
                'aria-expanded',
                abierto ? 'false' : 'true'
            );

            if (iconoUsuario) {
                iconoUsuario.classList.toggle('rotate-180');
            }
        });

        document.addEventListener('click', function (event) {
            if (!contenedorUsuario.contains(event.target)) {
                menuUsuario.classList.add('hidden');

                botonUsuario.setAttribute(
                    'aria-expanded',
                    'false'
                );

                if (iconoUsuario) {
                    iconoUsuario.classList.remove('rotate-180');
                }
            }
        });
    });
</script>
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

            <div class="absolute left-1/2 flex -translate-x-[55%] items-center lg:hidden">
                <img
                    src="{{ asset('images/main-logo.svg') }}"
                    alt="Universidad Católica de la Santísima Concepción"
                    class="h-10 w-auto"
                >
            </div>

            <div class="hidden min-w-0 lg:block">
                <div class="flex items-center gap-3">

                    <span class="text-lg font-semibold text-texto">
                        @yield('page-title', 'Inicio')
                    </span>

                    <span class="h-5 w-px bg-borde"></span>

                    <span class="text-lg text-texto-secundario">
                        Panel principal
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
                        Usuario
                    </p>

                    <p class="text-xs text-texto-secundario">
                        Administrador
                    </p>

                </div>

                <div class="flex h-10 w-10 items-center justify-center rounded-full bg-institucional text-base font-semibold text-white">
                    U
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

                <div class="border-b border-borde px-4 py-3 sm:hidden">

                    <p class="text-sm font-semibold text-texto">
                        Usuario
                    </p>

                    <p class="text-xs text-texto-secundario">
                        Administrador
                    </p>

                </div>

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
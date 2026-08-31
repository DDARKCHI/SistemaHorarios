<header class="sticky top-0 z-30 border-b border-borde bg-superficie">

    <div class="relative flex h-20 items-center justify-between px-6">

        <!-- Información de la página -->
        <div class="flex min-w-0 flex-1 items-center">

            <!-- Botón menú móvil -->
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


            <!-- Logo móvil -->
            <div class="absolute left-1/2 flex -translate-x-[55%] items-center lg:hidden">

                <img
                    src="{{ asset('images/main-logo.svg') }}"
                    alt="Universidad Católica de la Santísima Concepción"
                    class="h-10 w-auto"
                >

            </div>


            <!-- Información de la página -->
<div class="hidden min-w-0 lg:block">

    <!-- Ruta de navegación -->
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


        <!-- Información del usuario -->
        <div class="flex shrink-0 items-center gap-3">

            <div class="hidden text-right sm:block">

                <p class="text-sm font-semibold text-texto">
                    Usuario
                </p>

                <p class="text-xs text-texto-secundario">
                    Administrador
                </p>

            </div>


            <!-- Avatar -->
            <div class="flex h-10 w-10 cursor-pointer items-center justify-center rounded-full bg-institucional text-base font-semibold text-white transition-colors hover:bg-institucional-oscuro">

                U

            </div>

        </div>

    </div>

</header>
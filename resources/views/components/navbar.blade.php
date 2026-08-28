<header class="sticky top-0 z-30 border-b border-borde bg-superficie">

    <div class="flex h-20 items-center justify-between px-6">

        {{-- Información de la página --}}
        <div class="min-w-0">

            <p class="truncate text-sm text-texto-secundario">
                Sistema de Gestión de Horarios y Salas
            </p>

            <h1 class="text-xl font-semibold text-texto">
                @yield('page-title', 'Inicio')
            </h1>

        </div>


        {{-- Información del usuario --}}
        <div class="flex items-center gap-3">

            <div class="hidden text-right sm:block">

                <p class="text-sm font-semibold text-texto">
                    Usuario
                </p>

                <p class="text-xs text-texto-secundario">
                    Administrador
                </p>

            </div>


            {{-- Avatar temporal --}}
            <button
                type="button"
                class="flex h-10 w-10 items-center justify-center rounded-full bg-institucional text-sm font-bold text-white transition-colors hover:bg-institucional-oscuro"
                aria-label="Menú de usuario"
            >
                U
            </button>

        </div>

    </div>

</header>
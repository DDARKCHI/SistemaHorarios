@php
    $usuarioActual = auth()->user();

    $nombreUsuario = $usuarioActual?->name ?? 'Usuario';
    $rolUsuario = $usuarioActual?->getRoleNames()->first() ?? 'Sin rol';

    $inicialUsuario = strtoupper(
        mb_substr($nombreUsuario, 0, 1)
    );

    $migas = \App\Support\MigaDePan::para(request(), $usuarioActual);
@endphp

<header class="sticky top-0 z-30 border-b border-borde bg-superficie">

    <div class="ancho-pagina grid grid-cols-[2.75rem_minmax(0,1fr)_auto] items-center gap-x-3 lg:grid-cols-[minmax(0,1fr)_auto] lg:gap-x-6">

        <button
            type="button"
            id="boton-menu-movil"
            class="col-start-1 row-start-1 boton boton-secundario size-11 px-0 lg:hidden"
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

        <div class="col-start-2 row-start-1 flex h-16 items-center justify-center lg:hidden">
            <img
                src="{{ $identidad['institucion_url'] }}"
                alt="{{ $identidad['institucion'] }}"
                class="h-10 max-w-full w-auto object-contain"
            >
        </div>

        @include('components.miga-de-pan', ['migas' => $migas])

        <div
            class="relative col-start-3 row-start-1 flex min-h-16 shrink-0 items-center lg:col-start-2 lg:min-h-20"
            id="contenedor-usuario"
        >

            <button
                type="button"
                id="boton-usuario"
                class="flex items-center gap-3 rounded-lg px-2 py-1.5 transition-colors hover:bg-fondo"
                aria-expanded="false"
                aria-controls="menu-usuario"
                aria-label="Menú de usuario: {{ $nombreUsuario }} ({{ $rolUsuario }})"
            >

                <div class="hidden max-w-[11rem] text-right lg:block">

                    <p class="truncate text-sm font-semibold text-texto" title="{{ $nombreUsuario }}">
                        {{ $nombreUsuario }}
                    </p>

                    <p class="truncate text-xs text-texto-secundario" title="{{ $rolUsuario }}">
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
                class="absolute right-0 top-full z-50 mt-2 hidden w-60 overflow-hidden rounded-lg border border-borde bg-superficie shadow-lg"
            >
<a href="{{ route('ayuda.manual') }}" class="flex min-h-11 items-center gap-2 border-b border-borde px-4 py-3 text-sm font-semibold text-texto hover:bg-fondo"><svg aria-hidden="true" class="h-4 w-4 shrink-0 text-texto-secundario" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M4 4h6a3 3 0 0 1 3 3v14a3 3 0 0 0-3-3H4Zm9 3a3 3 0 0 1 3-3h5v14h-5a3 3 0 0 0-3 3"/></svg>Manual de usuario</a><a href="{{ route('cuenta.password.edit') }}" class="flex min-h-11 items-center gap-2 border-b border-borde px-4 py-3 text-sm font-semibold text-texto hover:bg-fondo"><svg aria-hidden="true" class="h-4 w-4 shrink-0 text-texto-secundario" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><circle cx="8" cy="8" r="5"/><path d="m12 12 9 9m-3-3 3-3m-6 0 3-3"/></svg>Cambiar contraseña</a>
                <form
                    method="POST"
                    action="{{ route('logout') }}"
                    data-confirmar="Tendrás que volver a ingresar a {{ $identidad['nombre'] }} para continuar."
                    data-confirmar-titulo="¿Cerrar sesión?"
                    data-confirmar-accion="Cerrar sesión"
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

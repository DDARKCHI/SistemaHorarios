<!DOCTYPE html>
<html lang="es">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Restablecer contraseña | PROSEC</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        input[type="password"]::-ms-reveal,
        input[type="password"]::-ms-clear {
            display: none;
        }
    </style>

    <x-favicon />
</head>

<body class="min-h-screen bg-fondo">

    <div class="flex min-h-screen items-center justify-center px-4 py-6 sm:px-5 sm:py-8">

        <div
            class="sombra-card min-w-0 w-full break-words rounded-xl border border-borde bg-superficie"
            style="max-width: 450px;"
        >

            <div class="px-5 pb-7 pt-8 sm:px-[42px] sm:pt-11">

                <div class="flex justify-center">

                    <img
                        src="{{ asset('images/prosec-logo.svg') }}"
                        alt="PROSEC - Programación Semestral de Cursos"
                        style="width: 245px; max-width: 100%; height: auto;"
                    >

                </div>

                <div class="mt-6">
                    <x-encabezado-pagina titulo="Restablecer contraseña">
                        Crea una nueva contraseña para recuperar el acceso a tu cuenta.
                    </x-encabezado-pagina>
                </div>

                @if ($errors->any())

                    <div
                        class="rounded-lg border border-red-200 bg-red-50"
                        style="
                            margin-bottom: 18px;
                            padding: 11px 13px;
                        "
                    >

                        <p class="text-sm text-red-700">
                            {{ $errors->first() }}
                        </p>

                    </div>

                @endif

                <form
                    method="POST"
                    action="{{ route('password.update') }}"
                >

                    @csrf

                    <input
                        type="hidden"
                        name="token"
                        value="{{ $token }}"
                    >

                    <input
                        type="hidden"
                        name="email"
                        value="{{ $email }}"
                    >

                    <div style="margin-bottom: 18px;">

                        <label
                            for="password"
                            class="block font-medium text-texto"
                            style="
                                margin-bottom: 7px;
                                font-size: 15px;
                            "
                        >
                            Nueva contraseña
                        </label>

                        <div class="relative">

                            <input
                                type="password"
                                id="password"
                                name="password"
                                required
                                autofocus
                                autocomplete="new-password"
                                class="w-full rounded-lg border border-borde bg-superficie text-texto outline-none transition-colors focus:border-institucional focus:ring-2 focus:ring-institucional/10"
                                style="
                                    height: 48px;
                                    padding: 0 46px 0 14px;
                                    font-size: 15px;
                                "
                            >

                            <button
                                type="button"
                                class="absolute inset-y-0 right-0 flex items-center justify-center px-4 text-gray-400 transition-colors hover:text-institucional"
                                data-toggle-password="password"
                                aria-label="Mostrar contraseña"
                            >

                                <svg
                                    class="icono-mostrar h-5 w-5"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"
                                    />

                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.269 2.943 9.542 7-1.273 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"
                                    />
                                </svg>

                                <svg
                                    class="icono-ocultar hidden h-5 w-5"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M13.875 18.825A10.05 10.05 0 0112 19c-4.477 0-8.268-2.943-9.542-7a9.956 9.956 0 012.293-3.95m3.249-2.08A9.953 9.953 0 0112 5c4.478 0 8.269 2.943 9.542 7a9.97 9.97 0 01-4.043 5.197M15 12a3 3 0 00-3-3"
                                    />

                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M3 3l18 18"
                                    />
                                </svg>

                            </button>

                        </div>

                    </div>

                    <div style="margin-bottom: 22px;">

                        <label
                            for="password_confirmation"
                            class="block font-medium text-texto"
                            style="
                                margin-bottom: 7px;
                                font-size: 15px;
                            "
                        >
                            Confirmar contraseña
                        </label>

                        <div class="relative">

                            <input
                                type="password"
                                id="password_confirmation"
                                name="password_confirmation"
                                required
                                autocomplete="new-password"
                                class="w-full rounded-lg border border-borde bg-superficie text-texto outline-none transition-colors focus:border-institucional focus:ring-2 focus:ring-institucional/10"
                                style="
                                    height: 48px;
                                    padding: 0 46px 0 14px;
                                    font-size: 15px;
                                "
                            >

                            <button
                                type="button"
                                class="absolute inset-y-0 right-0 flex items-center justify-center px-4 text-gray-400 transition-colors hover:text-institucional"
                                data-toggle-password="password_confirmation"
                                aria-label="Mostrar contraseña"
                            >

                                <svg
                                    class="icono-mostrar h-5 w-5"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"
                                    />

                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.269 2.943 9.542 7-1.273 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"
                                    />
                                </svg>

                                <svg
                                    class="icono-ocultar hidden h-5 w-5"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M13.875 18.825A10.05 10.05 0 0112 19c-4.477 0-8.268-2.943-9.542-7a9.956 9.956 0 012.293-3.95m3.249-2.08A9.953 9.953 0 0112 5c4.478 0 8.269 2.943 9.542 7a9.97 9.97 0 01-4.043 5.197M15 12a3 3 0 00-3-3"
                                    />

                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M3 3l18 18"
                                    />
                                </svg>

                            </button>

                        </div>

                    </div>

                    <button
                        type="submit"
                        class="flex w-full items-center justify-center rounded-lg bg-institucional font-semibold text-white transition-colors hover:bg-institucional-oscuro"
                        style="
                            height: 50px;
                            font-size: 15px;
                        "
                    >
                        Guardar nueva contraseña
                    </button>

                </form>

                <div
                    class="text-center"
                    style="margin-top: 16px;"
                >

                    <a
                        href="{{ route('login') }}"
                        class="inline-flex items-center justify-center text-[13px] font-medium text-gray-500 transition-colors hover:text-institucional hover:underline"
                        style="gap: 5px;"
                    >

                        <svg
                            class="block h-4 w-4 shrink-0"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                            aria-hidden="true"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M15 19l-7-7 7-7"
                            />
                        </svg>

                        <span>
                            Volver al inicio de sesión
                        </span>

                    </a>

                </div>

                <div
                    class="flex justify-center border-t border-borde"
                    style="
                        margin-top: 26px;
                        padding-top: 22px;
                    "
                >

                    <img
                        src="{{ asset('images/main-logo.svg') }}"
                        alt="Universidad Católica de la Santísima Concepción"
                        style="
                            height: 48px;
                            width: auto;
                            max-width: 100%;
                            object-fit: contain;
                        "
                    >

                </div>

            </div>

            <div
                class="border-t border-borde text-center text-texto-secundario"
                style="
                    padding: 12px 20px;
                    font-size: 12px;
                "
            >
                PROSEC · Programación Semestral de Cursos
            </div>

        </div>

    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const botones = document.querySelectorAll('[data-toggle-password]');

            botones.forEach(function (boton) {
                boton.addEventListener('click', function () {
                    const campoId = boton.dataset.togglePassword;
                    const campo = document.getElementById(campoId);

                    const iconoMostrar = boton.querySelector('.icono-mostrar');
                    const iconoOcultar = boton.querySelector('.icono-ocultar');

                    const estaOculta = campo.type === 'password';

                    campo.type = estaOculta ? 'text' : 'password';

                    iconoMostrar.classList.toggle('hidden', estaOculta);
                    iconoOcultar.classList.toggle('hidden', !estaOculta);

                    boton.setAttribute(
                        'aria-label',
                        estaOculta
                            ? 'Ocultar contraseña'
                            : 'Mostrar contraseña'
                    );
                });
            });
        });
    </script>

</body>

</html>

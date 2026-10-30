<!DOCTYPE html>
<html lang="es">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Recuperar contraseña | PROSEC</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

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
                    <x-encabezado-pagina titulo="Recuperar contraseña">
                        Ingresa tu RUT y enviaremos un enlace de recuperación
                        al correo asociado a tu cuenta.
                    </x-encabezado-pagina>
                </div>

                @if (session('status'))

                    <div
                        class="rounded-lg border border-green-200 bg-green-50"
                        style="
                            margin-bottom: 18px;
                            padding: 11px 13px;
                        "
                    >
                        <p class="text-sm text-green-700">
                            {{ session('status') }}
                        </p>
                    </div>

                @endif

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
                    action="{{ route('password.email') }}"
                >

                    @csrf

                    <div style="margin-bottom: 20px;">

                        <label
                            for="rut"
                            class="block font-medium text-texto"
                            style="
                                margin-bottom: 7px;
                                font-size: 15px;
                            "
                        >
                            RUT
                        </label>

                        <input
                            type="text"
                            id="rut"
                            name="rut"
                            value="{{ old('rut') }}"
                            required
                            autofocus
                            autocomplete="username"
                            placeholder="Sin puntos, ni dígito verificador"
                            class="w-full rounded-lg border border-borde bg-superficie text-texto outline-none transition-colors placeholder:text-texto-secundario focus:border-institucional focus:ring-2 focus:ring-institucional/10"
                            style="
                                height: 48px;
                                padding: 0 14px;
                                font-size: 15px;
                            "
                        >

                    </div>

                    <button
                        type="submit"
                        class="flex w-full items-center justify-center rounded-lg bg-institucional font-semibold text-white transition-colors hover:bg-institucional-oscuro"
                        style="
                            height: 50px;
                            font-size: 15px;
                        "
                    >

                        <span
                            class="inline-flex items-center justify-center"
                            style="gap: 8px;"
                        >

                            <svg
                                class="block h-[18px] w-[18px] shrink-0"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                                aria-hidden="true"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8m-18 8h18a2 2 0 002-2V6a2 2 0 00-2-2H3a2 2 0 00-2 2v8a2 2 0 002 2z"
                                />
                            </svg>

                            <span style="line-height: 1;">
                                Enviar enlace
                            </span>

                        </span>

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

                        <span style="line-height: 1;">
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

</body>

</html>

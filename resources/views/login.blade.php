<!DOCTYPE html>
<html lang="es">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Iniciar sesión | Sistema de Gestión de Horarios y Salas</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

</head>

<body class="min-h-screen bg-fondo">

    <div
        class="flex min-h-screen items-center justify-center px-5 py-8"
    >

        <!-- Tarjeta principal -->
        <div
            class="sombra-card w-full rounded-xl border border-borde bg-superficie"
            style="max-width: 500px;"
        >

            <div
                style="padding: 38px 42px 34px 42px;"
            >

                <!-- Logo -->
                <div class="flex justify-center">

                    <img
                        src="{{ asset('images/main-logo.svg') }}"
                        alt="Universidad Católica de la Santísima Concepción"
                        style="height: 82px; width: auto;"
                    >

                </div>


                <!-- Nombre del sistema -->
                <div
                    class="text-center"
                    style="margin-top: 12px;"
                >

                    <h1
                        class="font-bold text-texto"
                        style="font-size: 27px; line-height: 1.25;"
                    >
                        Sistema de Gestión
                    </h1>

                    <h2
                        class="font-bold text-texto"
                        style="font-size: 27px; line-height: 1.25;"
                    >
                        de Horarios y Salas
                    </h2>

                    <p
                        class="text-texto-secundario"
                        style="margin-top: 6px; font-size: 16px;"
                    >
                        Gestión académica
                    </p>

                </div>


                <!-- Formulario -->
                <div
                    style="margin-top: 28px;"
                >


                    <!-- Mensaje de error -->
                    @if ($errors->any())

                        <div
                            class="rounded-lg border border-red-200 bg-red-50"
                            style="margin-bottom: 18px; padding: 11px 13px;"
                        >

                            <p class="text-sm text-red-700">
                                {{ $errors->first() }}
                            </p>

                        </div>

                    @endif


                    <form
                        method="POST"
                        action="{{ route('login.procesar') }}"
                    >

                        @csrf


                        <!-- RUT -->
                        <div
                            style="margin-bottom: 18px;"
                        >

                            <label
                                for="rut"
                                class="block font-medium text-texto"
                                style="margin-bottom: 7px; font-size: 15px;"
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
                                style="height: 48px; padding: 0 14px; font-size: 15px;"
                            >

                        </div>


                        <!-- Contraseña -->
                        <div
                            style="margin-bottom: 22px;"
                        >

                            <label
                                for="password"
                                class="block font-medium text-texto"
                                style="margin-bottom: 7px; font-size: 15px;"
                            >
                                Contraseña
                            </label>

                            <input
                                type="password"
                                id="password"
                                name="password"
                                required
                                autocomplete="current-password"
                                placeholder="Contraseña"
                                class="w-full rounded-lg border border-borde bg-superficie text-texto outline-none transition-colors placeholder:text-texto-secundario focus:border-institucional focus:ring-2 focus:ring-institucional/10"
                                style="height: 48px; padding: 0 14px; font-size: 15px;"
                            >

                        </div>


                        <!-- Botón -->
                        <button
                            type="submit"
                            class="w-full rounded-lg bg-institucional font-semibold text-white transition-colors hover:bg-institucional-oscuro"
                            style="height: 50px; font-size: 15px;"
                        >
                            INGRESAR
                        </button>

                    </form>

                </div>

            </div>


            <!-- Pie -->
            <div
                class="border-t border-borde text-center text-texto-secundario"
                style="padding: 14px 20px; font-size: 12px;"
            >
                Sistema de Gestión de Horarios y Salas
            </div>

        </div>

    </div>

</body>

</html>
<!DOCTYPE html>
<html lang="es">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Iniciar sesión | PROSEC</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

</head>

<body class="min-h-screen bg-fondo">

    <div
        class="flex min-h-screen items-center justify-center px-5 py-8"
    >

       
        <div
            class="sombra-card w-full rounded-xl border border-borde bg-superficie"
            style="max-width: 450px;"
        >

            <div
                style="padding: 50px 42px 30px 42px;"
            >

                <!-- Logo -->
                <div class="flex justify-center">

                    <img
                        src="{{ asset('images/prosec-logo.svg') }}"
                        alt="PROSEC - Programación Semestral de Cursos"
                        style="width: 245px; height: auto;"
                    >

                </div>


                


                <!-- Formulario -->
                <div
                    style="margin-top: 28px;"
                >

                    
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


                       
                        <button
                            type="submit"
                            class="w-full rounded-lg bg-institucional font-semibold text-white transition-colors hover:bg-institucional-oscuro"
                            style="height: 50px; font-size: 15px;"
                        >
                            INGRESAR
                        </button>

                    </form>


                    <!-- Logo -->
                    <div
                        class="flex justify-center border-t border-borde"
                        style="margin-top: 28px; padding-top: 22px;"
                    >

                        <img
                            src="{{ asset('images/main-logo.svg') }}"
                            alt="Universidad Católica de la Santísima Concepción"
                            style="height: 48px; width: auto;"
                        >

                    </div>

                </div>

            </div>


            
            <div
                class="border-t border-borde text-center text-texto-secundario"
                style="padding: 12px 20px; font-size: 12px;"
            >
                PROSEC · Programación Semestral de Cursos
            </div>

        </div>

    </div>

</body>

</html>
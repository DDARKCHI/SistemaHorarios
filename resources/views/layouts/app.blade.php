<!DOCTYPE html>
<html lang="es">
<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>
        @yield('title', 'PROSEC - Programación Semestral de Cursos')
    </title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <x-favicon />
</head>

<body class="min-h-screen bg-fondo text-texto">

    <div class="min-h-screen">

        <!-- Sidebar -->
        @include('components.sidebar')


        <!-- Contenido principal -->
        <div
            id="contenido-principal"
            class="min-w-0 transition-all duration-300 lg:ml-64"
        >

            <!-- Navbar -->
            @include('components.navbar')


            <!-- Contenido de cada página -->
            <main class="min-w-0 p-3 sm:p-6">

                @yield('content')

            </main>

        </div>

    </div>

</body>
</html>

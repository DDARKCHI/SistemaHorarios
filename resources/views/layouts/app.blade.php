<!DOCTYPE html>
<html lang="es">
<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>
        @yield('title', 'Sistema de Gestión de Horarios y Salas')
    </title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

</head>

<body class="min-h-screen bg-fondo text-texto">

    <div class="min-h-screen">

        {{-- Sidebar --}}
        @include('components.sidebar')

        {{-- Contenido principal --}}
        <div class="lg:pl-64">

            {{-- Navbar --}}
            @include('components.navbar')

            {{-- Contenido de cada página --}}
            <main class="p-6">

                @yield('content')

            </main>

        </div>

    </div>

</body>
</html>
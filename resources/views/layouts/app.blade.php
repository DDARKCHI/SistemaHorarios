<!DOCTYPE html>
<html lang="es">
<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>
        @yield('title', $identidad['nombre'].' - '.$identidad['lema'])
    </title>

    <script>
        try {
            if (localStorage.getItem('sidebarColapsado') === 'true') {
                document.documentElement.dataset.sidebarInicial = 'contraido';
            }
        } catch {
            // La vista abre con el menú expandido si no hay preferencias guardadas.
        }
    </script>
    <link rel="preload" as="image" href="{{ $identidad['principal_url'] }}" media="(min-width: 64rem)">
    <link rel="preload" as="image" href="{{ $identidad['institucion_url'] }}">
    <link rel="expect" href="#contenido-pagina" blocking="render">

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <x-favicon />
    <x-identidad-estilos />
    <style>
        @view-transition {
            navigation: auto;
        }

        ::view-transition-group(root),
        ::view-transition-new(root) {
            animation: none;
        }

        ::view-transition-old(root) {
            display: none;
        }
    </style>
</head>

<body class="min-h-screen bg-fondo text-texto">

    <div class="min-h-screen">

        <!-- Sidebar -->
        @include('components.sidebar')


        <!-- Contenido principal -->
        <div
            id="contenido-principal"
            class="min-w-0 transition-[margin-left] duration-300 lg:ml-64"
        >

            <!-- Navbar -->
            @include('components.navbar')


            <!-- Contenido de cada página -->
            <main id="contenido-pagina" class="pagina-prosec ancho-pagina">

                @yield('content')

            </main>

        </div>

    </div>

    <x-dialogo-confirmacion />
    <x-estado-carga />
</body>
</html>

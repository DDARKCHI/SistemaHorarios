@props(['titulo', 'accionesAmplias' => false])

<header class="encabezado-pagina {{ $accionesAmplias ? 'encabezado-pagina--amplio' : '' }}">
    <div class="min-w-0 flex-1">
        <h1 class="text-2xl font-semibold leading-tight text-texto">{{ $titulo }}</h1>
        @if ($slot->isNotEmpty())
            <p class="mt-1.5 text-sm text-texto-secundario">{{ $slot }}</p>
        @endif
    </div>

    @if (isset($acciones) && $acciones->isNotEmpty())
        <div class="acciones-pagina">{{ $acciones }}</div>
    @endif
</header>

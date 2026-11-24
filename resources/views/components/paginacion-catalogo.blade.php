@props(['paginas', 'etiqueta'])

@if ($paginas->hasPages())
    <div class="mt-3 rounded-xl border border-borde bg-superficie p-3">
        {{ $paginas->onEachSide(1)->links('paginacion.prosec', ['etiqueta' => $etiqueta, 'mostrarRango' => false]) }}
    </div>
@endif

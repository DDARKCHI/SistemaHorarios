@props(['campos' => 3])

<form method="get" {{ $attributes->class('filtros-compactos rounded-xl border border-borde bg-superficie p-4 shadow-sm sm:p-5') }}>
    <div class="filtros-campos" data-campos="{{ $campos }}">
        {{ $slot }}
        <div class="filtros-acciones">{{ $acciones }}</div>
    </div>
</form>

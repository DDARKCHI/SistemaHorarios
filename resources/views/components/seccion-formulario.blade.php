@props(['titulo', 'id'])

<section {{ $attributes->class(['panel-seccion']) }} aria-labelledby="{{ $id }}">
    <h2 id="{{ $id }}" class="panel-titulo">{{ $titulo }}</h2>
    {{ $slot }}
</section>

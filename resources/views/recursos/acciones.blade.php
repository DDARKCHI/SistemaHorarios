<div @class(['flex min-w-0 gap-2', 'justify-end whitespace-nowrap [&>a]:shrink-0' => $enTabla ?? false, 'mt-auto [&>a]:flex-1' => ! ($enTabla ?? false)])>
    <a href="{{ route('recursos.ubicaciones.index', $equipamiento) }}" class="boton boton-secundario" aria-label="Ubicaciones de {{ $equipamiento->nombre }}">Ubicaciones</a>
    <a href="{{ route('recursos.edit', $equipamiento) }}" aria-label="Editar equipamiento {{ $equipamiento->nombre }}" class="boton boton-secundario">Editar</a>
</div>

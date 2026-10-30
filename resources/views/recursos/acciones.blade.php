<div class="flex flex-wrap gap-2 xl:flex-col">
    <a href="{{ route('recursos.ubicaciones.index', $equipamiento) }}" class="boton boton-secundario" aria-label="Ubicaciones de {{ $equipamiento->nombre }}">Ubicaciones</a>
    <a href="{{ route('recursos.edit', $equipamiento) }}" aria-label="Editar equipamiento {{ $equipamiento->nombre }}" class="boton boton-secundario">Editar</a>
</div>

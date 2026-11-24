<div @class(['flex min-w-0 gap-2', 'justify-end whitespace-nowrap [&>a]:shrink-0' => $enTabla ?? false, 'mt-auto [&>a]:flex-1' => ! ($enTabla ?? false)])>
    <a href="{{ route('salas.horario', ['espacio' => 'sala:'.$sala->id]) }}#calendario-espacio" aria-label="Ver horario de la sala {{ $sala->codigo }}" class="boton boton-secundario">Ver horario</a>
    <a href="{{ route('salas.edit', $sala) }}" aria-label="Editar sala {{ $sala->codigo }}" class="boton boton-secundario">Editar</a>
</div>

<div @class(['flex min-w-0 gap-2', 'justify-end whitespace-nowrap [&>a]:shrink-0' => $enTabla ?? false, 'mt-auto [&>a]:flex-1' => ! ($enTabla ?? false)])>
    <a href="{{ route('salas.horario', ['espacio' => ($tipo === 'fijo' ? 'laboratorio:' : 'movil:').$laboratorio->id]) }}#calendario-espacio" aria-label="Ver horario del laboratorio {{ $laboratorio->codigo ?: $laboratorio->nombre }}" class="boton boton-secundario">Ver horario</a>
    <a href="{{ route('laboratorios.edit', ['tipo' => $tipo, 'id' => $laboratorio->id]) }}" aria-label="Editar laboratorio {{ $laboratorio->codigo ?: $laboratorio->nombre }}" class="boton boton-secundario">Editar</a>
</div>

<div class="flex min-w-0 flex-wrap items-center gap-2 lg:justify-end">
    <a href="{{ route('salas.horario', ['espacio' => ($tipo === 'fijo' ? 'laboratorio:' : 'movil:').$laboratorio->id]) }}#calendario-espacio" aria-label="Ver horario del laboratorio {{ $laboratorio->codigo ?: $laboratorio->nombre }}" class="boton boton-secundario flex-1 lg:flex-none">Ver horario</a>
    <a href="{{ route('laboratorios.edit', ['tipo' => $tipo, 'id' => $laboratorio->id]) }}" aria-label="Editar laboratorio {{ $laboratorio->codigo ?: $laboratorio->nombre }}" class="boton boton-secundario flex-1 lg:flex-none">Editar</a>
</div>

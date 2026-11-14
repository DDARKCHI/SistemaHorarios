@php
    $sedeElegida = is_scalar($fila['sede_id'] ?? null) ? (string) $fila['sede_id'] : '';
    $espacioElegido = is_string($fila['espacio'] ?? null) ? $fila['espacio'] : '';
@endphp
<div data-fila-ubicacion class="grid min-w-0 grid-cols-1 items-end gap-3 rounded-lg border border-gray-200 bg-gray-50 p-3 sm:grid-cols-2 xl:grid-cols-[minmax(0,1fr)_minmax(0,1.5fr)_7rem_auto]">
    <div class="min-w-0">
        <label for="sede-inicial-{{ $indice }}" class="mb-1.5 block text-sm font-semibold text-gray-700">Sede</label>
        <select id="sede-inicial-{{ $indice }}" name="ubicaciones[{{ $indice }}][sede_id]" data-sede-inicial class="h-11 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm">
            <option value="">Todas las sedes</option>
            @foreach ($sedes as $sede)<option value="{{ $sede->id }}" data-universidad="{{ $sede->universidad_id }}" @selected($sedeElegida === (string) $sede->id)>{{ $sede->nombre }}</option>@endforeach
        </select>
    </div>
    <div class="min-w-0">
        <label for="espacio-inicial-{{ $indice }}" class="mb-1.5 block text-sm font-semibold text-gray-700">Sala o laboratorio móvil</label>
        <select id="espacio-inicial-{{ $indice }}" name="ubicaciones[{{ $indice }}][espacio]" data-espacio-inicial class="h-11 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm">
            <option value="">Seleccionar espacio</option>
            @foreach ($espacios as $espacio)<option value="{{ $espacio['clave'] }}" data-sede="{{ $espacio['sede_id'] }}" data-universidad="{{ $espacio['universidad_id'] }}" @selected($espacioElegido === $espacio['clave'])>{{ $espacio['nombre'] }}</option>@endforeach
        </select>
    </div>
    <div>
        <label for="unidades-inicial-{{ $indice }}" class="mb-1.5 block text-sm font-semibold text-gray-700">Unidades</label>
        <input id="unidades-inicial-{{ $indice }}" name="ubicaciones[{{ $indice }}][cantidad]" data-cantidad-inicial type="number" min="1" max="65535" step="1" placeholder="Cantidad" value="{{ is_scalar($fila['cantidad'] ?? null) ? $fila['cantidad'] : '' }}" class="h-11 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm">
    </div>
    <button type="button" data-quitar-ubicacion class="boton boton-secundario" aria-label="Quitar esta ubicación">Quitar</button>
</div>

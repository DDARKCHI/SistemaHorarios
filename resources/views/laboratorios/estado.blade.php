<span @class(['inline-block rounded-full px-2 py-1 text-[10px] font-semibold', 'bg-green-50 text-green-800' => $laboratorio->estado, 'bg-gray-100 text-gray-600' => ! $laboratorio->estado])>{{ $laboratorio->estado ? 'Registro activo' : 'Registro inactivo' }}</span>
<p class="mt-2 text-xs text-gray-600">{{ $laboratorio->disponible ? 'Uso habilitado' : 'Uso deshabilitado' }}</p>
@if ($tipo === 'fijo' && (! $laboratorio->sala?->estado || ! $laboratorio->sala?->disponible))
    <p class="mt-1 text-xs text-amber-800">Sala inactiva o deshabilitada</p>
@endif
@if (! ($tipo === 'fijo' ? $laboratorio->sala?->sede?->estado : $laboratorio->sede?->estado))
    <p class="mt-1 text-xs text-amber-800">Sede inactiva</p>
@endif

<span @class(['inline-block rounded-full px-2 py-1 text-[10px] font-semibold', 'bg-green-50 text-green-800' => $equipamiento->estado, 'bg-gray-100 text-gray-600' => ! $equipamiento->estado])>{{ $equipamiento->estado ? 'Registro activo' : 'Registro inactivo' }}</span>
<p class="mt-2 text-xs text-gray-600">{{ $equipamiento->disponible ? 'Uso habilitado' : 'Uso deshabilitado' }}</p>
@if (! $equipamiento->universidad?->estado)<p class="mt-1 text-xs text-amber-800">Institución inactiva</p>@endif
@if ($equipamiento->cantidad === 0)<p class="mt-1 text-xs text-gray-500">Sin unidades registradas</p>@endif

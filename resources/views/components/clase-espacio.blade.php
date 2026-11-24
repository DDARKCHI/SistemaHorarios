@props(['sesion', 'compacta' => false])

<article data-clase-id="{{ $sesion['id'] }}" class="min-w-0 rounded-md border border-gray-200 bg-white px-1.5 py-1.5 shadow-sm">
    <div class="flex min-w-0 items-start justify-between gap-1">
        <div class="min-w-0">
            <p class="break-words text-[10px] font-bold leading-tight text-gray-800">{{ $sesion['codigo_asignatura'] ?: 'Sin código' }}</p>
            <p class="mt-0.5 line-clamp-2 break-words text-[11px] font-medium leading-tight text-gray-700" title="{{ $sesion['nombre_asignatura'] }}">{{ $sesion['nombre_asignatura'] ?: 'Asignatura sin nombre' }}</p>
        </div>
        @if (filled($sesion['grupo']))
            <span class="max-w-16 shrink-0 break-words rounded-full bg-gray-100 px-1.5 py-0.5 text-[10px] font-semibold text-gray-600">{{ $sesion['grupo'] }}</span>
        @endif
    </div>
    <div class="mt-1.5 space-y-0.5 break-words text-[10px] leading-tight text-gray-500">
        <p><span class="font-semibold text-gray-700">Sec.:</span> {{ $sesion['seccion'] }}</p>
        <p><span class="font-semibold text-gray-700">Act.:</span> {{ $sesion['actividad'] }}</p>
        <p title="{{ implode(', ', $sesion['profesores']) }}"><span class="font-semibold text-gray-700">Docente:</span> {{ count($sesion['profesores']) ? implode(', ', $sesion['profesores']) : 'Sin docente asignado' }}</p>
        @foreach ($sesion['recursos'] ?? [] as $tipo => $codigos)
            @if (count($codigos))<p><span class="font-semibold text-gray-700">{{ $tipo }}:</span> {{ implode(', ', $codigos) }}</p>@endif
        @endforeach
    </div>
</article>

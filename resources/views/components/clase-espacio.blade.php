@props(['sesion', 'compacta' => false])

<article data-clase-id="{{ $sesion['id'] }}" @class([
    'min-w-0 rounded-lg border border-sky-200 bg-sky-50 text-gray-800',
    'p-2.5 text-xs' => $compacta,
    'p-3 text-sm' => ! $compacta,
])>
    <div class="flex min-w-0 flex-wrap items-center justify-between gap-1.5">
        <p class="break-words font-bold tracking-wide text-sky-900">{{ $sesion['codigo_asignatura'] ?: 'Sin código' }}</p>
        @if ($sesion['actividad'])
            <span class="max-w-full break-words rounded-md bg-white px-1.5 py-0.5 text-[10px] font-semibold text-sky-800">{{ $sesion['actividad'] }}</span>
        @endif
    </div>
    <p @class(['mt-1 break-words font-semibold leading-snug', 'line-clamp-2' => $compacta]) title="{{ $sesion['nombre_asignatura'] }}">{{ $sesion['nombre_asignatura'] ?: 'Asignatura sin registrar' }}</p>
    <p @class(['mt-2 break-words text-xs leading-relaxed text-gray-600', 'line-clamp-2' => $compacta])>
        Sección {{ $sesion['seccion'] ?: 'sin registrar' }}
        @if (filled($sesion['grupo']))<span aria-hidden="true"> · </span>Grupo {{ $sesion['grupo'] }}@endif
    </p>
    @if ($compacta)
    <details class="mt-1">
        <summary class="min-h-11 cursor-pointer py-3 text-[11px] font-semibold text-sky-800">Detalles · {{ count($sesion['profesores']) }} {{ count($sesion['profesores']) === 1 ? 'docente' : 'docentes' }}</summary>
        <p class="break-words text-xs font-semibold leading-relaxed">{{ $sesion['nombre_asignatura'] }}</p>
    @endif
    <div class="mt-2 border-t border-sky-100 pt-2">
        <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-500">{{ count($sesion['profesores']) === 1 ? 'Docente' : 'Docentes' }}</p>
        <ul class="mt-0.5 space-y-0.5 text-xs leading-relaxed text-gray-700">
            @forelse ($sesion['profesores'] as $nombreProfesor)
                <li class="break-words">{{ $nombreProfesor }}</li>
            @empty
                <li class="text-gray-500">Sin docente asignado</li>
            @endforelse
        </ul>
    </div>
    @if ($sesion['observaciones'])
        @if ($compacta)
            <p class="mt-2 text-[10px] font-semibold uppercase tracking-wide text-gray-500">Observaciones</p>
            <p class="mt-1 break-words pb-1 text-xs leading-relaxed whitespace-pre-line text-gray-600">{{ $sesion['observaciones'] }}</p>
        @else
        <details class="mt-1">
            <summary class="min-h-11 cursor-pointer py-3 text-xs font-semibold text-sky-800">Observaciones</summary>
            <p class="break-words pb-1 text-xs leading-relaxed whitespace-pre-line text-gray-600">{{ $sesion['observaciones'] }}</p>
        </details>
        @endif
    @endif
    @if ($compacta)</details>@endif
</article>

@props(['indicador'])
@php
    $porcentaje = $indicador['porcentaje'];
    $textoPorcentaje = $porcentaje === null ? 'Sin datos' : number_format($porcentaje, floor($porcentaje) == $porcentaje ? 0 : 1, ',', '.').'%';
@endphp
<article class="min-w-0 rounded-xl border border-borde bg-superficie p-4 shadow-sm" aria-label="{{ $indicador['titulo'] }}">
    <h3 class="text-sm font-semibold leading-5 text-texto">{{ $indicador['titulo'] }}</h3>
    <div class="mt-3 flex min-w-0 items-center gap-4">
        <div class="relative h-28 w-28 shrink-0" role="img" aria-label="{{ $indicador['titulo'] }}: {{ $textoPorcentaje }}{{ $indicador['total'] ? ', '.$indicador['asignados'].' de '.$indicador['total'] : '' }}">
            <svg class="h-full w-full -rotate-90" viewBox="0 0 120 120" aria-hidden="true">
                <circle cx="60" cy="60" r="48" fill="none" stroke-width="10" class="stroke-gray-100" />
                @if ($porcentaje !== null && $porcentaje > 0)
                    <circle cx="60" cy="60" r="48" fill="none" stroke-width="10" pathLength="100" stroke-dasharray="{{ min(100, $porcentaje) }} 100" class="stroke-institucional" />
                @endif
            </svg>
            <span class="absolute inset-0 flex items-center justify-center text-center font-bold tabular-nums text-texto {{ $porcentaje === null ? 'text-xs' : 'text-xl' }}" aria-hidden="true">{{ $textoPorcentaje }}</span>
        </div>
        <dl class="min-w-0 flex-1 space-y-2 text-sm">
            <div><dt class="flex items-center gap-2 text-texto-secundario"><span class="h-2 w-2 shrink-0 rounded-full bg-institucional" aria-hidden="true"></span>{{ $indicador['etiqueta_asignados'] }}</dt><dd class="mt-0.5 font-semibold tabular-nums text-texto">{{ number_format($indicador['asignados'], 0, ',', '.') }}</dd></div>
            <div><dt class="flex items-center gap-2 text-texto-secundario"><span class="h-2 w-2 shrink-0 rounded-full bg-gray-300" aria-hidden="true"></span>{{ $indicador['etiqueta_restantes'] }}</dt><dd class="mt-0.5 font-semibold tabular-nums text-texto">{{ number_format($indicador['restantes'], 0, ',', '.') }}</dd></div>
        </dl>
    </div>
    <p class="mt-3 text-xs leading-relaxed text-texto-secundario">{{ $indicador['descripcion'] }}</p>
    @if ($porcentaje === null)
        <p class="mt-1 text-xs font-medium text-texto-secundario">{{ $indicador['motivo_sin_datos'] }}</p>
    @endif
</article>

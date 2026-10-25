@props(['progreso'])

@php
    $presentacion = match ($progreso['estado']) {
        'sin_carga' => ['titulo' => 'Carga horaria sin definir', 'colores' => 'border-red-200 bg-red-50/70 text-red-700'],
        'excedido' => ['titulo' => 'Exceso de módulos', 'colores' => 'border-red-200 bg-red-50/70 text-red-700'],
        'completo' => ['titulo' => 'Módulos completos', 'colores' => 'border-green-200 bg-green-50/70 text-green-700'],
        'pendiente' => ['titulo' => 'Asignación pendiente', 'colores' => 'border-yellow-200 bg-yellow-50/70 text-yellow-800'],
        default => ['titulo' => 'Sin horarios asignados', 'colores' => 'border-gray-200 bg-gray-50 text-gray-700'],
    };
@endphp

<div class="mb-3">
    <div class="min-w-0 rounded-lg border px-4 py-3 {{ $presentacion['colores'] }}">
        <div class="flex min-w-0 items-start justify-between gap-3">
            <div class="min-w-0">
                <p class="text-xs font-bold uppercase">{{ $presentacion['titulo'] }}</p>

                @if ($progreso['estado'] === 'sin_carga')
                    <p class="mt-1 text-[11px]">La actividad debe tener una cantidad de módulos mayor que cero.</p>
                    <p class="mt-1 text-[11px]">Módulos asignados: {{ $progreso['asignados'] }}.</p>
                @else
                    <p class="mt-1 text-[11px]">{{ $progreso['asignados'] }} de {{ $progreso['requeridos'] }} módulos asignados.</p>

                    @if ($progreso['excedentes'] > 0)
                        <p class="mt-1 text-[11px] font-semibold">{{ $progreso['excedentes'] === 1 ? 'Sobra 1 módulo.' : 'Sobran ' . $progreso['excedentes'] . ' módulos.' }}</p>
                        <p class="mt-1 text-[11px]">Revisa los horarios del grupo antes de continuar.</p>
                    @elseif ($progreso['faltantes'] > 0)
                        <p class="mt-1 text-[11px] font-semibold">{{ $progreso['faltantes'] === 1 ? 'Falta 1 módulo por asignar.' : 'Faltan ' . $progreso['faltantes'] . ' módulos por asignar.' }}</p>
                    @endif
                @endif
            </div>

            <p class="shrink-0 text-xl font-bold">
                @if ($progreso['porcentaje'] !== null)
                    {{ rtrim(rtrim(number_format($progreso['porcentaje'], 1, ',', '.'), '0'), ',') }}%
                @else
                    —
                @endif
            </p>
        </div>
    </div>
    <p class="mt-1 text-[11px] text-gray-500">Este indicador solo cuenta módulos; no confirma la validación del horario.</p>
</div>

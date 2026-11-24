@props(['tipo', 'titulo' => null])

@php
    $exito = $tipo === 'exito';
@endphp

<div role="{{ $exito ? 'status' : 'alert' }}" @class([
    'flex items-start gap-3 rounded-xl border p-4 text-sm leading-6',
    'border-green-200 bg-green-50 text-green-800' => $exito,
    'border-red-200 bg-red-50 text-red-800' => ! $exito,
])>
    <svg class="mt-0.5 size-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <circle cx="12" cy="12" r="9"/>
        <path d="{{ $exito ? 'm8.5 12 2.2 2.2 4.8-4.8' : 'M12 7.5v5M12 16.5h.01' }}"/>
    </svg>
    <div class="min-w-0">
        @if ($titulo)<p class="font-semibold">{{ $titulo }}</p>@endif
        {{ $slot }}
    </div>
</div>

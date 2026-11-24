@props(['href', 'activo' => false])

<a
    href="{{ $href }}"
    @if ($activo) aria-current="location" @endif
    @class([
        'flex min-h-10 items-center gap-3 rounded-lg px-3 py-2 text-sm leading-5 transition-colors focus-visible:outline-2 focus-visible:outline-institucional max-lg:min-h-11',
        'bg-fondo font-semibold text-texto' => $activo,
        'font-normal text-texto-secundario hover:bg-fondo hover:text-texto' => ! $activo,
    ])
>
    <span aria-hidden="true" @class(['h-1.5 w-1.5 shrink-0 rounded-full', 'bg-institucional' => $activo, 'bg-gray-300' => ! $activo])></span>
    <span>{{ $slot }}</span>
</a>

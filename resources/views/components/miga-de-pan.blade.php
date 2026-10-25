<nav
    aria-label="Miga de pan"
    class="col-span-3 row-start-2 min-w-0 border-t border-borde/60 py-1 lg:col-span-1 lg:col-start-1 lg:row-start-1 lg:border-t-0 lg:py-0"
>
    @php
        $resumirInicio = count($migas) > 2 && $migas[0]['label'] === 'Inicio';
    @endphp

    <ol class="flex min-h-10 min-w-0 flex-wrap items-center gap-y-0.5 text-xs lg:flex-nowrap lg:text-sm">
        @foreach ($migas as $miga)
            <li class="{{ $resumirInicio && $loop->first ? 'hidden lg:flex' : 'flex' }} min-w-0 items-center {{ $miga['current'] ? 'max-w-full flex-1' : 'max-w-full shrink-0' }}">
                @unless ($loop->first)
                    <svg class="mx-1 h-3 w-3 shrink-0 text-texto-secundario/50 {{ $resumirInicio && $loop->index === 1 ? 'hidden lg:block' : '' }}" viewBox="0 0 20 20" fill="none" stroke="currentColor" aria-hidden="true" focusable="false">
                        <path d="m7.5 5 5 5-5 5" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                @endunless

                @if ($miga['url'])
                    <a
                        href="{{ $miga['url'] }}"
                        title="{{ $miga['label'] }}"
                        @if ($miga['label'] === 'Inicio') aria-label="Inicio" @endif
                        class="inline-flex min-h-10 max-w-[14rem] items-center gap-1.5 rounded-lg px-2 text-texto-secundario transition-colors hover:bg-fondo hover:text-institucional focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-institucional focus-visible:ring-offset-2 lg:max-w-[12rem]"
                    >
                        @if ($miga['label'] === 'Inicio')
                            <svg class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true" focusable="false">
                                <path d="m3 10 9-7 9 7M5 9v12h14V9M9 21v-7h6v7" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                            <span class="sr-only xl:not-sr-only">Inicio</span>
                        @else
                            <span class="truncate">{{ $miga['label'] }}</span>
                        @endif
                    </a>
                @elseif ($miga['current'])
                    <span aria-current="page" title="{{ $miga['label'] }}" class="block min-w-0 truncate rounded-lg bg-institucional/5 px-2.5 py-2 font-semibold text-institucional">
                        {{ $miga['label'] }}
                    </span>
                @else
                    <span title="{{ $miga['label'] }}" class="block max-w-[14rem] truncate px-2 py-2 text-texto-secundario lg:max-w-[12rem]">
                        {{ $miga['label'] }}
                    </span>
                @endif
            </li>
        @endforeach
    </ol>
</nav>

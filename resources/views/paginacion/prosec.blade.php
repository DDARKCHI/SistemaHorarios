@if ($paginator->hasPages())
    <nav aria-label="{{ $etiqueta ?? 'Paginación' }}" class="@container/paginacion">
        <div class="flex flex-col items-center gap-3 @min-[42rem]/paginacion:flex-row @min-[42rem]/paginacion:justify-between">
            <p class="text-sm text-texto-secundario">
                @if ($mostrarRango ?? true)
                    {{ __('Showing') }}
                    @if ($paginator->firstItem())
                        <span class="font-medium text-texto">{{ $paginator->firstItem() }}–{{ $paginator->lastItem() }}</span>
                    @else
                        0
                    @endif
                    {{ __('of') }} <span class="font-medium text-texto">{{ number_format($paginator->total(), 0, ',', '.') }}</span> {{ __('results') }}
                @else
                    Página <span class="font-medium text-texto">{{ $paginator->currentPage() }}</span> de {{ $paginator->lastPage() }}
                @endif
            </p>

            <div class="flex w-full flex-wrap items-center justify-between gap-1 rounded-lg border border-borde bg-fondo p-1 @min-[38rem]/paginacion:w-auto @min-[38rem]/paginacion:justify-center">
                @include('paginacion.direccion', ['anterior' => true, 'url' => $paginator->previousPageUrl()])

                @foreach ($elements as $elemento)
                    @if (is_string($elemento))
                        <span class="paginacion-control hidden @min-[38rem]/paginacion:inline-flex" aria-hidden="true">{{ $elemento }}</span>
                    @elseif (is_array($elemento))
                        @foreach ($elemento as $pagina => $url)
                            @if ($pagina === $paginator->currentPage())
                                <span class="paginacion-control hidden @min-[38rem]/paginacion:inline-flex" aria-current="page" aria-label="Página {{ $pagina }}">{{ $pagina }}</span>
                            @else
                                <a class="paginacion-control hidden @min-[38rem]/paginacion:inline-flex" href="{{ $url }}" aria-label="Ir a la página {{ $pagina }}">{{ $pagina }}</a>
                            @endif
                        @endforeach
                    @endif
                @endforeach

                @include('paginacion.direccion', ['anterior' => false, 'url' => $paginator->nextPageUrl()])
            </div>
        </div>
    </nav>
@endif

@php
    $texto = __($anterior ? 'pagination.previous' : 'pagination.next');
    $etiquetaControl = $url ? 'a' : 'span';
@endphp

<{{ $etiquetaControl }} class="paginacion-control inline-flex gap-2" aria-label="{{ $texto }}" @if ($url) href="{{ $url }}" rel="{{ $anterior ? 'prev' : 'next' }}" @else aria-disabled="true" @endif>
    <svg class="size-4 @unless($anterior) order-last @endunless" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path d="{{ $anterior ? 'm14 6-6 6 6 6' : 'm10 6 6 6-6 6' }}"/>
    </svg>
    <span class="@min-[38rem]/paginacion:sr-only">{{ $texto }}</span>
</{{ $etiquetaControl }}>

@props(['titulo'])

<header class="mb-6 flex min-w-0 flex-col gap-3 xl:flex-row xl:items-start xl:justify-between xl:gap-6">
    <div class="min-w-0 flex-1">
        <h1 class="text-2xl font-semibold text-gray-800">{{ $titulo }}</h1>
        @if ($slot->isNotEmpty())
            <p class="mt-1 text-sm text-gray-500">{{ $slot }}</p>
        @endif
    </div>

    @if (isset($acciones) && $acciones->isNotEmpty())
        <div class="min-w-0 shrink-0 xl:max-w-[50%]">
            {{ $acciones }}
        </div>
    @endif
</header>

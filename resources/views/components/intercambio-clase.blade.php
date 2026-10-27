@props(['horario'])
<article class="min-w-0 rounded-md border border-gray-200 bg-white px-1.5 py-1.5 shadow-sm">
    <div class="flex items-start justify-between gap-1">
        <div class="min-w-0">
            <p class="break-words text-[10px] font-bold leading-tight text-gray-800">{{ $horario->actividadSeccion?->seccion?->asignatura?->codigo }}</p>
            <p class="mt-0.5 line-clamp-2 break-words text-[11px] font-medium leading-tight text-gray-700" title="{{ $horario->actividadSeccion?->seccion?->asignatura?->nombre }}">{{ $horario->actividadSeccion?->seccion?->asignatura?->nombre }}</p>
        </div>
        @if ($horario->grupoSeccion)<span class="max-w-16 shrink-0 break-words rounded-full bg-gray-100 px-1.5 py-0.5 text-[10px] font-semibold text-gray-600">{{ $horario->grupoSeccion->codigo }}</span>@endif
    </div>
    <div class="mt-1.5 space-y-0.5 break-words text-[10px] leading-tight text-gray-500">
        <p><span class="font-semibold text-gray-700">Sec.:</span> {{ $horario->actividadSeccion?->seccion?->codigo }}</p>
        <p><span class="font-semibold text-gray-700">Act.:</span> {{ $horario->actividadSeccion?->tipo }}</p>
    </div>
</article>

@php($seleccion = $editable ? old('bloques', []) : ($respuesta?->bloques['disponibles'] ?? []))
@php($seleccion = is_array($seleccion) ? $seleccion : [])
<div class="disponibilidad-semanal" data-disponibilidad-semanal>
    <label class="disponibilidad-dia mb-3 font-semibold">
        Día de la semana
        <select data-elegir-dia class="mt-1.5 w-full rounded-lg border-borde bg-superficie px-3 text-sm font-normal">
            @foreach ($dias as $dia)
                <option value="{{ $dia->id }}">{{ $dia->nombre }}</option>
            @endforeach
        </select>
    </label>
    <div class="overflow-x-auto rounded-xl border border-borde" role="region" aria-label="Disponibilidad semanal" tabindex="0">
    <table class="w-full min-w-[640px] text-center text-sm">
        <caption class="sr-only">Disponibilidad semanal por día y módulo horario</caption>
        <thead class="bg-fondo"><tr><th scope="col" class="p-3 text-left">Módulo</th>@foreach ($dias as $dia)<th scope="col" data-dia="{{ $dia->id }}" class="p-3">{{ $dia->nombre }}</th>@endforeach</tr></thead>
        <tbody class="divide-y divide-borde">
        @foreach ($bloques as $bloque)
            <tr><th scope="row" class="p-3 text-left font-medium"><span class="block">Módulo {{ $bloque->numero }}</span><span class="text-xs font-normal text-texto-secundario">{{ substr($bloque->hora_inicio, 0, 5) }}–{{ substr($bloque->hora_fin, 0, 5) }}</span>@if ($bloque->es_almuerzo)<span class="block text-xs text-amber-700">Almuerzo</span>@endif</th>
            @foreach ($dias as $dia)
                @php($clave = $dia->id.':'.$bloque->id)
                <td data-dia="{{ $dia->id }}" class="border-l border-borde p-1 {{ !$editable && in_array($clave, $seleccion, true) ? 'bg-green-50' : '' }}">
                @if ($editable)<label class="flex min-h-12 cursor-pointer items-center justify-center rounded-lg hover:bg-green-50 has-[:checked]:bg-green-50"><input type="checkbox" name="bloques[]" value="{{ $clave }}" @checked(in_array($clave, $seleccion, true)) class="h-5 w-5 accent-green-700" aria-label="{{ $dia->nombre }}, módulo {{ $bloque->numero }}, {{ substr($bloque->hora_inicio, 0, 5) }}"></label>
                @else<span class="text-xs {{ in_array($clave, $seleccion, true) ? 'font-semibold text-green-800' : 'text-texto-secundario' }}">{{ in_array($clave, $seleccion, true) ? 'Disponible' : '—' }}</span>@endif
                </td>
            @endforeach</tr>
        @endforeach
        </tbody>
    </table>
    </div>
</div>

@extends('layouts.app')
@section('title', 'Ubicaciones del equipamiento | PROSEC')
@section('content')
<x-encabezado-pagina titulo="Ubicaciones del equipamiento">Distribuye unidades del inventario entre salas y laboratorios móviles.
    <x-slot:acciones><a href="{{ route('recursos.index', ['q' => $equipamiento->nombre, 'estado' => 'todos']) }}" class="boton boton-secundario">Volver al inventario</a></x-slot:acciones>
</x-encabezado-pagina>
<div class="min-w-0 space-y-4">
    @if (session('success'))<div role="status" class="rounded-lg border border-gray-200 bg-white p-5 text-sm shadow-sm">{{ session('success') }}</div>@endif
    @if ($errors->any())<div role="alert" class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800"><ul class="list-inside list-disc">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <section aria-label="Unidades del equipamiento" class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
        <h2 class="break-words text-base font-semibold text-gray-800">{{ $equipamiento->nombre }}</h2><p class="mt-1 text-sm text-gray-500">{{ $equipamiento->tipo }}</p>
        <dl class="mt-4 grid grid-cols-3 gap-4">@foreach (['En inventario' => $equipamiento->cantidad, 'Ubicadas' => $ubicadas, 'Sin ubicar' => max(0, $equipamiento->cantidad - $ubicadas)] as $etiqueta => $cantidad)<div class="min-w-0"><dt class="min-h-10 text-sm font-semibold text-gray-700 sm:min-h-0">{{ $etiqueta }}</dt><dd class="mt-1 text-2xl font-semibold">{{ $cantidad }}</dd></div>@endforeach</dl>
        <p class="mt-4 text-sm leading-6 text-gray-500">Las unidades ubicadas pertenecen al inventario. Los laboratorios utilizan el equipamiento de su sala física; las unidades de un laboratorio móvil viajan con él.</p>
    </section>
    @if ($seleccionada || ($equipamiento->estado && $equipamiento->disponible && $ubicadas < $equipamiento->cantidad))
        <form method="POST" action="{{ $seleccionada ? route('recursos.ubicaciones.update', ['equipamiento' => $equipamiento->id, 'ubicacion' => $seleccionada->id]) : route('recursos.ubicaciones.store', $equipamiento) }}" aria-label="Distribuir unidades" class="grid min-w-0 items-end gap-4 rounded-lg border border-gray-200 bg-white p-5 shadow-sm sm:p-6 lg:grid-cols-[2fr_1fr_auto]">
            @csrf
            @if ($seleccionada)
                @method('PUT')
                <div><h2 class="text-sm font-semibold text-gray-700">Modificar cantidad ubicada</h2><p class="mt-2 break-words text-sm">{{ $seleccionada->sala ? 'Sala '.$seleccionada->sala->codigo : 'Laboratorio móvil '.$seleccionada->laboratorioMovil?->codigo }}</p></div>
            @else
                <div><label for="espacio-equipamiento" class="mb-1.5 block text-sm font-semibold text-gray-700">Sala o laboratorio móvil</label><select id="espacio-equipamiento" name="espacio" data-select-buscable class="h-11 w-full min-w-0 rounded-lg border border-gray-300 px-3 text-sm"><option value="">Selecciona un espacio</option>@foreach ($espacios as $espacio)<option value="{{ $espacio['id'] }}" @selected(is_string(old('espacio')) && old('espacio') === $espacio['id'])>{{ $espacio['nombre'] }}</option>@endforeach</select></div>
            @endif
            <div><label for="cantidad-ubicacion" class="mb-1.5 block text-sm font-semibold text-gray-700">Cantidad de unidades</label><input id="cantidad-ubicacion" name="cantidad" type="number" min="1" max="65535" step="1" required value="{{ is_scalar(old('cantidad')) ? old('cantidad') : ($seleccionada?->cantidad ?? 1) }}" class="h-11 w-full min-w-0 rounded-lg border border-gray-300 px-3 text-sm"></div>
            <div class="flex flex-wrap gap-2">@if ($seleccionada)<a href="{{ route('recursos.ubicaciones.index', $equipamiento) }}" class="boton boton-secundario">Cancelar</a>@endif<button class="boton boton-primario" type="submit">{{ $seleccionada ? 'Guardar cantidad' : 'Ubicar unidades' }}</button></div>
        </form>
    @else
        <p class="rounded-lg border border-gray-200 bg-white p-5 text-sm text-gray-600">{{ ! $equipamiento->estado || ! $equipamiento->disponible ? 'El equipamiento está inactivo o no habilitado. Puedes retirar ubicaciones o reducir sus cantidades.' : 'Todas las unidades están ubicadas. Modifica una cantidad o retira una ubicación para redistribuirlas.' }}</p>
    @endif
    <section aria-labelledby="listado-ubicaciones" class="space-y-3"><h2 id="listado-ubicaciones" class="text-base font-semibold text-gray-800">Distribución actual</h2>
        @forelse ($ubicaciones as $ubicacion)
            @php $espacio = $ubicacion->sala ?? $ubicacion->laboratorioMovil; @endphp
            <article class="flex min-w-0 flex-col justify-between gap-4 rounded-lg border border-gray-200 bg-white p-5 shadow-sm sm:flex-row sm:items-center sm:p-6">
                <div class="min-w-0"><h3 class="break-words text-sm font-semibold">{{ $ubicacion->sala_id ? 'Sala' : 'Laboratorio móvil' }} {{ $espacio?->codigo ?? 'por revisar' }}</h3><p class="mt-1 text-sm text-gray-600">{{ $espacio?->sede?->nombre }} · {{ $ubicacion->cantidad }} {{ $ubicacion->cantidad === 1 ? 'unidad' : 'unidades' }}</p>@if (! $espacio?->estado || ! $espacio?->disponible)<p class="mt-1 text-sm text-gray-500">Espacio inactivo o no habilitado; sus unidades siguen ubicadas aquí.</p>@endif</div>
                <div class="flex flex-wrap gap-2"><a class="boton boton-secundario" href="{{ route('recursos.ubicaciones.index', ['equipamiento' => $equipamiento->id, 'ubicacion' => $ubicacion->id]) }}">Modificar</a><form method="POST" action="{{ route('recursos.ubicaciones.destroy', ['equipamiento' => $equipamiento->id, 'ubicacion' => $ubicacion->id]) }}">@csrf @method('DELETE')<button type="submit" class="boton boton-secundario">Quitar ubicación</button></form></div>
            </article>
        @empty<div class="rounded-lg border border-gray-200 bg-white p-8 text-center text-sm text-gray-500">Este equipamiento aún no tiene unidades ubicadas.</div>@endforelse
    </section>
</div>
@endsection

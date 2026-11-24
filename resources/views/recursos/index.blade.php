@extends('layouts.app')
@section('content')
@php
    $campo = 'h-11 w-full min-w-0 rounded-lg border border-gray-300 bg-white px-3 text-base text-gray-800 focus:border-institucional focus:ring-1 focus:ring-institucional sm:text-sm';
    $hayFiltros = filled($filtros['universidad_id']) || filled($filtros['tipo']) || filled($filtros['cantidad_minima']) || $filtros['estado'] !== 'activos' || filled($filtros['uso']);
@endphp
<x-encabezado-pagina titulo="Equipamiento y recursos">
    Administra el inventario institucional, sus tipos y cantidades registradas.
    <x-slot:acciones>
        <a href="{{ route('recursos.create') }}" class="boton boton-primario shrink-0">Registrar equipamiento</a>
    </x-slot:acciones>
</x-encabezado-pagina>

<div class="@container/catalogo w-full min-w-0 space-y-3">

    @if (session('success'))<div role="status" class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('success') }}</div>@endif
    @if ($errors->any())<div role="alert" class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800"><p class="font-semibold">Revisa los filtros de búsqueda.</p><ul class="mt-1 list-inside list-disc">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <section aria-label="Resumen del inventario">
        <dl class="grid grid-cols-1 gap-2 sm:grid-cols-3">@foreach (['total' => 'Registros de equipamiento', 'activos' => 'Registros activos', 'unidades' => 'Unidades en registros activos'] as $clave => $etiqueta)<div class="flex min-w-0 items-center justify-between gap-3 rounded-lg border border-gray-200 bg-white px-3 py-3 shadow-sm sm:block"><dt class="text-sm font-semibold text-gray-700">{{ $etiqueta }}</dt><dd class="text-xl font-bold text-gray-800 sm:mt-1">{{ $resumen[$clave] }}</dd></div>@endforeach</dl>
    </section>
    <form method="GET" action="{{ route('recursos.index') }}" aria-label="Buscar y filtrar equipamiento" class="grid min-w-0 grid-cols-1 items-end gap-3 rounded-lg border border-gray-200 bg-white p-4 shadow-sm @min-[26rem]/catalogo:grid-cols-[minmax(0,1fr)_auto] @min-[34rem]/catalogo:grid-cols-[minmax(0,1fr)_auto_auto]">
        <div class="min-w-0 @min-[26rem]/catalogo:col-span-2 @min-[34rem]/catalogo:col-span-1"><label for="busqueda-recursos" class="mb-1.5 block text-sm font-semibold text-gray-700">Buscar equipamiento</label><input id="busqueda-recursos" name="q" type="search" maxlength="100" value="{{ $filtros['q'] }}" placeholder="Nombre, tipo o descripción" class="{{ $campo }}"></div>
        <div class="flex gap-2"><button type="submit" class="boton boton-primario flex-1 @min-[34rem]/catalogo:flex-none">Buscar</button><a href="{{ route('recursos.index') }}" class="boton boton-secundario flex-1 @min-[34rem]/catalogo:flex-none">Limpiar</a></div>
        <details data-filtros-salas class="contents" @if ($hayFiltros) open @endif>
            <summary class="boton boton-secundario whitespace-nowrap">
                Filtros
                @if ($hayFiltros)<span class="shrink-0 rounded-full bg-fondo px-2 py-0.5 text-xs text-texto-secundario">Aplicados</span>@endif
            </summary>
            <div data-panel-filtros-salas class="col-span-full grid min-w-0 grid-cols-1 gap-3 @min-[29rem]/catalogo:grid-cols-2 @min-[59rem]/catalogo:grid-cols-5">
                <div><label for="institucion-recursos" class="mb-1.5 block text-sm font-semibold text-gray-700">Institución</label><select id="institucion-recursos" name="universidad_id" data-select-buscable class="{{ $campo }}"><option value="">Todas las instituciones</option>@foreach ($instituciones as $institucion)<option value="{{ $institucion->id }}" @selected((string) $filtros['universidad_id'] === (string) $institucion->id)>{{ $institucion->sigla }} · {{ $institucion->nombre }}{{ $institucion->estado ? '' : ' (inactiva)' }}</option>@endforeach</select></div>
                <div><label for="tipo-recursos" class="mb-1.5 block text-sm font-semibold text-gray-700">Tipo de equipamiento</label><select id="tipo-recursos" name="tipo" data-select-buscable class="{{ $campo }}"><option value="">Todos los tipos</option>@foreach ($tipos as $tipo)<option value="{{ $tipo }}" @selected($filtros['tipo'] === $tipo)>{{ $tipo }}</option>@endforeach</select></div>
                <div><label for="cantidad-minima-recursos" class="mb-1.5 block text-sm font-semibold text-gray-700">Cantidad mínima</label><input id="cantidad-minima-recursos" name="cantidad_minima" type="number" min="0" max="65535" step="1" inputmode="numeric" value="{{ $filtros['cantidad_minima'] }}" placeholder="Sin mínimo" class="{{ $campo }}"></div>
                <div><label for="estado-recursos" class="mb-1.5 block text-sm font-semibold text-gray-700">Estado del registro</label><select id="estado-recursos" name="estado" class="{{ $campo }}">@foreach (['activos' => 'Activos', 'inactivos' => 'Inactivos', 'todos' => 'Todos'] as $clave => $etiqueta)<option value="{{ $clave }}" @selected($filtros['estado'] === $clave)>{{ $etiqueta }}</option>@endforeach</select></div>
                <div><label for="uso-recursos" class="mb-1.5 block text-sm font-semibold text-gray-700">Habilitación de uso</label><select id="uso-recursos" name="uso" class="{{ $campo }}"><option value="">Todas</option><option value="habilitados" @selected($filtros['uso'] === 'habilitados')>Uso habilitado</option><option value="deshabilitados" @selected($filtros['uso'] === 'deshabilitados')>Uso deshabilitado</option></select></div>
            </div>
        </details>
    </form>
    <section aria-labelledby="listado-recursos" class="min-w-0">
        <div class="mb-2 flex flex-wrap items-baseline justify-between gap-2 px-1"><h2 id="listado-recursos" class="text-sm font-bold text-gray-800">Equipamiento registrado</h2><p class="text-xs text-gray-500">{{ $equipamientos->total() > 0 ? 'Mostrando '.$equipamientos->firstItem().'–'.$equipamientos->lastItem().' de '.$equipamientos->total() : '0 registros' }}</p></div>
        @if ($equipamientos->isEmpty())
            <div class="rounded-lg border border-gray-200 bg-white px-4 py-10 text-center shadow-sm"><h3 class="text-sm font-semibold text-gray-700">{{ $resumen['total'] === 0 ? 'No hay equipamiento registrado' : 'No se encontró equipamiento para esta consulta' }}</h3><p class="mt-2 text-sm text-gray-500">{{ $resumen['total'] === 0 ? 'Los recursos que registres aparecerán en este inventario.' : 'Ajusta el nombre, el tipo o los filtros de búsqueda.' }}</p></div>
        @else
            <div class="grid min-w-0 grid-cols-1 gap-3 @min-[42rem]/catalogo:grid-cols-2 @min-[61rem]/catalogo:hidden">
                @foreach ($equipamientos as $equipamiento)
                    <article aria-label="Equipamiento {{ $equipamiento->nombre }}" class="flex min-w-0 flex-col gap-3 rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                        <div><h3 class="break-words text-sm font-bold text-gray-900">{{ $equipamiento->nombre }}</h3><p class="mt-1 break-words text-xs text-gray-600">{{ $equipamiento->tipo ?: 'Sin tipo registrado' }}</p></div>
                        <dl class="space-y-3 text-sm"><div><dt class="text-xs font-semibold text-gray-500">Institución</dt><dd class="mt-1 break-words">{{ $equipamiento->universidad?->nombre ?? 'Sin institución registrada' }}</dd></div><div><dt class="text-xs font-semibold text-gray-500">Cantidad registrada</dt><dd class="mt-1 font-semibold">{{ $equipamiento->cantidad }} {{ $equipamiento->cantidad === 1 ? 'unidad' : 'unidades' }}</dd></div></dl>
                        @if (filled($equipamiento->descripcion))<p class="break-words text-xs leading-5 text-gray-600">{{ $equipamiento->descripcion }}</p>@endif
                        <div>@include('recursos.estado')</div>
                        @include('recursos.acciones')
                    </article>
                @endforeach
            </div>
            <div class="hidden min-w-0 overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm @min-[61rem]/catalogo:block">
                <div class="max-w-full overflow-x-auto" role="region" aria-label="Inventario de equipamiento" tabindex="0">
                    <table class="w-full min-w-[960px] table-fixed text-left text-sm">
                        <colgroup><col><col class="w-[13%]"><col class="w-[19%]"><col class="w-[10%]"><col class="w-[14%]"><col class="w-60"></colgroup>
                        <thead class="bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-500">
                            <tr>
                                <th scope="col" class="px-4 py-3">Equipamiento</th>
                                <th scope="col" class="px-4 py-3">Tipo</th>
                                <th scope="col" class="px-4 py-3">Institución</th>
                                <th scope="col" class="px-4 py-3">Cantidad</th>
                                <th scope="col" class="px-4 py-3">Estado y uso</th>
                                <th scope="col" class="px-4 py-3 text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($equipamientos as $equipamiento)
                                <tr class="align-middle hover:bg-gray-50">
                                    <th scope="row" class="px-4 py-3 font-normal">
                                        <p class="break-words font-bold text-gray-900">{{ $equipamiento->nombre }}</p>
                                        @if (filled($equipamiento->descripcion))
                                            <p class="mt-1 break-words text-xs leading-5 text-gray-600">{{ $equipamiento->descripcion }}</p>
                                        @endif
                                    </th>
                                    <td class="break-words px-4 py-3 text-gray-700">{{ $equipamiento->tipo ?: 'Sin tipo registrado' }}</td>
                                    <td class="break-words px-4 py-3 text-gray-700">{{ $equipamiento->universidad?->nombre ?? 'Sin institución registrada' }}</td>
                                    <td class="px-4 py-3 font-semibold text-gray-800">{{ $equipamiento->cantidad }} {{ $equipamiento->cantidad === 1 ? 'unidad' : 'unidades' }}</td>
                                    <td class="px-4 py-3">@include('recursos.estado')</td>
                                    <td class="px-4 py-3">@include('recursos.acciones', ['enTabla' => true])</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
        <x-paginacion-catalogo :paginas="$equipamientos" etiqueta="Paginación de equipamiento" />
    </section>
</div>
@endsection

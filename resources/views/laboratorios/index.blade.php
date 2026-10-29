@extends('layouts.app')

@section('content')
<div class="w-full min-w-0 space-y-3 px-2 py-2">
    <header class="flex flex-col gap-3 rounded-lg border border-gray-200 bg-white px-4 py-4 shadow-sm xl:flex-row xl:items-center xl:justify-between">
        <div class="min-w-0 flex-1">
            <h1 class="text-base font-bold text-gray-800">Laboratorios</h1>
            <p class="mt-1 text-sm text-gray-600">Administra los laboratorios fijos y móviles, su capacidad y habilitación de uso.</p>
        </div>
        <div class="flex shrink-0 flex-col gap-2 sm:flex-row">
            <a href="{{ route('laboratorios.create', ['tipo' => 'fijo']) }}" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-institucional px-4 py-2 text-sm font-semibold text-white hover:bg-institucional-oscuro">Crear laboratorio fijo</a>
            <a href="{{ route('laboratorios.create', ['tipo' => 'movil']) }}" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-red-200 bg-red-50 px-4 py-2 text-sm font-semibold text-institucional hover:bg-red-100">Crear laboratorio móvil</a>
        </div>
    </header>

    @if (session('success'))<div role="status" class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('success') }}</div>@endif
    @if ($errors->any())
        <div role="alert" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
            <p class="font-semibold">Revisa los filtros de búsqueda.</p>
            <ul class="mt-1 list-inside list-disc space-y-1">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <section aria-labelledby="resumen-laboratorios">
        <div class="mb-2 flex flex-wrap items-baseline justify-between gap-1 px-1">
            <h2 id="resumen-laboratorios" class="text-sm font-semibold text-gray-700">Resumen del catálogo</h2>
            <p class="text-[11px] text-gray-500">Los filtros se aplican al listado.</p>
        </div>
        <dl class="grid grid-cols-1 gap-2 sm:grid-cols-3">
            @foreach (['fijos' => 'Laboratorios fijos', 'moviles' => 'Laboratorios móviles', 'activos' => 'Registros activos'] as $clave => $etiqueta)
                <div class="flex min-w-0 items-center justify-between gap-3 rounded-lg border border-gray-200 bg-white px-3 py-3 shadow-sm sm:block">
                    <dt class="text-sm font-semibold text-gray-700">{{ $etiqueta }}</dt>
                    <dd class="text-xl font-bold text-gray-800 sm:mt-1">{{ $resumen[$clave] }}</dd>
                </div>
            @endforeach
        </dl>
    </section>

    <nav aria-label="Tipo de laboratorio" class="flex gap-2">
        @foreach (['fijo' => 'Laboratorios fijos', 'movil' => 'Laboratorios móviles'] as $opcion => $etiqueta)
            <a href="{{ route('laboratorios.index', array_replace($filtros, ['tipo' => $opcion])) }}" @if ($tipo === $opcion) aria-current="page" @endif @class(['inline-flex min-h-11 flex-1 items-center justify-center rounded-lg border px-3 py-2 text-center text-sm font-semibold sm:flex-none', 'border-red-200 bg-red-50 text-institucional' => $tipo === $opcion, 'border-gray-300 bg-white text-gray-700 hover:bg-gray-50' => $tipo !== $opcion])>{{ $etiqueta }}</a>
        @endforeach
    </nav>

    @php
        $hayFiltros = filled($filtros['sede_id']) || filled($filtros['capacidad_minima']) || $filtros['estado'] !== 'activos' || filled($filtros['uso']);
        $campo = 'h-11 w-full min-w-0 rounded-lg border border-gray-300 bg-white px-3 text-base text-gray-800 focus:border-institucional focus:ring-1 focus:ring-institucional sm:text-sm';
    @endphp
    <form method="GET" action="{{ route('laboratorios.index') }}" aria-label="Buscar y filtrar laboratorios" class="grid min-w-0 grid-cols-1 gap-3 rounded-lg border border-gray-200 bg-white p-4 shadow-sm sm:grid-cols-[minmax(0,1fr)_auto] sm:items-end lg:grid-cols-[minmax(0,1fr)_auto_minmax(10rem,13rem)]">
        <input type="hidden" name="tipo" value="{{ $tipo }}">
        <div class="min-w-0">
            <label for="busqueda-laboratorios" class="mb-1.5 block text-sm font-semibold text-gray-700">Buscar laboratorio</label>
            <input id="busqueda-laboratorios" name="q" type="search" maxlength="100" value="{{ $filtros['q'] }}" placeholder="{{ $tipo === 'fijo' ? 'Código, nombre o sala física' : 'Código o nombre' }}" class="{{ $campo }}">
        </div>
        <div class="flex gap-2 sm:col-start-2 sm:row-start-1">
            <button type="submit" class="inline-flex min-h-11 flex-1 items-center justify-center rounded-lg bg-institucional px-5 py-2 text-sm font-semibold text-white hover:bg-institucional-oscuro sm:flex-none">Buscar</button>
            <a href="{{ route('laboratorios.index', ['tipo' => $tipo]) }}" class="inline-flex min-h-11 flex-1 items-center justify-center rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 sm:flex-none">Limpiar</a>
        </div>
        <details data-filtros-salas class="contents" @if ($hayFiltros) open @endif>
            <summary class="min-h-11 cursor-pointer rounded-lg border border-gray-300 bg-gray-50 px-3 py-3 text-sm font-semibold text-gray-700 marker:text-institucional sm:col-span-2 lg:col-span-1 lg:col-start-3 lg:row-start-1">Filtrar laboratorios</summary>
            <div data-panel-filtros-salas class="grid min-w-0 grid-cols-1 gap-3 sm:col-span-2 sm:grid-cols-2 lg:col-span-3 xl:grid-cols-4">
                <div>
                    <label for="sede-laboratorios" class="mb-1.5 block text-sm font-semibold text-gray-700">Sede</label>
                    <select id="sede-laboratorios" name="sede_id" data-select-buscable class="{{ $campo }}">
                        <option value="">Todas las sedes</option>
                        @foreach ($sedes as $sede)<option value="{{ $sede->id }}" @selected((string) ($filtros['sede_id'] ?? '') === (string) $sede->id)>{{ $sede->nombre }}{{ $sede->estado ? '' : ' (inactiva)' }}</option>@endforeach
                    </select>
                </div>
                <div>
                    <label for="capacidad-minima-laboratorios" class="mb-1.5 block text-sm font-semibold text-gray-700">Capacidad mínima</label>
                    <input id="capacidad-minima-laboratorios" name="capacidad_minima" type="number" min="0" max="65535" step="1" inputmode="numeric" value="{{ $filtros['capacidad_minima'] }}" placeholder="Sin mínimo" class="{{ $campo }}">
                </div>
                <div>
                    <label for="estado-laboratorios" class="mb-1.5 block text-sm font-semibold text-gray-700">Estado del registro</label>
                    <select id="estado-laboratorios" name="estado" class="{{ $campo }}">
                        @foreach (['activos' => 'Activos', 'inactivos' => 'Inactivos', 'todos' => 'Todos'] as $clave => $etiqueta)<option value="{{ $clave }}" @selected($filtros['estado'] === $clave)>{{ $etiqueta }}</option>@endforeach
                    </select>
                </div>
                <div>
                    <label for="uso-laboratorios" class="mb-1.5 block text-sm font-semibold text-gray-700">Habilitación del recurso</label>
                    <select id="uso-laboratorios" name="uso" class="{{ $campo }}">
                        <option value="">Todas</option>
                        <option value="habilitados" @selected($filtros['uso'] === 'habilitados')>Uso habilitado</option>
                        <option value="deshabilitados" @selected($filtros['uso'] === 'deshabilitados')>Uso deshabilitado</option>
                    </select>
                </div>
            </div>
        </details>
    </form>

    <section aria-labelledby="listado-laboratorios" class="min-w-0">
        <div class="mb-2 flex flex-wrap items-baseline justify-between gap-2 px-1">
            <h2 id="listado-laboratorios" class="text-sm font-bold text-gray-800">{{ $tipo === 'fijo' ? 'Laboratorios fijos' : 'Laboratorios móviles' }}</h2>
            <p class="text-xs text-gray-500">{{ $laboratorios->total() > 0 ? 'Mostrando '.$laboratorios->firstItem().'–'.$laboratorios->lastItem().' de '.$laboratorios->total() : '0 registros' }}</p>
        </div>
        @if ($laboratorios->isEmpty())
            <div class="rounded-lg border border-gray-200 bg-white px-4 py-10 text-center shadow-sm">
                <h3 class="text-sm font-semibold text-gray-700">No hay laboratorios que coincidan con esta consulta</h3>
                <p class="mt-2 text-sm text-gray-500">Puedes registrar un laboratorio o ajustar los filtros de búsqueda.</p>
            </div>
        @else
            <div class="grid min-w-0 grid-cols-1 gap-3 md:grid-cols-2 xl:hidden">
                @foreach ($laboratorios as $laboratorio)
                    <article aria-label="Laboratorio {{ $laboratorio->codigo ?: $laboratorio->nombre }}" class="min-w-0 space-y-3 rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                        <div><h3 class="break-words text-sm font-bold text-gray-900">{{ $laboratorio->codigo ?: 'Sin código' }}</h3><p class="mt-1 break-words text-sm text-gray-700">{{ $laboratorio->nombre }}</p></div>
                        <dl class="space-y-3 text-sm">
                            <div><dt class="text-xs font-semibold text-gray-500">Sede</dt><dd class="mt-1 break-words">{{ $tipo === 'fijo' ? ($laboratorio->sala?->sede?->nombre ?? 'Sin sede registrada') : ($laboratorio->sede?->nombre ?? 'Sin sede registrada') }}</dd></div>
                            @if ($tipo === 'fijo')<div><dt class="text-xs font-semibold text-gray-500">Sala física</dt><dd class="mt-1 break-words">{{ $laboratorio->sala?->codigo ?? 'Sin sala registrada' }}</dd></div>@endif
                            <div><dt class="text-xs font-semibold text-gray-500">Capacidad</dt><dd class="mt-1">{{ $laboratorio->capacidad > 0 ? $laboratorio->capacidad.' personas' : 'Sin capacidad informada' }}</dd></div>
                        </dl>
                        <div>@include('laboratorios.estado')</div>
                        @include('laboratorios.acciones')
                    </article>
                @endforeach
            </div>
            <div class="hidden min-w-0 overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm xl:block">
                <div class="max-w-full overflow-x-auto" role="region" aria-label="Listado de laboratorios" tabindex="0">
                    <table class="w-full min-w-[900px] table-fixed text-left text-sm">
                        <colgroup><col class="w-[23%]"><col class="w-[22%]"><col class="w-[17%]"><col class="w-[18%]"><col class="w-[20%]"></colgroup>
                        <thead class="bg-gray-50 text-[10px] font-semibold uppercase tracking-wide text-gray-500"><tr><th scope="col" class="px-4 py-3">Laboratorio</th><th scope="col" class="px-4 py-3">{{ $tipo === 'fijo' ? 'Sede y sala física' : 'Sede de origen' }}</th><th scope="col" class="px-4 py-3">Capacidad</th><th scope="col" class="px-4 py-3">Estado y uso</th><th scope="col" class="px-4 py-3 text-right">Acciones</th></tr></thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($laboratorios as $laboratorio)
                                <tr class="align-top hover:bg-gray-50">
                                    <th scope="row" class="px-4 py-4 font-normal"><p class="break-words font-bold text-gray-900">{{ $laboratorio->codigo ?: 'Sin código' }}</p><p class="mt-1 break-words text-xs text-gray-600">{{ $laboratorio->nombre }}</p></th>
                                    <td class="px-4 py-4 text-xs"><p class="break-words text-gray-800">{{ $tipo === 'fijo' ? ($laboratorio->sala?->sede?->nombre ?? 'Sin sede registrada') : ($laboratorio->sede?->nombre ?? 'Sin sede registrada') }}</p>@if ($tipo === 'fijo')<p class="mt-2 break-words text-gray-500">Sala {{ $laboratorio->sala?->codigo ?? 'no registrada' }}</p>@endif</td>
                                    <td class="px-4 py-4 text-xs font-semibold text-gray-800">{{ $laboratorio->capacidad > 0 ? $laboratorio->capacidad.' personas' : 'Sin capacidad informada' }}</td>
                                    <td class="px-4 py-4">@include('laboratorios.estado')</td>
                                    <td class="px-4 py-4">@include('laboratorios.acciones')</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
        @if ($laboratorios->hasPages())
            <nav aria-label="Paginación de laboratorios" class="mt-3 flex flex-wrap items-center justify-between gap-2 rounded-lg border border-gray-200 bg-white px-3 py-3 text-sm shadow-sm">
                @if ($laboratorios->onFirstPage())<span aria-disabled="true" class="px-3 py-2 text-gray-400">Anterior</span>@else<a href="{{ $laboratorios->previousPageUrl() }}" rel="prev" class="inline-flex min-h-11 items-center rounded-lg border border-gray-300 px-3 py-2 text-gray-700 hover:bg-gray-50">Anterior</a>@endif
                <span class="text-xs text-gray-600">Página {{ $laboratorios->currentPage() }} de {{ $laboratorios->lastPage() }}</span>
                @if ($laboratorios->hasMorePages())<a href="{{ $laboratorios->nextPageUrl() }}" rel="next" class="inline-flex min-h-11 items-center rounded-lg border border-gray-300 px-3 py-2 text-gray-700 hover:bg-gray-50">Siguiente</a>@else<span aria-disabled="true" class="px-3 py-2 text-gray-400">Siguiente</span>@endif
            </nav>
        @endif
    </section>
</div>
@endsection

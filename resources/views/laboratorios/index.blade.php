@extends('layouts.app')

@section('content')
<x-encabezado-pagina titulo="Laboratorios">
    Administra los laboratorios, su capacidad y habilitación de uso.
    <x-slot:acciones>
        <div class="flex shrink-0 flex-col gap-2 sm:flex-row">
            <a href="{{ route('laboratorios.create', ['tipo' => 'fijo']) }}" class="boton boton-primario">Crear laboratorio</a>
            <a href="{{ route('laboratorios.create', ['tipo' => 'movil']) }}" class="boton boton-secundario">Crear laboratorio móvil</a>
        </div>
    </x-slot:acciones>
</x-encabezado-pagina>

<div class="@container/catalogo w-full min-w-0 space-y-3">

    @if (session('success'))<div role="status" class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('success') }}</div>@endif
    @if ($errors->any())
        <div role="alert" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
            <p class="font-semibold">Revisa los filtros de búsqueda.</p>
            <ul class="mt-1 list-inside list-disc space-y-1">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <section aria-label="Resumen del catálogo de laboratorios">
        <dl class="grid grid-cols-1 gap-2 sm:grid-cols-3">
            @foreach (['fijos' => 'Laboratorios', 'moviles' => 'Laboratorios móviles', 'activos' => 'Registros activos'] as $clave => $etiqueta)
                <div class="flex min-w-0 items-center justify-between gap-3 rounded-lg border border-gray-200 bg-white px-3 py-3 shadow-sm sm:block">
                    <dt class="text-sm font-semibold text-gray-700">{{ $etiqueta }}</dt>
                    <dd class="text-xl font-bold text-gray-800 sm:mt-1">{{ $resumen[$clave] }}</dd>
                </div>
            @endforeach
        </dl>
    </section>

    <nav aria-label="Tipo de laboratorio" class="flex gap-2">
        @foreach (['fijo' => 'Laboratorios', 'movil' => 'Laboratorios móviles'] as $opcion => $etiqueta)
            <a href="{{ route('laboratorios.index', array_replace($filtros, ['tipo' => $opcion])) }}" @if ($tipo === $opcion) aria-current="page" @endif @class(['boton flex-1 sm:flex-none', 'boton-primario' => $tipo === $opcion, 'boton-secundario' => $tipo !== $opcion])>{{ $etiqueta }}</a>
        @endforeach
    </nav>

    @php
        $hayFiltros = filled($filtros['sede_id']) || filled($filtros['capacidad_minima']) || $filtros['estado'] !== 'activos' || filled($filtros['uso']);
        $campo = 'h-11 w-full min-w-0 rounded-lg border border-gray-300 bg-white px-3 text-base text-gray-800 focus:border-institucional focus:ring-1 focus:ring-institucional sm:text-sm';
    @endphp
    <form method="GET" action="{{ route('laboratorios.index') }}" aria-label="Buscar y filtrar laboratorios" class="grid min-w-0 grid-cols-1 items-end gap-3 rounded-lg border border-gray-200 bg-white p-4 shadow-sm @min-[26rem]/catalogo:grid-cols-[minmax(0,1fr)_auto] @min-[34rem]/catalogo:grid-cols-[minmax(0,1fr)_auto_auto]">
        <input type="hidden" name="tipo" value="{{ $tipo }}">
        <div class="min-w-0 @min-[26rem]/catalogo:col-span-2 @min-[34rem]/catalogo:col-span-1">
            <label for="busqueda-laboratorios" class="mb-1.5 block text-sm font-semibold text-gray-700">Buscar laboratorio</label>
            <input id="busqueda-laboratorios" name="q" type="search" maxlength="100" value="{{ $filtros['q'] }}" placeholder="{{ $tipo === 'fijo' ? 'Código, nombre o sala física' : 'Código o nombre' }}" class="{{ $campo }}">
        </div>
        <div class="flex gap-2">
            <button type="submit" class="boton boton-primario flex-1 @min-[34rem]/catalogo:flex-none">Buscar</button>
            <a href="{{ route('laboratorios.index', ['tipo' => $tipo]) }}" class="boton boton-secundario flex-1 @min-[34rem]/catalogo:flex-none">Limpiar</a>
        </div>
        <details data-filtros-salas class="contents" @if ($hayFiltros) open @endif>
            <summary class="boton boton-secundario whitespace-nowrap">
                Filtros
                @if ($hayFiltros)<span class="shrink-0 rounded-full bg-fondo px-2 py-0.5 text-xs text-texto-secundario">Aplicados</span>@endif
            </summary>
            <div data-panel-filtros-salas class="col-span-full grid min-w-0 grid-cols-1 gap-3 @min-[29rem]/catalogo:grid-cols-2 @min-[52rem]/catalogo:grid-cols-4">
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
            <h2 id="listado-laboratorios" class="text-sm font-bold text-gray-800">{{ $tipo === 'fijo' ? 'Catálogo de laboratorios' : 'Catálogo de laboratorios móviles' }}</h2>
            <p class="text-xs text-gray-500">{{ $laboratorios->total() > 0 ? 'Mostrando '.$laboratorios->firstItem().'–'.$laboratorios->lastItem().' de '.$laboratorios->total() : '0 registros' }}</p>
        </div>
        @if ($laboratorios->isEmpty())
            <div class="rounded-lg border border-gray-200 bg-white px-4 py-10 text-center shadow-sm">
                <h3 class="text-sm font-semibold text-gray-700">No hay laboratorios que coincidan con esta consulta</h3>
                <p class="mt-2 text-sm text-gray-500">Puedes registrar un laboratorio o ajustar los filtros de búsqueda.</p>
            </div>
        @else
            <div class="grid min-w-0 grid-cols-1 gap-3 @min-[42rem]/catalogo:grid-cols-2 @min-[57rem]/catalogo:hidden">
                @foreach ($laboratorios as $laboratorio)
                    <article aria-label="Laboratorio {{ $laboratorio->codigo ?: $laboratorio->nombre }}" class="flex min-w-0 flex-col gap-3 rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                        <div>
                            <h3 class="break-words text-sm font-bold text-gray-900">{{ $laboratorio->codigo ?: $laboratorio->nombre }}</h3>
                            @if (filled($laboratorio->codigo) && $laboratorio->nombre !== $laboratorio->codigo)
                                <p class="mt-1 break-words text-sm text-gray-700">{{ $laboratorio->nombre }}</p>
                            @endif
                        </div>
                        <dl class="space-y-3 text-sm">
                            <div><dt class="text-xs font-semibold text-gray-500">Sede</dt><dd class="mt-1 break-words">{{ $tipo === 'fijo' ? ($laboratorio->sala?->sede?->nombre ?? 'Sin sede registrada') : ($laboratorio->sede?->nombre ?? 'Sin sede registrada') }}</dd></div>
                            @if ($tipo === 'fijo')<div><dt class="text-xs font-semibold text-gray-500">Sala física</dt><dd class="mt-1 break-words">{{ $laboratorio->sala?->codigo ?? 'Sin sala registrada' }}</dd></div>@endif
                            <div><dt class="text-xs font-semibold text-gray-500">Capacidad</dt><dd class="mt-1">{{ $laboratorio->capacidad > 0 ? $laboratorio->capacidad.($laboratorio->capacidad == 1 ? ' persona' : ' personas') : 'Sin informar' }}</dd></div>
                        </dl>
                        <div>@include('laboratorios.estado')</div>
                        @include('laboratorios.acciones')
                    </article>
                @endforeach
            </div>
            <div class="hidden min-w-0 overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm @min-[57rem]/catalogo:block">
                <div class="max-w-full overflow-x-auto" role="region" aria-label="Listado de laboratorios" tabindex="0">
                    <table class="w-full min-w-[900px] table-fixed text-left text-sm">
                        <colgroup><col><col class="w-[22%]"><col class="w-[16%]"><col class="w-[17%]"><col class="w-56"></colgroup>
                        <thead class="bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-500"><tr><th scope="col" class="px-4 py-3">Laboratorio</th><th scope="col" class="px-4 py-3">{{ $tipo === 'fijo' ? 'Sede y sala física' : 'Sede de origen' }}</th><th scope="col" class="px-4 py-3">Capacidad</th><th scope="col" class="px-4 py-3">Estado y uso</th><th scope="col" class="px-4 py-3 text-right">Acciones</th></tr></thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($laboratorios as $laboratorio)
                                <tr class="align-middle hover:bg-gray-50">
                                    <th scope="row" class="px-4 py-3 font-normal">
                                        <p class="break-words font-bold text-gray-900">{{ $laboratorio->codigo ?: $laboratorio->nombre }}</p>
                                        @if (filled($laboratorio->codigo) && $laboratorio->nombre !== $laboratorio->codigo)
                                            <p class="mt-1 break-words text-xs text-gray-600">{{ $laboratorio->nombre }}</p>
                                        @endif
                                    </th>
                                    <td class="px-4 py-3"><p class="break-words text-gray-800">{{ $tipo === 'fijo' ? ($laboratorio->sala?->sede?->nombre ?? 'Sin sede registrada') : ($laboratorio->sede?->nombre ?? 'Sin sede registrada') }}</p>@if ($tipo === 'fijo')<p class="mt-1 break-words text-xs text-gray-500">Sala {{ $laboratorio->sala?->codigo ?? 'no registrada' }}</p>@endif</td>
                                    <td class="px-4 py-3 font-semibold text-gray-800">{{ $laboratorio->capacidad > 0 ? $laboratorio->capacidad.($laboratorio->capacidad == 1 ? ' persona' : ' personas') : 'Sin informar' }}</td>
                                    <td class="px-4 py-3">@include('laboratorios.estado')</td>
                                    <td class="px-4 py-3">@include('laboratorios.acciones', ['enTabla' => true])</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
        <x-paginacion-catalogo :paginas="$laboratorios" etiqueta="Paginación de laboratorios" />
    </section>
</div>
@endsection

@extends('layouts.app')

@section('content')

<x-encabezado-pagina titulo="Salas y recursos">
    Administra el catálogo de salas y encuentra espacios por sede, tipo o capacidad.
    <x-slot:acciones>
        <div class="flex w-full shrink-0 flex-col gap-2 sm:w-auto sm:flex-row">
            <a href="{{ route('salas.horario') }}" class="boton boton-secundario">Consultar horarios</a>
            <a href="{{ route('salas.create') }}" class="boton boton-primario">Crear sala</a>
        </div>
    </x-slot:acciones>
</x-encabezado-pagina>

<div class="min-w-0 w-full space-y-3">
    @if (session('success'))
        <div role="status" class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('success') }}</div>
    @endif

    @if ($errors->any())
        <div role="alert" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
            <p class="font-semibold">Revisa los filtros de búsqueda.</p>
            <ul class="mt-1 list-inside list-disc space-y-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <section aria-label="Resumen del catálogo de salas">
        <dl class="grid grid-cols-1 gap-2 sm:grid-cols-3">
            <div class="flex min-w-0 items-center justify-between gap-3 rounded-lg border border-gray-200 bg-white px-3 py-3 shadow-sm sm:block">
                <dt class="text-sm font-semibold leading-5 text-gray-700">Salas registradas</dt>
                <dd class="text-xl font-bold text-gray-800 sm:mt-1">{{ $resumen['total'] }}</dd>
            </div>
            <div class="flex min-w-0 items-center justify-between gap-3 rounded-lg border border-gray-200 bg-white px-3 py-3 shadow-sm sm:block">
                <dt class="text-sm font-semibold leading-5 text-gray-700">Registros activos</dt>
                <dd class="text-xl font-bold text-gray-800 sm:mt-1">{{ $resumen['activas'] }}</dd>
            </div>
            <div class="flex min-w-0 items-center justify-between gap-3 rounded-lg border border-gray-200 bg-white px-3 py-3 shadow-sm sm:block">
                <dt class="text-sm font-semibold leading-5 text-gray-700">Sedes con salas</dt>
                <dd class="text-xl font-bold text-gray-800 sm:mt-1">{{ $resumen['sedes'] }}</dd>
            </div>
        </dl>
    </section>

    @php
        $hayFiltrosAvanzados = filled($filtros['sede_id']) || filled($filtros['tipo_sala_id'])
            || filled($filtros['capacidad_minima']) || $filtros['estado'] !== 'activas'
            || filled($filtros['uso']);
    @endphp
    <form method="GET" action="{{ route('salas.index') }}" aria-label="Buscar y filtrar salas" class="grid min-w-0 grid-cols-1 gap-3 rounded-lg border border-gray-200 bg-white p-4 shadow-sm sm:grid-cols-[minmax(0,1fr)_auto] sm:items-end lg:grid-cols-[minmax(0,1fr)_auto_auto]">
        <div class="min-w-0">
            <label for="busqueda-salas" class="mb-1.5 block text-sm font-semibold leading-5 text-gray-700">Buscar sala</label>
            <input id="busqueda-salas" type="search" name="q" maxlength="100" value="{{ $filtros['q'] ?? '' }}" placeholder="Código, nombre o edificio" class="h-11 w-full min-w-0 rounded-lg border border-gray-300 bg-white px-3 text-base text-gray-800 placeholder:text-gray-400 focus:border-institucional focus:ring-1 focus:ring-institucional sm:text-sm">
        </div>
        <div class="flex gap-2 sm:col-start-2 sm:row-start-1">
            <button type="submit" class="boton boton-primario flex-1 sm:flex-none">Buscar</button>
            <a href="{{ route('salas.index') }}" class="boton boton-secundario flex-1 sm:flex-none">Limpiar</a>
        </div>
        <details data-filtros-salas class="contents" @if ($hayFiltrosAvanzados || $errors->hasAny(['sede_id', 'tipo_sala_id', 'capacidad_minima', 'estado', 'uso'])) open @endif>
            <summary class="boton boton-secundario h-11 whitespace-nowrap sm:col-span-2 lg:col-span-1 lg:col-start-3 lg:row-start-1" title="Sede, tipo, capacidad, estado y uso">
                Filtrar salas
                @if ($hayFiltrosAvanzados)<span class="shrink-0 rounded-full bg-red-50 px-2 py-0.5 text-[11px] font-semibold text-institucional">Filtros aplicados</span>@endif
            </summary>
        <div data-panel-filtros-salas class="grid min-w-0 grid-cols-1 gap-3 sm:col-span-2 sm:grid-cols-2 lg:col-span-3 {{ $tiposSala->isEmpty() ? 'xl:grid-cols-4' : 'xl:grid-cols-5' }}">
            <div class="min-w-0">
                <label for="sede-salas" class="mb-1 block text-xs font-semibold text-gray-700">Sede</label>
                <select id="sede-salas" name="sede_id" class="h-11 w-full min-w-0 rounded-lg border border-gray-300 bg-white px-2 text-base text-gray-800 focus:border-institucional focus:ring-1 focus:ring-institucional sm:text-sm">
                    <option value="">Todas las sedes</option>
                    @foreach ($sedes as $sede)
                        <option value="{{ $sede->id }}" @selected((string) ($filtros['sede_id'] ?? '') === (string) $sede->id)>{{ $sede->nombre }}{{ $sede->estado ? '' : ' (inactiva)' }}</option>
                    @endforeach
                </select>
            </div>
            @if ($tiposSala->isNotEmpty())
<div class="min-w-0">
                <label for="tipo-salas" class="mb-1 block text-xs font-semibold text-gray-700">Tipo de sala</label>
                <select id="tipo-salas" name="tipo_sala_id" class="h-11 w-full min-w-0 rounded-lg border border-gray-300 bg-white px-2 text-base text-gray-800 focus:border-institucional focus:ring-1 focus:ring-institucional sm:text-sm">
                    <option value="">Todos los tipos</option>
                    @foreach ($tiposSala as $tipo)
                        <option value="{{ $tipo->id }}" @selected((string) ($filtros['tipo_sala_id'] ?? '') === (string) $tipo->id)>{{ $tipo->nombre }}{{ $tipo->estado ? '' : ' (inactivo)' }}</option>
                    @endforeach
                </select>
            </div>
@endif
            <div class="min-w-0">
                <label for="capacidad-salas" class="mb-1 block text-xs font-semibold text-gray-700">Capacidad mínima</label>
                <input id="capacidad-salas" type="number" name="capacidad_minima" min="0" max="65535" step="1" inputmode="numeric" value="{{ $filtros['capacidad_minima'] ?? '' }}" placeholder="Sin mínimo" class="h-11 w-full min-w-0 rounded-lg border border-gray-300 bg-white px-3 text-base text-gray-800 placeholder:text-gray-400 focus:border-institucional focus:ring-1 focus:ring-institucional sm:text-sm">
            </div>
            <div class="min-w-0">
                <label for="estado-salas" class="mb-1 block text-xs font-semibold text-gray-700">Estado del registro</label>
                <select id="estado-salas" name="estado" class="h-11 w-full min-w-0 rounded-lg border border-gray-300 bg-white px-2 text-base text-gray-800 focus:border-institucional focus:ring-1 focus:ring-institucional sm:text-sm">
                    <option value="activas" @selected(($filtros['estado'] ?? 'activas') === 'activas')>Activos</option>
                    <option value="inactivas" @selected(($filtros['estado'] ?? 'activas') === 'inactivas')>Inactivos</option>
                    <option value="todas" @selected(($filtros['estado'] ?? 'activas') === 'todas')>Todos</option>
                </select>
            </div>
            <div class="min-w-0">
                <label for="uso-salas" class="mb-1 block text-xs font-semibold text-gray-700">Habilitación de uso</label>
                <select id="uso-salas" name="uso" class="h-11 w-full min-w-0 rounded-lg border border-gray-300 bg-white px-2 text-base text-gray-800 focus:border-institucional focus:ring-1 focus:ring-institucional sm:text-sm">
                    <option value="">Todas</option>
                    <option value="habilitadas" @selected(($filtros['uso'] ?? '') === 'habilitadas')>Uso habilitado</option>
                    <option value="deshabilitadas" @selected(($filtros['uso'] ?? '') === 'deshabilitadas')>Uso deshabilitado</option>
                </select>
            </div>
        </div>
        </details>
    </form>

    <section aria-labelledby="listado-salas" class="min-w-0">
        <div class="mb-2 flex flex-wrap items-baseline justify-between gap-2 px-1">
            <h2 id="listado-salas" class="text-sm font-bold text-gray-800">{{ $hayFiltros ? 'Resultados de búsqueda' : 'Salas registradas' }}</h2>
            <p class="text-xs text-gray-500">
                @if ($salas->total() > 0)
                    Mostrando {{ $salas->firstItem() }}–{{ $salas->lastItem() }} de {{ $salas->total() }} {{ $salas->total() === 1 ? 'sala' : 'salas' }}
                @else
                    0 salas
                @endif
            </p>
        </div>

        @if ($salas->isEmpty())
            <div class="rounded-lg border border-gray-200 bg-white px-4 py-10 text-center shadow-sm">
                <h3 class="text-sm font-semibold text-gray-700">{{ (int) $resumen['total'] === 0 ? 'No hay salas registradas' : 'No se encontraron salas' }}</h3>
                <p class="mx-auto mt-2 max-w-md text-sm text-gray-500">{{ (int) $resumen['total'] === 0 ? 'Las salas que se registren aparecerán en este catálogo.' : 'Prueba con otro código, nombre o combinación de filtros.' }}</p>
                @if ((int) $resumen['total'] > 0)
                    <a href="{{ route('salas.index', ['estado' => 'todas']) }}" class="boton boton-secundario mt-4">Ver todas las salas</a>
                @endif
            </div>
        @else
            <div class="grid min-w-0 grid-cols-1 gap-3 md:grid-cols-2 xl:hidden">
                @foreach ($salas as $sala)
                    <article aria-label="Sala {{ $sala->codigo }}" class="min-w-0 rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                        <div class="flex min-w-0 items-start justify-between gap-3">
                            <div class="min-w-0 flex-1">
                                <h3 class="break-words text-sm font-bold text-gray-900">{{ $sala->codigo }}</h3>@if ($sala->laboratorios_count)<span class="mt-1 inline-flex rounded-full bg-sky-50 px-2 py-0.5 text-[10px] font-semibold text-sky-800">Laboratorio</span>@endif
                                <p class="mt-1 break-words text-sm text-gray-600">{{ $sala->nombre ?: 'Sin nombre registrado' }}</p>
                            </div>
                            <span @class(['shrink-0 rounded-full px-2 py-1 text-[10px] font-semibold', 'bg-green-50 text-green-800' => $sala->estado, 'bg-gray-100 text-gray-600' => ! $sala->estado])>{{ $sala->estado ? 'Registro activo' : 'Registro inactivo' }}</span>
                        </div>
                        <dl class="mt-4 space-y-3 text-sm">
                            <div>
                                <dt class="text-[11px] font-semibold text-gray-500">Sede</dt>
                                <dd class="mt-0.5 break-words text-gray-800">{{ $sala->sede?->nombre ?? 'Sin sede registrada' }} @if ($sala->sede && ! $sala->sede->estado)<span class="text-xs text-gray-500">(inactiva)</span>@endif</dd>
                            </div>
                            @if ($sala->tipoSala && !$sala->tipoSala->esDeImportacion())
<div>
                                <dt class="text-[11px] font-semibold text-gray-500">Tipo de sala</dt>
                                <dd class="mt-0.5 break-words text-gray-800">{{ $sala->tipoSala?->nombre ?? 'Sin tipo registrado' }} @if ($sala->tipoSala && ! $sala->tipoSala->estado)<span class="text-xs text-gray-500">(inactivo)</span>@endif</dd>
                            </div>
@endif
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <dt class="text-[11px] font-semibold text-gray-500">Capacidad registrada</dt>
                                    <dd class="mt-0.5 font-semibold text-gray-800">{{ $sala->capacidad > 0 ? $sala->capacidad.($sala->capacidad == 1 ? ' persona' : ' personas') : 'Sin capacidad informada' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-[11px] font-semibold text-gray-500">Habilitación de uso</dt>
                                    <dd class="mt-0.5 text-gray-800">{{ $sala->disponible ? 'Uso habilitado' : 'Uso deshabilitado' }}</dd>
                                </div>
                            </div>
                            <div>
                                <dt class="text-[11px] font-semibold text-gray-500">Ubicación</dt>
                                <dd class="mt-0.5 break-words text-gray-800">{{ $sala->edificio ?: 'Sin edificio registrado' }}{{ filled($sala->piso) ? ' · Piso '.$sala->piso : '' }}</dd>
                            </div>
                        </dl>
                        <a href="{{ route('salas.horario', ['espacio' => 'sala:'.$sala->id]) }}#calendario-espacio" aria-label="Ver horario de la sala {{ $sala->codigo }}" class="boton boton-secundario mt-3 w-full">Ver horario</a>
                        <a href="{{ route('salas.edit', $sala) }}" aria-label="Editar sala {{ $sala->codigo }}" class="boton boton-secundario mt-2 w-full">Editar</a>
                    </article>
                @endforeach
            </div>

            <div class="hidden min-w-0 overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm xl:block">
                <div class="max-w-full overflow-x-auto focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-institucional" role="region" aria-label="Listado de salas" tabindex="0">
                    <table class="w-full min-w-[1100px] table-fixed border-collapse text-left text-sm">
                        <colgroup><col class="w-[12%]"><col class="w-[18%]"><col class="w-[14%]"><col class="w-[18%]"><col class="w-[16%]"><col class="w-[22%]"></colgroup>
                        <thead class="bg-gray-50 text-[10px] font-semibold uppercase tracking-wide text-gray-500">
                            <tr>
                                <th scope="col" class="px-4 py-3">Sala</th>
                                <th scope="col" class="px-4 py-3 text-center">Sede</th>
                                <th scope="col" class="px-4 py-3">Capacidad registrada</th>
                                <th scope="col" class="px-4 py-3">Ubicación</th>
                                <th scope="col" class="px-4 py-3">Estado y uso</th>
                                <th scope="col" class="px-4 py-3 text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($salas as $sala)
                                <tr class="align-top hover:bg-gray-50">
                                    <th scope="row" class="px-4 py-4 font-normal">
                                        <div class="break-words font-bold text-gray-900">{{ $sala->codigo }}</div>@if ($sala->laboratorios_count)<span class="mt-1 inline-flex rounded-full bg-sky-50 px-2 py-0.5 text-[10px] font-semibold text-sky-800">Laboratorio</span>@endif
                                        <p class="mt-1 break-words text-xs text-gray-600">{{ $sala->nombre ?: 'Sin nombre registrado' }}</p>
                                    </th>
                                    <td class="px-4 py-4 text-center align-middle">
                                        <p class="break-words text-xs font-medium text-gray-800">{{ $sala->sede?->nombre ?? 'Sin sede registrada' }} @if ($sala->sede && ! $sala->sede->estado)<span class="font-normal text-gray-500">(inactiva)</span>@endif</p>
                                    </td>
                                    <td class="px-4 py-4 text-xs font-semibold text-gray-800">{{ $sala->capacidad > 0 ? $sala->capacidad.($sala->capacidad == 1 ? ' persona' : ' personas') : 'Sin capacidad informada' }}</td>
                                    <td class="px-4 py-4 text-xs text-gray-600">
                                        <p class="break-words">{{ $sala->edificio ?: 'Sin edificio registrado' }}</p>
                                        @if (filled($sala->piso))<p class="mt-1 break-words text-gray-500">Piso {{ $sala->piso }}</p>@endif
                                    </td>
                                    <td class="px-4 py-4">
                                        <span @class(['inline-block rounded-full px-2 py-1 text-[10px] font-semibold', 'bg-green-50 text-green-800' => $sala->estado, 'bg-gray-100 text-gray-600' => ! $sala->estado])>{{ $sala->estado ? 'Registro activo' : 'Registro inactivo' }}</span>
                                        <p class="mt-2 text-xs text-gray-600">{{ $sala->disponible ? 'Uso habilitado' : 'Uso deshabilitado' }}</p>
                                    </td>
                                    <td class="px-4 py-4">
                                        <div class="flex min-w-0 flex-nowrap items-center justify-end gap-2 whitespace-nowrap [&>a]:shrink-0">
                                        <a href="{{ route('salas.horario', ['espacio' => 'sala:'.$sala->id]) }}#calendario-espacio" aria-label="Ver horario de la sala {{ $sala->codigo }}" class="boton boton-secundario">Ver horario</a>
                                        <a href="{{ route('salas.edit', $sala) }}" aria-label="Editar sala {{ $sala->codigo }}" class="boton boton-secundario">Editar</a>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        @if ($salas->hasPages())
            <nav aria-label="Paginación de salas" class="mt-3 grid grid-cols-2 items-center gap-2 rounded-lg border border-gray-200 bg-white px-3 py-3 shadow-sm sm:flex sm:justify-between">
                @if ($salas->onFirstPage())
                    <span aria-disabled="true" class="boton boton-secundario">Anterior</span>
                @else
                    <a href="{{ $salas->previousPageUrl() }}" rel="prev" class="boton boton-secundario">Anterior</a>
                @endif
                <p class="order-first col-span-2 text-center text-xs text-gray-600 sm:order-none">Página <span class="font-semibold">{{ $salas->currentPage() }}</span> de {{ $salas->lastPage() }}</p>
                @if ($salas->hasMorePages())
                    <a href="{{ $salas->nextPageUrl() }}" rel="next" class="boton boton-secundario">Siguiente</a>
                @else
                    <span aria-disabled="true" class="boton boton-secundario">Siguiente</span>
                @endif
            </nav>
        @endif
    </section>
</div>

@endsection

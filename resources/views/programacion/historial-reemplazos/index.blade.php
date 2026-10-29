@extends('layouts.app')
@section('content')
@php
    $campo = 'h-11 w-full min-w-0 rounded-lg border border-gray-300 bg-white px-3 text-base text-gray-800 focus:border-institucional focus:ring-1 focus:ring-institucional sm:text-sm';
    $hayFiltros = collect($filtros)->except('q')->contains(fn ($valor) => filled($valor));
@endphp
<div class="w-full min-w-0 space-y-3 px-2 py-2">
    <header class="flex min-w-0 flex-col gap-3 rounded-lg border border-gray-200 bg-white p-4 shadow-sm sm:flex-row sm:items-center sm:justify-between">
        <div class="min-w-0"><h1 class="text-base font-bold text-gray-800">Historial de reemplazos</h1><p class="mt-1 text-sm text-gray-600">Consulta los reemplazos aplicados, sus docentes, módulos y el usuario que realizó la operación.</p></div>
        <a href="{{ route('programacion.intercambio') }}" class="inline-flex min-h-11 shrink-0 items-center justify-center rounded-lg border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Consultar reemplazo</a>
    </header>
    @if ($errors->any())
        <div role="alert" class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800"><p class="font-semibold">Revisa los filtros de búsqueda.</p><ul class="mt-1 list-inside list-disc">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif
    <form method="GET" action="{{ route('programacion.intercambio.historial') }}" aria-label="Buscar y filtrar reemplazos" class="grid min-w-0 grid-cols-1 gap-3 rounded-lg border border-gray-200 bg-white p-4 shadow-sm sm:grid-cols-[minmax(0,1fr)_auto] sm:items-end lg:grid-cols-[minmax(0,1fr)_auto_minmax(10rem,13rem)]">
        <div class="min-w-0"><label for="busqueda-historial" class="mb-1.5 block text-sm font-semibold text-gray-700">Buscar reemplazo</label><input id="busqueda-historial" name="q" type="search" maxlength="80" value="{{ $filtros['q'] }}" placeholder="Docente, RUT, ramo o usuario" class="{{ $campo }}"></div>
        <div class="flex gap-2 sm:col-start-2 sm:row-start-1">
            <button type="submit" class="inline-flex min-h-11 flex-1 items-center justify-center rounded-lg bg-institucional px-5 py-2 text-sm font-semibold text-white hover:bg-institucional-oscuro sm:flex-none">Buscar</button>
            <a href="{{ route('programacion.intercambio.historial') }}" class="inline-flex min-h-11 flex-1 items-center justify-center rounded-lg border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 sm:flex-none">Limpiar</a>
        </div>
        <details data-filtros-salas class="contents" @if ($hayFiltros) open @endif>
            <summary class="min-h-11 cursor-pointer rounded-lg border border-gray-300 bg-gray-50 px-3 py-3 text-sm font-semibold text-gray-700 marker:text-institucional sm:col-span-2 lg:col-span-1 lg:col-start-3 lg:row-start-1">Filtrar reemplazos</summary>
            <div data-panel-filtros-salas class="grid min-w-0 grid-cols-1 gap-3 sm:col-span-2 sm:grid-cols-2 lg:col-span-3 xl:grid-cols-3">
                <div><label for="periodo-historial" class="mb-1.5 block text-sm font-semibold text-gray-700">Período académico</label>
                    <select id="periodo-historial" name="periodo_id" data-select-buscable class="{{ $campo }}"><option value="">Todos los períodos</option>@foreach ($periodos as $periodo)<option value="{{ $periodo->id }}" @selected((string) $filtros['periodo_id'] === (string) $periodo->id)>{{ $periodo->nombre }}</option>@endforeach</select>
                </div>
                <div><label for="version-historial" class="mb-1.5 block text-sm font-semibold text-gray-700">Versión</label>
                    <select id="version-historial" name="version_id" data-select-buscable class="{{ $campo }}"><option value="">Todas las versiones</option>@foreach ($versiones as $version)<option value="{{ $version->id }}" @selected((string) $filtros['version_id'] === (string) $version->id)>{{ $version->periodoAcademico?->nombre }} · {{ $version->nombre }} · v{{ $version->version }}</option>@endforeach</select>
                </div>
                <div><label for="usuario-historial" class="mb-1.5 block text-sm font-semibold text-gray-700">Realizado por</label>
                    <select id="usuario-historial" name="usuario_id" data-select-buscable class="{{ $campo }}"><option value="">Todos los usuarios</option>@foreach ($usuarios as $usuario)<option value="{{ $usuario->id }}" @selected((string) $filtros['usuario_id'] === (string) $usuario->id)>{{ $usuario->name }}</option>@endforeach</select>
                </div>
                <div><label for="desde-historial" class="mb-1.5 block text-sm font-semibold text-gray-700">Desde</label><input id="desde-historial" name="desde" type="date" value="{{ $filtros['desde'] }}" class="{{ $campo }}"></div>
                <div><label for="hasta-historial" class="mb-1.5 block text-sm font-semibold text-gray-700">Hasta</label><input id="hasta-historial" name="hasta" type="date" value="{{ $filtros['hasta'] }}" class="{{ $campo }}"></div>
            </div>
        </details>
    </form>
    <section aria-labelledby="registros-reemplazos" class="min-w-0">
        <div class="mb-2 flex flex-wrap items-baseline justify-between gap-2 px-1"><h2 id="registros-reemplazos" class="text-sm font-bold text-gray-800">Reemplazos aplicados</h2><p class="text-xs text-gray-500">{{ $registros->total() > 0 ? 'Mostrando '.$registros->firstItem().'–'.$registros->lastItem().' de '.$registros->total() : '0 reemplazos' }} · {{ config('app.timezone') === 'America/Santiago' ? 'Hora de Santiago' : config('app.timezone') }}</p></div>
        @if ($registros->isEmpty())
            <div class="rounded-lg border border-gray-200 bg-white px-4 py-10 text-center shadow-sm"><h3 class="text-sm font-semibold text-gray-700">No hay reemplazos para esta consulta</h3><p class="mt-2 text-sm text-gray-500">Los reemplazos confirmados aparecerán aquí. Puedes ajustar los filtros para consultar otros períodos.</p></div>
        @else
            <div class="grid min-w-0 grid-cols-1 gap-3 md:grid-cols-2 xl:hidden">
                @foreach ($registros as $registro)
                    <article aria-label="Reemplazo {{ $registro['id'] }}" class="min-w-0 space-y-3 rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                        <div><p class="text-xs text-gray-500">#{{ $registro['id'] }} · {{ $registro['fecha'] ?: 'Sin fecha registrada' }}</p><h3 class="mt-1 break-words text-sm font-bold text-gray-900">{{ $registro['asignatura_codigo'] }} · {{ $registro['asignatura_nombre'] }}</h3><p class="mt-1 text-xs text-gray-600">{{ $registro['periodo'] }} · {{ $registro['version'] }} @if ($registro['version_numero'])v{{ $registro['version_numero'] }}@endif</p></div>
                        @include('programacion.historial-reemplazos.docentes')
                        <div class="flex flex-wrap items-center gap-2 text-xs"><span class="rounded-full bg-gray-100 px-2 py-1 font-semibold">{{ $registro['cantidad_modulos'] !== null ? $registro['cantidad_modulos'].' módulos' : 'Cantidad sin información' }}</span>@if (! $registro['detalle_registrado'])<span class="text-gray-500">Detalle parcial</span>@endif</div>
                        <p class="break-words text-xs text-gray-600">Realizado por: {{ $registro['responsable'] }}</p>
                        <a href="{{ route('programacion.intercambio.historial.detalle', ['registro' => $registro['id']]) }}" class="inline-flex min-h-11 w-full items-center justify-center rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm font-semibold text-institucional hover:bg-red-100">Ver detalle</a>
                    </article>
                @endforeach
            </div>
            <div class="hidden min-w-0 overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm xl:block">
                <div class="max-w-full overflow-x-auto" role="region" aria-label="Historial de reemplazos" tabindex="0">
                    <table class="w-full min-w-[900px] table-fixed text-left text-sm"><colgroup><col class="w-[20%]"><col class="w-[27%]"><col class="w-[29%]"><col class="w-[12%]"><col class="w-[12%]"></colgroup>
                        <thead class="bg-gray-50 text-[10px] font-semibold uppercase tracking-wide text-gray-500"><tr><th scope="col" class="px-4 py-3">Fecha y usuario</th><th scope="col" class="px-4 py-3">Clase y programación</th><th scope="col" class="px-4 py-3">Docentes</th><th scope="col" class="px-4 py-3">Módulos</th><th scope="col" class="px-4 py-3 text-right">Acciones</th></tr></thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($registros as $registro)
                                <tr class="align-top hover:bg-gray-50">
                                    <th scope="row" class="px-4 py-4 font-normal"><p class="text-xs font-semibold text-gray-800">{{ $registro['fecha'] ?: 'Sin fecha registrada' }}</p><p class="mt-1 break-words text-xs text-gray-600">{{ $registro['responsable'] }}</p><p class="mt-1 text-[11px] text-gray-500">Registro #{{ $registro['id'] }}</p></th>
                                    <td class="px-4 py-4"><p class="break-words text-xs font-bold text-gray-900">{{ $registro['asignatura_codigo'] }} · {{ $registro['asignatura_nombre'] }}</p><p class="mt-1 break-words text-xs text-gray-600">Sec. {{ $registro['seccion'] ?: 'sin dato' }} · {{ $registro['actividad'] ?: 'Actividad sin dato' }} · {{ $registro['grupo'] ?: 'Grupo sin dato' }}</p><p class="mt-2 break-words text-xs text-gray-500">{{ $registro['periodo'] }} · {{ $registro['version'] }} @if ($registro['version_numero'])v{{ $registro['version_numero'] }}@endif</p></td>
                                    <td class="px-4 py-4">@include('programacion.historial-reemplazos.docentes')</td>
                                    <td class="px-4 py-4"><p class="text-sm font-bold text-gray-800">{{ $registro['cantidad_modulos'] ?? 'Sin dato' }}</p>@if (! $registro['detalle_registrado'])<p class="mt-1 text-[11px] text-gray-500">Detalle parcial</p>@endif</td>
                                    <td class="px-4 py-4 text-right"><a href="{{ route('programacion.intercambio.historial.detalle', ['registro' => $registro['id']]) }}" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs font-semibold text-institucional hover:bg-red-100">Ver detalle</a></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
        @if ($registros->hasPages())
            <nav aria-label="Paginación de reemplazos" class="mt-3 flex flex-wrap items-center justify-between gap-2 rounded-lg border border-gray-200 bg-white px-3 py-3 text-sm shadow-sm">
                @if ($registros->onFirstPage())<span aria-disabled="true" class="px-3 py-2 text-gray-400">Anterior</span>@else<a href="{{ $registros->previousPageUrl() }}" rel="prev" class="inline-flex min-h-11 items-center rounded-lg border border-gray-300 px-3 py-2 text-gray-700 hover:bg-gray-50">Anterior</a>@endif
                <span class="text-xs text-gray-600">Página {{ $registros->currentPage() }} de {{ $registros->lastPage() }}</span>
                @if ($registros->hasMorePages())<a href="{{ $registros->nextPageUrl() }}" rel="next" class="inline-flex min-h-11 items-center rounded-lg border border-gray-300 px-3 py-2 text-gray-700 hover:bg-gray-50">Siguiente</a>@else<span aria-disabled="true" class="px-3 py-2 text-gray-400">Siguiente</span>@endif
            </nav>
        @endif
    </section>
</div>
@endsection

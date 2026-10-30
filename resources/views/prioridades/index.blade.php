@extends('layouts.app')
@section('title', 'Prioridades de recursos | PROSEC')
@section('content')
<x-encabezado-pagina titulo="Prioridades de recursos">Organiza las preferencias de uso que pueden asociarse a las actividades.
    <x-slot:acciones><a class="boton boton-primario" href="{{ route('prioridades.create') }}">Crear prioridad</a></x-slot:acciones>
</x-encabezado-pagina>
<div class="min-w-0 space-y-4">
    @if (session('success'))<div role="status" class="rounded-lg border border-gray-200 bg-white p-5 text-sm shadow-sm">{{ session('success') }}</div>@endif
    @if ($errors->any())<div role="alert" class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800">{{ $errors->first() }}</div>@endif
    <form method="GET" action="{{ route('prioridades.index') }}" aria-label="Buscar prioridades" class="grid min-w-0 items-end gap-4 rounded-lg border border-gray-200 bg-white p-5 shadow-sm sm:grid-cols-2 sm:p-6 xl:grid-cols-[2fr_1fr_1fr_auto]">
        <div class="min-w-0"><label for="buscar-prioridad" class="mb-1.5 block text-sm font-semibold text-gray-700">Buscar prioridad</label><input id="buscar-prioridad" name="q" type="search" maxlength="100" value="{{ $q }}" placeholder="Nombre o descripción" class="h-11 w-full min-w-0 rounded-lg border border-gray-300 px-3 text-sm"></div>
        <div><label for="nivel-prioridad-filtro" class="mb-1.5 block text-sm font-semibold text-gray-700">Nivel</label><input id="nivel-prioridad-filtro" name="nivel" type="number" min="1" max="255" step="1" value="{{ $nivel }}" placeholder="Todos" class="h-11 w-full min-w-0 rounded-lg border border-gray-300 px-3 text-sm"></div>
        <div><label for="estado-prioridad-filtro" class="mb-1.5 block text-sm font-semibold text-gray-700">Estado del registro</label><select id="estado-prioridad-filtro" name="estado" data-select-buscable class="h-11 w-full min-w-0 rounded-lg border border-gray-300 bg-white px-3 text-sm">@foreach (['activas' => 'Activas', 'inactivas' => 'Inactivas', 'todas' => 'Todas'] as $clave => $etiqueta)<option value="{{ $clave }}" @selected($estado === $clave)>{{ $etiqueta }}</option>@endforeach</select></div>
        <div class="flex flex-wrap gap-2"><button class="boton boton-primario" type="submit">Buscar</button><a class="boton boton-secundario" href="{{ route('prioridades.index') }}">Limpiar</a></div>
    </form>
    <p class="text-sm leading-6 text-gray-500">El nivel 1 tiene mayor prioridad que el 2. Varias prioridades pueden compartir nivel. Se guardan como preferencias; su aplicación automática en el generador sigue pendiente.</p>
    <div class="flex flex-wrap items-center justify-between gap-2"><h2 class="text-base font-semibold text-gray-800">Prioridades registradas</h2><p class="text-sm text-gray-500">{{ $prioridades->total() }} {{ $prioridades->total() === 1 ? 'registro' : 'registros' }}</p></div>
    @forelse ($prioridades as $prioridad)
        <article class="flex min-w-0 flex-col justify-between gap-4 rounded-lg border border-gray-200 bg-white p-5 shadow-sm sm:flex-row sm:items-start sm:p-6">
            <div class="min-w-0 flex-1"><h3 class="break-words text-base font-semibold text-gray-800">{{ $prioridad->nombre }}</h3><p class="mt-1 text-sm text-gray-600">Nivel {{ $prioridad->nivel }} · {{ $prioridad->estado ? 'Activa' : 'Inactiva' }}</p>@if ($prioridad->descripcion)<p class="mt-2 break-words whitespace-pre-line text-sm text-gray-500">{{ $prioridad->descripcion }}</p>@endif<p class="mt-3 text-sm text-gray-500">{{ $prioridad->requerimientos_actividad_count }} {{ $prioridad->requerimientos_actividad_count === 1 ? 'requerimiento vinculado' : 'requerimientos vinculados' }}</p></div>
            <a class="boton boton-secundario" href="{{ route('prioridades.edit', $prioridad) }}" aria-label="Editar prioridad {{ $prioridad->nombre }}">Editar</a>
        </article>
    @empty
        <div class="rounded-lg border border-gray-200 bg-white p-8 text-center shadow-sm"><h3 class="text-base font-semibold text-gray-800">No hay prioridades para esta consulta</h3><p class="mt-2 text-sm text-gray-500">Crea una prioridad o ajusta los filtros de búsqueda.</p></div>
    @endforelse
    @if ($prioridades->hasPages())<nav aria-label="Paginación de prioridades" class="flex flex-wrap items-center justify-between gap-2">
        @if ($prioridades->onFirstPage())<span class="boton boton-secundario" aria-disabled="true">Anterior</span>@else<a class="boton boton-secundario" href="{{ $prioridades->previousPageUrl() }}" rel="prev">Anterior</a>@endif
        <span class="text-sm text-gray-500">Página {{ $prioridades->currentPage() }} de {{ $prioridades->lastPage() }}</span>
        @if ($prioridades->hasMorePages())<a class="boton boton-secundario" href="{{ $prioridades->nextPageUrl() }}" rel="next">Siguiente</a>@else<span class="boton boton-secundario" aria-disabled="true">Siguiente</span>@endif
    </nav>@endif
</div>
@endsection

@extends('layouts.app')
@section('title', 'Prioridades de recursos | '.$identidad['nombre'])
@section('content')
<x-encabezado-pagina titulo="Prioridades de recursos">Define qué cursos tienen preferencia sobre cada tipo de recurso, en todas sus secciones.
    <x-slot:acciones><a class="boton boton-primario" href="{{ route('prioridades.create') }}">Crear prioridad</a></x-slot:acciones>
</x-encabezado-pagina>
<div class="min-w-0 space-y-4">
    @if (session('success'))<div role="status" class="rounded-lg border border-gray-200 bg-white p-5 text-sm shadow-sm">{{ session('success') }}</div>@endif
    @if ($errors->any())<div role="alert" class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800">{{ $errors->first() }}</div>@endif
    <x-filtros-compactos action="{{ route('prioridades.index') }}" aria-label="Buscar prioridades">
        <div class="min-w-0"><label for="buscar-prioridad" class="mb-1.5 block text-sm font-semibold text-gray-700">Buscar prioridad</label><input id="buscar-prioridad" name="q" type="search" maxlength="100" value="{{ $q }}" placeholder="Nombre o descripción" class="h-11 w-full min-w-0 rounded-lg border border-gray-300 px-3 text-sm"></div>
        <div><label for="nivel-prioridad-filtro" class="mb-1.5 block text-sm font-semibold text-gray-700">Nivel</label><input id="nivel-prioridad-filtro" name="nivel" type="number" min="1" max="255" step="1" value="{{ $nivel }}" placeholder="Todos" class="h-11 w-full min-w-0 rounded-lg border border-gray-300 px-3 text-sm"></div>
        <div><label for="estado-prioridad-filtro" class="mb-1.5 block text-sm font-semibold text-gray-700">Estado del registro</label><select id="estado-prioridad-filtro" name="estado" data-select-buscable class="h-11 w-full min-w-0 rounded-lg border border-gray-300 bg-white px-3 text-sm">@foreach (['activas' => 'Activas', 'inactivas' => 'Inactivas', 'todas' => 'Todas'] as $clave => $etiqueta)<option value="{{ $clave }}" @selected($estado === $clave)>{{ $etiqueta }}</option>@endforeach</select></div>
        <x-slot:acciones>
            <a class="boton boton-secundario" href="{{ route('prioridades.index') }}">Limpiar</a>
            <button class="boton boton-primario" type="submit">Buscar</button>
        </x-slot:acciones>
    </x-filtros-compactos>
    <p class="text-sm leading-6 text-gray-500">El nivel 1 tiene mayor prioridad que el 2. La revisión de cruces indica qué curso tiene preferencia; los horarios se conservan hasta que se ajuste la asignación.</p>
    <div class="flex flex-wrap items-center justify-between gap-2"><h2 class="text-base font-semibold text-gray-800">Prioridades registradas</h2><p class="text-sm text-gray-500">{{ $prioridades->total() }} {{ $prioridades->total() === 1 ? 'registro' : 'registros' }}</p></div>
    @forelse ($prioridades as $prioridad)
        <article class="flex min-w-0 flex-col justify-between gap-4 rounded-lg border border-gray-200 bg-white p-5 shadow-sm sm:flex-row sm:items-start sm:p-6">
            <div class="min-w-0 flex-1"><h3 class="break-words text-base font-semibold text-gray-800">{{ $prioridad->asignatura ? $prioridad->asignatura->codigo.' · '.$prioridad->asignatura->nombre : $prioridad->nombre }}</h3>@if ($prioridad->asignatura)<p class="mt-1 text-sm text-gray-600">{{ $prioridad->recurso_descripcion }} · Todas las secciones</p>@endif<p class="mt-1 text-sm text-gray-600">Nivel {{ $prioridad->nivel }} · {{ $prioridad->estado ? 'Activa' : 'Inactiva' }}</p>@if ($prioridad->descripcion)<p class="mt-2 break-words whitespace-pre-line text-sm text-gray-500">{{ $prioridad->descripcion }}</p>@endif<p class="mt-3 text-sm text-gray-500">{{ $prioridad->requerimientos_actividad_count }} {{ $prioridad->requerimientos_actividad_count === 1 ? 'requerimiento vinculado' : 'requerimientos vinculados' }}</p></div>
            <a class="boton boton-secundario" href="{{ route('prioridades.edit', $prioridad) }}" aria-label="Editar prioridad {{ $prioridad->nombre }}">Editar</a>
        </article>
    @empty
        <div class="rounded-lg border border-gray-200 bg-white p-8 text-center shadow-sm"><h3 class="text-base font-semibold text-gray-800">No hay prioridades para esta consulta</h3><p class="mt-2 text-sm text-gray-500">Crea una prioridad o ajusta los filtros de búsqueda.</p></div>
    @endforelse
    <x-paginacion-catalogo :paginas="$prioridades" etiqueta="Paginación de prioridades" />
</div>
@endsection

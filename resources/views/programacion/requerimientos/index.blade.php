@extends('layouts.app')
@section('title', 'Requerimientos de actividades | PROSEC')
@section('content')
<x-encabezado-pagina titulo="Requerimientos de actividades">
    Define los espacios y recursos que necesita cada actividad para revisar su programación.
    <x-slot:acciones><a href="{{ route('programacion.validacion', $contexto) }}" class="boton boton-secundario">Volver a revisión</a></x-slot:acciones>
</x-encabezado-pagina>
<div class="min-w-0 space-y-4">
    @if (session('success'))<div role="status" class="rounded-lg border border-gray-200 bg-white p-5 text-sm text-gray-800 shadow-sm">{{ session('success') }}</div>@endif
    @if ($errors->any())<div role="alert" class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800">{{ $errors->first() }}</div>@endif
    <form method="GET" action="{{ route('programacion.requerimientos.index') }}" aria-label="Buscar actividades" class="grid min-w-0 items-end gap-4 rounded-lg border border-gray-200 bg-white p-5 shadow-sm sm:p-6 lg:grid-cols-[1fr_2fr_auto]">
        <div><label for="periodo-requerimientos" class="mb-1.5 block text-sm font-semibold text-gray-700">Período académico</label>
            <select id="periodo-requerimientos" name="periodo_id" data-select-buscable class="h-11 w-full min-w-0 rounded-lg border border-gray-300 bg-white px-3 text-sm">
                @forelse ($periodos as $periodo)<option value="{{ $periodo->id }}" @selected((string) $periodoId === (string) $periodo->id)>{{ $periodo->nombre }}</option>@empty<option value="">Sin períodos disponibles</option>@endforelse
            </select>
        </div>
        <div><label for="buscar-actividad" class="mb-1.5 block text-sm font-semibold text-gray-700">Buscar actividad</label><input id="buscar-actividad" name="q" type="search" maxlength="120" value="{{ $q }}" placeholder="Código, asignatura, sección o actividad" class="h-11 w-full min-w-0 rounded-lg border border-gray-300 px-3 text-sm"></div>
        @if (isset($contexto['version_id']))<input type="hidden" name="version_id" id="version-regreso" value="{{ $contexto['version_id'] }}">@endif
        <div class="flex flex-wrap gap-2"><button type="submit" class="boton boton-primario">Buscar</button><a href="{{ route('programacion.requerimientos.index', $contexto) }}" class="boton boton-secundario">Limpiar</a></div>
    </form>
    <p class="text-sm leading-6 text-gray-500">Los requerimientos se comparten entre las versiones del período. Las actividades, secciones o períodos inactivos se muestran solo para consulta.</p>
    @forelse ($actividades as $actividad)
        @php $editable = $actividad->estado && $actividad->seccion->estado_activo && $actividad->seccion->periodoAcademico->estado; @endphp
        <article class="min-w-0 rounded-lg border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
            <div class="flex min-w-0 flex-col items-start justify-between gap-4 sm:flex-row">
                <div class="min-w-0 flex-1"><h2 class="break-words text-base font-semibold text-gray-800">{{ $actividad->seccion->asignatura?->codigo }} · {{ $actividad->seccion->asignatura?->nombre ?: 'Asignatura sin información' }}</h2><p class="mt-1 text-sm text-gray-600">Sección {{ $actividad->seccion->codigo }} · {{ $actividad->tipo }} · {{ $actividad->cantidad_horas }} módulos por grupo</p>@if (! $editable)<p class="mt-2 text-sm text-gray-500">Solo consulta: actividad, sección o período inactivo.</p>@endif</div>
                @if ($editable && auth()->user()->can('asignar horarios'))<a href="{{ route('programacion.requerimientos.create', ['actividad' => $actividad->id] + $contexto) }}" class="boton boton-primario">Agregar requerimiento</a>@endif
            </div>
            <ul class="mt-4 space-y-3" aria-label="Requerimientos de {{ $actividad->tipo }} · sección {{ $actividad->seccion->codigo }}">
                @forelse ($actividad->requerimientos as $requerimiento)
                    <li class="flex min-w-0 flex-col items-start justify-between gap-3 rounded-lg border border-gray-200 p-4 sm:flex-row">
                        <div class="min-w-0 flex-1"><h3 class="break-words text-sm font-semibold text-gray-800">{{ \App\Models\RequerimientoActividad::tipoNormalizado($requerimiento->tipo_requerimiento) ?? $requerimiento->tipo_requerimiento }}{{ $requerimiento->nombre_recurso ? ' · '.$requerimiento->nombre_recurso : '' }}</h3>
                            <p class="mt-1 text-sm text-gray-600">{{ $requerimiento->cantidad }} {{ $requerimiento->cantidad === 1 ? 'unidad' : 'unidades' }} · {{ $requerimiento->obligatorio ? 'Obligatorio' : 'Opcional' }} · {{ $requerimiento->estado ? 'Activo' : 'Inactivo' }}</p>
                            @if ($requerimiento->tipoSala && !$requerimiento->tipoSala->esDeImportacion())<p class="mt-1 text-sm text-gray-600">Tipo de sala: {{ $requerimiento->tipoSala->nombre }}{{ $requerimiento->tipoSala->estado ? '' : ' (inactivo)' }}</p>@endif
                            @if ($requerimiento->capacidad_minima !== null)<p class="mt-1 text-sm text-gray-600">Capacidad mínima por recurso: {{ $requerimiento->capacidad_minima }}</p>@endif
                            @if ($requerimiento->prioridadRecurso)<p class="mt-1 text-sm text-gray-600">Prioridad: {{ $requerimiento->prioridadRecurso->nombre }}{{ $requerimiento->prioridadRecurso->estado ? '' : ' (inactiva)' }} · Nivel {{ $requerimiento->prioridadRecurso->nivel }}</p>@endif
                            @if ($requerimiento->observaciones)<p class="mt-2 break-words whitespace-pre-line text-sm text-gray-500">{{ $requerimiento->observaciones }}</p>@endif
                        </div>
                        @if ($editable && auth()->user()->can('asignar horarios'))<a href="{{ route('programacion.requerimientos.edit', ['actividad' => $actividad->id, 'requerimiento' => $requerimiento->id] + $contexto) }}" class="boton boton-secundario" aria-label="Editar requerimiento {{ $requerimiento->id }}">Editar</a>@endif
                    </li>
                @empty<li class="rounded-lg bg-gray-50 p-4 text-sm text-gray-500">Esta actividad aún no tiene requerimientos configurados.</li>@endforelse
            </ul>
        </article>
    @empty
        <div class="rounded-lg border border-gray-200 bg-white p-8 text-center shadow-sm"><h2 class="text-base font-semibold text-gray-800">No hay actividades para esta consulta</h2><p class="mt-2 text-sm text-gray-500">Selecciona otro período o ajusta la búsqueda.</p></div>
    @endforelse
    @if ($actividades->hasPages())<nav aria-label="Paginación de actividades" class="flex flex-wrap items-center justify-between gap-2">
        @if ($actividades->onFirstPage())<span class="boton boton-secundario" aria-disabled="true">Anterior</span>@else<a class="boton boton-secundario" href="{{ $actividades->previousPageUrl() }}" rel="prev">Anterior</a>@endif
        <span class="text-sm text-gray-500">Página {{ $actividades->currentPage() }} de {{ $actividades->lastPage() }}</span>
        @if ($actividades->hasMorePages())<a class="boton boton-secundario" href="{{ $actividades->nextPageUrl() }}" rel="next">Siguiente</a>@else<span class="boton boton-secundario" aria-disabled="true">Siguiente</span>@endif
    </nav>@endif
</div>
<script>document.addEventListener('DOMContentLoaded', () => { document.getElementById('periodo-requerimientos').addEventListener('change', () => { document.getElementById('version-regreso')?.remove(); }); });</script>
@endsection

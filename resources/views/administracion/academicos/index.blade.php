@extends('layouts.app')
@section('title', $titulo.' | '.$identidad['nombre'])
@section('content')
<div class="space-y-6">
    <x-encabezado-pagina :titulo="$titulo">
        {{ $tipo === 'facultades' ? 'Una facultad o instituto reúne carreras. Las sedes indican dónde se imparten.' : ($tipo === 'planes' ? 'Organiza los planes y versiones de estudio de cada carrera.' : 'Organiza los semestres de cada carrera y sus secciones.') }}
        <x-slot:acciones><a class="boton boton-primario" href="{{ route('administracion.academicos.create', $tipo) }}">Crear {{ $singular }}</a></x-slot:acciones>
    </x-encabezado-pagina>
    @include('disponibilidad.avisos')
    <x-filtros-compactos :campos="$tipo === 'facultades' ? 3 : 4" aria-label="Filtros del catálogo académico">
        <div><label for="q" class="mb-2 block text-sm font-semibold">Buscar</label><input id="q" name="q" value="{{ $q }}" maxlength="100" placeholder="{{ $tipo === 'niveles' ? 'Nombre o número' : 'Nombre o código' }}" class="h-11 w-full rounded-lg border border-borde px-3"></div>
        <div><label for="universidad_id" class="mb-2 block text-sm font-semibold">Institución</label><select id="universidad_id" name="universidad_id" class="h-11 w-full rounded-lg border border-borde px-3"><option value="">Todas</option>@foreach ($universidades as $u)<option value="{{ $u->id }}" @selected(($filtros['universidad_id'] ?? null) == $u->id)>{{ $u->sigla ?: $u->nombre }}</option>@endforeach</select></div>
        @if ($tipo !== 'facultades')
        <div><label for="carrera_id" class="mb-2 block text-sm font-semibold">Carrera</label><select id="carrera_id" name="carrera_id" class="h-11 w-full rounded-lg border border-borde px-3"><option value="">Todas</option>@foreach ($carreras as $c)<option value="{{ $c->id }}" @selected(($filtros['carrera_id'] ?? null) == $c->id)>{{ $c->nombre }}{{ $c->codigo ? ' · '.$c->codigo : '' }} · {{ $c->universidad->sigla }}</option>@endforeach</select></div>
        @endif
        <div><label for="estado" class="mb-2 block text-sm font-semibold">Estado</label><select id="estado" name="estado" class="h-11 w-full rounded-lg border border-borde px-3">@foreach (['activos' => 'Activos', 'inactivos' => 'Inactivos', 'todos' => 'Todos'] as $valor => $etiqueta)<option value="{{ $valor }}" @selected($estado === $valor)>{{ $etiqueta }}</option>@endforeach</select></div>
        <x-slot:acciones>
            <a class="boton boton-secundario" href="{{ route('administracion.academicos.index', $tipo) }}">Limpiar filtros</a>
            <button type="submit" class="boton boton-primario">Filtrar</button>
        </x-slot:acciones>
    </x-filtros-compactos>
    <section class="overflow-hidden rounded-xl border border-borde bg-superficie shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-borde p-5"><h2 class="font-semibold">Registros del catálogo</h2><p class="text-sm text-texto-secundario">{{ $registros->total() }} {{ $registros->total() === 1 ? 'resultado' : 'resultados' }}</p></div>
        <div class="listado-adaptable overflow-x-auto" role="region" aria-label="{{ $titulo }}"><table role="table" class="w-full min-w-[760px] text-left text-sm">
            <thead role="rowgroup" class="bg-fondo text-xs uppercase text-texto-secundario"><tr role="row"><th role="columnheader" scope="col" class="p-4">{{ $tipo === 'facultades' ? 'Unidad académica' : ($tipo === 'planes' ? 'Plan de estudio' : 'Semestre') }}</th><th role="columnheader" scope="col" class="p-4">{{ $tipo === 'facultades' ? 'Institución' : 'Carrera e institución' }}</th><th role="columnheader" scope="col" class="p-4">{{ $tipo === 'facultades' ? 'Carreras y sedes' : 'Detalle' }}</th><th role="columnheader" scope="col" class="p-4">Estado</th><th role="columnheader" scope="col" class="p-4 text-right">Acciones</th></tr></thead>
            <tbody role="rowgroup" class="divide-y divide-borde">@forelse ($registros as $r)<tr role="row">
                <td role="cell" data-principal-listado class="max-w-xs p-4">
                    <p class="break-words font-semibold">{{ $r->nombre ?: ($r->codigo ?: 'Plan sin nombre') }}</p>
                    @if ($tipo === 'facultades')
                        <p class="mt-1 text-xs text-texto-secundario">{{ $r->codigo ? $r->codigo.' · ' : '' }}{{ $r->tipo }}</p>
                    @elseif ($tipo !== 'niveles' && $r->codigo)
                        <p class="mt-1 text-xs text-texto-secundario">{{ $r->codigo }}</p>
                    @endif
                </td>
                <td role="cell" class="max-w-xs p-4"><span class="etiqueta-listado" aria-hidden="true">{{ $tipo === 'facultades' ? 'Institución' : 'Carrera e institución' }}</span><div class="dato-listado">@if ($tipo === 'facultades'){{ $r->universidad->nombre }}@else<p>{{ $r->carrera->nombre }}</p><p class="mt-1 text-xs text-texto-secundario">{{ $r->carrera->universidad->sigla }}{{ $r->carrera->codigo ? ' · '.$r->carrera->codigo : '' }}</p>@endif</div></td>
                <td role="cell" class="max-w-sm p-4">
                    <span class="etiqueta-listado" aria-hidden="true">{{ $tipo === 'facultades' ? 'Carreras y sedes' : 'Detalle' }}</span>
                    <div class="dato-listado">
                        @if ($tipo === 'facultades')
                            @php
                                $sedesUnidad = $r->carreras
                                    ->flatMap(fn ($carrera) => $carrera->sedes->concat([$carrera->sede]))
                                    ->filter()->unique('id')->sortBy('nombre')->pluck('nombre');
                            @endphp
                            <p>{{ $r->carreras_count }} {{ (int) $r->carreras_count === 1 ? 'carrera vinculada' : 'carreras vinculadas' }}</p>
                            <p class="mt-1 text-xs leading-5 text-texto-secundario">{{ $sedesUnidad->isNotEmpty() ? $sedesUnidad->implode(' · ') : 'Sin sedes vinculadas a sus carreras' }}</p>
                        @elseif ($tipo === 'planes')
                            <p>Versión: {{ $r->version ?: 'Sin informar' }}</p>
                            <p class="mt-1 text-xs text-texto-secundario">Inicio: {{ $r->anio_inicio ?: 'Sin informar' }}</p>
                        @else
                            <p>Semestre {{ $r->numero }}</p>
                        @endif
                    </div>
                </td>
                <td role="cell" class="p-4"><span class="etiqueta-listado" aria-hidden="true">Estado</span><div class="dato-listado"><span class="whitespace-nowrap rounded-full px-3 py-1 text-xs font-semibold {{ $r->estado ? 'bg-green-50 text-green-800' : 'bg-fondo text-texto-secundario' }}">{{ $r->estado ? 'Activo' : 'Inactivo' }}</span></div></td>
                <td role="cell" data-acciones-listado class="p-4 text-right"><a class="boton boton-secundario" href="{{ route('administracion.academicos.edit', ['tipo' => $tipo, 'id' => $r->id]) }}">Editar</a></td>
            </tr>@empty<tr role="row"><td role="cell" colspan="5" class="p-8 text-center text-texto-secundario">No hay registros que coincidan con los filtros.</td></tr>@endforelse</tbody>
        </table></div><div class="border-t border-borde p-4">{{ $registros->links() }}</div>
    </section>
</div>
@endsection

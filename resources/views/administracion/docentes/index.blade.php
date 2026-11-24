@extends('layouts.app')
@section('title', 'Docentes | '.$identidad['nombre'])
@section('content')
<div class="space-y-6">
    <x-encabezado-pagina titulo="Docentes">Edita los datos y conserva las asignaciones y el historial académico.<x-slot:acciones><a class="boton boton-primario" href="{{ route('administracion.docentes.create') }}">Registrar docente</a></x-slot:acciones></x-encabezado-pagina>
    @include('disponibilidad.avisos')
    <x-filtros-compactos aria-label="Filtros de docentes">
        <div>
            <label for="q" class="mb-2 block text-sm font-semibold">Buscar docente</label>
            <input id="q" name="q" maxlength="100" value="{{ $q }}" placeholder="Nombre, RUT o correo" class="h-11 w-full rounded-lg border border-borde px-3">
        </div>
        <div>
            <label for="universidad_id" class="mb-2 block text-sm font-semibold">Institución</label>
            <select id="universidad_id" name="universidad_id" class="h-11 w-full rounded-lg border border-borde px-3">
                <option value="">Todas</option>
                @foreach ($universidades as $u)
                    <option value="{{ $u->id }}" @selected(($filtros['universidad_id'] ?? null) == $u->id)>{{ $u->sigla }} · {{ $u->nombre }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="estado" class="mb-2 block text-sm font-semibold">Estado</label>
            <select id="estado" name="estado" class="h-11 w-full rounded-lg border border-borde px-3">
                @foreach (['activos' => 'Activos', 'inactivos' => 'Inactivos', 'todos' => 'Todos'] as $v => $etiqueta)
                    <option value="{{ $v }}" @selected($estado === $v)>{{ $etiqueta }}</option>
                @endforeach
            </select>
        </div>
        <x-slot:acciones>
            <a class="boton boton-secundario" href="{{ route('administracion.docentes.index') }}">Limpiar filtros</a>
            <button type="submit" class="boton boton-primario">Consultar</button>
        </x-slot:acciones>
    </x-filtros-compactos>
    <section class="overflow-hidden rounded-xl border border-borde bg-superficie shadow-sm"><div class="flex flex-wrap justify-between gap-2 border-b border-borde p-5"><h2 class="font-semibold">Docentes registrados</h2><p class="text-sm text-texto-secundario">{{ $docentes->total() }} resultados</p></div><div class="listado-adaptable overflow-x-auto" role="region" aria-label="Docentes registrados"><table role="table" class="w-full min-w-[760px] text-left text-sm"><thead role="rowgroup" class="bg-fondo text-xs uppercase text-texto-secundario"><tr role="row"><th role="columnheader" scope="col" class="p-4">Docente</th><th role="columnheader" scope="col" class="p-4">Institución y unidad</th><th role="columnheader" scope="col" class="p-4">Correo institucional</th><th role="columnheader" scope="col" class="p-4">Estado</th><th role="columnheader" scope="col" class="p-4 text-right">Acciones</th></tr></thead><tbody role="rowgroup" class="divide-y divide-borde">@forelse ($docentes as $p)<tr role="row"><td role="cell" data-principal-listado class="max-w-xs p-4"><p class="font-semibold">{{ $p->apellido }}, {{ $p->nombre }}</p><p class="mt-1 text-xs text-texto-secundario">{{ $p->rut ?: 'RUT sin informar' }}</p></td><td role="cell" class="max-w-xs p-4"><span class="etiqueta-listado" aria-hidden="true">Institución y unidad</span><div class="dato-listado"><p>{{ $p->universidad->sigla }}</p><p class="mt-1 text-xs text-texto-secundario">{{ $p->unidad_academica ?: 'Unidad sin informar' }}</p></div></td><td role="cell" class="break-all p-4"><span class="etiqueta-listado" aria-hidden="true">Correo institucional</span><div class="dato-listado">{{ $p->correo_institucional ?: 'Sin informar' }}</div></td><td role="cell" class="p-4"><span class="etiqueta-listado" aria-hidden="true">Estado</span><div class="dato-listado">{{ $p->estado ? 'Activo' : 'Inactivo' }}</div></td><td role="cell" data-acciones-listado class="p-4 text-right"><a class="boton boton-secundario" href="{{ route('administracion.docentes.edit', $p) }}">Editar</a></td></tr>@empty<tr role="row"><td role="cell" colspan="5" class="p-8 text-center text-texto-secundario">No hay docentes que coincidan con la consulta.</td></tr>@endforelse</tbody></table></div><div class="border-t border-borde p-4">{{ $docentes->links() }}</div></section>
</div>
@endsection

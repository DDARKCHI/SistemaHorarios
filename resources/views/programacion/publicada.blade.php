@extends('layouts.app')
@section('title', 'Horario publicado | '.$identidad['nombre'])
@section('content')
<div class="space-y-6">
    <x-encabezado-pagina titulo="Horario publicado">{{ $cierre->datos['periodo'] }} · V{{ $cierre->datos['version'] }} · Publicado el {{ $cierre->created_at->format('d/m/Y H:i') }}
        <x-slot:acciones><a class="boton boton-secundario" href="{{ route('programacion.version.pdf', ['version' => $version] + $contexto + ['formato' => 'detalle']) }}">Descargar PDF</a><a class="boton boton-secundario" href="{{ route('programacion.version.pdf', ['version' => $version] + $contexto + ['formato' => 'semanal']) }}">PDF semanal</a></x-slot:acciones>
    </x-encabezado-pagina>
    @include('disponibilidad.avisos')
    <p class="text-sm text-texto-secundario">Esta consulta conserva los cursos, docentes y espacios del momento de publicación. {{ $version->es_actual ? 'Es la versión actual del período.' : 'Es una publicación anterior; consulta la programación actual para conocer la versión vigente.' }}</p>
    <form method="get" aria-label="Filtrar horario publicado" class="@container/publicacion space-y-3 rounded-xl border border-borde bg-superficie p-4 shadow-sm sm:p-5">
        <div class="grid min-w-0 grid-cols-1 items-end gap-3 @min-[23rem]/publicacion:grid-cols-2 @min-[34rem]/publicacion:grid-cols-3 @min-[58rem]/publicacion:grid-cols-5">
            <div class="col-span-full min-w-0 @min-[58rem]/publicacion:col-span-2">
                <label for="q" class="mb-2 block text-sm font-semibold">Buscar en el horario</label>
                <input id="q" name="q" value="{{ $q }}" maxlength="100" placeholder="Ramo, docente, sección o espacio" class="h-11 w-full rounded-lg border border-borde px-3">
            </div>
            @foreach ($campos as $campo => $titulo)
                @if (count($opciones[$campo]) > 0)
                    <div class="min-w-0">
                        <label for="filtro-{{ $campo }}" class="mb-2 block text-sm font-semibold">{{ $titulo }}</label>
                        <select id="filtro-{{ $campo }}" name="{{ $campo }}" class="h-11 w-full rounded-lg border border-borde px-3">
                            <option value="">Todos</option>
                            @foreach ($opciones[$campo] as $opcion)
                                <option value="{{ $opcion }}" @selected((string) ($filtros[$campo] ?? '') === $opcion)>{{ $opcion }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
            @endforeach
            <div class="col-span-full grid min-w-0 grid-cols-2 gap-2 @min-[34rem]/publicacion:col-span-1">
                <button type="submit" class="boton boton-primario">Filtrar</button>
                <a class="boton boton-secundario" href="{{ route('programacion.version.publicada', $version) }}">Limpiar</a>
            </div>
        </div>
        <p class="text-xs text-texto-secundario">Los PDF incluyen toda la selección, aunque ocupe varias páginas. Para archivos de hasta 300 módulos, aplica los filtros antes de descargar.</p>
    </form>
    <section class="overflow-hidden rounded-xl border border-borde bg-superficie shadow-sm"><h2 class="border-b border-borde p-5 font-semibold">Clases publicadas · {{ $horarios->total() }} módulos</h2><div class="listado-adaptable overflow-x-auto"><table role="table" class="w-full min-w-[800px] text-left text-sm"><thead role="rowgroup" class="bg-fondo text-xs uppercase text-texto-secundario"><tr role="row"><th role="columnheader" scope="col" class="p-4">Ramo y actividad</th><th role="columnheader" scope="col" class="p-4">Día y hora</th><th role="columnheader" scope="col" class="p-4">Docentes</th><th role="columnheader" scope="col" class="p-4">Espacios</th></tr></thead><tbody role="rowgroup" class="divide-y divide-borde">@forelse ($horarios as $h)<tr role="row"><td role="cell" data-principal-listado class="p-4"><p class="font-semibold">{{ $h['curso'] }}</p><p class="mt-1 text-xs text-texto-secundario">Sección {{ $h['seccion'] }} · {{ $h['actividad'] }} · {{ $h['grupo'] }}</p></td><td role="cell" class="p-4 whitespace-nowrap"><span class="etiqueta-listado" aria-hidden="true">Día y hora</span><div class="dato-listado">{{ $h['dia'] }}<br><span class="text-xs text-texto-secundario">{{ $h['hora'] }} · Módulo {{ $h['modulo'] }}</span></div></td><td role="cell" class="p-4"><span class="etiqueta-listado" aria-hidden="true">Docentes</span><div class="dato-listado">{{ $h['docentes'] }}</div></td><td role="cell" class="p-4"><span class="etiqueta-listado" aria-hidden="true">Espacios</span><div class="dato-listado">{{ $h['espacios'] }}</div></td></tr>@empty<tr role="row"><td role="cell" colspan="4" class="p-8 text-center text-texto-secundario">No hay clases que coincidan con la búsqueda.</td></tr>@endforelse</tbody></table></div><div class="border-t border-borde p-4">{{ $horarios->links() }}</div></section>
    <a class="boton boton-secundario" href="{{ route('programacion.semestral', ['periodo_id' => $version->periodo_academico_id, 'version_id' => $version->id]) }}">Volver a programación</a>
</div>
@endsection

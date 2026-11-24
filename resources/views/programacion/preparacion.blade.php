@extends('layouts.app')
@section('title', 'Preparar generación | '.$identidad['nombre'])
@section('content')
<div class="space-y-6">
    <x-encabezado-pagina titulo="Preparar generación">Revisa los datos de todo el período antes de construir una propuesta de horarios.</x-encabezado-pagina>
    @include('disponibilidad.avisos')
    <section class="flex flex-col gap-4 rounded-xl border border-borde bg-superficie p-4 shadow-sm sm:flex-row sm:items-center sm:justify-between sm:p-5">
        <div class="min-w-0">
            <h2 class="font-semibold">Generación automática</h2>
            <p class="mt-1 text-sm text-texto-secundario">Completa los horarios y espacios de toda la oferta conservando las asignaciones de la versión base.</p>
        </div>
        <div class="flex shrink-0 flex-wrap gap-2">
            <a class="boton boton-secundario" href="{{ route('programacion.completar.index', ['periodo_id' => $periodo?->id, 'version_id' => $version && $version->tipo === 'semestral' ? $version->id : null]) }}">Completar programación</a>
            @if ($version)
                <form method="post" action="{{ route('programacion.semestral.generar') }}" data-confirmar="Se creará un borrador nuevo con horarios y espacios compatibles, conservando las asignaciones de la base." data-confirmar-titulo="Generar programación" data-confirmar-accion="Generar">
                    @csrf
                    <input type="hidden" name="version_base_id" value="{{ $version->id }}">
                    <input type="hidden" name="solicitud" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                    <button type="submit" class="boton boton-primario">Generar programación</button>
                </form>
            @endif
        </div>
    </section>
    <form method="get" class="grid gap-4 rounded-xl border border-borde bg-superficie p-4 shadow-sm sm:p-5 lg:grid-cols-[minmax(0,1fr)_minmax(0,2fr)_auto] lg:items-end">
        <div><label for="periodo_id" class="mb-2 block text-sm font-semibold">Período académico</label><select id="periodo_id" name="periodo_id" class="h-11 w-full rounded-lg border border-borde px-3" onchange="this.form.querySelector('[name=version_id]').disabled=true; this.form.submit()">@foreach ($periodos as $p)<option value="{{ $p->id }}" @selected($periodo?->id === $p->id)>{{ $p->nombre }}</option>@endforeach</select></div>
        <div><label for="version_id" class="mb-2 block text-sm font-semibold">Versión base</label><select id="version_id" name="version_id" class="h-11 w-full rounded-lg border border-borde px-3">@forelse ($versiones as $v)<option value="{{ $v->id }}" @selected($version?->id === $v->id)>{{ $v->nombre }} · V{{ $v->version }} · {{ $v->estado }}</option>@empty<option value="">Sin versiones disponibles</option>@endforelse</select></div>
        <button class="boton boton-primario">Revisar preparación</button>
    </form>
    @if ($reporte)
        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="Resumen del período">
            @foreach (['secciones' => 'Secciones activas', 'grupos' => 'Grupos por planificar', 'modulos' => 'Módulos requeridos', 'aprobados' => 'Docentes con disponibilidad apta'] as $clave => $nombre)
            <div class="rounded-xl border border-borde bg-superficie p-5 shadow-sm"><p class="text-sm text-texto-secundario">{{ $nombre }}</p><p class="mt-2 text-2xl font-bold">{{ number_format($reporte[$clave], 0, ',', '.') }}@if ($clave === 'aprobados')<span class="text-sm font-normal text-texto-secundario"> / {{ $reporte['docentes'] }}</span>@endif</p></div>
            @endforeach
        </section>
        <section class="space-y-3 rounded-xl border border-borde bg-superficie p-5 shadow-sm">
            <h2 class="font-semibold">{{ $reporte['bloqueos'] ? 'Hay datos que completar antes de generar' : 'No se detectaron bloqueos en los datos revisados' }}</h2>
            <p class="text-sm text-texto-secundario">Esta revisión no genera horarios. Los topes entre clases se comprueban al generar la propuesta.</p>
            <p class="text-sm text-texto-secundario">Los módulos de almuerzo están disponibles solo para asignaciones manuales excepcionales.</p>
        </section>
        @if ($version)
        <section class="flex flex-col gap-4 rounded-xl border border-borde bg-superficie p-4 shadow-sm sm:flex-row sm:items-center sm:justify-between sm:p-5">
            <div class="min-w-0">
                <h2 class="font-semibold">Reorganizar horarios existentes</h2>
                <p class="mt-1 text-sm text-texto-secundario">Propón horarios para las clases de la versión base. Podrás revisar la propuesta antes de guardarla como borrador.</p>
            </div>
            <form method="post" action="{{ route('programacion.reorganizacion.generar') }}" class="shrink-0">
                @csrf
                <input type="hidden" name="version_id" value="{{ $version->id }}">
                <button @disabled($reporte['bloqueos'] > 0) class="boton boton-primario w-full sm:w-auto">Generar propuesta</button>
            </form>
        </section>
        @endif
        <section class="overflow-hidden rounded-xl border border-borde bg-superficie shadow-sm" aria-labelledby="observaciones-preparacion">
            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-borde p-5"><h2 id="observaciones-preparacion" class="font-semibold">Observaciones de preparación</h2><span class="text-sm text-texto-secundario">{{ $reporte['bloqueos'] }} bloqueos · {{ $observaciones->total() - $reporte['bloqueos'] }} avisos</span></div>
            <div class="divide-y divide-borde">
            @forelse ($observaciones as $fila)
                <article class="flex flex-col justify-between gap-3 p-4 sm:flex-row sm:items-center sm:p-5"><div class="min-w-0"><div class="flex flex-wrap items-center gap-2"><span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $fila['tipo'] === 'bloqueo' ? 'bg-red-50 text-red-800' : 'bg-sky-50 text-sky-800' }}">{{ $fila['tipo'] === 'bloqueo' ? 'Por completar' : 'Aviso' }}</span><h3 class="text-sm font-semibold">{{ $fila['titulo'] }}</h3></div><p class="mt-2 text-sm text-texto-secundario">{{ $fila['detalle'] }}</p></div>@if ($fila['url'])<a href="{{ $fila['url'] }}" class="boton boton-secundario shrink-0 self-start sm:self-center">Revisar</a>@endif</article>
            @empty<p class="p-6 text-sm text-texto-secundario">No se encontraron observaciones en los datos revisados.</p>@endforelse
            </div><div class="border-t border-borde p-4">{{ $observaciones->links() }}</div>
        </section>
    @else<p class="rounded-xl border border-borde bg-superficie p-6 text-sm text-texto-secundario">Registra un período académico para revisar su preparación.</p>@endif
</div>
@endsection

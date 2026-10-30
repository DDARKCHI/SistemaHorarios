@extends('layouts.app')
@section('title', 'Revisión de conflictos | PROSEC')
@section('content')
<x-encabezado-pagina titulo="Revisión de conflictos">
    Revisa topes, asignaciones, capacidades, disponibilidad docente y módulos requeridos en una versión semestral.
    <x-slot:acciones>
        @can('ver programacion semestral')
        <a href="{{ route('programacion.semestral', array_filter(['periodo_id' => $periodoId, 'version_id' => $version?->id])) }}" class="boton boton-secundario">Volver a programación</a>
        @endcan
    </x-slot:acciones>
</x-encabezado-pagina>

<div class="min-w-0 space-y-4">
    @if ($errors->any())
        <div role="alert" class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800">{{ $errors->first() }}</div>
    @endif
    <form method="GET" action="{{ route('programacion.validacion') }}" class="grid min-w-0 items-end gap-4 rounded-lg border border-gray-200 bg-white p-5 shadow-sm sm:grid-cols-2 sm:p-6 xl:grid-cols-[1fr_1fr_1fr_auto]" aria-label="Seleccionar programación a revisar">
        <div><label for="periodo-validacion" class="mb-1.5 block font-semibold text-gray-700">Período académico</label>
            <select id="periodo-validacion" name="periodo_id" data-select-buscable class="h-11 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm text-gray-700">
                @forelse ($periodos as $periodo)<option value="{{ $periodo->id }}" @selected((string) $periodoId === (string) $periodo->id)>{{ $periodo->nombre }}</option>@empty<option value="">Sin períodos disponibles</option>@endforelse
            </select>
        </div>
        <div><label for="version-validacion" class="mb-1.5 block font-semibold text-gray-700">Versión semestral</label>
            <select id="version-validacion" name="version_id" data-select-buscable class="h-11 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm text-gray-700">
                @forelse ($versiones as $item)<option value="{{ $item->id }}" @selected($version?->id === $item->id)>v{{ $item->version }} · {{ $item->estado }}{{ $item->es_actual ? ' · Actual' : '' }}</option>@empty<option value="">Sin versiones semestrales</option>@endforelse
            </select>
        </div>
        <div><label for="tipo-validacion" class="mb-1.5 block font-semibold text-gray-700">Tipo de revisión</label>
            <select id="tipo-validacion" name="tipo" data-select-buscable class="h-11 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm text-gray-700">
                @foreach ($tipos as $clave => $etiqueta)<option value="{{ $clave }}" @selected($tipo === $clave)>{{ $etiqueta }}</option>@endforeach
            </select>
        </div>
        <button class="boton boton-primario" type="submit">Revisar</button>
    </form>

    @if (! $reporte)
        <div class="rounded-lg border border-gray-200 bg-white p-8 text-center shadow-sm"><h2 class="text-base font-semibold text-gray-800">Sin programación semestral para revisar</h2><p class="mt-2 text-sm text-gray-500">Elige un período que tenga una versión semestral.</p></div>
    @else
        <dl class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-6">
            @foreach (['modulos' => 'Módulos revisados', 'conflictos' => 'Cruces detectados', 'asignaciones' => 'Asignaciones incompletas', 'capacidad' => 'Capacidades por revisar', 'disponibilidad' => 'Disponibilidad por revisar', 'datos' => 'Datos por revisar'] as $clave => $etiqueta)
                <div class="flex min-w-0 items-center justify-between gap-3 rounded-lg border border-gray-200 bg-white p-4 shadow-sm sm:block"><dt class="text-sm font-semibold text-gray-700">{{ $etiqueta }}</dt><dd class="text-2xl font-semibold text-gray-800 sm:mt-1">{{ number_format($reporte[$clave], 0, ',', '.') }}</dd></div>
            @endforeach
        </dl>
        <section aria-labelledby="resumen-carga" class="min-w-0 rounded-lg border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 id="resumen-carga" class="text-base font-semibold text-gray-800">Cumplimiento de módulos</h2>
                <a class="boton boton-secundario" href="{{ route('programacion.validacion', ['periodo_id' => $periodoId, 'version_id' => $version->id, 'tipo' => 'carga']) }}">Revisar carga</a>
            </div>
            <p class="mt-3 text-sm leading-6 text-gray-500">Se comparan todas las actividades y grupos activos del período con los módulos de esta versión, incluidos los grupos aún sin horario. Los faltantes y excesos se calculan por grupo y no se compensan.</p>
            <dl class="mt-4 grid grid-cols-2 gap-4 lg:grid-cols-4">
                @foreach (['requeridos' => 'Requeridos', 'asignados' => 'Asignados a grupos válidos', 'pendientes' => 'Pendientes', 'excedentes' => 'En exceso'] as $clave => $etiqueta)
                    <div class="min-w-0"><dt class="text-sm font-semibold text-gray-700">{{ $etiqueta }}</dt><dd class="mt-1 text-2xl font-semibold text-gray-800">{{ number_format($reporte['carga'][$clave], 0, ',', '.') }}</dd></div>
                @endforeach
            </dl>
            @if ($reporte['carga']['grupos_sin_carga'] || $reporte['carga']['actividades_sin_grupos'])
                <p class="mt-3 text-sm font-semibold text-gray-700">Los módulos requeridos están incompletos: revisa las actividades sin grupos y los grupos sin carga definida.</p>
            @endif
            <p class="mt-4 text-sm leading-6 text-gray-500">{{ count($reporte['carga']['observaciones']) }} {{ count($reporte['carga']['observaciones']) === 1 ? 'observación de carga' : 'observaciones de carga' }}. La carga se mide en módulos según la actividad, sin convertirlos a horas de 60 minutos.</p>
        </section>
        <p class="text-sm leading-6 text-gray-500">Se compara la disponibilidad registrada para el período por intervalos reales. La aprobación formal de disponibilidades y la compatibilidad de recursos aún requieren sus controles correspondientes.</p>
        @if ($observaciones->isEmpty())
            <div class="rounded-lg border border-gray-200 bg-white p-8 text-center shadow-sm">
                <h2 class="text-base font-semibold text-gray-800">{{ $reporte['modulos'] === 0 ? 'Esta versión no tiene módulos activos' : ($tipo === 'todos' ? 'Sin observaciones detectadas' : 'Sin observaciones para este filtro') }}</h2>
                <p class="mt-2 text-sm text-gray-500">{{ $reporte['modulos'] === 0 ? 'Los módulos que se incorporen a esta versión podrán revisarse aquí.' : 'Puedes revisar otra versión o cambiar el tipo de revisión.' }}</p>
            </div>
        @else
            <p class="text-sm text-gray-500">{{ $observaciones->total() }} {{ $observaciones->total() === 1 ? 'observación' : 'observaciones' }} · Página {{ $observaciones->currentPage() }} de {{ $observaciones->lastPage() }}</p>
            <div class="space-y-3">
                @foreach ($observaciones as $observacion)
                    <article class="min-w-0 rounded-lg border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
                        <p class="mb-2 text-sm font-semibold text-gray-500">{{ ['conflicto' => 'Tope horario', 'asignaciones' => 'Asignaciones incompletas', 'capacidad' => 'Capacidad por revisar', 'disponibilidad' => 'Disponibilidad por revisar', 'carga' => 'Carga por revisar', 'datos' => 'Datos por revisar'][$observacion['tipo']] }}</p>
                        <h2 class="text-base font-semibold text-gray-800">{{ $observacion['titulo'] ?? ($observacion['dia'].' · '.$observacion['intervalo']) }}</h2>
                        <p class="mt-1 text-sm text-gray-600">{{ $observacion['mensaje'] }}</p>
                        @if ($observacion['recursos'])<ul class="mt-3 flex flex-wrap gap-2" aria-label="Asignaciones involucradas">@foreach ($observacion['recursos'] as $recurso)<li class="max-w-full break-words rounded-lg bg-gray-100 px-3 py-1.5 text-sm text-gray-700">{{ $tipos[$recurso['tipo']] }}: {{ $recurso['nombre'] }}</li>@endforeach</ul>@endif
                        <div class="mt-4 grid min-w-0 gap-3 md:grid-cols-2">
                            @foreach ($observacion['horarios'] ?: [$observacion['clase']] as $modulo)
                                <div class="min-w-0 rounded-lg border border-gray-200 p-4"><h3 class="break-words text-sm font-semibold text-gray-800">{{ $modulo['asignatura'] ?: 'Asignatura sin información' }}</h3><p class="mt-1 break-words text-sm text-gray-600">Sección {{ $modulo['seccion'] }} · {{ $modulo['actividad'] }}{{ $modulo['grupo'] ? ' · '.$modulo['grupo'] : '' }}</p>@if (isset($modulo['id']))<p class="mt-2 text-sm text-gray-500">{{ $modulo['hora'] }} · Módulo {{ $modulo['bloque'] ?? 'sin número' }} · Horario #{{ $modulo['id'] }}</p>@endif</div>
                            @endforeach
                        </div>
                    </article>
                @endforeach
            </div>
            @if ($observaciones->hasPages())<nav aria-label="Paginación de observaciones" class="flex flex-wrap items-center justify-between gap-2">
                @if ($observaciones->onFirstPage())<span class="boton boton-secundario" aria-disabled="true">Anterior</span>@else<a class="boton boton-secundario" href="{{ $observaciones->previousPageUrl() }}" rel="prev">Anterior</a>@endif
                @if ($observaciones->hasMorePages())<a class="boton boton-secundario" href="{{ $observaciones->nextPageUrl() }}" rel="next">Siguiente</a>@else<span class="boton boton-secundario" aria-disabled="true">Siguiente</span>@endif
            </nav>@endif
        @endif
    @endif
</div>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const periodo = document.getElementById('periodo-validacion');
    periodo.addEventListener('change', () => {
        const form = periodo.form;
        document.getElementById('version-validacion').disabled = true;
        form.requestSubmit();
    });
});
</script>
@endsection

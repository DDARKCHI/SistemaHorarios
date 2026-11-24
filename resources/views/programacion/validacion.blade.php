@extends('layouts.app')
@section('title', 'Revisión de conflictos | '.$identidad['nombre'])
@section('content')
<x-encabezado-pagina titulo="Revisión de conflictos">
    Identifica topes de horario y datos por completar en una versión semestral.
    <x-slot:acciones>
        <a href="{{ route('programacion.requerimientos.index', array_filter(['periodo_id' => $periodoId, 'version_id' => $version?->id])) }}" class="boton boton-secundario">Configurar requerimientos</a>
        @can('ver programacion semestral')
        <a href="{{ route('programacion.semestral', array_filter(['periodo_id' => $periodoId, 'version_id' => $version?->id])) }}" class="boton boton-secundario">Volver a programación</a>
        @endcan
    </x-slot:acciones>
</x-encabezado-pagina>

<div class="min-w-0 space-y-4">
    @if ($errors->any())
        <div role="alert" class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800">{{ $errors->first() }}</div>
    @endif
    <form method="GET" action="{{ route('programacion.validacion') }}" class="grid min-w-0 items-end gap-4 rounded-xl border border-borde bg-white p-5 shadow-sm sm:grid-cols-2 sm:p-6 xl:grid-cols-[1fr_1fr_1fr_auto]" aria-label="Seleccionar programación a revisar">
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
        <div class="rounded-xl border border-borde bg-white p-8 text-center shadow-sm"><h2 class="text-base font-semibold text-gray-800">Sin programación semestral para revisar</h2><p class="mt-2 text-sm text-gray-500">Elige un período que tenga una versión semestral.</p></div>
    @else
        <dl class="grid grid-cols-2 gap-3 xl:grid-cols-4">
            @foreach (['modulos' => 'Módulos revisados', 'conflictos' => 'Cruces detectados', 'asignaciones' => 'Asignaciones incompletas', 'capacidad' => 'Capacidades por revisar', 'disponibilidad' => 'Disponibilidad por revisar', 'recursos' => 'Recursos por revisar', 'carga_observaciones' => 'Carga por revisar', 'datos' => 'Datos por revisar'] as $clave => $etiqueta)
                <div class="flex min-w-0 flex-col items-start justify-between gap-3 rounded-xl border border-borde bg-white p-4 shadow-sm sm:block"><dt class="text-sm font-semibold text-gray-700">{{ $etiqueta }}</dt><dd class="text-3xl font-bold tabular-nums sm:mt-3 {{ $clave === 'conflictos' ? 'text-institucional' : 'text-texto' }}">{{ number_format($reporte[$clave], 0, ',', '.') }}</dd></div>
            @endforeach
        </dl>
        <div class="rounded-xl border border-borde bg-superficie px-5 py-4 text-sm leading-6">
            <p><span class="font-semibold">{{ number_format($reporte['observaciones']->count(), 0, ',', '.') }} observaciones en total.</span>
                @if ($reporte['conflictos'])
                    {{ number_format($reporte['conflictos'], 0, ',', '.') }} {{ $reporte['conflictos'] === 1 ? 'corresponde a un tope de horario' : 'corresponden a topes de horario' }}.
                @else
                    No se detectaron topes de horario.
                @endif
                Las demás revisan asignaciones, capacidades, disponibilidad y carga.
            </p>
            <p class="mt-1 text-texto-secundario">Cada módulo se revisa por separado y puede tener más de una observación. Una disponibilidad sin registrar requiere confirmación del docente.</p>
        </div>
        <section aria-labelledby="resumen-carga" class="min-w-0 rounded-xl border border-borde bg-white p-5 shadow-sm sm:p-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 id="resumen-carga" class="text-base font-semibold text-gray-800">Cumplimiento de módulos</h2>
                <a class="boton boton-secundario" href="{{ route('programacion.validacion', ['periodo_id' => $periodoId, 'version_id' => $version->id, 'tipo' => 'carga']) }}">Revisar carga</a>
            </div>
            <p class="mt-3 text-sm leading-6 text-gray-500">Compara la carga requerida con los módulos asignados a cada grupo.</p>
            <dl class="mt-5 grid grid-cols-2 gap-4 rounded-xl bg-fondo p-4 lg:grid-cols-4">
                @foreach (['requeridos' => 'Requeridos', 'cubiertos' => 'Cubiertos', 'pendientes' => 'Pendientes', 'excedentes' => 'En exceso'] as $clave => $etiqueta)
                    <div class="min-w-0"><dt class="text-sm font-semibold text-gray-700">{{ $etiqueta }}</dt><dd class="mt-1 text-2xl font-semibold text-gray-800">{{ number_format($reporte['carga'][$clave], 0, ',', '.') }}</dd></div>
                @endforeach
            </dl>
            @if ($reporte['carga']['grupos_sin_carga'] || $reporte['carga']['actividades_sin_grupos'])
                <p class="mt-3 text-sm font-semibold text-gray-700">Los módulos requeridos están incompletos: revisa las actividades sin grupos y los grupos sin carga definida.</p>
            @endif
            <p class="mt-4 text-xs leading-5 text-texto-secundario">Los módulos cubiertos y pendientes completan la carga requerida. Los módulos en exceso se cuentan aparte, por grupo.</p>
        </section>
        <details class="rounded-xl border border-borde bg-white px-5 py-4 text-sm"><summary class="cursor-pointer font-semibold">¿Qué comprueba la revisión?</summary><p class="mt-3 leading-6 text-texto-secundario">Carga, cruces, asignaciones, capacidades, disponibilidad y requerimientos de recursos. Corrige las observaciones antes de activar, aprobar o publicar.</p></details>
        @if ($observaciones->isEmpty())
            <div class="rounded-xl border border-borde bg-white p-8 text-center shadow-sm">
                <h2 class="text-base font-semibold text-gray-800">{{ $reporte['modulos'] === 0 ? 'Esta versión no tiene módulos activos' : ($tipo === 'todos' ? 'Sin observaciones detectadas' : 'Sin observaciones para este filtro') }}</h2>
                <p class="mt-2 text-sm text-gray-500">{{ $reporte['modulos'] === 0 ? 'Los módulos que se incorporen a esta versión podrán revisarse aquí.' : 'Puedes revisar otra versión o cambiar el tipo de revisión.' }}</p>
            </div>
        @else
            <div class="flex flex-wrap items-center justify-between gap-3">
                <p class="text-sm text-gray-500">{{ $observaciones->total() }} {{ $observaciones->total() === 1 ? 'observación' : 'observaciones' }}</p>
                <a class="boton boton-secundario" href="{{ route('programacion.validacion.exportar', ['periodo_id' => $periodoId, 'version_id' => $version->id, 'tipo' => $tipo]) }}">Exportar a Excel</a>
            </div>
            <p class="text-sm text-gray-500">La descarga incluye todas las páginas del filtro aplicado.</p>
            <div class="space-y-3">
                @foreach ($observaciones as $observacion)
                    <article class="min-w-0 overflow-hidden rounded-xl border border-borde border-l-4 border-l-institucional/40 bg-white p-5 shadow-sm sm:p-6">
                        <p class="mb-3 inline-flex rounded-full bg-institucional/5 px-3 py-1 text-xs font-semibold text-institucional">{{ $etiquetasObservacion[$observacion['tipo']] }}</p>
                        <h2 class="text-base font-semibold text-gray-800">{{ $observacion['titulo'] ?? ($observacion['dia'].' · '.$observacion['intervalo']) }}</h2>
                        <p class="mt-1 text-sm text-gray-600">{{ $observacion['mensaje'] }}</p>
                        @if ($observacion['recursos'])<ul class="mt-3 flex flex-wrap gap-2" aria-label="Asignaciones involucradas">@foreach ($observacion['recursos'] as $recurso)<li class="max-w-full break-words rounded-lg bg-gray-100 px-3 py-1.5 text-sm text-gray-700">{{ $tipos[$recurso['tipo']] }}: {{ $recurso['nombre'] }}</li>@endforeach</ul>@endif
                        <div class="mt-4 grid min-w-0 gap-3 md:grid-cols-2">
                            @foreach ($observacion['horarios'] ?: [$observacion['clase']] as $modulo)
                                <div class="min-w-0 rounded-lg border border-borde bg-fondo/60 p-4"><h3 class="break-words text-sm font-semibold text-gray-800">{{ $modulo['asignatura'] ?: 'Asignatura sin información' }}</h3><p class="mt-1 break-words text-sm text-gray-600">Sección {{ $modulo['seccion'] }} · {{ $modulo['actividad'] }}{{ $modulo['grupo'] ? ' · '.$modulo['grupo'] : '' }}</p>@if (isset($modulo['id']))<p class="mt-2 text-sm text-gray-500">{{ $modulo['hora'] }} · Módulo {{ $modulo['bloque'] ?? 'sin número' }} · Horario #{{ $modulo['id'] }}</p>@endif</div>
                            @endforeach
                        </div>
                    </article>
                @endforeach
            </div>
            <x-paginacion-catalogo :paginas="$observaciones" etiqueta="Paginación de observaciones" />
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

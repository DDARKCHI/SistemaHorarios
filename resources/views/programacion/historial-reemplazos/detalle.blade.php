@extends('layouts.app')
@section('content')
<div class="mx-auto w-full min-w-0 max-w-5xl space-y-3 px-2 py-2">
    <header class="flex min-w-0 flex-col gap-3 rounded-lg border border-gray-200 bg-white p-4 shadow-sm sm:flex-row sm:items-center sm:justify-between">
        <div class="min-w-0"><h1 class="text-base font-bold text-gray-800">Detalle del reemplazo #{{ $registro['id'] }}</h1><p class="mt-1 text-sm text-gray-600">{{ $registro['fecha'] ?: 'Sin fecha registrada' }} · Realizado por {{ $registro['responsable'] }}</p></div>
        <a href="{{ route('programacion.intercambio.historial') }}" class="inline-flex min-h-11 shrink-0 items-center justify-center rounded-lg border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Volver al historial</a>
    </header>
    @if (! $registro['detalle_registrado'])
        <p role="note" class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm leading-6 text-amber-900">Este registro es anterior al detalle completo o conserva información parcial. Los nombres y datos de la clase que se muestran como referencia pueden provenir de la versión o de las fichas actuales. Los campos sin referencias disponibles se muestran como información no disponible.</p>
    @else
        <p class="px-1 text-xs leading-5 text-gray-600">Los datos de este detalle quedaron guardados al aplicar el reemplazo.</p>
    @endif
    <section aria-labelledby="clase-historial" class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
        <h2 id="clase-historial" class="break-words text-sm font-bold text-gray-900">{{ $registro['asignatura_codigo'] }} · {{ $registro['asignatura_nombre'] }}</h2>
        <p class="mt-1 break-words text-sm text-gray-600">Sección {{ $registro['seccion'] ?: 'sin dato' }} · {{ $registro['actividad'] ?: 'Actividad sin dato' }} · {{ $registro['grupo'] ?: 'Grupo sin dato' }}</p>
        <dl class="mt-4 grid min-w-0 grid-cols-1 gap-4 text-sm sm:grid-cols-3">
            <div><dt class="font-semibold text-gray-500">Período</dt><dd class="mt-1 break-words">{{ $registro['periodo'] }}</dd></div>
            <div><dt class="font-semibold text-gray-500">Versión</dt><dd class="mt-1 break-words">{{ $registro['version'] }} @if ($registro['version_numero'])· v{{ $registro['version_numero'] }}@endif</dd></div>
            <div><dt class="font-semibold text-gray-500">Módulos reemplazados</dt><dd class="mt-1 font-bold">{{ $registro['cantidad_modulos'] ?? 'Sin dato registrado' }}</dd></div>
        </dl>
    </section>
    <section aria-label="Docentes del reemplazo" class="grid min-w-0 grid-cols-1 gap-3 sm:grid-cols-2">
        @foreach (['origen' => 'Docente anterior', 'candidato' => 'Docente de reemplazo'] as $clave => $etiqueta)
            <div class="min-w-0 rounded-lg border border-gray-200 bg-white p-4 shadow-sm"><h2 class="text-sm font-semibold text-gray-600">{{ $etiqueta }}</h2><p class="mt-2 break-words text-base font-bold text-gray-900">{{ $registro[$clave] }}</p><p class="mt-1 text-xs text-gray-500">RUT: {{ $registro[$clave.'_rut'] ?: 'Sin RUT registrado' }}</p></div>
        @endforeach
    </section>
    <section aria-labelledby="disponibilidad-historial" class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm"><h2 id="disponibilidad-historial" class="text-sm font-bold text-gray-800">Comprobación de disponibilidad</h2><p class="mt-2 text-sm leading-6 text-gray-600">{{ $registro['comprobacion'] }}</p></section>
    <section aria-labelledby="modulos-historial" class="space-y-2">
        <h2 id="modulos-historial" class="px-1 text-sm font-bold text-gray-800">{{ $registro['fuente_modulos'] === 'registrado' ? 'Módulos cubiertos al aplicar el reemplazo' : 'Referencias actuales de los módulos' }}</h2>
        @if ($registro['fuente_modulos'] === 'referencias_actuales')<p class="px-1 text-xs leading-5 text-gray-500">El registro anterior conserva los identificadores. Estos horarios se consultan en los datos actuales y pueden haber cambiado después de la operación.</p>@endif
        <div class="grid min-w-0 grid-cols-1 gap-2 sm:grid-cols-2 xl:grid-cols-3">
            @forelse ($registro['modulos'] as $modulo)
                <article class="min-w-0 rounded-lg border border-gray-200 bg-white p-4 shadow-sm"><h3 class="text-sm font-semibold text-gray-800">{{ $modulo['dia'] ?: 'Día sin información' }}</h3><p class="mt-1 text-sm text-gray-700">{{ $modulo['hora_inicio'] && $modulo['hora_fin'] ? $modulo['hora_inicio'].'–'.$modulo['hora_fin'] : 'Horario sin información' }}</p><p class="mt-1 text-xs text-gray-500">{{ $modulo['bloque'] ? 'Módulo '.$modulo['bloque'].' · ' : '' }}Registro de horario #{{ $modulo['id'] }}</p></article>
            @empty
                <p class="rounded-lg border border-gray-200 bg-white p-4 text-sm text-gray-600 sm:col-span-2 xl:col-span-3">No hay detalle de módulos conservado para este registro.</p>
            @endforelse
        </div>
    </section>
</div>
@endsection

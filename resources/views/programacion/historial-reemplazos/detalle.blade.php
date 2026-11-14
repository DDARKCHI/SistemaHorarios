@extends('layouts.app')
@section('content')
<x-encabezado-pagina titulo="Detalle del reemplazo #{{ $registro['id'] }}">
    {{ $registro['fecha'] ?: 'Sin fecha registrada' }} · Realizado por {{ $registro['responsable'] }}
    <x-slot:acciones>
        <a href="{{ route('programacion.intercambio.historial') }}" class="inline-flex min-h-11 shrink-0 items-center justify-center rounded-lg border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Volver al historial</a>
    </x-slot:acciones>
</x-encabezado-pagina>

<div class="panel-editor">
    <section aria-labelledby="clase-historial" class="panel-contexto">
        <h2 id="clase-historial" class="break-words text-sm font-bold text-gray-900">{{ $registro['asignatura_codigo'] }} · {{ $registro['asignatura_nombre'] }}</h2>
        <p class="mt-1 break-words text-sm text-gray-600">Sección {{ $registro['seccion'] ?: 'sin dato' }} · {{ $registro['actividad'] ?: 'Actividad sin dato' }} · {{ $registro['grupo'] ?: 'Grupo sin dato' }}</p>
        <dl class="panel-resumen">
            <div><dt class="font-semibold text-gray-500">Período</dt><dd class="mt-1 break-words">{{ $registro['periodo'] }}</dd></div>
            <div><dt class="font-semibold text-gray-500">Versión</dt><dd class="mt-1 break-words">{{ $registro['version'] }} @if ($registro['version_numero'])· v{{ $registro['version_numero'] }}@endif</dd></div>
            <div><dt class="font-semibold text-gray-500">Módulos reemplazados</dt><dd class="mt-1 font-bold">{{ $registro['cantidad_modulos'] ?? 'Sin dato registrado' }}</dd></div>
        </dl>
    </section>
    <div class="panel-layout">
        <div class="panel-columna">
            <x-seccion-formulario :titulo="$registro['fuente_modulos'] === 'registrado' ? 'Módulos cubiertos al aplicar el reemplazo' : 'Referencias actuales de los módulos'" id="modulos-historial">
                @if ($registro['fuente_modulos'] === 'referencias_actuales')<p class="mb-4 text-xs leading-5 text-gray-500">El registro anterior conserva los identificadores. Estos horarios se consultan en los datos actuales y pueden haber cambiado después de la operación.</p>@endif
                <div class="panel-modulos">
                    @forelse ($registro['modulos'] as $modulo)
                        <article class="panel-modulo">
                            <h3 class="text-sm font-semibold text-gray-800">{{ $modulo['dia'] ?: 'Día sin información' }}</h3>
                            <p class="mt-1 text-sm text-gray-700">{{ $modulo['hora_inicio'] && $modulo['hora_fin'] ? $modulo['hora_inicio'].'–'.$modulo['hora_fin'] : 'Horario sin información' }}</p>
                            <p class="mt-1 text-xs text-gray-500">{{ $modulo['bloque'] ? 'Módulo '.$modulo['bloque'].' · ' : '' }}Registro de horario #{{ $modulo['id'] }}</p>
                        </article>
                    @empty
                        <p class="text-sm text-gray-600">No hay detalle de módulos conservado para este registro.</p>
                    @endforelse
                </div>
            </x-seccion-formulario>
        </div>
        <div class="panel-columna">
            <x-seccion-formulario titulo="Docentes del reemplazo" id="docentes-historial">
                @foreach (['origen' => 'Docente anterior', 'candidato' => 'Docente de reemplazo'] as $clave => $etiqueta)
                    <div class="panel-docente">
                        <h3 class="text-xs font-semibold text-gray-500">{{ $etiqueta }}</h3>
                        <p class="mt-1 break-words text-base font-semibold text-gray-900">{{ $registro[$clave] }}</p>
                        <p class="mt-1 text-xs text-gray-500">RUT: {{ $registro[$clave.'_rut'] ?: 'Sin RUT registrado' }}</p>
                    </div>
                @endforeach
            </x-seccion-formulario>
            <x-seccion-formulario titulo="Comprobación de disponibilidad" id="disponibilidad-historial">
                <p class="text-sm leading-6 text-gray-600">{{ $registro['comprobacion'] }}</p>
                @if (! $registro['detalle_registrado'])
                        <p role="note" class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm leading-6 text-amber-900">Este registro es anterior al detalle completo o conserva información parcial. Los nombres y datos de la clase que se muestran como referencia pueden provenir de la versión o de las fichas actuales. Los campos sin referencias disponibles se muestran como información no disponible.</p>
                    @else
                        <p class="px-1 text-xs leading-5 text-gray-600">Los datos de este detalle quedaron guardados al aplicar el reemplazo.</p>
                    @endif
            </x-seccion-formulario>
        </div>
    </div>
</div>
@endsection

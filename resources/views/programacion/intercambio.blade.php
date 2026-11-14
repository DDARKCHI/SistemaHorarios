@extends('layouts.app')
@section('title', 'Intercambio docente')
@section('content')
<x-encabezado-pagina titulo="Intercambio docente">
    Elige una clase, compara candidatos y confirma el reemplazo cuando sea compatible.
    <x-slot:acciones>
        <div class="flex shrink-0 flex-col gap-2 sm:flex-row">
            <a href="{{ route('programacion.intercambio.historial') }}" class="boton boton-secundario">Historial de reemplazos</a>
            <a href="{{ route('programacion.intercambio.carga') }}" class="boton boton-primario">Consultar carga por ramo</a>
        </div>
    </x-slot:acciones>
</x-encabezado-pagina>

<div class="min-w-0 space-y-4">

    @if (session('success'))<p role="status" class="rounded-lg border border-green-200 bg-green-50 p-4 text-sm text-green-800">{{ session('success') }}</p>@endif
    @if ($errors->any())<div role="alert" class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800"><ul class="list-inside list-disc">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    @if (! $versionActual)
        <section class="rounded-lg border border-gray-200 bg-white p-8 text-center"><h2 class="text-sm font-semibold">Sin programación semestral actual</h2><p class="mt-2 text-sm text-gray-500">Se necesita un período activo con una versión semestral actual para evaluar reemplazos.</p></section>
    @else
        <div class="flex flex-wrap items-center gap-2 text-sm text-gray-600"><span class="font-semibold">{{ $periodoActual->nombre }}</span><span class="rounded-full bg-white px-3 py-1 text-xs">Semestral v{{ $versionActual->version }} · {{ $versionActual->estado }}</span></div>
        <section class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm" aria-labelledby="clase-reemplazo">
            <h2 id="clase-reemplazo" class="mb-3 text-sm font-bold text-gray-800">1. Selecciona la clase que se quiere cubrir</h2>
            <form method="GET" action="{{ route('programacion.intercambio') }}" id="origen-reemplazo" class="grid min-w-0 items-end gap-3 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.5fr)_auto]">
                <div class="min-w-0"><label for="docente-reemplazo" class="mb-1.5 block font-semibold text-gray-700">Docente actual</label>
                    <select data-select-buscable id="docente-reemplazo" name="docente_id" class="h-11 w-full min-w-0 rounded-lg border border-gray-300 bg-white px-3 text-sm text-gray-700 focus:border-institucional focus:ring-1 focus:ring-institucional">
                        <option value="">Busca por RUT o nombre</option>@foreach ($docentes as $item)<option value="{{ $item->id }}" @selected($docente?->id === $item->id)>{{ trim($item->nombre.' '.$item->apellido) }} · {{ $item->rut }}</option>@endforeach
                    </select>
                </div>
                <div class="min-w-0"><label for="grupo-reemplazo" class="mb-1.5 block font-semibold text-gray-700">Ramo, sección y grupo</label>
                    <select data-select-buscable id="grupo-reemplazo" name="grupo_id" @disabled($cursos->isEmpty()) class="h-11 w-full min-w-0 rounded-lg border border-gray-300 bg-white px-3 text-sm text-gray-700 focus:border-institucional focus:ring-1 focus:ring-institucional">
                        <option value="">{{ $docente ? 'Selecciona una clase del docente' : 'Primero selecciona un docente' }}</option>
                        @foreach ($cursos as $item)<option value="{{ $item['grupo_id'] }}" @selected(($curso['grupo_id'] ?? null) === $item['grupo_id'])>{{ $item['asignatura']?->codigo }} · {{ $item['asignatura']?->nombre }} · Sec. {{ $item['seccion']->codigo }} · {{ $item['actividad']->tipo }} · {{ $item['grupo']->codigo }}</option>@endforeach
                    </select>
                </div>
                <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-institucional px-5 py-2 text-sm font-semibold text-white hover:bg-institucional-oscuro">Ver clase</button>
            </form>
            @if ($docente && $cursos->isEmpty())<p class="mt-3 text-sm text-gray-500">Este docente no tiene clases con grupos activos para reemplazar en esta versión.</p>@endif
        </section>
        @if ($curso)
            <section class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm" aria-label="Clase seleccionada">
                <div class="flex min-w-0 flex-wrap items-start justify-between gap-3"><div class="min-w-0"><h2 class="break-words text-sm font-bold text-gray-800">{{ $curso['asignatura']?->codigo }} · {{ $curso['asignatura']?->nombre }}</h2><p class="mt-1 text-sm text-gray-600">Sección {{ $curso['seccion']->codigo }} · {{ $curso['actividad']->tipo }} · {{ $curso['grupo']->codigo }}</p><p class="mt-1 text-sm text-gray-600">Docente: {{ trim($docente->nombre.' '.$docente->apellido) }}</p></div><span class="shrink-0 rounded-full bg-gray-100 px-3 py-1 text-xs font-semibold">{{ $objetivo->count() }} {{ $objetivo->count() === 1 ? 'módulo a cubrir' : 'módulos a cubrir' }}</span></div>
                <ul class="mt-3 flex flex-wrap gap-2">@foreach ($objetivo as $modulo)<li class="rounded-lg border border-gray-200 px-3 py-2 text-xs text-gray-600">{{ $modulo->dia?->nombre }} · {{ substr((string) $modulo->bloqueHorario?->hora_inicio, 0, 5) }}–{{ substr((string) $modulo->bloqueHorario?->hora_fin, 0, 5) }}</li>@endforeach</ul>
            </section>
            <section id="candidatos-reemplazo" class="scroll-mt-24 rounded-lg border border-gray-200 bg-white p-4 shadow-sm" aria-labelledby="buscar-candidato">
                <h2 id="buscar-candidato" class="mb-3 text-sm font-bold text-gray-800">2. Busca un posible reemplazo</h2>
                <form method="GET" action="{{ route('programacion.intercambio') }}#candidatos-reemplazo" class="grid min-w-0 items-end gap-3 sm:grid-cols-[minmax(0,1fr)_auto]">
                    <input type="hidden" name="docente_id" value="{{ $docente->id }}"><input type="hidden" name="grupo_id" value="{{ $curso['grupo_id'] }}">
                    <div class="min-w-0"><label for="busqueda-candidato" class="mb-1.5 block font-semibold text-gray-700">RUT o nombre del candidato</label><input id="busqueda-candidato" type="search" name="q" maxlength="80" value="{{ $datos['q'] ?? '' }}" placeholder="Ej.: apellido o RUT con o sin puntos" class="h-11 w-full min-w-0 rounded-lg border border-gray-300 px-3 text-base text-gray-800 focus:border-institucional focus:ring-1 focus:ring-institucional sm:text-sm"></div>
                    <button type="submit" class="min-h-11 rounded-lg bg-institucional px-5 py-2 text-sm font-semibold text-white hover:bg-institucional-oscuro">Buscar docentes</button>
                </form>
                @if ($candidatos)
                    <div class="mt-4 grid min-w-0 gap-3 sm:grid-cols-2 xl:grid-cols-3">
                        @forelse ($candidatos as $item)
                            <article class="flex min-w-0 flex-col gap-3 rounded-lg border border-gray-200 p-3">
                                <div class="min-w-0"><h3 class="break-words text-sm font-semibold text-gray-800">{{ trim($item->nombre.' '.$item->apellido) }}</h3><p class="mt-1 text-xs text-gray-500">RUT: {{ $item->rut ?: 'Sin RUT registrado' }}</p></div>
                                <a href="{{ route('programacion.intercambio', ['docente_id' => $docente->id, 'grupo_id' => $curso['grupo_id'], 'q' => $datos['q'] ?? null, 'candidato_id' => $item->id]) }}#comparacion-reemplazo" class="mt-auto inline-flex min-h-11 items-center justify-center rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm font-semibold text-institucional hover:bg-red-100">Comparar disponibilidad</a>
                            </article>
                        @empty<p class="text-sm text-gray-500 sm:col-span-2 xl:col-span-3">No se encontraron docentes activos con esa búsqueda.</p>@endforelse
                    </div>
                    @if ($candidatos->hasPages())<div class="mt-4">{{ $candidatos->fragment('candidatos-reemplazo')->links() }}</div>@endif
                @else<p class="mt-3 text-sm text-gray-500">Busca un docente para comparar su carga actual y la disponibilidad declarada.</p>@endif
            </section>
        @endif
        @if ($comparacion)
            @php
                $porCelda = $comparacion['carga']->groupBy(fn ($h) => $h->dia_id.'-'.$h->bloque_horario_id);
                $objetivoPorCelda = $comparacion['filas']->groupBy(fn ($f) => $f['horario']->dia_id.'-'.$f['horario']->bloque_horario_id);
                $disponiblePorCelda = $comparacion['disponibilidad']->filter(fn ($d) => mb_strtolower(trim((string) $d->tipo_disponibilidad)) === 'disponible')->keyBy(fn ($d) => $d->dia_id.'-'.$d->bloque_horario_id);
            @endphp
            <section id="comparacion-reemplazo" class="min-w-0 scroll-mt-24 space-y-3" aria-labelledby="titulo-comparacion">
                <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                    <div class="flex min-w-0 flex-wrap items-start justify-between gap-3"><div class="min-w-0"><h2 id="titulo-comparacion" class="text-sm font-bold text-gray-800">3. Compara y confirma el reemplazo</h2><p class="mt-1 break-words text-base font-semibold text-gray-900">{{ trim($comparacion['candidato']->nombre.' '.$comparacion['candidato']->apellido) }}</p></div><span @class(['rounded-full px-3 py-1 text-xs font-semibold', 'bg-green-50 text-green-800' => $comparacion['compatible'], 'bg-red-50 text-red-800' => ! $comparacion['compatible']])>{{ $comparacion['compatible'] ? 'Sin topes de clases' : 'Reemplazo no compatible' }}</span></div>
                    <dl class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-3">
                        <div class="rounded-lg bg-gray-50 p-3"><dt class="text-sm text-gray-600">Carga actual del candidato</dt><dd class="mt-1 text-xl font-bold">{{ $comparacion['carga']->count() }} <span class="text-xs font-normal">módulos</span></dd></div>
                        <div class="rounded-lg bg-gray-50 p-3"><dt class="text-sm text-gray-600">Módulos con topes</dt><dd class="mt-1 text-xl font-bold">{{ $comparacion['topes'] }}</dd></div>
                        <div class="rounded-lg bg-gray-50 p-3"><dt class="text-sm text-gray-600">Disponibilidad declarada</dt><dd class="mt-1 text-sm font-semibold">{{ $comparacion['sin_disponibilidad'] ? 'Sin informar' : $comparacion['cubiertos'].' de '.$objetivo->count().' módulos cubiertos' }}</dd></div>
                    </dl>
                    @if ($comparacion['horas_incompletas'])<p class="mt-3 rounded-lg bg-amber-50 p-3 text-sm text-amber-900">Hay módulos con horas incompletas. Corrige esos datos antes de confirmar un reemplazo.</p>@endif
                    @if ($comparacion['fuera'])<p class="mt-3 rounded-lg bg-red-50 p-3 text-sm text-red-800">{{ $comparacion['fuera'] }} módulos quedan fuera de la disponibilidad declarada.</p>@endif
                </div>
                <div class="grid min-w-0 gap-2 sm:grid-cols-2 xl:grid-cols-3">
                    @foreach ($comparacion['filas'] as $fila)
                        <article class="rounded-lg border border-gray-200 bg-white p-3"><p class="text-sm font-semibold">{{ $fila['horario']->dia?->nombre }} · {{ substr((string) $fila['horario']->bloqueHorario?->hora_inicio, 0, 5) }}–{{ substr((string) $fila['horario']->bloqueHorario?->hora_fin, 0, 5) }}</p><p class="mt-1 text-sm {{ $fila['conflictos']->isEmpty() ? 'text-green-700' : 'text-red-700' }}">{{ $fila['conflictos']->isEmpty() ? 'Sin topes con otras clases' : 'Coincide con otra clase del candidato' }}</p><p class="mt-1 text-xs text-gray-500">Disponibilidad: {{ $fila['disponible'] === null ? 'sin informar' : ($fila['disponible'] ? 'declarada' : 'fuera de lo declarado') }}</p>@foreach ($fila['conflictos'] as $tope)<p class="mt-1 break-words text-xs text-red-800">{{ $tope->actividadSeccion?->seccion?->asignatura?->codigo }} · {{ $tope->actividadSeccion?->seccion?->asignatura?->nombre }}</p>@endforeach</article>
                    @endforeach
                </div>
                <div class="min-w-0 overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                    <div class="flex flex-wrap gap-x-4 gap-y-2 border-b border-gray-200 p-3 text-xs text-gray-600"><span><span class="mr-1 inline-block h-2 w-2 rounded-full bg-gray-400" aria-hidden="true"></span>Clases actuales</span><span><span class="mr-1 inline-block h-2 w-2 rounded-full bg-sky-500" aria-hidden="true"></span>Clase a cubrir</span><span><span class="mr-1 inline-block h-2 w-2 rounded-full bg-green-500" aria-hidden="true"></span>Disponibilidad declarada</span><span><span class="mr-1 inline-block h-2 w-2 rounded-full bg-red-500" aria-hidden="true"></span>Coincidencia de clases</span></div>
                    <div class="relative max-h-[70vh] min-w-0 max-w-full overflow-auto" role="region" aria-label="Comparación semanal del candidato" tabindex="0">
                        <table class="w-full min-w-[1000px] table-fixed border-collapse text-left">
                            <caption class="sr-only">Clases actuales del candidato y módulos de la clase que se quiere cubrir</caption>
                            <colgroup><col class="w-28">@foreach ($dias as $dia)<col>@endforeach</colgroup>
                            <thead><tr><th scope="col" class="sticky top-0 left-0 z-30 border-b border-r border-gray-200 bg-gray-100 px-2 py-3 text-xs">Horario</th>@foreach ($dias as $dia)<th scope="col" class="sticky top-0 z-20 border-b border-r border-gray-200 bg-gray-100 px-2 py-3 text-center text-xs">{{ $dia->nombre }}</th>@endforeach</tr></thead>
                            <tbody>@foreach ($bloques as $bloque)<tr class="align-top"><th scope="row" class="sticky left-0 z-10 border-b border-r border-gray-200 bg-gray-50 px-2 py-3 text-xs font-normal">{{ substr((string) $bloque->hora_inicio, 0, 5) }}–{{ substr((string) $bloque->hora_fin, 0, 5) }}</th>
                                @foreach ($dias as $dia)
                                    @php($clave = $dia->id.'-'.$bloque->id)
                                    @php($solicitados = $objetivoPorCelda->get($clave, collect()))
                                    @php($compartidos = $solicitados->pluck('horario.id'))
                                    @php($topesCelda = $solicitados->contains(fn ($f) => $f['conflictos']->isNotEmpty()))
                                    <td @class([
                                        'border-b border-r border-gray-200 p-1.5',
                                        'bg-red-50' => $topesCelda,
                                        'bg-sky-50' => ! $topesCelda && $solicitados->isNotEmpty(),
                                        'bg-green-50' => ! $topesCelda && $solicitados->isEmpty() && $disponiblePorCelda->has($clave),
                                    ])>
                                        <div class="space-y-2">@foreach ($porCelda->get($clave, collect())->reject(fn ($h) => $compartidos->contains($h->id)) as $horario)<div><p class="mb-1 text-[10px] font-semibold text-gray-500">Clase actual</p><x-intercambio-clase :horario="$horario" /></div>@endforeach
                                            @foreach ($solicitados as $fila)<div><p class="mb-1 text-[10px] font-semibold {{ $fila['conflictos']->isEmpty() ? 'text-sky-800' : 'text-red-800' }}">{{ $fila['conflictos']->isEmpty() ? 'Clase a cubrir' : 'Tope de horario' }}</p><x-intercambio-clase :horario="$fila['horario']" /></div>@endforeach
                                        </div>
                                    </td>
                                @endforeach
                            </tr>@endforeach</tbody>
                        </table>
                    </div>
                </div>
                @can('asignar profesores')
                    <form method="POST" action="{{ route('programacion.intercambio.aplicar') }}" class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm" onsubmit="return confirm('Se sustituirá al docente de esta clase en la versión actual. ¿Confirmas el reemplazo?')">
                        @csrf
                        @foreach (['docente_id' => $docente->id, 'grupo_id' => $curso['grupo_id'], 'candidato_id' => $comparacion['candidato']->id, 'periodo_id' => $periodoActual->id, 'version_id' => $versionActual->id, 'huella' => $huella] as $campo => $valor)<input type="hidden" name="{{ $campo }}" value="{{ $valor }}">@endforeach
                        <p class="text-sm text-gray-700">Se reemplazará a <strong>{{ trim($docente->nombre.' '.$docente->apellido) }}</strong> por <strong>{{ trim($comparacion['candidato']->nombre.' '.$comparacion['candidato']->apellido) }}</strong> en los {{ $objetivo->count() }} módulos de esta clase.</p>
                        @if ($comparacion['sin_disponibilidad'])<label class="mt-3 flex min-h-11 items-start gap-3 rounded-lg bg-amber-50 p-3 text-amber-900"><input type="checkbox" name="confirmar_disponibilidad" value="1" required class="mt-1 h-4 w-4 shrink-0 rounded border-gray-300">El docente no ha registrado disponibilidad para este período. Confirmo que consulté con él y puede cubrir estos módulos.</label>@endif
                        @if ($versionActual->estado !== 'Borrador')<p class="mt-3 text-sm text-amber-800">Solo se pueden aplicar reemplazos en una versión en borrador.</p>@endif
                        <button type="submit" @disabled(! $comparacion['compatible'] || $versionActual->estado !== 'Borrador') class="mt-3 inline-flex min-h-11 w-full items-center justify-center rounded-lg bg-institucional px-5 py-2 text-sm font-semibold text-white hover:bg-institucional-oscuro disabled:cursor-not-allowed disabled:bg-gray-200 disabled:text-gray-500 sm:w-auto">Confirmar reemplazo</button>
                    </form>
                @endcan
            </section>
        @endif
    @endif
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const docente = document.getElementById('docente-reemplazo');
    const grupo = document.getElementById('grupo-reemplazo');
    if (docente && grupo) docente.addEventListener('change', function () { grupo.value = ''; document.getElementById('origen-reemplazo').submit(); });
});
</script>
@endsection

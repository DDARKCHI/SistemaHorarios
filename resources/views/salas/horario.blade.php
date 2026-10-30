@extends('layouts.app')

@section('content')
@php
    $vista = $vista ?? null;
    $urlHorario = fn (array $cambios = []) => route('salas.horario', array_filter(
        array_replace($parametros, $cambios), fn ($valor) => $valor !== null && $valor !== ''
    )).(isset($cambios['vista']) ? ($cambios['vista'] === 'semana' ? '#semana-espacio' : '#dia-espacio') : (isset($cambios['dia_id']) ? '#dia-espacio' : ''));
    $claseSemanal = $vista === 'dia' ? 'hidden' : ($vista === 'semana' ? '' : 'hidden xl:block');
    $claseDiaria = $vista === 'semana' ? 'hidden' : ($vista === 'dia' ? '' : 'xl:hidden');
    $indiceDia = $dias->search(fn ($dia) => $dia->id === $diaSeleccionado?->id);
    $diaAnterior = $indiceDia !== false && $indiceDia > 0 ? $dias->get($indiceDia - 1) : null;
    $diaSiguiente = $indiceDia !== false ? $dias->get($indiceDia + 1) : null;
    $sesionesDia = $diaSeleccionado ? $sesiones->where('dia_id', $diaSeleccionado->id) : collect();
@endphp

<x-encabezado-pagina titulo="Horario de espacios">
    Consulta las clases registradas en una sala o laboratorio, por día y semana.
    <x-slot:acciones>
        <a href="{{ route('salas.index') }}" class="inline-flex min-h-11 shrink-0 items-center justify-center rounded-lg border border-gray-300 px-3 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50">Catálogo de salas</a>
    </x-slot:acciones>
</x-encabezado-pagina>

<div class="min-w-0 w-full space-y-3">

    @if ($errors->any())
        <div role="alert" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
            <p class="font-semibold">Revisa la selección del horario.</p>
            <ul class="mt-1 list-inside list-disc space-y-1">
                @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
    @endif

    <details class="min-w-0 rounded-lg border border-gray-200 bg-white p-3 shadow-sm" @if (! $espacioSeleccionado || ! $periodoSeleccionado || ! $versionSeleccionada || $errors->any()) open @endif>
    <summary class="min-h-11 cursor-pointer px-1 py-3 text-sm font-semibold text-gray-700 marker:text-institucional">{{ $espacioSeleccionado && $periodoSeleccionado && $versionSeleccionada ? 'Cambiar espacio o programación' : 'Seleccionar espacio y programación' }}</summary>
    <form method="GET" action="{{ route('salas.horario') }}" aria-label="Seleccionar espacio y programación" class="mt-2 grid min-w-0 grid-cols-1 items-end gap-3 sm:grid-cols-2 xl:grid-cols-[minmax(0,1.5fr)_minmax(0,1fr)_minmax(0,1.2fr)_auto]">
        <div class="min-w-0 sm:col-span-2 xl:col-span-1">
            <label for="espacio-horario" class="mb-1.5 block text-sm font-semibold leading-5 text-gray-700">Sala o laboratorio</label>
            <select data-select-buscable id="espacio-horario" name="espacio" class="h-11 w-full min-w-0 rounded-lg border border-gray-300 bg-white px-2 text-base text-gray-800 focus:border-institucional focus:ring-1 focus:ring-institucional sm:text-sm">
                <option value="">Selecciona un espacio</option>
                @foreach (['sala' => 'Salas', 'laboratorio' => 'Laboratorios fijos', 'movil' => 'Laboratorios móviles'] as $tipoEspacio => $etiquetaGrupo)
                    @if ($espacios->contains('tipo', $tipoEspacio))
                        <optgroup label="{{ $etiquetaGrupo }}">
                            @foreach ($espacios->where('tipo', $tipoEspacio) as $opcion)
                                <option value="{{ $opcion['clave'] }}" data-universidad="{{ $opcion['universidad_id'] }}" @selected(($espacioSeleccionado['clave'] ?? '') === $opcion['clave'])>{{ $opcion['etiqueta'] }}</option>
                            @endforeach
                        </optgroup>
                    @endif
                @endforeach
            </select>
        </div>
        <div class="min-w-0">
            <label for="periodo-horario-espacio" class="mb-1.5 block text-sm font-semibold leading-5 text-gray-700">Período académico</label>
            <select data-select-buscable id="periodo-horario-espacio" name="periodo_id" class="h-11 w-full min-w-0 rounded-lg border border-gray-300 bg-white px-2 text-base text-gray-800 focus:border-institucional focus:ring-1 focus:ring-institucional sm:text-sm">
                <option value="">Selecciona un período</option>
                @foreach ($periodos as $periodo)
                    <option value="{{ $periodo->id }}" data-universidad="{{ $periodo->universidad_id }}" @selected($periodoSeleccionado?->id === $periodo->id)>{{ $periodo->nombre }}{{ $periodo->estado ? ' · Activo' : '' }}</option>
                @endforeach
            </select>
        </div>
        <div class="min-w-0">
            <label for="version-horario-espacio" class="mb-1.5 block text-sm font-semibold leading-5 text-gray-700">Versión de programación</label>
            <select data-select-buscable id="version-horario-espacio" name="version_id" class="h-11 w-full min-w-0 rounded-lg border border-gray-300 bg-white px-2 text-base text-gray-800 focus:border-institucional focus:ring-1 focus:ring-institucional sm:text-sm">
                <option value="">Selecciona una versión</option>
                @foreach ($versiones as $version)
                    <option value="{{ $version->id }}" @selected($versionSeleccionada?->id === $version->id)>{{ $version->tipo === 'semestral' ? 'Semestral' : 'Importación anterior' }} · v{{ $version->version }} · {{ $version->estado }}{{ $version->es_actual ? ' · Actual' : '' }}</option>
                @endforeach
            </select>
        </div>
        @if ($vista)<input type="hidden" name="vista" value="{{ $vista }}">@endif
        @if ($diaSeleccionado)<input type="hidden" name="dia_id" value="{{ $diaSeleccionado->id }}">@endif
        <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-institucional px-5 py-2 text-sm font-semibold text-white hover:bg-institucional-oscuro sm:col-span-2 xl:col-span-1">Consultar</button>
    </form>
    </details>

    @if ($espacioSeleccionado)
        <section aria-labelledby="espacio-consultado" class="rounded-lg border border-gray-200 bg-white px-4 py-4 shadow-sm">
            <div class="grid min-w-0 gap-4 sm:grid-cols-2 xl:grid-cols-[minmax(0,1fr)_minmax(0,1.2fr)_auto]">
                <div class="min-w-0">
                    <p class="text-sm font-semibold leading-5 text-gray-700">{{ $espacioSeleccionado['tipo'] === 'sala' ? 'Sala consultada' : ($espacioSeleccionado['tipo'] === 'movil' ? 'Laboratorio móvil consultado' : 'Laboratorio consultado') }}</p>
                    <h2 id="espacio-consultado" class="mt-1 break-words text-lg font-bold text-gray-900">{{ $espacioSeleccionado['codigo'] ?: 'Sin código' }}</h2>
                    @if (filled($espacioSeleccionado['nombre']) && $espacioSeleccionado['nombre'] !== $espacioSeleccionado['codigo'])
                        <p class="mt-0.5 break-words text-sm text-gray-700">{{ $espacioSeleccionado['nombre'] }}</p>
                    @endif
                    <p class="mt-1 break-words text-xs text-gray-500">{{ $espacioSeleccionado['sede'] ?: 'Sin sede registrada' }}</p>
                </div>
                <dl class="grid min-w-0 grid-cols-2 gap-3">
                    <div class="min-w-0">
                        <dt class="text-sm font-semibold leading-5 text-gray-700">Capacidad registrada</dt>
                        <dd class="mt-1 text-sm text-gray-700">{{ $espacioSeleccionado['capacidad'] > 0 ? $espacioSeleccionado['capacidad'].($espacioSeleccionado['capacidad'] == 1 ? ' persona' : ' personas') : 'Sin capacidad informada' }}</dd>
                    </div>
                    <div class="min-w-0">
                        <dt class="text-sm font-semibold leading-5 text-gray-700">Ubicación</dt>
                        <dd class="mt-1 break-words text-sm text-gray-700">{{ $espacioSeleccionado['tipo'] === 'movil' ? 'Recurso móvil' : ($espacioSeleccionado['edificio'] ?: 'Sin edificio registrado') }}{{ filled($espacioSeleccionado['piso']) ? ' · Piso '.$espacioSeleccionado['piso'] : '' }}</dd>
                    </div>
                </dl>
                <div class="flex min-w-0 flex-wrap items-start gap-2 text-[11px] sm:col-span-2 xl:col-span-1 xl:max-w-56 xl:justify-end">
                    <span @class(['rounded-full px-2.5 py-1 font-semibold', 'bg-green-50 text-green-800' => $espacioSeleccionado['estado'], 'bg-gray-100 text-gray-600' => ! $espacioSeleccionado['estado']])>{{ $espacioSeleccionado['estado'] ? 'Registro activo' : 'Registro inactivo' }}</span>
                    <span class="rounded-full bg-gray-100 px-2.5 py-1 font-medium text-gray-600">{{ $espacioSeleccionado['disponible'] ? 'Uso habilitado en catálogo' : 'Uso deshabilitado en catálogo' }}</span>
                </div>
            </div>
            @if ($espacioSeleccionado['tipo'] === 'laboratorio')
                <div class="mt-3 flex min-w-0 flex-col gap-2 border-t border-gray-100 pt-3 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-xs leading-relaxed text-gray-600">Se muestran las asignaciones explícitas al laboratorio. Consulta también el uso de la sala que lo contiene.</p>
                    <a href="{{ $urlHorario(['espacio' => 'sala:'.$espacioSeleccionado['sala_id']]) }}" class="inline-flex min-h-11 shrink-0 items-center justify-center rounded-lg border border-gray-300 px-3 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50">Ver sala {{ $espacioSeleccionado['sala_codigo'] }}</a>
                </div>
            @elseif ($espacioSeleccionado['tipo'] === 'movil')
                <p class="mt-3 border-t border-gray-100 pt-3 text-xs leading-relaxed text-gray-500">Se muestran las asignaciones de este laboratorio móvil en la versión seleccionada. Cada clase indica la sala cuando está registrada.</p>
            @else
                <p class="mt-3 border-t border-gray-100 pt-3 text-xs leading-relaxed text-gray-500">Incluye las asignaciones directas a esta sala y las de los laboratorios ubicados en ella, sin repetir clases.</p>
            @endif
        </section>
    @endif

    @if (! $espacioSeleccionado)
        <section class="rounded-lg border border-gray-200 bg-white px-4 py-12 text-center shadow-sm">
            <h2 class="text-sm font-semibold text-gray-700">Selecciona una sala o laboratorio</h2>
            <p class="mx-auto mt-2 max-w-lg text-sm leading-relaxed text-gray-500">Elige un espacio para consultar sus clases, los docentes y los módulos de cada día.</p>
            @if ($espacios->isEmpty())<p class="mt-2 text-xs text-gray-500">Todavía no hay espacios registrados en el catálogo.</p>@endif
        </section>
    @elseif (! $periodoSeleccionado)
        <section class="rounded-lg border border-gray-200 bg-white px-4 py-10 text-center shadow-sm">
            <h2 class="text-sm font-semibold text-gray-700">Selecciona un período académico</h2>
            <p class="mt-2 text-sm text-gray-500">Elige el período cuya programación deseas consultar.</p>
            @if ($periodos->isEmpty())<p class="mt-2 text-xs text-gray-500">No hay períodos registrados para este espacio.</p>@endif
        </section>
    @elseif (! $versionSeleccionada)
        <section class="rounded-lg border border-gray-200 bg-white px-4 py-10 text-center shadow-sm">
            <h2 class="text-sm font-semibold text-gray-700">Selecciona una versión de programación</h2>
            <p class="mx-auto mt-2 max-w-lg text-sm leading-relaxed text-gray-500">Consulta una versión semestral o una importación anterior del período seleccionado.</p>
            @if ($versiones->isEmpty())<p class="mt-2 text-xs text-gray-500">Este período todavía no tiene versiones disponibles.</p>@endif
        </section>
    @else
        <section aria-label="Resumen del horario" class="space-y-2">
            <div class="flex min-w-0 flex-wrap items-center gap-2 px-1 text-xs text-gray-600">
                <span class="break-words font-semibold">{{ $periodoSeleccionado->nombre }}</span>
                <span class="rounded-full bg-white px-2.5 py-1 text-[11px] font-semibold text-gray-700">{{ $versionSeleccionada->tipo === 'semestral' ? 'Semestral' : 'Importación anterior' }} · v{{ $versionSeleccionada->version }}</span>
                <span class="rounded-full border border-gray-200 px-2.5 py-1 text-[11px] text-gray-600">{{ $versionSeleccionada->estado }}</span>
            </div>
            <dl class="grid grid-cols-1 gap-2 sm:grid-cols-3">
                <div class="flex min-w-0 items-center justify-between gap-3 rounded-lg border border-gray-200 bg-white px-3 py-3 shadow-sm sm:block"><dt class="text-sm font-semibold leading-5 text-gray-700">Bloques con clases</dt><dd class="text-xl font-bold sm:mt-1 text-gray-800">{{ $resumen['bloques'] }}</dd></div>
                <div class="flex min-w-0 items-center justify-between gap-3 rounded-lg border border-gray-200 bg-white px-3 py-3 shadow-sm sm:block"><dt class="text-sm font-semibold leading-5 text-gray-700">Asignaturas</dt><dd class="text-xl font-bold sm:mt-1 text-gray-800">{{ $resumen['asignaturas'] }}</dd></div>
                <div class="flex min-w-0 items-center justify-between gap-3 rounded-lg border border-gray-200 bg-white px-3 py-3 shadow-sm sm:block"><dt class="text-sm font-semibold leading-5 text-gray-700">Bloques con varias clases</dt><dd class="text-xl font-bold sm:mt-1 {{ $resumen['coincidencias'] ? 'text-amber-700' : 'text-gray-800' }}">{{ $resumen['coincidencias'] }}</dd></div>
            </dl>
        </section>

        @if ($sesiones->isEmpty())
            <section class="rounded-lg border border-gray-200 bg-white px-4 py-10 text-center shadow-sm">
                <h2 class="text-sm font-semibold text-gray-700">Sin clases registradas en esta versión</h2>
                <p class="mx-auto mt-2 max-w-lg text-sm leading-relaxed text-gray-500">No hay asignaciones activas al espacio seleccionado en esta programación.</p>
            </section>
        @else
            <div id="calendario-espacio" class="scroll-mt-32"></div>
            <div class="flex min-w-0 flex-col gap-2 rounded-lg border border-gray-200 bg-white px-3 py-3 shadow-sm sm:flex-row sm:items-center sm:justify-between">
                <p class="text-xs text-gray-600">Consulta diaria o semana completa</p>
                <nav aria-label="Vista del horario" class="flex gap-2">
                    <a href="{{ $urlHorario(['vista' => 'dia']) }}" @if ($vista === 'dia') aria-current="page" @endif @class(['inline-flex min-h-11 flex-1 items-center justify-center rounded-lg border px-4 py-2 text-xs font-semibold sm:flex-none', 'border-institucional bg-institucional text-white' => $vista === 'dia', 'border-gray-300 bg-white text-gray-700 hover:bg-gray-50' => $vista === 'semana', 'border-institucional bg-institucional text-white xl:border-gray-300 xl:bg-white xl:text-gray-700' => $vista === null])>Por día</a>
                    <a href="{{ $urlHorario(['vista' => 'semana']) }}" @if ($vista === 'semana') aria-current="page" @endif @class(['inline-flex min-h-11 flex-1 items-center justify-center rounded-lg border px-4 py-2 text-xs font-semibold sm:flex-none', 'border-institucional bg-institucional text-white' => $vista === 'semana', 'border-gray-300 bg-white text-gray-700 hover:bg-gray-50' => $vista === 'dia', 'border-gray-300 bg-white text-gray-700 xl:border-institucional xl:bg-institucional xl:text-white' => $vista === null])>Semana</a>
                </nav>
            </div>

            @if ($resumen['coincidencias'] > 0)
                <p class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-xs leading-relaxed text-amber-900">Hay más de una clase registrada en algunos bloques. Se muestran todas las asignaciones para que puedas revisarlas.</p>
            @endif

            <section id="semana-espacio" class="min-w-0 scroll-mt-32 {{ $claseSemanal }}" aria-labelledby="titulo-semana-espacio">
                <h2 id="titulo-semana-espacio" class="px-1 text-sm font-bold text-gray-800">Horario semanal · {{ $espacioSeleccionado['codigo'] }}</h2>
                <p class="mb-2 mt-1 break-words px-1 text-[11px] text-gray-500">{{ $periodoSeleccionado->nombre }} · {{ $versionSeleccionada->tipo === 'semestral' ? 'Semestral' : 'Importación anterior' }} v{{ $versionSeleccionada->version }}</p>
                <div class="min-w-0 overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                    <p class="border-b border-gray-200 px-3 py-2 text-[11px] text-gray-500">Desplaza la tabla para consultar todos los días si es necesario.</p>
                    <div role="region" aria-label="Horario semanal del espacio" tabindex="0" class="relative max-h-[75vh] min-w-0 max-w-full overflow-auto focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-institucional">
                        <table class="w-full min-w-[1100px] table-fixed border-collapse text-left">
                            <caption class="sr-only">Clases del espacio por día y módulo horario</caption>
                            <colgroup><col class="w-28">@foreach ($dias as $dia)<col>@endforeach</colgroup>
                            <thead class="bg-gray-100">
                                <tr>
                                    <th scope="col" class="sticky top-0 left-0 z-30 border-b border-r border-gray-200 bg-gray-100 px-3 py-3 text-xs font-bold text-gray-700">Horario</th>
                                    @foreach ($dias as $dia)
                                        <th scope="col" class="sticky top-0 z-20 border-b border-r border-gray-200 bg-gray-100 px-2 py-3 text-center text-xs font-bold text-gray-700 last:border-r-0">{{ $dia->nombre }} @if (! $dia->estado)<span class="mt-1 block text-[10px] font-normal text-gray-500">Registro inactivo</span>@endif</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($bloques as $bloque)
                                    <tr class="align-top">
                                        <th scope="row" class="sticky left-0 z-10 border-b border-r border-gray-200 bg-gray-50 px-3 py-3 text-xs font-normal text-gray-600">
                                            <span class="block font-semibold tabular-nums text-gray-800">{{ substr((string) $bloque->hora_inicio, 0, 5) }}–{{ substr((string) $bloque->hora_fin, 0, 5) }}</span>
                                            <span class="mt-1 block text-[10px]">Módulo {{ $bloque->numero }}</span>
                                            @if ($bloque->es_almuerzo)<span class="mt-1 block text-[10px] text-gray-500">Almuerzo</span>@endif
                                            @if (! $bloque->estado)<span class="mt-1 block text-[10px] text-gray-500">Bloque inactivo</span>@endif
                                        </th>
                                        @foreach ($dias as $dia)
                                            @php($clases = $celdas->get($dia->id.'-'.$bloque->id, collect()))
                                            <td class="border-b border-r border-gray-200 p-2 last:border-r-0">
                                                @if ($clases->count() > 1)<p class="mb-2 rounded-md bg-amber-50 px-2 py-1.5 text-[10px] font-semibold text-amber-800">Varias clases registradas en este bloque</p>@endif
                                                <div class="space-y-2">
                                                    @forelse ($clases as $sesion)
                                                        <x-clase-espacio :sesion="$sesion" :compacta="true" />
                                                    @empty
                                                        <p class="py-2 text-center text-[11px] text-gray-400"><span class="sr-only">Sin asignación registrada</span></p>
                                                    @endforelse
                                                </div>
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>

            <section id="dia-espacio" class="min-w-0 scroll-mt-32 space-y-3 {{ $claseDiaria }}" aria-labelledby="titulo-dia-espacio">
                <form method="GET" action="{{ route('salas.horario') }}#dia-espacio" aria-label="Elegir día del horario" class="rounded-lg border border-gray-200 bg-white p-3 shadow-sm">
                    @foreach ($parametros as $nombreParametro => $valorParametro)
                        @if ($nombreParametro !== 'dia_id' && $valorParametro !== null && $valorParametro !== '')<input type="hidden" name="{{ $nombreParametro }}" value="{{ $valorParametro }}">@endif
                    @endforeach
                    <label for="dia-horario-espacio" class="mb-1.5 block text-sm font-semibold leading-5 text-gray-700">Día a consultar</label>
                    <div class="flex min-w-0 flex-wrap items-center gap-2">
                        @if ($diaAnterior)
                            <a href="{{ $urlHorario(['dia_id' => $diaAnterior->id]) }}" aria-label="Día anterior" class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50"><span aria-hidden="true">←</span></a>
                        @else
                            <span aria-disabled="true" aria-label="Día anterior" class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-lg border border-gray-200 text-gray-300"><span aria-hidden="true">←</span></span>
                        @endif
                        <select id="dia-horario-espacio" name="dia_id" class="h-11 min-w-0 flex-1 rounded-lg border border-gray-300 bg-white px-2 text-base text-gray-800 focus:border-institucional focus:ring-1 focus:ring-institucional sm:text-sm">
                            @foreach ($dias as $dia)<option value="{{ $dia->id }}" @selected($diaSeleccionado?->id === $dia->id)>{{ $dia->nombre }}{{ $dia->estado ? '' : ' · Inactivo' }}</option>@endforeach
                        </select>
                        @if ($diaSiguiente)
                            <a href="{{ $urlHorario(['dia_id' => $diaSiguiente->id]) }}" aria-label="Día siguiente" class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50"><span aria-hidden="true">→</span></a>
                        @else
                            <span aria-disabled="true" aria-label="Día siguiente" class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-lg border border-gray-200 text-gray-300"><span aria-hidden="true">→</span></span>
                        @endif
                        <button type="submit" class="inline-flex min-h-11 w-full items-center justify-center rounded-lg bg-institucional px-4 py-2 text-xs font-semibold text-white hover:bg-institucional-oscuro sm:w-auto">Ver día</button>
                    </div>
                </form>
                <div class="flex flex-wrap items-baseline justify-between gap-2 px-1">
                    <h2 id="titulo-dia-espacio" class="break-words text-sm font-bold text-gray-800">{{ $diaSeleccionado?->nombre ?? 'Agenda diaria' }} · {{ $espacioSeleccionado['codigo'] }}</h2>
                    <p class="text-xs text-gray-500">{{ $sesionesDia->count() }} {{ $sesionesDia->count() === 1 ? 'asignación de módulo' : 'asignaciones de módulos' }}</p>
                </div>
                <p class="break-words px-1 text-[11px] text-gray-500">{{ $periodoSeleccionado->nombre }} · {{ $versionSeleccionada->tipo === 'semestral' ? 'Semestral' : 'Importación anterior' }} v{{ $versionSeleccionada->version }}</p>
                @if ($sesionesDia->isEmpty())
                    <div class="rounded-lg border border-gray-200 bg-white px-4 py-10 text-center shadow-sm">
                        <h3 class="text-sm font-semibold text-gray-700">Sin clases registradas para este día</h3>
                        <p class="mt-2 text-sm text-gray-500">Consulta otro día o revisa la semana completa.</p>
                    </div>
                @else
                    <ol class="space-y-3">
                        @foreach ($bloques as $bloque)
                            @php($clases = $celdas->get($diaSeleccionado->id.'-'.$bloque->id, collect()))
                            @if ($clases->isNotEmpty())
                                <li class="grid min-w-0 grid-cols-[4.5rem_minmax(0,1fr)] items-start gap-2 sm:grid-cols-[6rem_minmax(0,1fr)] sm:gap-3">
                                    <div class="min-w-0 pt-3 text-xs text-gray-500">
                                        <p class="font-bold tabular-nums text-gray-800">{{ substr((string) $bloque->hora_inicio, 0, 5) }}</p>
                                        <p class="mt-0.5 tabular-nums">{{ substr((string) $bloque->hora_fin, 0, 5) }}</p>
                                        <p class="mt-1 text-[10px]">Módulo {{ $bloque->numero }}</p>
                                        @if (! $bloque->estado)<p class="mt-1 text-[10px]">Bloque inactivo</p>@endif
                                    </div>
                                    <div class="min-w-0 space-y-2">
                                        @if ($clases->count() > 1)<p class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs font-semibold text-amber-900">Varias clases registradas en este bloque</p>@endif
                                        @foreach ($clases as $sesion)<x-clase-espacio :sesion="$sesion" />@endforeach
                                    </div>
                                </li>
                            @endif
                        @endforeach
                    </ol>
                @endif
            </section>
        @endif
        <p class="px-1 text-[11px] leading-relaxed text-gray-500">Se muestran las asignaciones registradas en la versión consultada. La ausencia de clases no confirma que el espacio esté disponible para una nueva asignación.</p>
    @endif
</div>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const espacio = document.getElementById('espacio-horario');
        const periodo = document.getElementById('periodo-horario-espacio');
        const version = document.getElementById('version-horario-espacio');

        periodo.addEventListener('change', function () {
            version.value = '';
            version.dispatchEvent(new Event('change', { bubbles: true }));
        });
        espacio.addEventListener('change', function () {
            const institucionEspacio = espacio.selectedOptions[0]?.dataset.universidad;
            const institucionPeriodo = periodo.selectedOptions[0]?.dataset.universidad;
            if (institucionEspacio && institucionPeriodo && institucionEspacio !== institucionPeriodo) {
                periodo.value = '';
                periodo.dispatchEvent(new Event('change', { bubbles: true }));
            }
        });
    });
</script>
@endsection

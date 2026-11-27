@extends('layouts.app')

@section('content')

@php
    $versionConsultada = $versiones->firstWhere('id', (int) $versionId);
@endphp

<x-encabezado-pagina titulo="Programación semestral">
    <x-slot:acciones>
        @can('generar programacion semestral')
        <a class="boton boton-primario" href="{{ route('programacion.completar.index', ['periodo_id' => $periodoId, 'version_id' => $versionId]) }}">Completar programación</a>
        @endcan
        @can('gestionar conflictos')
        <a href="{{ route('programacion.validacion', array_filter(['periodo_id' => $periodoId, 'version_id' => $versionId])) }}" class="boton boton-secundario">Revisar conflictos</a>
        @endcan
        @canany(['administrar usuarios', 'asignar horarios', 'generar programacion semestral'])
            <details data-menu-acciones class="relative">
                <summary class="boton boton-secundario w-full">Más acciones</summary>
                <div class="z-40 mt-2 grid gap-0.5 rounded-xl border border-borde bg-superficie p-1.5 shadow-lg sm:absolute sm:left-0 sm:w-64 xl:left-auto xl:right-0">
                    @can('administrar usuarios')
                        <a class="accion-menu" href="{{ route('administracion.secciones.index', ['periodo_id' => $periodoId]) }}">Oferta del período</a>
                    @endcan
                    @can('asignar horarios')
                        <a class="accion-menu" href="{{ route('programacion.cambios.index', ['periodo_id' => $periodoId]) }}">Solicitudes de cambio</a>
                    @endcan
                    @can('generar programacion semestral')
                        <a class="accion-menu" href="{{ route('programacion.preparacion', ['periodo_id' => $periodoId]) }}">Preparar generación</a>
                        @if ($versionConsultada)
                            <a class="accion-menu" href="{{ route('programacion.version.cierre', $versionConsultada) }}">Aprobación y publicación</a>
                            @if (!$versionConsultada->es_actual && $versionConsultada->estado === 'Borrador')
                                <a class="accion-menu" href="{{ route('programacion.version.revisar', $versionConsultada) }}">Revisar para activar</a>
                            @endif
                        @endif
                    @endcan
                </div>
            </details>
        @endcanany
    </x-slot:acciones>
</x-encabezado-pagina>

<div class="min-w-0 w-full">
    @if ($versionConsultada && in_array($versionConsultada->estado, ['Aprobado', 'Publicado'], true))
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-borde bg-superficie p-4 text-sm"><p><strong>{{ $versionConsultada->estado }}</strong> · Esta versión está cerrada a modificaciones de horarios. Para ajustar su programación, crea un nuevo borrador.</p>@if ($versionConsultada->estado === 'Publicado')<a class="boton boton-secundario" href="{{ route('programacion.version.publicada', $versionConsultada) }}">Consultar copia publicada</a>@endif</div>
    @endif

    @if (session('success') || $errors->any())
        <div class="mb-3 space-y-2">@include('disponibilidad.avisos')</div>
    @endif

    @can('generar programacion semestral')
        <div class="mb-2 rounded-lg border border-gray-200 bg-white px-4 py-4 shadow-sm">
            <div class="grid min-w-0 items-center gap-4 xl:grid-cols-[minmax(0,1.15fr)_minmax(0,1fr)]">

                <div class="min-w-0">
                    <div class="text-sm font-semibold text-gray-800">
                        Generar programación semestral
                    </div>

                    <div class="mt-0.5 text-xs text-gray-500">
                        Completa horarios y espacios según disponibilidad, capacidad y prioridad, conservando las asignaciones de la base.
                    </div>
                </div>

                @if ($versionesBase->isNotEmpty())
                    <form
                        method="POST"
                        action="{{ route('programacion.semestral.generar') }}"
                        class="grid min-w-0 w-full gap-3 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-end"
                        data-confirmar="Se generará un borrador nuevo a partir de las asignaciones de la base, completando los horarios y espacios pendientes."
                        data-confirmar-titulo="Generar programación semestral"
                        data-confirmar-accion="Generar"
                        data-confirmar-seleccion="version_base_id"
                    >
                        @csrf
                        <input type="hidden" name="solicitud" value="{{ (string) \Illuminate\Support\Str::uuid() }}">

                        <div class="min-w-0">
                            <label
                                for="version_base_id"
                                class="mb-1.5 block text-sm font-semibold leading-5 text-gray-700"
                            >
                                Versión base
                            </label>

                            <select data-select-buscable
                                id="version_base_id"
                                name="version_base_id"
                                required
                                class="h-11 min-w-0 w-full max-w-full rounded-md border-gray-300 bg-white px-3 text-sm text-gray-700 focus:border-institucional focus:ring-institucional"
                            >
                                @foreach ($versionesBase as $versionBase)
                                    <option value="{{ $versionBase->id }}" @selected((int) $versionBase->id === (int) $versionId)>
                                        {{ $versionBase->tipo === 'anterior' ? 'Anterior' : 'Semestral' }} · V{{ number_format((float) $versionBase->version, 1) }}

                                        @if ($versionBase->es_actual)
                                            — Actual
                                        @endif
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <button
                            type="submit"
                            class="boton boton-primario"
                        >
                            Generar programación
                        </button>
                    </form>
                @else
                    <div class="rounded-md bg-gray-100 px-3 py-2 text-xs text-gray-500">
                        Prepara las secciones del período e inicia un borrador desde Oferta del período para generar su programación.
                    </div>
                @endif

            </div>
        </div>
    @endcan

    <form
        id="filtros-form"
        data-filtros-url="{{ route('programacion.semestral.filtros') }}"
        method="GET"
        action="{{ route('programacion.semestral') }}"
        class="@container/filtros-programacion mb-2 rounded-lg border border-gray-200 bg-white p-4 shadow-sm sm:p-5"
    >

        <div class="grid min-w-0 grid-cols-1 items-end gap-3 @min-[19rem]/filtros-programacion:grid-cols-2 @min-[40rem]/filtros-programacion:grid-cols-6 @min-[56rem]/filtros-programacion:grid-cols-20">

            <div class="min-w-0 @min-[40rem]/filtros-programacion:col-span-2 @min-[56rem]/filtros-programacion:col-span-3">
                <label
                    for="periodo_id"
                    class="mb-1.5 block text-sm font-semibold leading-5 text-gray-700"
                >
                    Período
                </label>

                <select data-select-buscable
                    id="periodo_id"
                    name="periodo_id"
                    class="h-11 w-full min-w-0 truncate rounded-lg border border-borde bg-superficie px-2.5 text-xs text-texto focus:border-institucional focus:ring-institucional"
                >
                    @foreach ($periodos as $periodo)
                        <option
                            value="{{ $periodo->id }}"
                            @selected($periodoId == $periodo->id)
                        >
                            {{ $periodo->anio }}-{{ $periodo->numero_semestre }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="min-w-0 @min-[40rem]/filtros-programacion:col-span-2 @min-[56rem]/filtros-programacion:col-span-4">
                <label
                    for="version_id"
                    class="mb-1.5 block text-sm font-semibold leading-5 text-gray-700"
                >
                    Versión
                </label>

                <select data-select-buscable
                    id="version_id"
                    name="version_id"
                    class="h-11 w-full min-w-0 truncate rounded-lg border border-borde bg-superficie px-2.5 text-xs text-texto focus:border-institucional focus:ring-institucional"
                    @disabled($versiones->isEmpty())
                >
                    @if ($versiones->isEmpty())
                        <option value="">
                            Sin versiones
                        </option>
                    @else
                        @foreach ($versiones as $version)
                            <option
                                value="{{ $version->id }}"
                                @selected($versionId == $version->id)
                            >
                                V{{ number_format((float) $version->version, 1) }}

                                @if ($version->es_actual)
                                    — Actual
                                @endif
                            </option>
                        @endforeach
                    @endif
                </select>
            </div>

            <div class="min-w-0 @min-[40rem]/filtros-programacion:col-span-2 @min-[56rem]/filtros-programacion:col-span-4">
                <label
                    for="sede_id"
                    class="mb-1.5 block text-sm font-semibold leading-5 text-gray-700"
                >
                    Sede
                </label>

                <select data-select-buscable
                    id="sede_id"
                    name="sede_id"
                    class="h-11 w-full min-w-0 truncate rounded-lg border border-borde bg-superficie px-2.5 text-xs text-texto focus:border-institucional focus:ring-institucional"
                >
                    <option value="">
                        Todas las sedes
                    </option>

                    @foreach ($sedes as $sede)
                        <option
                            value="{{ $sede->id }}"
                            @selected($sedeId == $sede->id)
                        >
                            {{ $sede->nombre }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="min-w-0 @min-[40rem]/filtros-programacion:col-span-2 @min-[56rem]/filtros-programacion:col-span-9">
                <label
                    for="carrera_id"
                    class="mb-1.5 block text-sm font-semibold leading-5 text-gray-700"
                >
                    Carrera
                </label>

                <select data-select-buscable
                    id="carrera_id"
                    name="carrera_id"
                    class="h-11 w-full min-w-0 truncate rounded-lg border border-borde bg-superficie px-2.5 text-xs text-texto focus:border-institucional focus:ring-institucional"
                >
                    <option value="">
                        Todas las carreras
                    </option>

                    @foreach ($carreras as $carrera)
                        <option
                            value="{{ $carrera->id }}"
                            @selected($carreraId == $carrera->id)
                        >
                            {{ $carrera->nombre }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="min-w-0 @min-[40rem]/filtros-programacion:col-span-2 @min-[56rem]/filtros-programacion:col-span-2">
                <label
                    for="semestre"
                    class="mb-1.5 block text-sm font-semibold leading-5 text-gray-700"
                >
                    Semestre
                </label>

                <select data-select-buscable
                    id="semestre"
                    name="semestre"
                    class="h-11 w-full min-w-0 truncate rounded-lg border border-borde bg-superficie px-2.5 text-xs text-texto focus:border-institucional focus:ring-institucional"
                >
                    <option value="">
                        Todos
                    </option>

                    @foreach ($semestres as $valorSemestre)
                        <option
                            value="{{ $valorSemestre }}"
                            @selected((string) $semestre === (string) $valorSemestre)
                        >
                            {{ $valorSemestre }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="min-w-0 @min-[40rem]/filtros-programacion:col-span-2 @min-[56rem]/filtros-programacion:col-span-2">
                <label
                    for="seccion_numero"
                    class="mb-1.5 block text-sm font-semibold leading-5 text-gray-700"
                >
                    Sección
                </label>

                <select data-select-buscable
                    id="seccion_numero"
                    name="seccion_numero"
                    class="h-11 w-full min-w-0 truncate rounded-lg border border-borde bg-superficie px-2.5 text-xs text-texto focus:border-institucional focus:ring-institucional"
                >
                    <option value="">
                        Todas
                    </option>

                    @foreach ($secciones->pluck('codigo')->unique()->sort()->values() as $numeroSeccion)
                        <option
                            value="{{ $numeroSeccion }}"
                            @selected($seccionNumero == $numeroSeccion)
                        >
                            {{ $numeroSeccion }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="min-w-0 @min-[40rem]/filtros-programacion:col-span-2 @min-[56rem]/filtros-programacion:col-span-6">
                <label
                    for="asignatura_id"
                    class="mb-1.5 block text-sm font-semibold leading-5 text-gray-700"
                >
                    Ramo
                </label>

                <select data-select-buscable
                    id="asignatura_id"
                    name="asignatura_id"
                    class="h-11 w-full min-w-0 truncate rounded-lg border border-borde bg-superficie px-2.5 text-xs text-texto focus:border-institucional focus:ring-institucional"
                >
                    <option value="">
                        Todos los ramos
                    </option>

                    @foreach ($asignaturas as $asignatura)
                        <option
                            value="{{ $asignatura->id }}"
                            @selected($asignaturaId == $asignatura->id)
                        >
                            {{ $asignatura->codigo }} — {{ $asignatura->nombre }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="min-w-0 @min-[40rem]/filtros-programacion:col-span-2 @min-[56rem]/filtros-programacion:col-span-6">
                <label
                    for="profesor_id"
                    class="mb-1.5 block text-sm font-semibold leading-5 text-gray-700"
                >
                    Docente
                </label>

                <select data-select-buscable
                    id="profesor_id"
                    name="profesor_id"
                    class="h-11 w-full min-w-0 truncate rounded-lg border border-borde bg-superficie px-2.5 text-xs text-texto focus:border-institucional focus:ring-institucional"
                >
                    <option value="">
                        Todos los docentes
                    </option>

                    @foreach ($profesores as $profesor)
                        <option
                            value="{{ $profesor->id }}"
                            @selected($profesorId == $profesor->id)
                        >
                            {{ trim($profesor->nombre . ' ' . $profesor->apellido) }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="col-span-full min-w-0 @min-[40rem]/filtros-programacion:col-span-2 @min-[56rem]/filtros-programacion:col-span-4">
                @if ($hayFiltroAplicado)
                    <p class="mb-1.5 text-right text-xs leading-5 text-texto-secundario">
                        <span class="font-semibold text-texto">{{ number_format($horarios->count(), 0, ',', '.') }}</span>
                        {{ $horarios->count() === 1 ? 'horario' : 'horarios' }}
                    </p>
                @endif
                <div class="grid grid-cols-2 gap-2">
                    <button type="submit" class="boton boton-primario">Aplicar</button>
                    <a href="{{ route('programacion.semestral') }}" class="boton boton-secundario">Limpiar</a>
                </div>
            </div>

        </div>
        <p id="estado-filtros-semestral" role="status" class="mt-3 hidden text-sm text-texto-secundario"></p>
    </form>

    @if ($versiones->isEmpty())

        <div class="rounded-lg border border-gray-200 bg-white px-4 py-12 text-center shadow-sm">

            <div class="mx-auto flex h-11 w-11 items-center justify-center rounded-full bg-gray-100 text-gray-500">
                <svg
                    class="h-5 w-5"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M8 7V3m8 4V3M4 11h16M5 5h14a1 1 0 011 1v14a1 1 0 01-1 1H5a1 1 0 01-1-1V6a1 1 0 011-1z"
                    />
                </svg>
            </div>

            <div class="mt-3 text-sm font-semibold text-gray-700">
                Todavía no existe una programación semestral.
            </div>

            <div class="mx-auto mt-1 max-w-xl text-xs leading-relaxed text-gray-400">
                Selecciona una versión de la programación anterior y utiliza el botón Generar programación.
            </div>

        </div>

    @elseif (!$hayFiltroAplicado)

        <div role="status" class="rounded-lg border border-gray-200 bg-white px-4 py-10 text-center shadow-sm">
            <div class="text-sm font-semibold text-gray-700">
                Selecciona un filtro para consultar los horarios.
            </div>

        </div>

    @elseif ($horarios->isEmpty())

        <div class="rounded-lg border border-gray-200 bg-white px-4 py-10 text-center shadow-sm">

            <div class="text-sm font-semibold text-gray-700">
                No hay horarios para los filtros seleccionados.
            </div>

            <div class="mt-1 text-xs text-gray-400">
                Cambia los filtros y vuelve a aplicar.
            </div>

        </div>

    @else

        <div class="min-w-0 w-full overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">

            <div id="cuadricula-programacion-semestral" data-calendario-semanal class="min-w-0 max-w-full overflow-x-auto overscroll-x-contain focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-institucional" role="region" aria-label="Horario de programación semestral" tabindex="0">

                <table class="w-full min-w-[900px] table-fixed border-collapse">

                    <colgroup>
                        <col class="w-[70px]">

                        @foreach ($diasVisualizacion as $dia)
                            <col>
                        @endforeach
                    </colgroup>

                    <thead>
                        <tr class="bg-gray-100">

                            <th scope="col" data-eje-horas class="sticky left-0 z-20 border-b border-r border-gray-200 bg-gray-100 px-1.5 py-2 text-left text-xs font-semibold text-gray-700">
                                Bloque
                            </th>

                            @foreach ($diasVisualizacion as $dia)
                                <th scope="col" data-dia-semanal="{{ $dia->nombre }}" class="border-b border-r border-gray-200 px-1.5 py-2 text-center text-xs font-semibold text-gray-700 last:border-r-0">
                                    {{ $dia->nombre }}
                                </th>
                            @endforeach

                        </tr>
                    </thead>

                    <tbody>

                        @foreach ($bloques as $bloque)

                            <tr>

                                <td class="sticky left-0 z-10 border-b border-r border-gray-200 bg-gray-50 px-1.5 py-1.5 align-top">

                                    <div class="text-xs font-bold text-gray-800">
                                        {{ $bloque->numero }}
                                    </div>

                                    <div class="mt-0.5 whitespace-nowrap text-[10px] leading-tight text-gray-500">
                                        {{ \Carbon\Carbon::parse($bloque->hora_inicio)->format('H:i') }}
                                    </div>

                                    <div class="whitespace-nowrap text-[10px] leading-tight text-gray-500">
                                        {{ \Carbon\Carbon::parse($bloque->hora_fin)->format('H:i') }}
                                    </div>

                                    @if ($bloque->es_almuerzo)
                                        <div class="mt-1 text-[10px] font-semibold text-gray-400">
                                            Almuerzo
                                        </div>
                                    @endif

                                </td>

                                @foreach ($diasVisualizacion as $dia)

                                    @php
                                        $clave = $dia->id . '-' . $bloque->id;
                                        $horariosCelda = $horariosPorBloque->get($clave, collect());
                                    @endphp

                                    <td class="border-b border-r border-gray-200 p-1 align-top last:border-r-0">

                                        @if ($horariosCelda->isEmpty())

                                            <div class="min-h-[68px]"></div>

                                        @else

                                            <div class="space-y-1">

                                                @foreach ($horariosCelda as $horario)

                                                    @php
                                                        $actividad = $horario->actividadSeccion;
                                                        $seccion = $actividad?->seccion;
                                                        $asignatura = $seccion?->asignatura;
                                                        $grupo = $horario->grupoSeccion;
                                                    @endphp

                                                    <div class="rounded-md border border-gray-200 bg-white px-1.5 py-1.5 shadow-sm">

                                                        <div class="flex items-start justify-between gap-1">

                                                            <div class="min-w-0">

                                                                <div class="truncate text-[10px] font-bold leading-tight text-gray-800">
                                                                    {{ $asignatura?->codigo ?? 'Sin código' }}
                                                                </div>

                                                                <div
                                                                    class="mt-0.5 line-clamp-2 break-words text-[11px] font-medium leading-tight text-gray-700"
                                                                    title="{{ $asignatura?->nombre }}"
                                                                >
                                                                    {{ $asignatura?->nombre ?? 'Asignatura sin nombre' }}
                                                                </div>

                                                            </div>

                                                            @if ($grupo)
                                                                <span class="shrink-0 rounded-full bg-gray-100 px-1.5 py-0.5 text-[10px] font-semibold text-gray-600">
                                                                    {{ $grupo->codigo }}
                                                                </span>
                                                            @endif

                                                        </div>

                                                        <div class="mt-1.5 space-y-0.5 text-[10px] leading-tight text-gray-500">

                                                            <div>
                                                                <span class="font-semibold text-gray-700">
                                                                    Sec.:
                                                                </span>

                                                                {{ $seccion?->codigo ?? '—' }}
                                                            </div>

                                                            <div>
                                                                <span class="font-semibold text-gray-700">
                                                                    Act.:
                                                                </span>

                                                                {{ $actividad?->tipo ?? '—' }}
                                                            </div>

                                                            @if ($horario->docentesEfectivos->isNotEmpty())
                                                                <div>
                                                                    <span class="font-semibold text-gray-700">Docente:</span>
                                                                    {{ $horario->docentesEfectivos->map(fn ($asignacion) => trim($asignacion->profesor?->nombre.' '.$asignacion->profesor?->apellido))->filter()->implode(', ') }}
                                                                </div>
                                                            @endif

                                                            @if ($horario->asignacionesSalas->isNotEmpty())

                                                                <div>
                                                                    <span class="font-semibold text-gray-700">
                                                                        Sala:
                                                                    </span>

                                                                    @foreach ($horario->asignacionesSalas as $asignacion)

                                                                        {{ $asignacion->sala?->codigo ?? $asignacion->sala?->nombre }}

                                                                        @if (!$loop->last)
                                                                            ,
                                                                        @endif

                                                                    @endforeach
                                                                </div>

                                                            @endif

                                                            @if ($horario->asignacionesLaboratorios->isNotEmpty())

                                                                <div>
                                                                    <span class="font-semibold text-gray-700">
                                                                        Lab.:
                                                                    </span>

                                                                    @foreach ($horario->asignacionesLaboratorios as $asignacion)

                                                                        {{ $asignacion->laboratorio?->codigo ?? $asignacion->laboratorio?->nombre }}

                                                                        @if (!$loop->last)
                                                                            ,
                                                                        @endif

                                                                    @endforeach
                                                                </div>

                                                            @endif

                                                            @if ($horario->asignacionesLaboratoriosMoviles->isNotEmpty())

                                                                <div>
                                                                    <span class="font-semibold text-gray-700">
                                                                        Lab. móvil:
                                                                    </span>

                                                                    @foreach ($horario->asignacionesLaboratoriosMoviles as $asignacion)

                                                                        {{ $asignacion->laboratorioMovil?->codigo ?? $asignacion->laboratorioMovil?->nombre }}

                                                                        @if (!$loop->last)
                                                                            ,
                                                                        @endif

                                                                    @endforeach
                                                                </div>

                                                            @endif

                                                        </div>

                                                    </div>

                                                @endforeach

                                            </div>

                                        @endif

                                    </td>

                                @endforeach

                            </tr>

                        @endforeach

                    </tbody>

                </table>

            </div>

        </div>

    @endif

</div>

<script>
(() => {
    const formulario = document.getElementById('filtros-form');
    const estado = document.getElementById('estado-filtros-semestral');
    const aplicar = formulario.querySelector('button[type="submit"]');
    const campos = [...formulario.querySelectorAll('select[name]')];
    const [periodo, version, sede, ...dependientes] = campos;
    const opciones = [
        ['carreras', 'Todas las carreras', ({ id, nombre }) => new Option(nombre, id)],
        ['semestres', 'Todos', semestre => new Option(semestre, semestre)],
        ['secciones', 'Todas', seccion => new Option(seccion, seccion)],
        ['asignaturas', 'Todos los ramos', ({ id, codigo, nombre }) => new Option(`${codigo} — ${nombre}`, id)],
        ['profesores', 'Todos los docentes', ({ id, nombre }) => new Option(nombre, id)],
    ];
    let actual;

    function mostrarEstado(texto, error = false) {
        estado.textContent = texto;
        estado.classList.toggle('hidden', !texto);
        estado.classList.toggle('text-red-700', error);
    }

    function restablecerEstado() {
        actual = null;
        aplicar.disabled = false;
        dependientes.forEach(campo => { campo.disabled = false; });
        formulario.removeAttribute('aria-busy');
        estado.classList.remove('estado-filtros-cargando');
    }

    async function actualizarFiltros(indice) {
        actual?.abort();
        const consulta = new AbortController();
        actual = consulta;
        const parametros = new URLSearchParams(campos.slice(0, indice + 3)
            .filter(campo => campo.value).map(campo => [campo.name, campo.value]));
        dependientes.forEach((campo, posicion) => {
            campo.disabled = posicion >= indice;
            if (campo.disabled) campo.replaceChildren(new Option(opciones[posicion][1], ''));
        });
        aplicar.disabled = true;
        formulario.setAttribute('aria-busy', 'true');
        estado.classList.add('estado-filtros-cargando');
        mostrarEstado('Actualizando filtros…');

        const espera = setTimeout(() => consulta.abort(), 15000);
        try {
            const respuesta = await fetch(`${formulario.dataset.filtrosUrl}?${parametros}`, {
                signal: consulta.signal,
                headers: { Accept: 'application/json' },
            });
            if (!respuesta.ok) throw new Error('No fue posible actualizar los filtros.');
            const datos = await respuesta.json();
            if (actual !== consulta) return;
            const listas = opciones.slice(indice).map(([clave, vacio, crear]) => [
                new Option(vacio, ''), ...datos[clave].map(crear),
            ]);
            listas.forEach((lista, posicion) => dependientes[indice + posicion].replaceChildren(...lista));
            mostrarEstado('');
        } catch {
            if (actual === consulta) mostrarEstado('Presiona Aplicar para actualizar los filtros y consultar.', true);
        } finally {
            clearTimeout(espera);
            if (actual === consulta) restablecerEstado();
        }
    }

    [sede, ...dependientes.slice(0, -1)].forEach((campo, indice) => {
        campo.addEventListener('change', () => actualizarFiltros(indice));
    });
    periodo.addEventListener('change', () => {
        actual?.abort();
        restablecerEstado();
        version.selectedIndex = -1;
        dependientes.forEach(campo => { campo.value = ''; });
        formulario.requestSubmit();
    });
    version.addEventListener('change', () => {
        actual?.abort();
        restablecerEstado();
        formulario.requestSubmit();
    });
    formulario.addEventListener('submit', evento => {
        if (actual) evento.preventDefault();
    });
})();
</script>

@endsection

@extends('layouts.app')

@section('content')

<x-encabezado-pagina titulo="Consultar programación anterior">
    Revisa una programación anterior y consulta sus cursos, docentes y horarios mediante los filtros.
</x-encabezado-pagina>

<div class="min-w-0 w-full">

    <form
        id="filtros-form"
        data-filtros-programacion-anterior
        data-filtros-url="{{ route('programacion.filtros') }}"
        method="GET"
        action="{{ route('programacion.index') }}"
        class="mb-2 rounded-lg border border-gray-200 bg-white p-5 shadow-sm sm:p-6"
    >

        <div class="grid min-w-0 grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4 xl:items-end">

            <div class="min-w-0">
                <label for="periodo_id" class="mb-1.5 block text-sm font-semibold leading-5 text-gray-700">
                    Período
                </label>

                <select data-select-buscable
                    id="periodo_id"
                    name="periodo_id"
                    class="h-9 min-w-0 w-full max-w-full truncate rounded-md border-gray-300 bg-white px-2.5 text-xs text-gray-700 focus:border-red-600 focus:ring-red-600"
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

            <div class="min-w-0">
                <label for="version_id" class="mb-1.5 block text-sm font-semibold leading-5 text-gray-700">
                    Versión
                </label>

                <select data-select-buscable
                    id="version_id"
                    name="version_id"
                    class="h-9 min-w-0 w-full max-w-full truncate rounded-md border-gray-300 bg-white px-2.5 text-xs text-gray-700 focus:border-red-600 focus:ring-red-600"
                >
                    @foreach ($versiones as $version)
                        <option
                            value="{{ $version->id }}"
                            @selected($versionId == $version->id)
                        >
                            V{{ number_format($version->version, 1) }}
                            @if ($version->es_actual)
                                — Actual
                            @endif
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="min-w-0">
                <label for="sede_id" class="mb-1.5 block text-sm font-semibold leading-5 text-gray-700">
                    Sede
                </label>

                <select data-select-buscable
                    id="sede_id"
                    name="sede_id"
                    class="h-9 min-w-0 w-full max-w-full truncate rounded-md border-gray-300 bg-white px-2.5 text-xs text-gray-700 focus:border-red-600 focus:ring-red-600"
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

            <div class="min-w-0">
                <label for="carrera_id" class="mb-1.5 block text-sm font-semibold leading-5 text-gray-700">
                    Carrera
                </label>

                <select data-select-buscable
                    id="carrera_id"
                    name="carrera_id"
                    class="h-9 min-w-0 w-full max-w-full truncate rounded-md border-gray-300 bg-white px-2.5 text-xs text-gray-700 focus:border-red-600 focus:ring-red-600"
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

            <div class="min-w-0">
                <label for="semestre" class="mb-1.5 block text-sm font-semibold leading-5 text-gray-700">
                    Semestre
                </label>

                <select data-select-buscable
                    id="semestre"
                    name="semestre"
                    class="h-9 min-w-0 w-full max-w-full truncate rounded-md border-gray-300 bg-white px-2.5 text-xs text-gray-700 focus:border-red-600 focus:ring-red-600"
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

            <div class="min-w-0">
                <label for="seccion_numero" class="mb-1.5 block text-sm font-semibold leading-5 text-gray-700">
                    Sección
                </label>

                <select data-select-buscable
                    id="seccion_numero"
                    name="seccion_numero"
                    class="h-9 min-w-0 w-full max-w-full truncate rounded-md border-gray-300 bg-white px-2.5 text-xs text-gray-700 focus:border-red-600 focus:ring-red-600"
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

            <div class="min-w-0">
                <label for="asignatura_id" class="mb-1.5 block text-sm font-semibold leading-5 text-gray-700">
                    Ramo
                </label>

                <select data-select-buscable
                    id="asignatura_id"
                    name="asignatura_id"
                    class="h-9 min-w-0 w-full max-w-full truncate rounded-md border-gray-300 bg-white px-2.5 text-xs text-gray-700 focus:border-red-600 focus:ring-red-600"
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

            <div class="min-w-0">
                <label for="profesor_id" class="mb-1.5 block text-sm font-semibold leading-5 text-gray-700">
                    Profesor
                </label>

                <select data-select-buscable
                    id="profesor_id"
                    name="profesor_id"
                    class="h-9 min-w-0 w-full max-w-full truncate rounded-md border-gray-300 bg-white px-2.5 text-xs text-gray-700 focus:border-red-600 focus:ring-red-600"
                >
                    <option value="">
                        Todos los profesores
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

            <div class="flex min-w-0 flex-wrap items-center gap-2 sm:col-span-2 xl:col-span-4">
            <button
                type="submit"
                id="aplicar-filtros"
                class="h-9 min-w-0 w-full max-w-full whitespace-nowrap rounded-md bg-red-700 px-4 text-xs font-semibold text-white transition hover:bg-red-800 sm:w-auto"
            >
                Aplicar
            </button>

            <a
                href="{{ route('programacion.index') }}"
               class="flex h-9 min-w-0 w-full max-w-full items-center justify-center whitespace-nowrap rounded-md border border-gray-300 bg-white px-4 text-xs font-medium text-gray-700 transition hover:bg-gray-50 sm:w-auto"
            >
                Limpiar
            </a>

            <div class="flex min-w-0 flex-wrap items-center gap-x-1 px-1 text-xs text-gray-500 sm:ml-auto">

    @if ($hayFiltroAplicado)

        <span class="font-semibold text-gray-700">
            {{ number_format($horarios->count()) }}
        </span>

        <span class="ml-1">
            {{ $horarios->count() === 1 ? 'horario' : 'horarios' }}
        </span>

    @else

        <span class="text-gray-400">
            Sin consulta
        </span>

    @endif

</div>
            </div>
        </div>
        <p data-estado-filtros role="status" aria-live="polite" class="mt-3 hidden text-sm text-gray-500"></p>
    </form>


        @if (!$hayFiltroAplicado)

    <div class="rounded-lg border border-gray-200 bg-white px-6 py-12 text-center shadow-sm">

        <div class="mx-auto flex h-10 w-10 items-center justify-center rounded-full bg-gray-100 text-gray-500">
            <svg
                class="h-5 w-5"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
                aria-hidden="true"
            >
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2a1 1 0 01-.293.707L13 14.414V19a1 1 0 01-.553.894l-4 2A1 1 0 017 21v-6.586L3.293 10.707A1 1 0 013 10V4z"
                />
            </svg>
        </div>

        <div class="mt-3 text-sm font-semibold text-gray-700">
            Selecciona al menos un filtro para visualizar la programación.
        </div>

        <div class="mt-1 text-xs text-gray-400">
            Puedes filtrar por sede, carrera, semestre, sección, ramo o profesor.
        </div>

    </div>

@elseif ($horarios->isEmpty())

    <div class="rounded-lg border border-gray-200 bg-white px-4 py-10 text-center shadow-sm">

        <div class="text-sm font-semibold text-gray-700">
            No hay programación para los filtros seleccionados.
        </div>

        <div class="mt-1 text-xs text-gray-400">
            Prueba seleccionando otros filtros para realizar una nueva búsqueda.
        </div>

    </div>

@else

        <div class="min-w-0 w-full overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">

            <p class="border-b border-gray-200 px-3 py-2 text-[11px] text-gray-500 xl:hidden">Desliza la tabla para ver todos los días.</p>

            <div class="min-w-0 max-w-full overflow-x-auto focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-institucional" role="region" aria-label="Horario de programación anterior" tabindex="0">

                <table class="w-full min-w-[900px] table-fixed border-collapse">

                    <colgroup>
                        <col class="w-[70px]">

                        @foreach ($diasVisualizacion as $dia)
                            <col>
                        @endforeach
                    </colgroup>

                    <thead>
                        <tr class="bg-gray-100">

                            <th class="sticky left-0 z-20 border-b border-r border-gray-200 bg-gray-100 px-1.5 py-2 text-left text-[10px] font-bold uppercase tracking-wide text-gray-600">
                                Módulo
                            </th>

                            @foreach ($diasVisualizacion as $dia)
                                <th class="border-b border-r border-gray-200 px-1.5 py-2 text-center text-[10px] font-bold uppercase tracking-wide text-gray-700 last:border-r-0">
                                    {{ $dia->nombre }}
                                </th>
                            @endforeach

                        </tr>
                    </thead>

                    <tbody>

                        @foreach ($bloques as $bloque)

    @php
        $bloqueTieneHorarios = $diasVisualizacion->contains(function ($dia) use ($bloque, $horariosPorBloque) {
            $clave = $dia->id . '-' . $bloque->id;

            return $horariosPorBloque
                ->get($clave, collect())
                ->isNotEmpty();
        });
    @endphp

    <tr>

                                <td class="sticky left-0 z-10 border-b border-r border-gray-200 bg-gray-50 px-1.5 {{ $bloqueTieneHorarios ? 'py-1.5' : 'py-0.5' }} align-top">

                                    <div class="text-xs font-bold text-gray-800">
                                        {{ $bloque->numero }}
                                    </div>

                                    <div class="mt-0.5 whitespace-nowrap text-[10px] leading-tight text-gray-500">
                                        {{ \Carbon\Carbon::parse($bloque->hora_inicio)->format('H:i') }}
                                    </div>

                                    <div class="whitespace-nowrap text-[10px] leading-tight text-gray-500">
                                        {{ \Carbon\Carbon::parse($bloque->hora_fin)->format('H:i') }}
                                    </div>

                                    

                                </td>

                                @foreach ($diasVisualizacion as $dia)

                                    @php
                                        $clave = $dia->id . '-' . $bloque->id;
                                        $horariosCelda = $horariosPorBloque->get($clave, collect());
                                    @endphp

                                    <td class="border-b border-r border-gray-200 p-1 align-top last:border-r-0">

                                        @if ($horariosCelda->isEmpty())

                                            <div class="{{ $bloqueTieneHorarios ? 'min-h-[68px]' : 'min-h-[34px]' }}"></div>

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

                                                            @if ($horario->profesores->isNotEmpty())

                                                                <div
                                                                    title="@foreach ($horario->profesores as $horarioProfesor){{ trim(($horarioProfesor->profesor?->nombre ?? '') . ' ' . ($horarioProfesor->profesor?->apellido ?? '')) }}@if (!$loop->last), @endif @endforeach"
                                                                >

                                                                    <span class="font-semibold text-gray-700">
                                                                        Prof.:
                                                                    </span>

                                                                    @foreach ($horario->profesores as $horarioProfesor)

                                                                        {{ trim(($horarioProfesor->profesor?->nombre ?? '') . ' ' . ($horarioProfesor->profesor?->apellido ?? '')) }}

                                                                        @if (!$loop->last)
                                                                            ,
                                                                        @endif

                                                                    @endforeach

                                                                </div>

                                                            @elseif ($seccion?->profesores?->isNotEmpty())

                                                                <div>

                                                                    <span class="font-semibold text-gray-700">
                                                                        Prof.:
                                                                    </span>

                                                                    @foreach ($seccion->profesores as $seccionProfesor)

                                                                        {{ trim(($seccionProfesor->profesor?->nombre ?? '') . ' ' . ($seccionProfesor->profesor?->apellido ?? '')) }}

                                                                        @if (!$loop->last)
                                                                            ,
                                                                        @endif

                                                                    @endforeach

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

@endsection

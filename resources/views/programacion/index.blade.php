@extends('layouts.app')

@section('content')

<div class="min-w-0 w-full px-2 py-2">

    <form
        id="filtros-form"
        method="GET"
        action="{{ route('programacion.index') }}"
        class="mb-2 rounded-lg border border-gray-200 bg-white px-4 py-3 shadow-sm"
    >

        <div class="grid min-w-0 grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4 xl:items-end">

            <div class="min-w-0">
                <label for="periodo_id" class="mb-1.5 block text-sm font-semibold leading-5 text-gray-700">
                    Período
                </label>

                <select
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

                <select
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

                <select
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

                <select
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

                <select
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

                <select
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

                <select
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

                <select
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

<script>

    const filtrosUrl = @json(route('programacion.filtros'));

    const periodoSelect = document.getElementById('periodo_id');
    const versionSelect = document.getElementById('version_id');
    const sedeSelect = document.getElementById('sede_id');
    const carreraSelect = document.getElementById('carrera_id');
    const semestreSelect = document.getElementById('semestre');
    const seccionSelect = document.getElementById('seccion_numero');
    const asignaturaSelect = document.getElementById('asignatura_id');
    const profesorSelect = document.getElementById('profesor_id');

    const carreraSeleccionada = @json($carreraId);
    const semestreSeleccionado = @json($semestre);
    const seccionSeleccionada = @json($seccionNumero);
    const asignaturaSeleccionada = @json($asignaturaId);
    const profesorSeleccionado = @json($profesorId);

    const filtrosBuscables = new Map();

    function normalizarTexto(texto) {

        return String(texto ?? '')
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .toLowerCase()
            .trim();
    }

    function crearFiltroBuscable(select) {

        if (!select || filtrosBuscables.has(select)) {
            return;
        }

        const contenedor = document.createElement('div');
        contenedor.className = 'relative w-full';

        const input = document.createElement('input');
        input.type = 'text';
        input.autocomplete = 'off';
        input.spellcheck = false;

        input.className =
            'h-9 min-w-0 w-full max-w-full rounded-md border border-gray-300 bg-white px-2.5 pr-8 text-xs text-gray-700 outline-none transition placeholder:text-gray-400 focus:border-red-600 focus:ring-1 focus:ring-red-600';

        const icono = document.createElement('div');

        icono.className =
            'pointer-events-none absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-400';

        icono.innerHTML = `
            <svg
                class="h-3.5 w-3.5"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
                aria-hidden="true"
            >
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M19 9l-7 7-7-7"
                />
            </svg>
        `;

        const lista = document.createElement('div');

        lista.className =
    'absolute left-0 right-0 top-full z-50 mt-1 hidden max-h-60 w-full overflow-y-auto rounded-md border border-gray-200 bg-white py-1 shadow-lg';
        contenedor.appendChild(input);
        contenedor.appendChild(icono);
        contenedor.appendChild(lista);

        select.insertAdjacentElement(
            'afterend',
            contenedor
        );

        select.classList.add('hidden');

        const estado = {
            contenedor,
            input,
            lista,
            abierto: false,
            indiceActivo: -1,
        };

        filtrosBuscables.set(
            select,
            estado
        );

        function obtenerOpciones() {

            return Array.from(select.options);
        }

        function obtenerOpcionSeleccionada() {

            return select.options[
                select.selectedIndex
            ] ?? null;
        }

        function actualizarTextoSeleccionado() {

            const opcion = obtenerOpcionSeleccionada();

            input.value = opcion
                ? opcion.textContent.replace(/\s+/g, ' ').trim()
                : '';
        }

        function cerrarLista() {

            lista.classList.add('hidden');

            estado.abierto = false;
            estado.indiceActivo = -1;
        }

        function abrirLista() {

            lista.classList.remove('hidden');

            estado.abierto = true;
        }

        function seleccionarOpcion(option) {

            if (!option || option.disabled) {
                return;
            }

            select.value = option.value;

            actualizarTextoSeleccionado();

            cerrarLista();

            select.dispatchEvent(
                new Event(
                    'change',
                    {
                        bubbles: true
                    }
                )
            );

            input.blur();
        }

        function renderizarOpciones(busqueda = '') {

            lista.innerHTML = '';

            const termino = normalizarTexto(busqueda);

            const opciones = obtenerOpciones().filter(
                function (option) {

                    if (option.disabled) {
                        return false;
                    }

                    if (!termino) {
                        return true;
                    }

                    return normalizarTexto(
                        option.textContent
                    ).includes(termino);
                }
            );

            if (opciones.length === 0) {

                const vacio = document.createElement('div');

                vacio.className =
                    'px-3 py-2.5 text-xs text-gray-400';

                vacio.textContent =
                    'No se encontraron resultados';

                lista.appendChild(vacio);

                estado.indiceActivo = -1;

                return;
            }

            opciones.forEach(
                function (option, indice) {

                    const item = document.createElement('button');

                    item.type = 'button';

                    item.dataset.indice = indice;

                    item.className =
                        'flex w-full items-center px-3 py-2 text-left text-xs text-gray-700 transition hover:bg-gray-50';

                    if (
                        String(option.value) ===
                        String(select.value)
                    ) {
                        item.classList.add(
                            'bg-red-50',
                            'font-semibold',
                            'text-red-700'
                        );
                    }

                    const texto = document.createElement('span');

                    texto.className =
    'block min-w-0 whitespace-normal break-words';

                    texto.textContent =
                        option.textContent.replace(/\s+/g, ' ').trim();

                    item.appendChild(texto);

                    if (
                        String(option.value) ===
                        String(select.value)
                    ) {

                        const check = document.createElement('span');

                        check.className =
                            'ml-2 shrink-0 text-red-700';

                        check.innerHTML = `
                            <svg
                                class="h-3.5 w-3.5"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M5 13l4 4L19 7"
                                />
                            </svg>
                        `;

                        item.appendChild(check);
                    }

                    item.addEventListener(
                        'mousedown',
                        function (event) {

                            event.preventDefault();

                            seleccionarOpcion(option);
                        }
                    );

                    lista.appendChild(item);
                }
            );

            estado.indiceActivo = -1;
        }

        function destacarOpcionActiva() {

            const items = Array.from(
                lista.querySelectorAll('button')
            );

            items.forEach(
                function (item, indice) {

                    item.classList.toggle(
                        'bg-gray-100',
                        indice === estado.indiceActivo
                    );
                }
            );

            const activo =
                items[estado.indiceActivo];

            if (activo) {
                activo.scrollIntoView({
                    block: 'nearest'
                });
            }
        }

        input.addEventListener(
            'focus',
            function () {

                input.select();

                renderizarOpciones('');

                abrirLista();
            }
        );

        input.addEventListener(
            'click',
            function () {

                if (!estado.abierto) {

                    renderizarOpciones('');

                    abrirLista();
                }
            }
        );

        input.addEventListener(
            'input',
            function () {

                renderizarOpciones(
                    input.value
                );

                abrirLista();
            }
        );

        input.addEventListener(
            'keydown',
            function (event) {

                const items = Array.from(
                    lista.querySelectorAll('button')
                );

                if (
                    event.key === 'ArrowDown'
                ) {

                    event.preventDefault();

                    if (!estado.abierto) {

                        renderizarOpciones(
                            input.value
                        );

                        abrirLista();
                    }

                    estado.indiceActivo =
                        Math.min(
                            estado.indiceActivo + 1,
                            items.length - 1
                        );

                    destacarOpcionActiva();

                    return;
                }

                if (
                    event.key === 'ArrowUp'
                ) {

                    event.preventDefault();

                    estado.indiceActivo =
                        Math.max(
                            estado.indiceActivo - 1,
                            0
                        );

                    destacarOpcionActiva();

                    return;
                }

                if (
                    event.key === 'Enter'
                ) {

                    if (
                        estado.abierto &&
                        estado.indiceActivo >= 0 &&
                        items[estado.indiceActivo]
                    ) {

                        event.preventDefault();

                        items[
                            estado.indiceActivo
                        ].dispatchEvent(
                            new MouseEvent(
                                'mousedown',
                                {
                                    bubbles: true
                                }
                            )
                        );
                    }

                    return;
                }

                if (
                    event.key === 'Escape'
                ) {

                    cerrarLista();

                    actualizarTextoSeleccionado();

                    input.blur();
                }
            }
        );

        input.addEventListener(
            'blur',
            function () {

                setTimeout(
                    function () {

                        cerrarLista();

                        actualizarTextoSeleccionado();

                    },
                    100
                );
            }
        );

        select.addEventListener(
            'change',
            function () {

                actualizarTextoSeleccionado();
            }
        );

        const observador = new MutationObserver(
            function () {

                actualizarTextoSeleccionado();

                if (estado.abierto) {

                    renderizarOpciones(
                        input.value
                    );
                }
            }
        );

        observador.observe(
            select,
            {
                childList: true,
                subtree: true
            }
        );

        actualizarTextoSeleccionado();
    }

    function inicializarFiltrosBuscables() {

        [
            periodoSelect,
            versionSelect,
            sedeSelect,
            carreraSelect,
            semestreSelect,
            seccionSelect,
            asignaturaSelect,
            profesorSelect,
        ].forEach(
            function (select) {

                crearFiltroBuscable(select);
            }
        );
    }

    function limpiarSelect(select, texto) {

        select.innerHTML = '';

        const option =
            document.createElement('option');

        option.value = '';
        option.textContent = texto;

        select.appendChild(option);
    }

    function cargarCarreras(
        carreras,
        valorSeleccionado = null
    ) {

        limpiarSelect(
            carreraSelect,
            'Todas las carreras'
        );

        carreras.forEach(
            function (carrera) {

                const option =
                    document.createElement('option');

                option.value = carrera.id;
                option.textContent = carrera.nombre;

                if (
                    String(carrera.id) ===
                    String(valorSeleccionado)
                ) {
                    option.selected = true;
                }

                carreraSelect.appendChild(option);
            }
        );
    }

    function cargarSemestres(
        semestres,
        valorSeleccionado = null
    ) {

        limpiarSelect(
            semestreSelect,
            'Todos'
        );

        semestres.forEach(
            function (semestre) {

                const option =
                    document.createElement('option');

                option.value = semestre;
                option.textContent = semestre;

                if (
                    String(semestre) ===
                    String(valorSeleccionado)
                ) {
                    option.selected = true;
                }

                semestreSelect.appendChild(option);
            }
        );
    }

    function cargarSecciones(
        secciones,
        valorSeleccionado = null
    ) {

        limpiarSelect(
            seccionSelect,
            'Todas'
        );

        secciones.forEach(
            function (seccion) {

                const option =
                    document.createElement('option');

                option.value = seccion;
                option.textContent = seccion;

                if (
                    String(seccion) ===
                    String(valorSeleccionado)
                ) {
                    option.selected = true;
                }

                seccionSelect.appendChild(option);
            }
        );
    }

    function cargarAsignaturas(
        asignaturas,
        valorSeleccionado = null
    ) {

        limpiarSelect(
            asignaturaSelect,
            'Todos los ramos'
        );

        asignaturas.forEach(
            function (asignatura) {

                const option =
                    document.createElement('option');

                option.value = asignatura.id;

                option.textContent =
                    asignatura.codigo +
                    ' — ' +
                    asignatura.nombre;

                if (
                    String(asignatura.id) ===
                    String(valorSeleccionado)
                ) {
                    option.selected = true;
                }

                asignaturaSelect.appendChild(option);
            }
        );
    }

    function cargarProfesores(
        profesores,
        valorSeleccionado = null
    ) {

        limpiarSelect(
            profesorSelect,
            'Todos los profesores'
        );

        profesores.forEach(
            function (profesor) {

                const option =
                    document.createElement('option');

                option.value = profesor.id;
                option.textContent = profesor.nombre;

                if (
                    String(profesor.id) ===
                    String(valorSeleccionado)
                ) {
                    option.selected = true;
                }

                profesorSelect.appendChild(option);
            }
        );
    }

    async function actualizarFiltros(
        opciones = {}
    ) {

        const parametros =
            new URLSearchParams();

        if (periodoSelect.value) {

            parametros.set(
                'periodo_id',
                periodoSelect.value
            );
        }

        if (versionSelect.value) {

            parametros.set(
                'version_id',
                versionSelect.value
            );
        }

        if (sedeSelect.value) {

            parametros.set(
                'sede_id',
                sedeSelect.value
            );
        }

        if (carreraSelect.value) {

            parametros.set(
                'carrera_id',
                carreraSelect.value
            );
        }

        if (
            opciones.semestre !== undefined &&
            opciones.semestre !== ''
        ) {

            parametros.set(
                'semestre',
                opciones.semestre
            );
        }

        if (opciones.seccionNumero) {

            parametros.set(
                'seccion_numero',
                opciones.seccionNumero
            );
        }

        if (opciones.asignaturaId) {

            parametros.set(
                'asignatura_id',
                opciones.asignaturaId
            );
        }

        try {

            const respuesta = await fetch(
                filtrosUrl +
                '?' +
                parametros.toString(),
                {
                    headers: {
                        'Accept': 'application/json'
                    }
                }
            );

            if (!respuesta.ok) {

                throw new Error(
                    'No fue posible actualizar los filtros.'
                );
            }

            const datos =
                await respuesta.json();

            if (
                opciones.actualizarCarreras
            ) {

                cargarCarreras(
                    datos.carreras,
                    opciones.carreraSeleccionada
                        ?? null
                );
            }

            if (
                opciones.actualizarSemestres
            ) {

                cargarSemestres(
                    datos.semestres,
                    opciones.semestreSeleccionado
                        ?? null
                );
            }

            if (
                opciones.actualizarSecciones
            ) {

                cargarSecciones(
                    datos.secciones,
                    opciones.seccionSeleccionada
                        ?? null
                );
            }

            if (
                opciones.actualizarAsignaturas
            ) {

                cargarAsignaturas(
                    datos.asignaturas,
                    opciones.asignaturaSeleccionada
                        ?? null
                );
            }

            if (
                opciones.actualizarProfesores
            ) {

                cargarProfesores(
                    datos.profesores,
                    opciones.profesorSeleccionado
                        ?? null
                );
            }

        } catch (error) {

            console.error(error);
        }
    }

    sedeSelect.addEventListener(
        'change',
        async function () {

            limpiarSelect(
                carreraSelect,
                'Cargando carreras...'
            );

            limpiarSelect(
                semestreSelect,
                'Cargando semestres...'
            );

            limpiarSelect(
                seccionSelect,
                'Cargando secciones...'
            );

            limpiarSelect(
                asignaturaSelect,
                'Cargando ramos...'
            );

            limpiarSelect(
                profesorSelect,
                'Cargando profesores...'
            );

            await actualizarFiltros({

                actualizarCarreras: true,
                actualizarSemestres: true,
                actualizarSecciones: true,
                actualizarAsignaturas: true,
                actualizarProfesores: true

            });
        }
    );

    carreraSelect.addEventListener(
        'change',
        async function () {

            limpiarSelect(
                semestreSelect,
                'Cargando semestres...'
            );

            limpiarSelect(
                seccionSelect,
                'Cargando secciones...'
            );

            limpiarSelect(
                asignaturaSelect,
                'Cargando ramos...'
            );

            limpiarSelect(
                profesorSelect,
                'Cargando profesores...'
            );

            await actualizarFiltros({

                actualizarSemestres: true,
                actualizarSecciones: true,
                actualizarAsignaturas: true,
                actualizarProfesores: true

            });
        }
    );

    semestreSelect.addEventListener(
        'change',
        async function () {

            const semestre =
                semestreSelect.value;

            limpiarSelect(
                seccionSelect,
                'Cargando secciones...'
            );

            limpiarSelect(
                asignaturaSelect,
                'Cargando ramos...'
            );

            limpiarSelect(
                profesorSelect,
                'Cargando profesores...'
            );

            await actualizarFiltros({

                semestre: semestre,
                actualizarSecciones: true,
                actualizarAsignaturas: true,
                actualizarProfesores: true

            });
        }
    );

    seccionSelect.addEventListener(
        'change',
        async function () {

            const seccionNumero =
                seccionSelect.value;

            if (!seccionNumero) {

                await actualizarFiltros({

                    semestre:
                        semestreSelect.value,

                    actualizarAsignaturas:
                        true,

                    actualizarProfesores:
                        true

                });

                return;
            }

            limpiarSelect(
                asignaturaSelect,
                'Cargando ramos...'
            );

            limpiarSelect(
                profesorSelect,
                'Cargando profesores...'
            );

            await actualizarFiltros({

                semestre:
                    semestreSelect.value,

                seccionNumero:
                    seccionNumero,

                actualizarAsignaturas:
                    true,

                actualizarProfesores:
                    true

            });
        }
    );

    asignaturaSelect.addEventListener(
        'change',
        async function () {

            const asignaturaId =
                asignaturaSelect.value;

            if (!asignaturaId) {

                await actualizarFiltros({

                    semestre:
                        semestreSelect.value,

                    seccionNumero:
                        seccionSelect.value,

                    actualizarProfesores:
                        true

                });

                return;
            }

            limpiarSelect(
                profesorSelect,
                'Cargando profesores...'
            );

            await actualizarFiltros({

                semestre:
                    semestreSelect.value,

                seccionNumero:
                    seccionSelect.value,

                asignaturaId:
                    asignaturaId,

                actualizarProfesores:
                    true

            });
        }
    );

    periodoSelect.addEventListener(
        'change',
        function () {

            document
                .getElementById('filtros-form')
                .submit();
        }
    );

    versionSelect.addEventListener(
        'change',
        function () {

            document
                .getElementById('filtros-form')
                .submit();
        }
    );

    document.addEventListener(
        'click',
        function (event) {

            filtrosBuscables.forEach(
                function (estado) {

                    if (
                        !estado.contenedor.contains(
                            event.target
                        )
                    ) {

                        estado.lista.classList.add(
                            'hidden'
                        );

                        estado.abierto = false;
                    }
                }
            );
        }
    );

    document.addEventListener(
        'DOMContentLoaded',
        async function () {

            inicializarFiltrosBuscables();

            await actualizarFiltros({

                actualizarCarreras: true,
                actualizarSemestres: true,
                actualizarSecciones: true,
                actualizarAsignaturas: true,
                actualizarProfesores: true,

                carreraSeleccionada:
                    carreraSeleccionada,

                semestreSeleccionado:
                    semestreSeleccionado,

                seccionSeleccionada:
                    seccionSeleccionada,

                asignaturaSeleccionada:
                    asignaturaSeleccionada,

                profesorSeleccionado:
                    profesorSeleccionado

            });
        }
    );

</script>

@endsection
@extends('layouts.app')

@section('content')

<div class="w-full px-2 py-2">

    {{-- ENCABEZADO --}}
    <div class="mb-2 rounded-lg border border-gray-200 bg-white px-4 py-3 shadow-sm">

        <div class="flex items-center justify-between gap-4">

            <div>
                <h1 class="text-base font-bold text-gray-800">
                    Intercambio Docente
                </h1>

                <p class="mt-0.5 text-xs text-gray-500">
                    Consulta profesores que actualmente imparten una asignatura
                    y revisa su carga, ubicación y horarios para evaluar posibles reemplazos.
                </p>
            </div>

            @if ($asignatura)

                <div class="rounded-md bg-gray-100 px-3 py-2 text-right">

                    <div class="text-[9px] font-semibold uppercase tracking-wide text-gray-500">
                        Asignatura seleccionada
                    </div>

                    <div class="text-xs font-bold text-gray-800">
                        {{ $asignatura->codigo }} — {{ $asignatura->nombre }}
                    </div>

                </div>

            @endif

        </div>

    </div>


    {{-- BUSCADOR --}}
    <form
        method="GET"
        action="{{ route('programacion.intercambio') }}"
        class="mb-2 rounded-lg border border-gray-200 bg-white px-4 py-3 shadow-sm"
    >

        <div class="grid grid-cols-[1fr_auto] items-end gap-3">

            <div class="min-w-0">

                <label
                    for="asignatura_id"
                    class="mb-1 block text-[11px] font-semibold text-gray-600"
                >
                    Ramo
                </label>

                <div class="relative w-full">

    <select
        id="asignatura_id"
        name="asignatura_id"
        class="hidden"
    >
        <option value="">
            Selecciona una asignatura
        </option>

        @foreach ($asignaturas as $item)

            <option
                value="{{ $item->id }}"
                @selected($asignatura?->id == $item->id)
            >
                {{ $item->codigo }} — {{ $item->nombre }}
            </option>

        @endforeach
    </select>

    <input
        type="text"
        id="asignatura_busqueda"
        autocomplete="off"
        spellcheck="false"
        placeholder="Buscar por código o nombre..."
        class="h-9 w-full rounded-md border border-gray-300 bg-white px-2.5 pr-8 text-xs text-gray-700 outline-none transition placeholder:text-gray-400 focus:border-red-600 focus:ring-1 focus:ring-red-600"
    >

    <div class="pointer-events-none absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-400">
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
    </div>

    <div
        id="asignatura_lista"
        class="absolute left-0 right-0 top-full z-50 mt-1 hidden max-h-64 overflow-y-auto rounded-md border border-gray-200 bg-white py-1 shadow-lg"
    ></div>

</div>
            </div>


            <button
                type="submit"
                class="h-9 whitespace-nowrap rounded-md bg-red-700 px-5 text-xs font-semibold text-white transition hover:bg-red-800"
            >
                Buscar profesores
            </button>

        </div>

    </form>


    {{-- SIN ASIGNATURA --}}
    @if (!$asignatura)

        <div class="rounded-lg border border-gray-200 bg-white px-4 py-12 text-center shadow-sm">

            <div class="text-sm font-semibold text-gray-700">
                Selecciona una asignatura
            </div>

            <div class="mt-1 text-xs text-gray-400">
                Podrás consultar los profesores que actualmente la imparten,
                sus sedes, carga horaria y horarios ocupados.
            </div>

        </div>


    {{-- SIN RESULTADOS --}}
    @elseif ($resultados->isEmpty())

        <div class="rounded-lg border border-gray-200 bg-white px-4 py-12 text-center shadow-sm">

            <div class="text-sm font-semibold text-gray-700">
                No se encontraron profesores
            </div>

            <div class="mt-1 text-xs text-gray-400">
                La asignatura seleccionada no tiene profesores asociados
                en la programación actual.
            </div>

        </div>


    {{-- RESULTADOS --}}
    @else

        {{-- RESUMEN --}}
        <div class="mb-2 grid grid-cols-3 gap-2">

            <div class="rounded-lg border border-gray-200 bg-white px-3 py-2 shadow-sm">

                <div class="text-[9px] font-semibold uppercase tracking-wide text-gray-500">
                    Profesores
                </div>

                <div class="mt-0.5 text-lg font-bold text-gray-800">
                    {{ $resultados->count() }}
                </div>

            </div>


            <div class="rounded-lg border border-gray-200 bg-white px-3 py-2 shadow-sm">

                <div class="text-[9px] font-semibold uppercase tracking-wide text-gray-500">
                    Sedes donde imparten
                </div>

                <div class="mt-0.5 text-lg font-bold text-gray-800">
                    {{ $resultados->flatMap(fn ($resultado) => $resultado['sedes'])->pluck('id')->unique()->count() }}
                </div>

            </div>


            <div class="rounded-lg border border-gray-200 bg-white px-3 py-2 shadow-sm">

                <div class="text-[9px] font-semibold uppercase tracking-wide text-gray-500">
                    Asignatura consultada
                </div>

                <div class="mt-0.5 truncate text-xs font-bold text-gray-800">
                    {{ $asignatura->codigo }}
                </div>

            </div>

        </div>


        {{-- CONTENEDOR DE RESULTADOS Y HORARIO --}}
        <div class="grid grid-cols-1 gap-2 xl:grid-cols-[minmax(0,1.65fr)_minmax(320px,0.85fr)]">


            {{-- TABLA DE PROFESORES --}}
            <div class="min-w-0 overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">

                <div class="border-b border-gray-200 bg-gray-100 px-4 py-2">

                    <div class="text-[10px] font-bold uppercase tracking-wide text-gray-600">
                        Profesores que imparten la asignatura
                    </div>

                </div>


                <div class="overflow-x-auto">

                    <table class="w-full border-collapse">

                        <thead>

                            <tr class="bg-gray-50">

                                <th class="border-b border-r border-gray-200 px-3 py-2 text-left text-[10px] font-bold uppercase tracking-wide text-gray-600">
                                    Profesor
                                </th>

                                <th class="border-b border-r border-gray-200 px-3 py-2 text-left text-[10px] font-bold uppercase tracking-wide text-gray-600">
                                    Sede(s)
                                </th>

                                <th class="border-b border-r border-gray-200 px-3 py-2 text-left text-[10px] font-bold uppercase tracking-wide text-gray-600">
                                    Carga actual
                                </th>

                                <th class="border-b border-gray-200 px-3 py-2 text-left text-[10px] font-bold uppercase tracking-wide text-gray-600">
                                    Asignaturas actuales
                                </th>

                            </tr>

                        </thead>


                        <tbody>

                            @foreach ($resultados as $indice => $resultado)

                                @php

                                    $profesor = $resultado['profesor'];

                                    $sedes = $resultado['sedes'];

                                    $cantidadHorarios =
                                        $resultado['cantidad_horarios'];

                                    $cantidadAsignaturas =
                                        $resultado['cantidad_asignaturas'];

                                    $cargaPorAsignatura =
                                        $resultado['carga_por_asignatura'];

                                    $horariosOcupados =
                                        collect($resultado['horarios_ocupados']);

                                @endphp


                                <tr
                                    class="align-top hover:bg-gray-50"
                                    data-profesor-row="{{ $indice }}"
                                >

                                    {{-- PROFESOR --}}
                                    <td class="border-b border-r border-gray-200 px-3 py-3">

                                        <div class="text-xs font-bold text-gray-800">
                                            {{ trim(($profesor?->nombre ?? '') . ' ' . ($profesor?->apellido ?? '')) ?: 'Sin profesor' }}
                                        </div>


                                        @if ($profesor?->correo_institucional)

                                            <div class="mt-0.5 text-[9px] text-gray-500">
                                                {{ $profesor->correo_institucional }}
                                            </div>

                                        @endif


                                        @if ($profesor?->tipo_profesor)

                                            <div class="mt-1">

                                                <span class="rounded-full bg-gray-100 px-2 py-0.5 text-[8px] font-semibold text-gray-600">
                                                    {{ $profesor->tipo_profesor }}
                                                </span>

                                            </div>

                                        @endif

                                    </td>


                                    {{-- SEDES --}}
                                    <td class="border-b border-r border-gray-200 px-3 py-3">

                                        @if ($sedes->isEmpty())

                                            <div class="text-[9px] text-gray-400">
                                                Sin sede registrada
                                            </div>

                                        @else

                                            <div class="flex flex-wrap gap-1">

                                                @foreach ($sedes as $sede)

                                                    <span class="rounded-full bg-gray-100 px-2 py-1 text-[8px] font-semibold text-gray-600">
                                                        {{ $sede->nombre }}
                                                    </span>

                                                @endforeach

                                            </div>

                                        @endif

                                    </td>


                                    {{-- CARGA --}}
                                    <td class="border-b border-r border-gray-200 px-3 py-3">

                                        <div class="text-lg font-bold text-gray-800">
                                            {{ $cantidadHorarios }}
                                        </div>

                                        <div class="text-[9px] text-gray-500">
                                            módulos semanales
                                        </div>

                                        <div class="mt-2 text-[9px] text-gray-500">
                                            {{ $cantidadAsignaturas }}
                                            {{ $cantidadAsignaturas == 1 ? 'asignatura' : 'asignaturas' }}
                                        </div>

                                    </td>


                                    {{-- ASIGNATURAS --}}
                                    <td class="border-b border-gray-200 px-3 py-3">

                                        @if ($cargaPorAsignatura->isEmpty())

                                            <span class="text-[9px] text-gray-400">
                                                Sin carga registrada
                                            </span>

                                        @else

                                            <div class="space-y-1.5">

                                                @foreach ($cargaPorAsignatura as $carga)

                                                    @php

                                                        $asignaturaCarga =
                                                            $carga['asignatura'];

                                                        $horariosAsignatura =
                                                            $horariosOcupados->filter(
                                                                function ($horario) use ($asignaturaCarga) {

                                                                    return $asignaturaCarga
                                                                        && isset($horario['asignatura'])
                                                                        && $horario['asignatura']?->id
                                                                            == $asignaturaCarga->id;

                                                                }
                                                            );

                                                    @endphp


                                                    @if ($asignaturaCarga)

                                                        {{-- ASIGNATURA SELECCIONABLE --}}
                                                        <button
                                                            type="button"
                                                            class="js-asignatura w-full rounded-md border border-gray-200 bg-gray-50 px-2.5 py-2 text-left transition hover:border-gray-300 hover:bg-gray-100"
                                                            data-profesor="{{ $indice }}"
                                                            data-asignatura="{{ $asignaturaCarga->id }}"
                                                        >

                                                            <div class="flex items-center justify-between gap-2">

                                                                <div class="text-[9px] font-bold text-gray-800">
                                                                    {{ $asignaturaCarga->codigo }}
                                                                </div>

                                                                <span class="text-[8px] font-semibold text-gray-400">
                                                                    {{ $carga['cantidad_horarios'] }}
                                                                    {{ $carga['cantidad_horarios'] == 1 ? 'módulo' : 'módulos' }}
                                                                </span>

                                                            </div>


                                                            <div class="mt-0.5 text-[8px] leading-tight text-gray-500">
                                                                {{ $asignaturaCarga->nombre }}
                                                            </div>

                                                        </button>


                                                        {{-- DATOS OCULTOS --}}
                                                        <div
                                                            class="js-datos-asignatura hidden"
                                                            data-profesor="{{ $indice }}"
                                                            data-asignatura="{{ $asignaturaCarga->id }}"
                                                            data-codigo="{{ $asignaturaCarga->codigo }}"
                                                            data-nombre="{{ $asignaturaCarga->nombre }}"
                                                        >

                                                            @foreach ($horariosAsignatura as $horario)

                                                                @php

                                                                    $horarioModelo =
                                                                        $resultado['horarios_carga']
                                                                            ->firstWhere(
                                                                                'id',
                                                                                $horario['horario_id']
                                                                            );

                                                                    $sedesHorario =
                                                                        $horarioModelo?->asignacionesSalas
                                                                            ?->map(
                                                                                fn ($asignacion) =>
                                                                                    $asignacion->sala?->sede?->nombre
                                                                            )
                                                                            ->filter()
                                                                            ->unique()
                                                                            ->join(', ');

                                                                @endphp


                                                                <div
                                                                    class="js-horario-dato"
                                                                    data-dia="{{ $horario['dia'] ?? 'Día no definido' }}"
                                                                    data-bloque="{{ $horario['bloque'] ?? '' }}"
                                                                    data-hora-inicio="{{ $horario['hora_inicio'] ?? '' }}"
                                                                    data-hora-fin="{{ $horario['hora_fin'] ?? '' }}"
                                                                    data-sede="{{ $sedesHorario }}"
                                                                ></div>

                                                            @endforeach

                                                        </div>

                                                    @endif

                                                @endforeach

                                            </div>

                                        @endif

                                    </td>

                                </tr>

                            @endforeach

                        </tbody>

                    </table>

                </div>

            </div>


            {{-- PANEL DERECHO --}}
            <div class="min-w-0">

                <div
                    class="sticky top-2 overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm"
                >

                    {{-- CABECERA --}}
                    <div class="border-b border-gray-200 bg-gray-100 px-4 py-3">

                        <div class="text-[10px] font-bold uppercase tracking-wide text-gray-600">
                            Horario
                        </div>


                        <div
                            id="panel-subtitulo"
                            class="mt-1 text-xs font-bold text-gray-800"
                        >
                            Selecciona una asignatura
                        </div>


                        <div
                            id="panel-profesor"
                            class="mt-0.5 text-[9px] text-gray-400"
                        >
                            El horario aparecerá aquí
                        </div>

                    </div>


                    {{-- CONTENIDO --}}
                    <div
                        id="panel-contenido"
                        class="max-h-[calc(100vh-250px)] overflow-y-auto px-3 py-3"
                    >

                        <div class="py-10 text-center">

                            <div class="text-2xl text-gray-300">
                                ◷
                            </div>

                            <div class="mt-2 text-[10px] font-semibold text-gray-500">
                                Selecciona una asignatura
                            </div>

                            <div class="mt-1 text-[9px] text-gray-400">
                                Haz clic sobre una asignatura de la tabla
                                para visualizar sus horarios.
                            </div>

                        </div>

                    </div>

                </div>

            </div>

        </div>


        {{-- EVALUACIÓN --}}
        <div class="mt-2 rounded-lg border border-gray-200 bg-white px-4 py-3 shadow-sm">

            <div class="text-[10px] font-bold uppercase tracking-wide text-gray-600">
                Evaluación de reemplazo
            </div>

            <div class="mt-1 text-[9px] text-gray-400">
                En esta sección se podrá comprobar posteriormente la disponibilidad
                del profesor y detectar conflictos con el horario que se desea cubrir.
            </div>

        </div>


        {{-- JAVASCRIPT --}}
        <script>

            document.addEventListener('DOMContentLoaded', function () {

                const botones =
                    document.querySelectorAll('.js-asignatura');

                const panelContenido =
                    document.getElementById('panel-contenido');

                const panelSubtitulo =
                    document.getElementById('panel-subtitulo');

                const panelProfesor =
                    document.getElementById('panel-profesor');


                function escaparHTML(texto) {

                    const div =
                        document.createElement('div');

                    div.textContent =
                        texto ?? '';

                    return div.innerHTML;

                }


                function seleccionarAsignatura(boton) {

                    const profesorId =
                        boton.dataset.profesor;

                    const asignaturaId =
                        boton.dataset.asignatura;


                    /*
                     * IMPORTANTE:
                     * quitar TODAS las selecciones anteriores.
                     *
                     * De esta manera solamente puede existir
                     * una asignatura seleccionada en toda la pantalla.
                     */
                    botones.forEach(function (item) {

                        item.classList.remove(
                            'border-red-300',
                            'bg-red-50'
                        );

                        item.classList.add(
                            'border-gray-200',
                            'bg-gray-50'
                        );

                    });


                    /*
                     * Marcar únicamente la seleccionada.
                     */
                    boton.classList.remove(
                        'border-gray-200',
                        'bg-gray-50'
                    );

                    boton.classList.add(
                        'border-red-300',
                        'bg-red-50'
                    );


                    /*
                     * Buscar los datos correspondientes.
                     */
                    const datos =
                        document.querySelector(
                            '.js-datos-asignatura[data-profesor="' +
                            profesorId +
                            '"][data-asignatura="' +
                            asignaturaId +
                            '"]'
                        );


                    if (!datos) {

                        panelSubtitulo.textContent =
                            'Sin información';

                        panelProfesor.textContent =
                            '';

                        panelContenido.innerHTML = `
                            <div class="py-8 text-center text-[10px] text-gray-400">
                                No se encontraron horarios para esta asignatura.
                            </div>
                        `;

                        return;

                    }


                    const codigo =
                        datos.dataset.codigo || '';

                    const nombre =
                        datos.dataset.nombre || '';


                    /*
                     * Obtener nombre del profesor.
                     */
                    const fila =
                        document.querySelector(
                            '[data-profesor-row="' +
                            profesorId +
                            '"]'
                        );


                    let nombreProfesor =
                        '';


                    if (fila) {

                        const elemento =
                            fila.querySelector(
                                'td:first-child .text-xs'
                            );

                        if (elemento) {

                            nombreProfesor =
                                elemento.textContent.trim();

                        }

                    }


                    panelSubtitulo.innerHTML =
                        escaparHTML(codigo) +
                        ' — ' +
                        escaparHTML(nombre);


                    panelProfesor.textContent =
                        nombreProfesor;


                    /*
                     * Obtener horarios.
                     */
                    const horarios =
                        datos.querySelectorAll(
                            '.js-horario-dato'
                        );


                    if (!horarios.length) {

                        panelContenido.innerHTML = `
                            <div class="py-8 text-center text-[10px] text-gray-400">
                                Esta asignatura no tiene horarios registrados.
                            </div>
                        `;

                        return;

                    }


                    /*
                     * Agrupar horarios por día.
                     */
                    const porDia = {};


                    horarios.forEach(function (horario) {

                        const dia =
                            horario.dataset.dia ||
                            'Día no definido';


                        if (!porDia[dia]) {

                            porDia[dia] = [];

                        }


                        porDia[dia].push(horario);

                    });


                    let html = '';


                    Object.keys(porDia).forEach(function (dia) {

                        html += `
                            <div class="mb-3 last:mb-0">

                                <div class="mb-1.5 flex items-center gap-2">

                                    <div class="text-[9px] font-bold uppercase tracking-wide text-gray-600">
                                        ${escaparHTML(dia)}
                                    </div>

                                    <div class="h-px flex-1 bg-gray-200"></div>

                                </div>
                        `;


                        porDia[dia].forEach(function (horario) {

                            const bloque =
                                horario.dataset.bloque || '';

                            const horaInicio =
                                horario.dataset.horaInicio || '';

                            const horaFin =
                                horario.dataset.horaFin || '';

                            const sede =
                                horario.dataset.sede || '';


                            html += `
                                <div class="mb-1.5 rounded-md border border-gray-200 bg-gray-50 px-3 py-2">

                                    <div class="flex items-center justify-between gap-3">

                                        <div>

                                            <div class="text-[10px] font-bold text-gray-700">
                                                ${
                                                    horaInicio && horaFin
                                                    ? escaparHTML(horaInicio) +
                                                      ' - ' +
                                                      escaparHTML(horaFin)
                                                    : 'Horario no definido'
                                                }
                                            </div>

                                            ${
                                                sede
                                                ? `
                                                    <div class="mt-0.5 text-[8px] text-gray-400">
                                                        ${escaparHTML(sede)}
                                                    </div>
                                                `
                                                : ''
                                            }

                                        </div>


                                        ${
                                            bloque
                                            ? `
                                                <span class="rounded-full bg-white px-2 py-1 text-[8px] font-bold text-gray-500">
                                                    M${escaparHTML(bloque)}
                                                </span>
                                            `
                                            : ''
                                        }

                                    </div>

                                </div>
                            `;

                        });


                        html += `
                            </div>
                        `;

                    });


                    panelContenido.innerHTML =
                        html;

                }


                /*
                 * Registrar eventos de cada asignatura.
                 */
                botones.forEach(function (boton) {

                    boton.addEventListener(
                        'click',
                        function () {

                            seleccionarAsignatura(this);

                        }
                    );

                });


                /*
                 * IMPORTANTE:
                 * No seleccionar ninguna asignatura automáticamente.
                 *
                 * El usuario debe hacer clic explícitamente
                 * en la asignatura que desea consultar.
                 */

            });

        </script>

@endif

<script>
    document.addEventListener('DOMContentLoaded', function () {

        const select =
            document.getElementById('asignatura_id');

        const input =
            document.getElementById('asignatura_busqueda');

        const lista =
            document.getElementById('asignatura_lista');

        if (!select || !input || !lista) {
            return;
        }

        function normalizar(texto) {
            return String(texto ?? '')
                .normalize('NFD')
                .replace(/[\u0300-\u036f]/g, '')
                .toLowerCase()
                .trim();
        }

        function opcionSeleccionada() {
            return select.options[
                select.selectedIndex
            ] ?? null;
        }

        function actualizarInput() {
            const opcion =
                opcionSeleccionada();

            if (opcion && opcion.value) {
                input.value =
                    opcion.textContent.trim();
            }
        }

        function cerrarLista() {
            lista.classList.add('hidden');
        }

        function abrirLista() {
            lista.classList.remove('hidden');
        }

        function seleccionarOpcion(option) {
            select.value = option.value;

            input.value =
                option.textContent.trim();

            cerrarLista();
        }

        function renderizar(busqueda = '') {
            lista.innerHTML = '';

            const termino =
                normalizar(busqueda);

            const opciones =
                Array.from(select.options)
                    .filter(function (option) {

                        if (!option.value) {
                            return false;
                        }

                        if (!termino) {
                            return true;
                        }

                        return normalizar(
                            option.textContent
                        ).includes(termino);
                    });

            if (opciones.length === 0) {
                const vacio =
                    document.createElement('div');

                vacio.className =
                    'px-3 py-2.5 text-xs text-gray-400';

                vacio.textContent =
                    'No se encontraron asignaturas';

                lista.appendChild(vacio);

                return;
            }

            opciones.forEach(function (option) {

                const boton =
                    document.createElement('button');

                boton.type = 'button';

                boton.className =
                    'flex w-full items-center px-3 py-2 text-left text-xs text-gray-700 transition hover:bg-gray-50';

                if (
                    String(option.value) ===
                    String(select.value)
                ) {
                    boton.classList.add(
                        'bg-red-50',
                        'font-semibold',
                        'text-red-700'
                    );
                }

                const texto =
                    document.createElement('span');

                texto.className =
                    'block whitespace-nowrap';

                texto.textContent =
                    option.textContent.trim();

                boton.appendChild(texto);

                boton.addEventListener(
                    'mousedown',
                    function (event) {
                        event.preventDefault();
                        seleccionarOpcion(option);
                    }
                );

                lista.appendChild(boton);
            });
        }

        input.addEventListener(
            'focus',
            function () {
                input.select();
                renderizar('');
                abrirLista();
            }
        );

        input.addEventListener(
            'input',
            function () {
                select.value = '';

                renderizar(
                    input.value
                );

                abrirLista();
            }
        );

        input.addEventListener(
            'click',
            function () {
                renderizar(
                    input.value
                );

                abrirLista();
            }
        );

        input.addEventListener(
            'blur',
            function () {
                setTimeout(
                    function () {
                        cerrarLista();

                        if (!select.value) {
                            input.value = '';
                        }
                    },
                    100
                );
            }
        );

        document.addEventListener(
            'click',
            function (event) {
                if (
                    !input.contains(event.target) &&
                    !lista.contains(event.target)
                ) {
                    cerrarLista();
                }
            }
        );

        actualizarInput();
    });
</script>

</div>

@endsection
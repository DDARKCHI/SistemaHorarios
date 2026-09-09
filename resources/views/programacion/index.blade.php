@extends('layouts.app')

@section('content')

<div class="w-full px-2 py-2">

    <form
        id="filtros-form"
        method="GET"
        action="{{ route('programacion.index') }}"
        class="mb-2 rounded-lg border border-gray-200 bg-white px-4 py-3 shadow-sm"
    >

        <div class="grid grid-cols-[0.7fr_0.8fr_1.1fr_1.45fr_0.75fr_0.8fr_1.45fr_1.45fr_auto_auto_auto] items-end gap-3">

            <div class="min-w-0">
                <label for="periodo_id" class="mb-1 block text-[11px] font-semibold text-gray-600">
                    Período
                </label>

                <select
                    id="periodo_id"
                    name="periodo_id"
                    class="h-9 w-full truncate rounded-md border-gray-300 bg-white px-2.5 text-xs text-gray-700 focus:border-red-600 focus:ring-red-600"
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
                <label for="version_id" class="mb-1 block text-[11px] font-semibold text-gray-600">
                    Versión
                </label>

                <select
                    id="version_id"
                    name="version_id"
                    class="h-9 w-full truncate rounded-md border-gray-300 bg-white px-2.5 text-xs text-gray-700 focus:border-red-600 focus:ring-red-600"
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
                <label for="sede_id" class="mb-1 block text-[11px] font-semibold text-gray-600">
                    Sede
                </label>

                <select
                    id="sede_id"
                    name="sede_id"
                    class="h-9 w-full truncate rounded-md border-gray-300 bg-white px-2.5 text-xs text-gray-700 focus:border-red-600 focus:ring-red-600"
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
                <label for="carrera_id" class="mb-1 block text-[11px] font-semibold text-gray-600">
                    Carrera
                </label>

                <select
                    id="carrera_id"
                    name="carrera_id"
                    class="h-9 w-full truncate rounded-md border-gray-300 bg-white px-2.5 text-xs text-gray-700 focus:border-red-600 focus:ring-red-600"
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
                <label for="semestre" class="mb-1 block text-[11px] font-semibold text-gray-600">
                    Semestre
                </label>

                <select
                    id="semestre"
                    name="semestre"
                    class="h-9 w-full truncate rounded-md border-gray-300 bg-white px-2.5 text-xs text-gray-700 focus:border-red-600 focus:ring-red-600"
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
                <label for="seccion_numero" class="mb-1 block text-[11px] font-semibold text-gray-600">
                    Sección
                </label>

                <select
                    id="seccion_numero"
                    name="seccion_numero"
                    class="h-9 w-full truncate rounded-md border-gray-300 bg-white px-2.5 text-xs text-gray-700 focus:border-red-600 focus:ring-red-600"
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
                <label for="asignatura_id" class="mb-1 block text-[11px] font-semibold text-gray-600">
                    Ramo
                </label>

                <select
                    id="asignatura_id"
                    name="asignatura_id"
                    class="h-9 w-full truncate rounded-md border-gray-300 bg-white px-2.5 text-xs text-gray-700 focus:border-red-600 focus:ring-red-600"
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
                <label for="profesor_id" class="mb-1 block text-[11px] font-semibold text-gray-600">
                    Profesor
                </label>

                <select
                    id="profesor_id"
                    name="profesor_id"
                    class="h-9 w-full truncate rounded-md border-gray-300 bg-white px-2.5 text-xs text-gray-700 focus:border-red-600 focus:ring-red-600"
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

            <button
                type="submit"
                id="aplicar-filtros"
                class="h-9 whitespace-nowrap rounded-md bg-red-700 px-4 text-xs font-semibold text-white transition hover:bg-red-800"
            >
                Aplicar
            </button>

            <a
                href="{{ route('programacion.index') }}"
                class="flex h-9 items-center justify-center whitespace-nowrap rounded-md border border-gray-300 bg-white px-4 text-xs font-medium text-gray-700 transition hover:bg-gray-50"
            >
                Limpiar
            </a>

            <div class="flex h-9 items-center justify-end whitespace-nowrap px-1 text-xs text-gray-500">
                <span class="font-semibold text-gray-700">
                    {{ number_format($horarios->count()) }}
                </span>

                <span class="ml-1">
                    {{ $horarios->count() === 1 ? 'horario' : 'horarios' }}
                </span>
            </div>

        </div>
    </form>

    @if ($horarios->isEmpty())

        <div class="rounded-lg border border-gray-200 bg-white px-4 py-10 text-center shadow-sm">

            <div class="text-sm font-semibold text-gray-700">
                No hay programación para los filtros seleccionados.
            </div>

            <div class="mt-1 text-xs text-gray-400">
                Prueba seleccionando otro período, versión, sede, carrera, semestre, sección, ramo o profesor.
            </div>

        </div>

    @else

        <div class="w-full overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">

            <div class="overflow-x-auto">

                <table class="w-full table-fixed border-collapse">

                    <colgroup>
                        <col class="w-[70px]">

                        @foreach ($diasVisualizacion as $dia)
                            <col>
                        @endforeach
                    </colgroup>

                    <thead>
                        <tr class="bg-gray-100">

                            <th class="sticky left-0 z-20 border-b border-r border-gray-200 bg-gray-100 px-1.5 py-2 text-left text-[10px] font-bold uppercase tracking-wide text-gray-600">
                                Bloque
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

                            <tr>

                                <td class="sticky left-0 z-10 border-b border-r border-gray-200 bg-gray-50 px-1.5 py-1.5 align-top">

                                    <div class="text-xs font-bold text-gray-800">
                                        {{ $bloque->numero }}
                                    </div>

                                    <div class="mt-0.5 whitespace-nowrap text-[9px] leading-tight text-gray-500">
                                        {{ \Carbon\Carbon::parse($bloque->hora_inicio)->format('H:i') }}
                                    </div>

                                    <div class="whitespace-nowrap text-[9px] leading-tight text-gray-500">
                                        {{ \Carbon\Carbon::parse($bloque->hora_fin)->format('H:i') }}
                                    </div>

                                    @if ($bloque->es_almuerzo)
                                        <div class="mt-1 text-[8px] font-semibold text-gray-400">
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
                                                                    class="mt-0.5 line-clamp-2 text-[9px] font-medium leading-tight text-gray-700"
                                                                    title="{{ $asignatura?->nombre }}"
                                                                >
                                                                    {{ $asignatura?->nombre ?? 'Asignatura sin nombre' }}
                                                                </div>

                                                            </div>

                                                            @if ($grupo)

                                                                <span class="shrink-0 rounded-full bg-gray-100 px-1.5 py-0.5 text-[8px] font-semibold text-gray-600">
                                                                    {{ $grupo->codigo }}
                                                                </span>

                                                            @endif

                                                        </div>

                                                        <div class="mt-1.5 space-y-0.5 text-[8px] leading-tight text-gray-500">

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

    function limpiarSelect(select, texto) {

        select.innerHTML = '';

        const option = document.createElement('option');

        option.value = '';
        option.textContent = texto;

        select.appendChild(option);
    }

    function cargarCarreras(carreras, valorSeleccionado = null) {

        limpiarSelect(
            carreraSelect,
            'Todas las carreras'
        );

        carreras.forEach(function (carrera) {

            const option = document.createElement('option');

            option.value = carrera.id;
            option.textContent = carrera.nombre;

            if (String(carrera.id) === String(valorSeleccionado)) {
                option.selected = true;
            }

            carreraSelect.appendChild(option);
        });
    }

    function cargarSemestres(semestres, valorSeleccionado = null) {

        limpiarSelect(
            semestreSelect,
            'Todos'
        );

        semestres.forEach(function (semestre) {

            const option = document.createElement('option');

            option.value = semestre;
            option.textContent = semestre;

            if (String(semestre) === String(valorSeleccionado)) {
                option.selected = true;
            }

            semestreSelect.appendChild(option);
        });
    }

    function cargarSecciones(secciones, valorSeleccionado = null) {

        limpiarSelect(
            seccionSelect,
            'Todas'
        );

        secciones.forEach(function (seccion) {

            const option = document.createElement('option');

            option.value = seccion;
            option.textContent = seccion;

            if (String(seccion) === String(valorSeleccionado)) {
                option.selected = true;
            }

            seccionSelect.appendChild(option);
        });
    }

    function cargarAsignaturas(asignaturas, valorSeleccionado = null) {

        limpiarSelect(
            asignaturaSelect,
            'Todos los ramos'
        );

        asignaturas.forEach(function (asignatura) {

            const option = document.createElement('option');

            option.value = asignatura.id;

            option.textContent =
                asignatura.codigo + ' — ' + asignatura.nombre;

            if (String(asignatura.id) === String(valorSeleccionado)) {
                option.selected = true;
            }

            asignaturaSelect.appendChild(option);
        });
    }

    function cargarProfesores(profesores, valorSeleccionado = null) {

        limpiarSelect(
            profesorSelect,
            'Todos los profesores'
        );

        profesores.forEach(function (profesor) {

            const option = document.createElement('option');

            option.value = profesor.id;
            option.textContent = profesor.nombre;

            if (String(profesor.id) === String(valorSeleccionado)) {
                option.selected = true;
            }

            profesorSelect.appendChild(option);
        });
    }

    async function actualizarFiltros(opciones = {}) {

        const parametros = new URLSearchParams();

        if (periodoSelect.value) {
            parametros.set(
                'periodo_id',
                periodoSelect.value
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

        if (opciones.semestre !== undefined) {
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
                filtrosUrl + '?' + parametros.toString(),
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

            const datos = await respuesta.json();

            if (opciones.actualizarCarreras) {
                cargarCarreras(
                    datos.carreras,
                    opciones.carreraSeleccionada ?? null
                );
            }

            if (opciones.actualizarSemestres) {
                cargarSemestres(
                    datos.semestres,
                    opciones.semestreSeleccionado ?? null
                );
            }

            if (opciones.actualizarSecciones) {
                cargarSecciones(
                    datos.secciones,
                    opciones.seccionSeleccionada ?? null
                );
            }

            if (opciones.actualizarAsignaturas) {
                cargarAsignaturas(
                    datos.asignaturas,
                    opciones.asignaturaSeleccionada ?? null
                );
            }

            if (opciones.actualizarProfesores) {
                cargarProfesores(
                    datos.profesores,
                    opciones.profesorSeleccionado ?? null
                );
            }

        } catch (error) {

            console.error(error);
        }
    }

    sedeSelect.addEventListener('change', async function () {

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
    });

    carreraSelect.addEventListener('change', async function () {

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
    });

    semestreSelect.addEventListener('change', async function () {

        const semestre = semestreSelect.value;

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
    });

    seccionSelect.addEventListener('change', async function () {

        const seccionNumero = seccionSelect.value;

        if (!seccionNumero) {

            await actualizarFiltros({

                semestre: semestreSelect.value,
                actualizarAsignaturas: true,
                actualizarProfesores: true

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

            semestre: semestreSelect.value,
            seccionNumero: seccionNumero,
            actualizarAsignaturas: true,
            actualizarProfesores: true

        });
    });

    asignaturaSelect.addEventListener('change', async function () {

        const asignaturaId = asignaturaSelect.value;

        if (!asignaturaId) {

            await actualizarFiltros({

                semestre: semestreSelect.value,
                seccionNumero: seccionSelect.value,
                actualizarProfesores: true

            });

            return;
        }

        limpiarSelect(
            profesorSelect,
            'Cargando profesores...'
        );

        await actualizarFiltros({

            semestre: semestreSelect.value,
            seccionNumero: seccionSelect.value,
            asignaturaId: asignaturaId,
            actualizarProfesores: true

        });
    });

    periodoSelect.addEventListener('change', function () {
        document.getElementById('filtros-form').submit();
    });

    versionSelect.addEventListener('change', function () {
        document.getElementById('filtros-form').submit();
    });

    document.addEventListener('DOMContentLoaded', async function () {

        await actualizarFiltros({

            actualizarCarreras: true,
            actualizarSemestres: true,
            actualizarSecciones: true,
            actualizarAsignaturas: true,
            actualizarProfesores: true,

            carreraSeleccionada: carreraSeleccionada,
            semestreSeleccionado: semestreSeleccionado,
            seccionSeleccionada: seccionSeleccionada,
            asignaturaSeleccionada: asignaturaSeleccionada,
            profesorSeleccionado: profesorSeleccionado

        });
    });

</script>

@endsection
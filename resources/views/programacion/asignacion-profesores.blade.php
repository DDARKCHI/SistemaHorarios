@extends('layouts.app')

@section('content')

<x-encabezado-pagina titulo="Asignación de docentes">
    Selecciona una sección y asigna docentes a cada actividad y grupo.
</x-encabezado-pagina>

<div class="min-w-0 w-full">
@php
    $programacionCerrada = \App\Models\VersionPlanificacion::where('periodo_academico_id', $periodoId)->where('tipo', 'semestral')->where('es_actual', true)->whereIn('estado', ['Aprobado', 'Publicado'])->exists();
@endphp
@if ($programacionCerrada)
<p role="status" class="mb-4 rounded-xl border border-borde bg-superficie p-4 text-sm text-texto-secundario">La programación actual está aprobada o publicada. Puedes consultar los docentes; para cambiarlos, crea y activa un borrador nuevo.</p>
@endif

    @if (session('success'))
        <div class="mb-2 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-xs font-medium text-green-700">
            {{ session('success') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="mb-2 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-xs text-red-700">
            {{ $errors->first() }}
        </div>
    @endif

    <x-filtros-compactos
        id="filtros-form"
        :campos="4"
        action="{{ route('programacion.asignacion.profesores') }}"
        class="mb-2"
        aria-label="Filtros para asignar docentes"
    >
        <div>
            <label for="periodo_id" class="mb-2 block text-sm font-semibold">
                Período
            </label>

            <select data-select-buscable
                id="periodo_id"
                name="periodo_id"
                class="h-11 min-w-0 w-full rounded-lg border border-borde bg-superficie px-3 text-sm text-texto focus:border-institucional focus:ring-institucional"
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

        <div>
            <label for="carrera_id" class="mb-2 block text-sm font-semibold">
                Carrera
            </label>

            <select data-select-buscable
                id="carrera_id"
                name="carrera_id"
                class="h-11 min-w-0 w-full rounded-lg border border-borde bg-superficie px-3 text-sm text-texto focus:border-institucional focus:ring-institucional"
            >
                <option value="">Todas las carreras</option>

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

        <div>
            <label for="semestre" class="mb-2 block text-sm font-semibold">
                Semestre
            </label>

            <select data-select-buscable
                id="semestre"
                name="semestre"
                class="h-11 min-w-0 w-full rounded-lg border border-borde bg-superficie px-3 text-sm text-texto focus:border-institucional focus:ring-institucional"
            >
                <option value="">Todos</option>

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

        <div>
            <label for="seccion_id" class="mb-2 block text-sm font-semibold">
                Sección
            </label>

            <select data-select-buscable
                id="seccion_id"
                name="seccion_id"
                class="h-11 min-w-0 w-full rounded-lg border border-borde bg-superficie px-3 text-sm text-texto focus:border-institucional focus:ring-institucional"
            >
                <option value="">Seleccionar sección</option>

                @foreach ($seccionesFiltro as $seccionFiltro)
                    <option
                        value="{{ $seccionFiltro->id }}"
                        @selected($seccionId == $seccionFiltro->id)
                    >
                        {{ $seccionFiltro->codigo }}
                        —
                        {{ $seccionFiltro->asignatura?->codigo }}
                        {{ $seccionFiltro->asignatura?->nombre }}
                    </option>
                @endforeach
            </select>
        </div>

        <x-slot:acciones>
            <a href="{{ route('programacion.asignacion.profesores') }}" class="boton boton-secundario">Limpiar</a>
            <button type="submit" class="boton boton-primario">Aplicar</button>
        </x-slot:acciones>
    </x-filtros-compactos>

    @if (!$seccion)

        <div class="rounded-xl border border-borde bg-white px-4 py-10 text-center shadow-sm">
            <div class="text-sm font-semibold text-gray-700">
                Selecciona una sección para administrar sus docentes.
            </div>

            <div class="mt-1 text-xs text-gray-400">
                Los filtros superiores limitarán las opciones disponibles.
            </div>
        </div>

    @else

        <div class="mb-2 rounded-xl border border-borde bg-white px-4 py-3 shadow-sm">

            <div class="flex flex-col gap-2 lg:flex-row lg:items-center lg:justify-between">

                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="text-sm font-bold text-gray-800">
                            {{ $seccion->asignatura?->codigo ?? 'Sin código' }}
                        </span>

                        <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-semibold text-gray-600">
                            Sección {{ $seccion->codigo }}
                        </span>

                        @if ($seccion->semestre)
                            <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-semibold text-gray-600">
                                Semestre {{ $seccion->semestre }}
                            </span>
                        @endif
                    </div>

                    <div class="mt-1 text-xs font-semibold text-gray-700">
                        {{ $seccion->asignatura?->nombre ?? $seccion->nombre }}
                    </div>

                    @if ($seccion->carreras->isNotEmpty())
                        <div class="mt-1 text-xs text-gray-500">
                            <span class="font-semibold text-gray-600">
                                Carrera:
                            </span>

                            @foreach ($seccion->carreras as $carrera)
                                {{ $carrera->nombre }}@if (!$loop->last), @endif
                            @endforeach
                        </div>
                    @endif
                </div>

                <div class="text-xs text-gray-500">
                    {{ $seccion->actividades->count() }}
                    {{ $seccion->actividades->count() === 1 ? 'actividad' : 'actividades' }}
                </div>
            </div>

        </div>

        @if ($seccion->actividades->isEmpty())

            <div class="rounded-xl border border-borde bg-white px-4 py-10 text-center shadow-sm">
                <div class="text-sm font-semibold text-gray-700">
                    Esta sección no tiene actividades registradas.
                </div>
            </div>

        @else

            <div class="space-y-2">

                @foreach ($seccion->actividades as $actividad)

                    <div class="overflow-hidden rounded-xl border border-borde bg-white shadow-sm">

                        <div class="border-b border-gray-200 bg-gray-50 px-4 py-2.5">

                            <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">

                                <div>
                                    <div class="text-xs font-bold text-gray-800">
                                        {{ $actividad->tipo }}
                                    </div>

                                    <div class="mt-0.5 text-xs text-gray-500">
                                        {{ $actividad->cantidad_horas ?? 0 }} horas
                                        ·
                                        {{ $actividad->cantidad_estudiantes ?? 0 }} estudiantes
                                    </div>
                                </div>

                                <div class="text-xs text-gray-500">
                                    {{ $actividad->grupos->count() }}
                                    {{ $actividad->grupos->count() === 1 ? 'grupo' : 'grupos' }}
                                </div>

                            </div>

                        </div>

                        @foreach ($actividad->grupos as $grupo)

                            @php
                                $asignaciones = $grupo->profesores
                                    ->where('estado', true)
                                    ->sortByDesc('es_principal');
                            @endphp

                            <div class="grid min-w-0 grid-cols-1 gap-4 border-b border-gray-200 p-4 last:border-b-0 xl:grid-cols-[minmax(0,0.7fr)_minmax(0,1.2fr)_minmax(0,1fr)] [&>div]:min-w-0">

                                <div>
                                    <div class="text-xs font-bold text-gray-800">
                                        {{ $grupo->codigo }}
                                    </div>

                                    <div class="mt-1 space-y-0.5 text-xs text-gray-500">
                                        <div>
                                            <span class="font-semibold text-gray-600">Cupos:</span>
                                            {{ $grupo->cupos ?? '—' }}
                                        </div>

                                        <div>
                                            <span class="font-semibold text-gray-600">Inscritos:</span>
                                            {{ $grupo->inscritos ?? '—' }}
                                        </div>
                                    </div>
                                </div>

                                <div>
                                    <div class="mb-2 text-xs font-bold uppercase tracking-wide text-gray-500">
                                        Docentes asignados
                                    </div>

                                    @if ($asignaciones->isEmpty())

                                        <div class="rounded-md border border-dashed border-gray-300 bg-gray-50 px-3 py-4 text-center text-xs text-gray-400">
                                            Sin docente asignado.
                                        </div>

                                    @else

                                        <div class="space-y-2">

                                            @foreach ($asignaciones as $asignacion)

                                                <div class="rounded-md border border-gray-200 px-3 py-2">

                                                    <div class="flex items-start justify-between gap-2">

                                                        <div class="min-w-0 break-words">
                                                            <div class="flex flex-wrap items-center gap-2">
                                                                <span class="text-xs font-semibold text-gray-800">
                                                                    {{ trim(
                                                                        ($asignacion->profesor?->nombre ?? '')
                                                                        . ' '
                                                                        . ($asignacion->profesor?->apellido ?? '')
                                                                    ) }}
                                                                </span>

                                                                @if ($asignacion->es_principal)
                                                                    <span class="rounded-full bg-red-50 px-2 py-0.5 text-xs font-bold text-red-700">
                                                                        Principal
                                                                    </span>
                                                                @endif
                                                            </div>

                                                            <div class="mt-1 text-xs text-gray-500">
                                                                {{ strtr($asignacion->tipo_participacion ?? '', ['PROFESOR' => 'DOCENTE', 'Profesor' => 'Docente', 'profesor' => 'docente']) }}
                                                            </div>

                                                            <div class="mt-0.5 text-xs text-gray-400">
                                                                RUT:
                                                                {{ $asignacion->profesor?->rut ?? '—' }}
                                                            </div>
                                                        </div>

                                                        @unless ($programacionCerrada)
                                                        <form class="shrink-0"
                                                            method="POST"
                                                            action="{{ route(
                                                                'programacion.asignacion.profesores.eliminar',
                                                                $asignacion
                                                            ) }}"
                                                            data-confirmar="Se quitará esta asignación docente de la programación."
                                                            data-confirmar-titulo="Quitar asignación docente"
                                                            data-confirmar-accion="Quitar asignación"
                                                        >
                                                            @csrf
                                                            @method('DELETE')

                                                            <input type="hidden" name="periodo_id" value="{{ $periodoId }}">
                                                            <input type="hidden" name="carrera_id" value="{{ $carreraId }}">
                                                            <input type="hidden" name="semestre" value="{{ $semestre }}">

                                                            <button
                                                                type="submit"
                                                                class="shrink-0 whitespace-nowrap rounded-md border border-red-200 bg-white px-2 py-1 text-xs font-semibold text-red-700 [overflow-wrap:normal] hover:bg-red-50"
                                                            >
                                                                Eliminar
                                                            </button>
                                                        </form>
                                                        @endunless

                                                    </div>

                                                </div>

                                            @endforeach

                                        </div>

                                    @endif
                                </div>

                                <div>
                                    <div class="mb-2 text-xs font-bold uppercase tracking-wide text-gray-500">
                                        Asignar docente
                                    </div>

                                    @unless ($programacionCerrada)
                                    <form
                                        method="POST"
                                        action="{{ route('programacion.asignacion.profesores.guardar') }}"
                                        class="min-w-0 space-y-2 formulario-profesor"
                                        data-seccion-id="{{ $seccion->id }}"
                                        data-confirmar="Se registrará la asignación del docente seleccionado."
                                        data-confirmar-titulo="Asignar docente"
                                        data-confirmar-accion="Asignar docente"
                                    >
                                        @csrf

                                        <input type="hidden" name="grupo_seccion_id" value="{{ $grupo->id }}">
                                        <input type="hidden" name="periodo_id" value="{{ $periodoId }}">
                                        <input type="hidden" name="carrera_id" value="{{ $carreraId }}">
                                        <input type="hidden" name="semestre" value="{{ $semestre }}">

                                        <div>
                                            <label class="mb-1 block text-xs font-semibold text-gray-600">
                                                Docentes sugeridos
                                            </label>

                                            <select
                                                name="profesor_id"
                                                class="profesor-select h-9 min-w-0 w-full max-w-full rounded-md border-gray-300 bg-white px-2.5 text-xs text-gray-700 focus:border-institucional focus:ring-institucional"
                                            >
                                                <option value="">
                                                    Seleccionar docente
                                                </option>

                                                @foreach ($profesoresSugeridos as $profesor)
                                                    <option value="{{ $profesor->id }}">
                                                        {{ trim($profesor->nombre . ' ' . $profesor->apellido) }}
                                                        — {{ $profesor->rut }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>

                                        <div class="rounded-md border border-gray-200 bg-gray-50 p-2">

                                            <label class="mb-1 block text-xs font-semibold text-gray-600">
                                                Buscar otro docente
                                            </label>

                                            <div class="flex gap-2">

                                                <input
                                                    type="text"
                                                    class="buscar-profesor h-9 min-w-0 flex-1 rounded-md border-gray-300 bg-white px-2.5 text-xs text-gray-700 focus:border-institucional focus:ring-institucional"
                                                    placeholder="Nombre, RUT o correo"
                                                >

                                                <button
                                                    type="button"
                                                    class="boton-buscar rounded-md border border-gray-300 bg-white px-3 text-xs font-semibold text-gray-700 hover:bg-gray-100"
                                                >
                                                    Buscar
                                                </button>

                                            </div>

                                            <div class="resultados-profesor mt-2 hidden min-w-0 space-y-1 break-words"></div>

                                        </div>

                                        <select
                                            name="tipo_participacion"
                                            required
                                            class="h-9 min-w-0 w-full max-w-full rounded-md border-gray-300 bg-white px-2.5 text-xs text-gray-700 focus:border-institucional focus:ring-institucional"
                                        >
                                            <option value="PROFESOR RESPONSABLE">
                                                Docente responsable
                                            </option>

                                            <option value="PROFESOR COLABORADOR">
                                                Docente colaborador
                                            </option>

                                            <option value="AYUDANTE">
                                                Ayudante
                                            </option>
                                        </select>

                                        <textarea
                                            name="observaciones"
                                            rows="2"
                                            maxlength="1000"
                                            placeholder="Observaciones opcionales"
                                            class="w-full resize-none rounded-md border-gray-300 bg-white px-2.5 py-2 text-xs text-gray-700 focus:border-institucional focus:ring-institucional"
                                        ></textarea>

                                        <label class="flex cursor-pointer items-center gap-2 rounded-md border border-gray-200 bg-gray-50 px-3 py-2">
                                            <input
                                                type="checkbox"
                                                name="es_principal"
                                                value="1"
                                                class="h-4 w-4 shrink-0 rounded border-gray-300 text-red-700 focus:ring-institucional"
                                            >

                                            <span class="text-xs font-medium text-gray-700">
                                                Docente principal del grupo
                                            </span>
                                        </label>

                                        <button
                                            type="submit"
                                            class="boton boton-primario w-full"
                                        >
                                            Asignar
                                        </button>

                                    </form>
                                    @endunless
                                </div>

                            </div>

                        @endforeach

                    </div>

                @endforeach

            </div>

        @endif

    @endif

</div>

<script>
    const filtrosUrl = @json(
        route('programacion.asignacion.profesores.filtros')
    );

    const periodoSelect = document.getElementById('periodo_id');
    const carreraSelect = document.getElementById('carrera_id');
    const semestreSelect = document.getElementById('semestre');
    const seccionSelect = document.getElementById('seccion_id');

    function limpiarSelect(select, texto) {
        select.innerHTML = '';

        const option = document.createElement('option');

        option.value = '';
        option.textContent = texto;

        select.appendChild(option);
    }

    function cargarCarreras(carreras) {
        limpiarSelect(carreraSelect, 'Todas las carreras');

        carreras.forEach(function (carrera) {
            const option = document.createElement('option');

            option.value = carrera.id;
            option.textContent = carrera.nombre;

            carreraSelect.appendChild(option);
        });
    }

    function cargarSemestres(semestres) {
        limpiarSelect(semestreSelect, 'Todos');

        semestres.forEach(function (semestre) {
            const option = document.createElement('option');

            option.value = semestre;
            option.textContent = semestre;

            semestreSelect.appendChild(option);
        });
    }

    function cargarSecciones(secciones) {
        limpiarSelect(seccionSelect, 'Seleccionar sección');

        secciones.forEach(function (seccion) {
            const option = document.createElement('option');

            option.value = seccion.id;
            option.textContent = seccion.texto;

            seccionSelect.appendChild(option);
        });
    }

    let ultimaConsultaFiltros = 0;

    async function consultarFiltros(parametros) {
        const consultaActual = ++ultimaConsultaFiltros;
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
                throw new Error('No fue posible actualizar los filtros.');
            }

            const datos = await respuesta.json();
            return consultaActual === ultimaConsultaFiltros ? datos : null;

        } catch (error) {
            console.error(error);

            return null;
        }
    }

    periodoSelect.addEventListener('change', async function () {
        limpiarSelect(carreraSelect, 'Cargando carreras...');
        limpiarSelect(semestreSelect, 'Cargando semestres...');
        limpiarSelect(seccionSelect, 'Cargando secciones...');

        const parametros = new URLSearchParams();

        parametros.set('periodo_id', periodoSelect.value);

        const datos = await consultarFiltros(parametros);

        if (!datos) {
            return;
        }

        cargarCarreras(datos.carreras);
        cargarSemestres(datos.semestres);
        cargarSecciones(datos.secciones);
    });

    carreraSelect.addEventListener('change', async function () {
        limpiarSelect(semestreSelect, 'Cargando semestres...');
        limpiarSelect(seccionSelect, 'Cargando secciones...');

        const parametros = new URLSearchParams();

        parametros.set('periodo_id', periodoSelect.value);

        if (carreraSelect.value) {
            parametros.set('carrera_id', carreraSelect.value);
        }

        const datos = await consultarFiltros(parametros);

        if (!datos) {
            return;
        }

        cargarSemestres(datos.semestres);
        cargarSecciones(datos.secciones);
    });

    semestreSelect.addEventListener('change', async function () {
        limpiarSelect(seccionSelect, 'Cargando secciones...');

        const parametros = new URLSearchParams();

        parametros.set('periodo_id', periodoSelect.value);

        if (carreraSelect.value) {
            parametros.set('carrera_id', carreraSelect.value);
        }

        if (semestreSelect.value) {
            parametros.set('semestre', semestreSelect.value);
        }

        const datos = await consultarFiltros(parametros);

        if (!datos) {
            return;
        }

        cargarSecciones(datos.secciones);
    });

    document.querySelectorAll('.formulario-profesor').forEach(function (formulario) {
        const inputBusqueda = formulario.querySelector('.buscar-profesor');
        const botonBuscar = formulario.querySelector('.boton-buscar');
        const resultados = formulario.querySelector('.resultados-profesor');
        const profesorSelect = formulario.querySelector('.profesor-select');

        async function buscarProfesor() {
            const termino = inputBusqueda.value.trim();

            resultados.innerHTML = '';

            if (termino.length < 2) {
                resultados.classList.remove('hidden');
                resultados.innerHTML = `
                    <div class="text-xs text-gray-500">
                        Escribe al menos 2 caracteres.
                    </div>
                `;
                return;
            }

            resultados.classList.remove('hidden');
            resultados.innerHTML = `
                <div class="text-xs text-gray-500">
                    Buscando...
                </div>
            `;

            const parametros = new URLSearchParams();

            parametros.set('buscar_profesores', '1');
            parametros.set('seccion_id', formulario.dataset.seccionId);
            parametros.set('q', termino);

            const datos = await consultarFiltros(parametros);

            resultados.innerHTML = '';

            if (!datos || datos.profesores.length === 0) {
                resultados.innerHTML = `
                    <div class="text-xs text-gray-500">
                        No se encontraron docentes.
                    </div>
                `;
                return;
            }

            datos.profesores.forEach(function (profesor) {
                const tipoDocente = (profesor.tipo_profesor ?? '').replace(/\bprofesor(?:a)?\b/gi, (termino) => ({
                    PROFESOR: 'DOCENTE', PROFESORA: 'DOCENTE',
                    Profesor: 'Docente', Profesora: 'Docente',
                    profesor: 'docente', profesora: 'docente',
                }[termino] ?? 'Docente'));
                const boton = document.createElement('button');

                boton.type = 'button';
                boton.className =
                    'block min-w-0 w-full break-words rounded-md border border-gray-200 bg-white px-2 py-2 text-left text-xs hover:bg-gray-50';

                boton.innerHTML = `
                    <div class="font-semibold text-gray-700">
                        ${profesor.nombre}
                    </div>
                    <div class="text-gray-500">
                        RUT: ${profesor.rut ?? '—'}
                        ${tipoDocente ? ' · ' + tipoDocente : ''}
                    </div>
                `;

                boton.addEventListener('click', function () {
                    let option = profesorSelect.querySelector(
                        'option[value="' + profesor.id + '"]'
                    );

                    if (!option) {
                        option = document.createElement('option');

                        option.value = profesor.id;
                        option.textContent =
                            profesor.nombre + ' — ' + (profesor.rut ?? '');

                        profesorSelect.appendChild(option);
                    }

                    profesorSelect.value = profesor.id;

                    resultados.classList.add('hidden');
                    inputBusqueda.value = '';
                });

                resultados.appendChild(boton);
            });
        }

        botonBuscar.addEventListener('click', buscarProfesor);

        inputBusqueda.addEventListener('keydown', function (event) {
            if (event.key === 'Enter') {
                event.preventDefault();

                buscarProfesor();
            }
        });
    });
</script>

@endsection

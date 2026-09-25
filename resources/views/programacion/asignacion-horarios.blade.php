@extends('layouts.app')

@section('content')
<div class="w-full px-2 py-2">

    <div class="mb-2 rounded-lg border border-gray-200 bg-white px-4 py-3 shadow-sm">
        <h1 class="text-base font-bold text-gray-800">
            Asignación de horarios
        </h1>

        <p class="mt-0.5 text-xs text-gray-500">
            Consulta y modifica los horarios de la programación semestral actual.
        </p>
    </div>

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

    @if (!$version)
        <div class="mb-2 rounded-lg border border-yellow-200 bg-yellow-50 px-4 py-3 text-xs text-yellow-700">
            No existe una versión semestral actual para el período seleccionado.
        </div>
    @endif

    <div class="mb-2 rounded-lg border border-gray-200 bg-white px-4 py-3 shadow-sm">

        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">

            <div>
                <label for="periodo_id" class="mb-1 block text-xs font-semibold text-gray-600">
                    Período
                </label>

                <select
                    id="periodo_id"
                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-institucional focus:outline-none focus:ring-1 focus:ring-institucional"
                >
                    <option value="">
                        Seleccionar
                    </option>

                    @foreach ($periodos as $periodo)
                        <option
                            value="{{ $periodo->id }}"
                            @selected((int) $periodoId === (int) $periodo->id)
                        >
                            {{ $periodo->nombre ?? ($periodo->anio . '-' . $periodo->numero_semestre) }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="carrera_id" class="mb-1 block text-xs font-semibold text-gray-600">
                    Carrera
                </label>

                <select
                    id="carrera_id"
                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-institucional focus:outline-none focus:ring-1 focus:ring-institucional"
                >
                    <option value="">
                        Todas
                    </option>

                    @foreach ($carreras as $carrera)
                        <option
                            value="{{ $carrera->id }}"
                            @selected((int) $carreraId === (int) $carrera->id)
                        >
                            {{ $carrera->nombre }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="semestre" class="mb-1 block text-xs font-semibold text-gray-600">
                    Semestre
                </label>

                <select
                    id="semestre"
                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-institucional focus:outline-none focus:ring-1 focus:ring-institucional"
                >
                    <option value="">
                        Todos
                    </option>

                    @foreach ($semestres as $item)
                        <option
                            value="{{ $item }}"
                            @selected((string) $semestre === (string) $item)
                        >
                            Semestre {{ $item }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="seccion_id" class="mb-1 block text-xs font-semibold text-gray-600">
                    Sección
                </label>

                <select
                    id="seccion_id"
                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-institucional focus:outline-none focus:ring-1 focus:ring-institucional"
                >
                    <option value="">
                        Seleccionar
                    </option>

                    @foreach ($seccionesFiltro as $item)
                        <option
                            value="{{ $item->id }}"
                            @selected((int) $seccionId === (int) $item->id)
                        >
                            {{ $item->codigo }}
                            —
                            {{ $item->asignatura?->codigo }}
                            {{ $item->asignatura?->nombre ?? $item->nombre }}
                        </option>
                    @endforeach
                </select>
            </div>

        </div>

    </div>

    @if ($version)
        <div class="mb-2 rounded-lg border border-gray-200 bg-white px-4 py-3 shadow-sm">
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">

                <div>
                    <div class="text-[10px] font-bold uppercase tracking-wide text-gray-400">
                        Versión actual
                    </div>

                    <div class="mt-1 text-sm font-semibold text-gray-800">
                        {{ $version->nombre }}
                    </div>
                </div>

                <div>
                    <div class="text-[10px] font-bold uppercase tracking-wide text-gray-400">
                        Versión
                    </div>

                    <div class="mt-1 text-sm font-semibold text-gray-800">
                        V{{ $version->version }}
                    </div>
                </div>

                <div>
                    <div class="text-[10px] font-bold uppercase tracking-wide text-gray-400">
                        Estado
                    </div>

                    <div class="mt-1 text-sm font-semibold text-gray-800">
                        {{ $version->estado }}
                    </div>
                </div>

            </div>
        </div>
    @endif

    @if ($seccion)

        <div class="mb-2 rounded-lg border border-gray-200 bg-white px-4 py-3 shadow-sm">

            <div class="flex flex-col gap-1 sm:flex-row sm:items-start sm:justify-between">

                <div>
                    <h2 class="text-sm font-bold text-gray-800">
                        {{ $seccion->asignatura?->codigo }}
                        —
                        {{ $seccion->asignatura?->nombre ?? $seccion->nombre }}
                    </h2>

                    <div class="mt-1 text-xs text-gray-500">
                        Sección {{ $seccion->codigo }}
                        @if ($seccion->semestre)
                            · Semestre {{ $seccion->semestre }}
                        @endif
                    </div>
                </div>

                <div class="text-xs text-gray-500">
                    {{ $seccion->modalidad }}
                </div>

            </div>

        </div>

        @forelse ($seccion->actividades as $actividad)

            <div class="mb-3 overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">

                <div class="border-b border-gray-200 bg-gray-50 px-4 py-3">
                    <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">

                        <div>
                            <h3 class="text-sm font-bold text-gray-800">
                                {{ $actividad->tipo }}
                            </h3>

                            <p class="text-xs text-gray-500">
                                {{ $actividad->cantidad_horas }} hora(s)
                                @if ($actividad->cantidad_estudiantes)
                                    · {{ $actividad->cantidad_estudiantes }} estudiante(s)
                                @endif
                            </p>
                        </div>

                    </div>
                </div>

                <div class="divide-y divide-gray-100">

                    @forelse ($actividad->grupos as $grupo)

                        @php
                            $profesoresGrupo = $grupo->profesores;

                            $profesoresSinDisponibilidad = $profesoresGrupo->filter(function ($grupoProfesor) {
                                return $grupoProfesor->profesor
                                    && $grupoProfesor->profesor->disponibilidades->isEmpty();
                            });
                        @endphp

                        <div class="px-4 py-4">

                            <div class="mb-3 flex flex-col gap-2 lg:flex-row lg:items-start lg:justify-between">

                                <div>
                                    <div class="text-sm font-bold text-gray-800">
                                        {{ $grupo->codigo }}
                                    </div>

                                    <div class="mt-0.5 text-xs text-gray-500">
                                        Cupos: {{ $grupo->cupos ?? '—' }}
                                        ·
                                        Inscritos: {{ $grupo->inscritos ?? '—' }}
                                    </div>
                                </div>

                                <div class="flex flex-wrap gap-2">

                                    @forelse ($profesoresGrupo as $grupoProfesor)

                                        @if ($grupoProfesor->profesor)

                                            <span class="inline-flex items-center rounded-full border border-gray-200 bg-gray-50 px-3 py-1 text-xs text-gray-700">
                                                {{ trim($grupoProfesor->profesor->nombre . ' ' . $grupoProfesor->profesor->apellido) }}

                                                @if ($grupoProfesor->es_principal)
                                                    <span class="ml-1 font-semibold text-institucional">
                                                        · Principal
                                                    </span>
                                                @endif
                                            </span>

                                        @endif

                                    @empty

                                        <span class="inline-flex items-center rounded-full border border-yellow-200 bg-yellow-50 px-3 py-1 text-xs text-yellow-700">
                                            Sin profesor asignado
                                        </span>

                                    @endforelse

                                </div>

                            </div>

                            @if ($profesoresSinDisponibilidad->isNotEmpty())

                                <div class="mb-3 rounded-lg border border-yellow-200 bg-yellow-50 px-3 py-2 text-xs text-yellow-700">
                                    Uno o más profesores de este grupo no han informado disponibilidad para este período.
                                </div>

                            @endif

                            <div class="mb-3">

                                <div class="mb-2 text-xs font-bold uppercase tracking-wide text-gray-500">
                                    Horarios actuales
                                </div>

                                @forelse ($grupo->horarios as $horario)

                                    <div class="mb-2 flex flex-col gap-2 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 sm:flex-row sm:items-center sm:justify-between">

                                        <div class="text-xs text-gray-700">

                                            <span class="font-semibold">
                                                {{ $horario->dia?->nombre }}
                                            </span>

                                            ·

                                            Bloque {{ $horario->bloqueHorario?->numero }}

                                            ·

                                            {{ substr($horario->bloqueHorario?->hora_inicio, 0, 5) }}
                                            -
                                            {{ substr($horario->bloqueHorario?->hora_fin, 0, 5) }}

                                            @if ($horario->observaciones)
                                                <span class="text-gray-500">
                                                    · {{ $horario->observaciones }}
                                                </span>
                                            @endif

                                        </div>

                                        <form
                                            method="POST"
                                            action="{{ route('programacion.asignacion.horarios.eliminar', $horario) }}"
                                        >
                                            @csrf
                                            @method('DELETE')

                                            <input
                                                type="hidden"
                                                name="carrera_id"
                                                value="{{ $carreraId }}"
                                            >

                                            <input
                                                type="hidden"
                                                name="semestre"
                                                value="{{ $semestre }}"
                                            >

                                            <button
                                                type="submit"
                                                class="rounded-lg border border-red-200 bg-white px-3 py-1.5 text-xs font-semibold text-red-600 transition hover:bg-red-50"
                                                onclick="return confirm('¿Eliminar este horario?')"
                                            >
                                                Eliminar
                                            </button>
                                        </form>

                                    </div>

                                @empty

                                    <div class="rounded-lg border border-dashed border-gray-300 px-3 py-3 text-xs text-gray-500">
                                        Este grupo no tiene horarios asignados en la programación semestral actual.
                                    </div>

                                @endforelse

                            </div>

                            @if ($profesoresGrupo->isNotEmpty())

                                <form
                                    method="POST"
                                    action="{{ route('programacion.asignacion.horarios.guardar') }}"
                                    class="rounded-lg border border-gray-200 bg-white p-3"
                                >
                                    @csrf

                                    <input
                                        type="hidden"
                                        name="grupo_seccion_id"
                                        value="{{ $grupo->id }}"
                                    >

                                    <input
                                        type="hidden"
                                        name="carrera_id"
                                        value="{{ $carreraId }}"
                                    >

                                    <input
                                        type="hidden"
                                        name="semestre"
                                        value="{{ $semestre }}"
                                    >

                                    <div class="mb-2 text-xs font-bold uppercase tracking-wide text-gray-500">
                                        Agregar bloque
                                    </div>

                                    <div class="grid grid-cols-1 gap-2 md:grid-cols-2 xl:grid-cols-4">

                                        <div>
                                            <label class="mb-1 block text-xs font-semibold text-gray-600">
                                                Día
                                            </label>

                                            <select
                                                name="dia_id"
                                                required
                                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-institucional focus:outline-none focus:ring-1 focus:ring-institucional"
                                            >
                                                <option value="">
                                                    Seleccionar
                                                </option>

                                                @foreach ($dias as $dia)
                                                    <option value="{{ $dia->id }}">
                                                        {{ $dia->nombre }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>

                                        <div>
                                            <label class="mb-1 block text-xs font-semibold text-gray-600">
                                                Bloque
                                            </label>

                                            <select
                                                name="bloque_horario_id"
                                                required
                                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-institucional focus:outline-none focus:ring-1 focus:ring-institucional"
                                            >
                                                <option value="">
                                                    Seleccionar
                                                </option>

                                                @foreach ($bloques as $bloque)
                                                    <option value="{{ $bloque->id }}">
                                                        Bloque {{ $bloque->numero }}
                                                        —
                                                        {{ substr($bloque->hora_inicio, 0, 5) }}
                                                        -
                                                        {{ substr($bloque->hora_fin, 0, 5) }}

                                                        @if ($bloque->es_almuerzo)
                                                            — Almuerzo
                                                        @endif
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>

                                        <div>
                                            <label class="mb-1 block text-xs font-semibold text-gray-600">
                                                Observaciones
                                            </label>

                                            <input
                                                type="text"
                                                name="observaciones"
                                                maxlength="255"
                                                placeholder="Opcional"
                                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-institucional focus:outline-none focus:ring-1 focus:ring-institucional"
                                            >
                                        </div>

                                        <div class="flex items-end">
                                            <button
                                                type="submit"
                                                class="w-full rounded-lg bg-institucional px-4 py-2 text-sm font-semibold text-white transition hover:opacity-90"
                                            >
                                                Agregar horario
                                            </button>
                                        </div>

                                    </div>

                                </form>

                            @endif

                        </div>

                    @empty

                        <div class="px-4 py-4 text-xs text-gray-500">
                            Esta actividad no tiene grupos registrados.
                        </div>

                    @endforelse

                </div>

            </div>

        @empty

            <div class="rounded-lg border border-gray-200 bg-white px-4 py-4 text-xs text-gray-500 shadow-sm">
                La sección seleccionada no tiene actividades registradas.
            </div>

        @endforelse

    @else

        <div class="rounded-lg border border-dashed border-gray-300 bg-white px-4 py-6 text-center text-sm text-gray-500">
            Selecciona una sección para consultar y administrar sus horarios.
        </div>

    @endif

</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const periodo = document.getElementById('periodo_id');
    const carrera = document.getElementById('carrera_id');
    const semestre = document.getElementById('semestre');
    const seccion = document.getElementById('seccion_id');

    const filtrosUrl = @json(route('programacion.asignacion.horarios.filtros'));
    const indexUrl = @json(route('programacion.asignacion.horarios'));

    function limpiarSelect(select, texto) {
        select.innerHTML = '';

        const opcion = document.createElement('option');
        opcion.value = '';
        opcion.textContent = texto;

        select.appendChild(opcion);
    }

    function agregarOpcion(select, valor, texto) {
        const opcion = document.createElement('option');
        opcion.value = valor;
        opcion.textContent = texto;

        select.appendChild(opcion);
    }

    async function cargarFiltros() {
        if (!periodo.value) {
            limpiarSelect(carrera, 'Todas');
            limpiarSelect(semestre, 'Todos');
            limpiarSelect(seccion, 'Seleccionar');
            return;
        }

        const parametros = new URLSearchParams({
            periodo_id: periodo.value,
            carrera_id: carrera.value,
            semestre: semestre.value
        });

        const respuesta = await fetch(
            filtrosUrl + '?' + parametros.toString(),
            {
                headers: {
                    'Accept': 'application/json'
                }
            }
        );

        if (!respuesta.ok) {
            return;
        }

        const datos = await respuesta.json();

        const carreraActual = carrera.value;
        const semestreActual = semestre.value;

        limpiarSelect(carrera, 'Todas');

        datos.carreras.forEach(function (item) {
            agregarOpcion(
                carrera,
                item.id,
                item.nombre
            );
        });

        if (
            carreraActual
            && Array.from(carrera.options).some(
                opcion => opcion.value === carreraActual
            )
        ) {
            carrera.value = carreraActual;
        }

        limpiarSelect(semestre, 'Todos');

        datos.semestres.forEach(function (item) {
            agregarOpcion(
                semestre,
                item,
                'Semestre ' + item
            );
        });

        if (
            semestreActual
            && Array.from(semestre.options).some(
                opcion => opcion.value === semestreActual
            )
        ) {
            semestre.value = semestreActual;
        }

        limpiarSelect(seccion, 'Seleccionar');

        datos.secciones.forEach(function (item) {
            agregarOpcion(
                seccion,
                item.id,
                item.texto
            );
        });
    }

    function navegar() {
        if (!seccion.value) {
            return;
        }

        const parametros = new URLSearchParams({
            periodo_id: periodo.value,
            carrera_id: carrera.value,
            semestre: semestre.value,
            seccion_id: seccion.value
        });

        window.location.href =
            indexUrl + '?' + parametros.toString();
    }

    periodo.addEventListener('change', async function () {
        carrera.value = '';
        semestre.value = '';

        await cargarFiltros();
    });

    carrera.addEventListener('change', async function () {
        semestre.value = '';

        await cargarFiltros();
    });

    semestre.addEventListener('change', async function () {
        await cargarFiltros();
    });

    seccion.addEventListener('change', navegar);
});
</script>
@endsection
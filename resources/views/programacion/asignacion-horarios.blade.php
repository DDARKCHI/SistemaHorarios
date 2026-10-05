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

                <div class="divide-y divide-gray-100">

                    @forelse ($actividad->grupos as $grupo)
@php
    $profesoresGrupo = $grupo->profesores;

    $profesoresSinDisponibilidad = $profesoresGrupo->filter(function ($grupoProfesor) {
        return $grupoProfesor->profesor
            && $grupoProfesor->profesor->disponibilidades->isEmpty();
    });

    $cantidadMaximaModulos = (int) $actividad->cantidad_horas;
    $cantidadModulosAsignados = $grupo->horarios->count();

    $cantidadModulosDisponibles = max(
        0,
        $cantidadMaximaModulos - $cantidadModulosAsignados
    );

    $porcentajeAsignacion = $cantidadMaximaModulos > 0
        ? min(
            100,
            (int) round(
                ($cantidadModulosAsignados / $cantidadMaximaModulos) * 100
            )
        )
        : 0;
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

                                                {{ trim(
                                                    $grupoProfesor->profesor->nombre
                                                    . ' '
                                                    . $grupoProfesor->profesor->apellido
                                                ) }}

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

    @if ($porcentajeAsignacion === 100)

        <div class="rounded-lg border border-green-200 bg-green-50/70 px-4 py-3">

            <div class="flex items-center justify-between gap-3">

                <div>
                    <div class="text-xs font-bold uppercase text-green-700">
                        Asignación completa
                    </div>

                    <div class="mt-0.5 text-[11px] text-green-600">
                        {{ $cantidadModulosAsignados }} de {{ $cantidadMaximaModulos }} módulos asignados.
                    </div>
                </div>

                <div class="text-xl font-bold text-green-700">
                    {{ $porcentajeAsignacion }}%
                </div>

            </div>

        </div>

    @elseif ($porcentajeAsignacion > 0)

        <div class="rounded-lg border border-yellow-200 bg-yellow-50/70 px-4 py-3">

            <div class="flex items-center justify-between gap-3">

                <div>
                    <div class="text-xs font-bold uppercase text-yellow-700">
                        Asignación pendiente
                    </div>

                    <div class="mt-0.5 text-[11px] text-yellow-700">
                        {{ $cantidadModulosAsignados }} de {{ $cantidadMaximaModulos }} módulos asignados.
                    </div>

                    <div class="mt-1 text-[11px] font-semibold text-yellow-800">
                        @if ($cantidadModulosDisponibles === 1)
                            Falta 1 módulo por asignar.
                        @else
                            Faltan {{ $cantidadModulosDisponibles }} módulos por asignar.
                        @endif
                    </div>
                </div>

                <div class="text-xl font-bold text-yellow-700">
                    {{ $porcentajeAsignacion }}%
                </div>

            </div>

        </div>

    @else

        <div class="rounded-lg border border-red-200 bg-red-50/70 px-4 py-3">

            <div class="flex items-center justify-between gap-3">

                <div>
                    <div class="text-xs font-bold uppercase text-red-700">
                        Sin horarios asignados
                    </div>

                    <div class="mt-0.5 text-[11px] text-red-600">
                        0 de {{ $cantidadMaximaModulos }} módulos asignados.
                    </div>

                    <div class="mt-1 text-[11px] font-semibold text-red-700">
                        @if ($cantidadModulosDisponibles === 1)
                            Falta 1 módulo por asignar.
                        @else
                            Faltan {{ $cantidadModulosDisponibles }} módulos por asignar.
                        @endif
                    </div>
                </div>

                <div class="text-xl font-bold text-red-700">
                    0%
                </div>

            </div>

        </div>

    @endif

</div>

                            <div class="mb-3">

                                <div class="mb-2 text-xs font-bold uppercase tracking-wide text-gray-500">
                                    Horarios actuales
                                </div>

                                @forelse ($grupo->horarios as $horario)

                                    <div class="mb-2 flex flex-col gap-3 rounded-lg border border-gray-200 bg-gray-50 px-3 py-3 lg:flex-row lg:items-center lg:justify-between">

    <div class="min-w-0 flex-1">

        <div class="text-xs text-gray-700">

            <span class="font-semibold uppercase">
                {{ $horario->dia?->nombre }}
            </span>

            <span class="text-gray-400">
                ·
            </span>

            <span class="font-medium">
                Módulo {{ $horario->bloqueHorario?->numero }}
            </span>

            <span class="text-gray-400">
                ·
            </span>

            {{ substr($horario->bloqueHorario?->hora_inicio, 0, 5) }}
            -
            {{ substr($horario->bloqueHorario?->hora_fin, 0, 5) }}

        </div>

        @if ($horario->observaciones)

            <div class="mt-1 text-[11px] text-gray-500">
                {{ $horario->observaciones }}
            </div>

        @endif

    </div>

    @if ($horario->profesores->isNotEmpty())

        <div class="shrink-0 rounded-md border border-gray-200 bg-white px-3 py-2">

            <div class="text-[9px] font-bold uppercase tracking-wide text-gray-400">
                {{ $horario->profesores->count() === 1 ? 'Profesor asignado' : 'Profesores asignados' }}
            </div>

            <div class="mt-0.5 text-[11px] font-semibold text-gray-700">

                {{ $horario->profesores
                    ->filter(fn ($horarioProfesor) => $horarioProfesor->profesor)
                    ->map(function ($horarioProfesor) {
                        return trim(
                            $horarioProfesor->profesor->nombre
                            . ' '
                            . $horarioProfesor->profesor->apellido
                        );
                    })
                    ->implode(' · ') }}

            </div>

        </div>

    @endif

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

    @if ($porcentajeAsignacion < 100)

        <form
            method="POST"
            action="{{ route('programacion.asignacion.horarios.guardar') }}"
    class="js-form-horario rounded-lg border border-gray-200 bg-white p-3"
    data-max-modulos="{{ $cantidadMaximaModulos }}"
    data-modulos-asignados="{{ $cantidadModulosAsignados }}"
    data-modulos-disponibles="{{ $cantidadModulosDisponibles }}"
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

                                    <div class="mb-3">

                                        <div class="text-xs font-bold uppercase tracking-wide text-gray-500">
                                            Agregar horario
                                        </div>

                                        <div class="mt-1 text-xs text-gray-400">
    Selecciona un día y los módulos horarios correspondientes.
</div>



                                    </div>

                                    <div class="mb-3 grid grid-cols-1 gap-3 lg:grid-cols-2">

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

                                    </div>

                                    <div class="mb-3">

    <div class="mb-2 text-xs font-semibold text-gray-600">
        Profesor(es) para este horario
    </div>

    @if ($profesoresGrupo->count() === 1)

        @php
            $grupoProfesorUnico = $profesoresGrupo->first();
            $profesorUnico = $grupoProfesorUnico?->profesor;
        @endphp

        <input
            type="hidden"
            name="profesores_ids[]"
            value="{{ $profesorUnico?->id }}"
        >

        <div class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-xs text-gray-700">

            <span class="font-semibold">
                {{ trim(
                    ($profesorUnico?->nombre ?? '')
                    . ' '
                    . ($profesorUnico?->apellido ?? '')
                ) }}
            </span>

            <span class="ml-1 text-gray-400">
                · Seleccionado automáticamente
            </span>

        </div>

    @else

        <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">

            @foreach ($profesoresGrupo as $grupoProfesor)

                @if ($grupoProfesor->profesor)

                    <label class="flex cursor-pointer items-center gap-2 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 transition hover:border-gray-300 hover:bg-white">

                        <input
                            type="checkbox"
                            name="profesores_ids[]"
                            value="{{ $grupoProfesor->profesor->id }}"
                            class="rounded border-gray-300 text-institucional focus:ring-institucional"
                        >

                        <span class="text-xs text-gray-700">

                            {{ trim(
                                $grupoProfesor->profesor->nombre
                                . ' '
                                . $grupoProfesor->profesor->apellido
                            ) }}

                            @if ($grupoProfesor->es_principal)

                                <span class="font-semibold text-institucional">
                                    · Principal
                                </span>

                            @endif

                        </span>

                    </label>

                @endif

            @endforeach

        </div>

        <div class="mt-1 text-[11px] text-gray-400">
            Selecciona uno o más profesores que dictarán los módulos elegidos.
        </div>

    @endif

</div>
                                    <div class="mb-3">

                                        <div class="mb-2 flex items-center justify-between">

                                            <label class="text-xs font-semibold text-gray-600">
                                                Módulos horarios
                                            </label>

                                            <span class="text-[11px] text-gray-400">
                                               Máximo {{ $cantidadMaximaModulos }}
                                            </span>

                                        </div>

                                        <div class="grid grid-cols-2 gap-2 sm:grid-cols-3 md:grid-cols-4 xl:grid-cols-5">

                                            @foreach ($bloques as $bloque)

                                                <label
                                                    class="flex cursor-pointer items-start gap-2 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 transition hover:border-gray-300 hover:bg-white"
                                                >
                                                    <input
                                                        type="checkbox"
                                                        name="bloques_horarios[]"
                                                        value="{{ $bloque->id }}"
                                                        class="mt-0.5 rounded border-gray-300 text-institucional focus:ring-institucional"
                                                    >

                                                    <span class="min-w-0">

                                                        <span class="block text-xs font-semibold text-gray-700">
                                                            Módulo {{ $bloque->numero }}
                                                        </span>

                                                        <span class="block text-[11px] text-gray-500">
                                                            {{ substr($bloque->hora_inicio, 0, 5) }}
                                                            -
                                                            {{ substr($bloque->hora_fin, 0, 5) }}
                                                        </span>

                                                        @if ($bloque->es_almuerzo)
                                                            <span class="mt-0.5 block text-[10px] font-semibold text-yellow-600">
                                                                Almuerzo
                                                            </span>
                                                        @endif

                                                    </span>

                                                </label>

                                            @endforeach

                                        </div>

                                    </div>

                                    <div class="flex justify-end">

                                        <button
                                            type="submit"
                                            class="rounded-lg bg-institucional px-4 py-2 text-sm font-semibold text-white transition hover:opacity-90"
                                        >
                                            Agregar horario
                                        </button>

                                    </div>

                                </form>
@endif
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

    const filtrosUrl = @json(
        route('programacion.asignacion.horarios.filtros')
    );

    const indexUrl = @json(
        route('programacion.asignacion.horarios')
    );

    const filtrosBuscables = new Map();

    function normalizarTexto(texto) {
        return String(texto ?? '')
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .toLowerCase()
            .trim();
    }

    function crearFiltroBuscable(select, placeholder) {

        if (!select || filtrosBuscables.has(select)) {
            return;
        }

        const contenedor = document.createElement('div');
        contenedor.className = 'relative w-full';

        const input = document.createElement('input');

        input.type = 'text';
        input.autocomplete = 'off';
        input.spellcheck = false;
        input.placeholder = placeholder;

        input.className =
            'h-10 w-full rounded-lg border border-gray-300 bg-white px-3 pr-8 text-sm text-gray-700 outline-none transition placeholder:text-gray-400 focus:border-institucional focus:ring-1 focus:ring-institucional';

        const icono = document.createElement('div');

        icono.className =
            'pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-gray-400';

        icono.innerHTML = `
            <svg
                class="h-4 w-4"
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
            'absolute left-0 top-full z-50 mt-1 hidden w-max min-w-full max-w-[520px] max-h-60 overflow-y-auto rounded-md border border-gray-200 bg-white py-1 shadow-lg';

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
        };

        filtrosBuscables.set(
            select,
            estado
        );

        function obtenerOpciones() {
            return Array.from(select.options);
        }
        function obtenerTextoOpcion(opcion) {

    return opcion
        ? opcion.textContent
            .replace(/\s+/g, ' ')
            .trim()
        : '';
}

        function cerrarLista() {
            lista.classList.add('hidden');
        }

        function mostrarOpciones(busqueda = '') {

            lista.innerHTML = '';

            const textoBusqueda =
                normalizarTexto(busqueda);

            const opciones = obtenerOpciones()
                .filter(function (opcion) {

                    if (!opcion.value) {
                        return false;
                    }

                    return normalizarTexto(
    obtenerTextoOpcion(opcion)
).includes(textoBusqueda);
                });

            if (opciones.length === 0) {

                const vacio =
                    document.createElement('div');

                vacio.className =
                    'px-3 py-2 text-xs text-gray-400';

                vacio.textContent =
                    'Sin resultados';

                lista.appendChild(vacio);

            } else {

                opciones.forEach(function (opcion) {

                    const item =
                        document.createElement('button');

                    item.type = 'button';

                    item.className =
    'flex w-full items-center px-3 py-2 text-left text-xs text-gray-700 transition hover:bg-gray-50';

if (
    String(opcion.value) ===
    String(select.value)
) {
    item.classList.add(
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
    obtenerTextoOpcion(opcion);

item.appendChild(texto);

if (
    String(opcion.value) ===
    String(select.value)
) {

    const check =
        document.createElement('span');

    check.className =
        'ml-2 shrink-0 text-red-700';

    check.innerHTML = `
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

                            select.value =
                                opcion.value;

                            input.value =
    obtenerTextoOpcion(opcion);

                            cerrarLista();

                            select.dispatchEvent(
                                new Event(
                                    'change',
                                    {
                                        bubbles: true
                                    }
                                )
                            );
                        }
                    );

                    lista.appendChild(item);
                });
            }

            lista.classList.remove('hidden');
        }

       input.addEventListener(
    'focus',
    function () {

        input.select();

        mostrarOpciones('');
    }
);

input.addEventListener(
    'click',
    function () {

        input.select();

        mostrarOpciones('');
    }
);

        input.addEventListener(
            'input',
            function () {

                const opcionSeleccionada =
                    select.options[
                        select.selectedIndex
                    ];

                const textoSeleccionado =
    opcionSeleccionada?.value
        ? obtenerTextoOpcion(opcionSeleccionada)
        : '';

                if (
                    normalizarTexto(input.value)
                    !== normalizarTexto(textoSeleccionado)
                ) {
                    select.value = '';
                }

                mostrarOpciones(
                    input.value
                );
            }
        );

        input.addEventListener(
            'keydown',
            function (event) {

                if (event.key === 'Escape') {
                    cerrarLista();
                    input.blur();
                }
            }
        );

        input.addEventListener(
            'blur',
            function () {

                setTimeout(function () {

                    cerrarLista();

                    const opcionSeleccionada =
                        select.options[
                            select.selectedIndex
                        ];

                    if (
                        opcionSeleccionada
                        && opcionSeleccionada.value
                    ) {
                        input.value =
    obtenerTextoOpcion(opcionSeleccionada);
                    } else {
                        input.value = '';
                    }

                }, 120);
            }
        );

        actualizarFiltroBuscable(select);
    }

    function actualizarFiltroBuscable(select) {

        const estado =
            filtrosBuscables.get(select);

        if (!estado) {
            return;
        }

        const opcionSeleccionada =
            select.options[
                select.selectedIndex
            ];

        if (
            opcionSeleccionada
            && opcionSeleccionada.value
        ) {
            estado.input.value =
    opcionSeleccionada
        .textContent
        .replace(/\s+/g, ' ')
        .trim();
        } else {
            estado.input.value = '';
        }

        estado.lista.classList.add(
            'hidden'
        );
    }

    function limpiarSelect(select, texto) {

        select.innerHTML = '';

        const opcion =
            document.createElement('option');

        opcion.value = '';
        opcion.textContent = texto;

        select.appendChild(opcion);

        actualizarFiltroBuscable(select);
    }

    function agregarOpcion(
        select,
        valor,
        texto
    ) {

        const opcion =
            document.createElement('option');

        opcion.value = valor;
        opcion.textContent = texto;

        select.appendChild(opcion);
    }

    async function cargarFiltros() {

        if (!periodo.value) {

            limpiarSelect(
                carrera,
                'Todas'
            );

            limpiarSelect(
                semestre,
                'Todos'
            );

            limpiarSelect(
                seccion,
                'Seleccionar'
            );

            return;
        }

        const parametros =
            new URLSearchParams({
                periodo_id:
                    periodo.value,

                carrera_id:
                    carrera.value,

                semestre:
                    semestre.value
            });

        const respuesta = await fetch(
            filtrosUrl
            + '?'
            + parametros.toString(),
            {
                headers: {
                    'Accept':
                        'application/json'
                }
            }
        );

        if (!respuesta.ok) {
            return;
        }

        const datos =
            await respuesta.json();

        const carreraActual =
            carrera.value;

        const semestreActual =
            semestre.value;

        limpiarSelect(
            carrera,
            'Todas'
        );

        datos.carreras.forEach(
            function (item) {

                agregarOpcion(
                    carrera,
                    item.id,
                    item.nombre
                );
            }
        );

        if (
            carreraActual
            && Array.from(
                carrera.options
            ).some(
                opcion =>
                    opcion.value
                    === carreraActual
            )
        ) {
            carrera.value =
                carreraActual;
        }

        actualizarFiltroBuscable(
            carrera
        );

        limpiarSelect(
            semestre,
            'Todos'
        );

        datos.semestres.forEach(
            function (item) {

                agregarOpcion(
                    semestre,
                    item,
                    'Semestre ' + item
                );
            }
        );

        if (
            semestreActual
            && Array.from(
                semestre.options
            ).some(
                opcion =>
                    opcion.value
                    === semestreActual
            )
        ) {
            semestre.value =
                semestreActual;
        }

        actualizarFiltroBuscable(
            semestre
        );

        limpiarSelect(
            seccion,
            'Seleccionar'
        );

        datos.secciones.forEach(
            function (item) {

                agregarOpcion(
                    seccion,
                    item.id,
                    item.texto
                );
            }
        );

        actualizarFiltroBuscable(
            seccion
        );
    }

    function navegar() {

        if (!seccion.value) {
            return;
        }

        const parametros =
            new URLSearchParams({
                periodo_id:
                    periodo.value,

                carrera_id:
                    carrera.value,

                semestre:
                    semestre.value,

                seccion_id:
                    seccion.value
            });

        window.location.href =
            indexUrl
            + '?'
            + parametros.toString();
    }

    crearFiltroBuscable(
        periodo,
        'Buscar período...'
    );

    crearFiltroBuscable(
        carrera,
        'Buscar carrera...'
    );

    crearFiltroBuscable(
        semestre,
        'Buscar semestre...'
    );

    crearFiltroBuscable(
        seccion,
        'Buscar sección...'
    );

    periodo.addEventListener(
        'change',
        async function () {

            carrera.value = '';
            semestre.value = '';
            seccion.value = '';

            actualizarFiltroBuscable(
                carrera
            );

            actualizarFiltroBuscable(
                semestre
            );

            actualizarFiltroBuscable(
                seccion
            );

            await cargarFiltros();
        }
    );

    carrera.addEventListener(
        'change',
        async function () {

            semestre.value = '';
            seccion.value = '';

            actualizarFiltroBuscable(
                semestre
            );

            actualizarFiltroBuscable(
                seccion
            );

            await cargarFiltros();
        }
    );

    semestre.addEventListener(
        'change',
        async function () {

            seccion.value = '';

            actualizarFiltroBuscable(
                seccion
            );

            await cargarFiltros();
        }
    );

    seccion.addEventListener(
        'change',
        navegar
    );

    document.addEventListener(
        'click',
        function (event) {

            filtrosBuscables.forEach(
                function (estado) {

                    if (
                        !estado.contenedor
                            .contains(
                                event.target
                            )
                    ) {
                        estado.lista
                            .classList
                            .add('hidden');
                    }
                }
            );
        }
    );

    document
        .querySelectorAll(
            '.js-form-horario'
        )
        .forEach(
            function (formulario) {

                const maxModulos =
                    Number(
                        formulario.dataset
                            .maxModulos || 0
                    );

                const modulosAsignados =
                    Number(
                        formulario.dataset
                            .modulosAsignados || 0
                    );

                const disponibles =
                    Math.max(
                        0,
                        maxModulos
                        - modulosAsignados
                    );

                const checkboxes =
                    Array.from(
                        formulario
                            .querySelectorAll(
                                'input[name="bloques_horarios[]"]'
                            )
                    );

                const boton =
                    formulario.querySelector(
                        'button[type="submit"]'
                    );

                function actualizarSeleccion() {

                    const seleccionados =
                        checkboxes.filter(
                            checkbox =>
                                checkbox.checked
                        );

                    checkboxes.forEach(
                        function (checkbox) {

                            if (
                                seleccionados.length
                                    >= disponibles
                                && !checkbox.checked
                            ) {
                                checkbox.disabled =
                                    true;
                            } else {
                                checkbox.disabled =
                                    false;
                            }
                        }
                    );

                    if (boton) {

                        boton.disabled =
                            disponibles <= 0;

                        boton.classList.toggle(
                            'opacity-50',
                            disponibles <= 0
                        );

                        boton.classList.toggle(
                            'cursor-not-allowed',
                            disponibles <= 0
                        );
                    }
                }

                checkboxes.forEach(
                    function (checkbox) {

                        checkbox.addEventListener(
                            'change',
                            actualizarSeleccion
                        );
                    }
                );

                actualizarSeleccion();
            }
        );
});
</script>
@endsection
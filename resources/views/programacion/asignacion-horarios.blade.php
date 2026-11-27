@extends('layouts.app')

@section('content')
<x-encabezado-pagina titulo="Asignación de horarios">
    Consulta y modifica los horarios de la programación semestral actual.
</x-encabezado-pagina>

<div class="min-w-0 w-full">

    @if (session('success'))
        <div class="mb-2 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-xs font-medium text-green-700">
            {{ session('success') }}
        </div>
    @endif

    @if ($errors->any())
        <div role="alert" class="mb-2 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            {{ $errors->first() }}
        </div>
    @endif

    @if ($version && ! $editable)
        <div role="status" class="mb-4 rounded-xl border border-borde bg-white p-5 text-sm text-gray-600 shadow-sm sm:p-6">Esta programación está disponible para consulta. Los horarios solo se modifican en la versión actual en borrador de un período activo.</div>
    @endif

    @if (!$version)
        <div class="mb-2 rounded-lg border border-yellow-200 bg-yellow-50 px-4 py-3 text-xs text-yellow-700">
            No existe una versión semestral actual para el período seleccionado.
        </div>
    @endif

    <x-filtros-compactos
        id="filtros-horarios"
        :campos="4"
        action="{{ route('programacion.asignacion.horarios') }}"
        data-url-opciones="{{ route('programacion.asignacion.horarios.filtros') }}"
        class="mb-3"
        aria-label="Filtros para asignar horarios"
    >
        <div>
            <label for="periodo_id" class="mb-2 block text-sm font-semibold">Período</label>
            <select id="periodo_id" name="periodo_id" data-select-buscable class="h-11 min-w-0 w-full rounded-lg border border-borde bg-superficie px-3 text-sm text-texto">
                <option value="">Seleccionar período</option>
                @foreach ($periodos as $periodo)
                    <option value="{{ $periodo->id }}" @selected((int) $periodoId === (int) $periodo->id)>{{ $periodo->nombre }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="carrera_id" class="mb-2 block text-sm font-semibold">Carrera</label>
            <select id="carrera_id" name="carrera_id" data-select-buscable class="h-11 min-w-0 w-full rounded-lg border border-borde bg-superficie px-3 text-sm text-texto">
                <option value="">Todas las carreras</option>
                @foreach ($carreras as $carrera)
                    <option value="{{ $carrera->id }}" @selected((int) $carreraId === (int) $carrera->id)>{{ $carrera->nombre }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="semestre" class="mb-2 block text-sm font-semibold">Semestre</label>
            <select id="semestre" name="semestre" data-select-buscable class="h-11 min-w-0 w-full rounded-lg border border-borde bg-superficie px-3 text-sm text-texto">
                <option value="">Todos</option>
                @foreach ($semestres as $item)
                    <option value="{{ $item }}" @selected((string) $semestre === (string) $item)>Semestre {{ $item }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="seccion_id" class="mb-2 block text-sm font-semibold">Sección</label>
            <select id="seccion_id" name="seccion_id" data-select-buscable class="h-11 min-w-0 w-full rounded-lg border border-borde bg-superficie px-3 text-sm text-texto">
                <option value="">Seleccionar sección</option>
                @foreach ($seccionesFiltro as $item)
                    <option value="{{ $item->id }}" @selected((int) $seccionId === (int) $item->id)>{{ $item->codigo }} · {{ $item->asignatura?->codigo }} {{ $item->asignatura?->nombre ?? $item->nombre }}</option>
                @endforeach
            </select>
        </div>
        <x-slot:acciones>
            <a href="{{ route('programacion.asignacion.horarios') }}" class="boton boton-secundario">Limpiar</a>
            <button type="submit" class="boton boton-primario">Consultar</button>
        </x-slot:acciones>
    </x-filtros-compactos>
    <p id="estado-filtros-horarios" class="mb-3 rounded-lg border border-borde bg-superficie px-4 py-3 text-sm text-texto-secundario" role="status" hidden></p>

    <div id="resultado-asignacion-horarios" class="min-w-0">
    @if ($version)
        <div class="mb-3 flex flex-wrap items-center justify-between gap-2 rounded-xl border border-borde bg-superficie px-4 py-3 text-sm shadow-sm">
            <p class="font-semibold text-texto">{{ $version->nombre }}</p>
            <p class="rounded-full bg-fondo px-3 py-1 text-xs font-medium text-texto-secundario">V{{ $version->version }} · {{ $version->estado }}</p>
        </div>
    @endif

    @if ($seccion)

        <div class="mb-2 rounded-xl border border-borde bg-white px-4 py-3 shadow-sm">

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

            <div class="mb-3 overflow-hidden rounded-xl border border-borde bg-white shadow-sm">

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
    $docentesBase = $grupo->profesores->isNotEmpty() ? $grupo->profesores : $seccion->profesores;
    $profesoresGrupo = $docentesBase
        ->concat($grupo->horarios->flatMap(fn ($h) => $h->profesores))
        ->filter(fn ($asignacion) => $asignacion->profesor?->estado)
        ->unique('profesor_id')->values();

    $profesoresSinDisponibilidad = $profesoresGrupo->filter(function ($grupoProfesor) {
        return $grupoProfesor->profesor
            && $grupoProfesor->profesor->disponibilidades->isEmpty();
    });

    $cantidadMaximaModulos = (int) $actividad->cantidad_horas;
    $cantidadModulosAsignados = $grupo->horarios->count();

    $progresoModulos = \App\Support\ProgresoModulos::calcular(
        $cantidadModulosAsignados,
        $cantidadMaximaModulos
    );
    $cantidadModulosDisponibles = $progresoModulos['faltantes'];
    $restaurar = is_scalar(old('grupo_seccion_id')) && is_scalar(old('version_id'))
        && (string) old('grupo_seccion_id') === (string) $grupo->id && (string) old('version_id') === (string) $version->id;
    $idsAnteriores = static fn ($campo) => $restaurar && is_array(old($campo)) ? array_map('strval', array_filter(old($campo), 'is_scalar')) : [];
    $diaAnterior = $restaurar && is_scalar(old('dia_id')) ? (string) old('dia_id') : '';
    $observacionAnterior = $restaurar && is_scalar(old('observaciones')) ? (string) old('observaciones') : '';
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

                                <div class="flex min-w-0 max-w-full flex-wrap gap-2">

                                    @forelse ($profesoresGrupo as $grupoProfesor)

                                        @if ($grupoProfesor->profesor)

                                            <span class="inline-flex min-w-0 max-w-full flex-wrap items-center break-words rounded-full border border-gray-200 bg-gray-50 px-3 py-1 text-xs text-gray-700">

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
                                            Sin docente asignado
                                        </span>

                                    @endforelse

                                </div>

                            </div>

                            @if ($profesoresSinDisponibilidad->isNotEmpty())

                                <div class="mb-3 rounded-lg border border-yellow-200 bg-yellow-50 px-3 py-2 text-xs text-yellow-700">
                                    Uno o más docentes de este grupo no han informado disponibilidad para este período.
                                </div>

                            @endif
                            <x-progreso-modulos :progreso="$progresoModulos" />

                            <div class="mb-3">

                                <div class="mb-2 text-xs font-bold uppercase tracking-wide text-gray-500">
                                    Horarios actuales
                                </div>

                                @forelse ($grupo->horarios as $horario)
                                    @php
                                        $docentesHorario = $horario->profesores->isNotEmpty() ? $horario->profesores : $docentesBase;
                                        $nombresDocentes = $docentesHorario->unique('profesor_id')->pluck('profesor')->filter()
                                            ->map(fn ($docente) => trim($docente->nombre.' '.$docente->apellido))->implode(' · ');
                                        $laboratoriosHorario = $horario->asignacionesLaboratorios->pluck('laboratorio')->filter();
                                        $salasHorario = $horario->asignacionesSalas->pluck('sala')->filter()->whereNotIn('id', $laboratoriosHorario->pluck('sala_id'));
                                        $espaciosHorario = $salasHorario->map(fn ($sala) => $sala->codigo.($sala->nombre && $sala->nombre !== $sala->codigo ? ' · '.$sala->nombre : ''))
                                            ->concat($laboratoriosHorario->pluck('nombre'))
                                            ->concat($horario->asignacionesLaboratoriosMoviles->pluck('laboratorioMovil.nombre'))
                                            ->filter()->unique()->implode(' · ');
                                    @endphp

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

            {{ substr((string) $horario->bloqueHorario?->hora_inicio, 0, 5) }}
            -
            {{ substr((string) $horario->bloqueHorario?->hora_fin, 0, 5) }}

        </div>

        @if ($horario->observaciones)

            <div class="mt-1 break-words text-xs text-gray-500">
                {{ $horario->observaciones }}
            </div>

        @endif

    </div>

    <dl class="min-w-0 break-words rounded-md border border-gray-200 bg-white px-3 py-2 text-xs lg:flex-1">
        <dt class="font-semibold text-gray-500">{{ $docentesHorario->unique('profesor_id')->count() === 1 ? 'Docente asignado' : 'Docentes' }}</dt>
        <dd class="mt-0.5 font-semibold text-gray-700">{{ $nombresDocentes ?: 'Sin docente asignado' }}</dd>
        @if ($nombresDocentes && $horario->profesores->isEmpty())
            <dd class="mt-0.5 text-gray-500">{{ $grupo->profesores->isNotEmpty() ? 'Asignación del grupo' : 'Asignación de la sección' }}</dd>
        @endif
        <dt class="mt-2 font-semibold text-gray-500">Espacio</dt>
        <dd class="mt-0.5 text-gray-700">{{ $espaciosHorario ?: 'Pendiente de asignar' }}</dd>
    </dl>

                                        @if ($editable)
                                        <div class="flex flex-wrap items-center gap-2">
                                        <a class="boton boton-secundario" href="{{ route('programacion.cambios.create', $horario) }}">Solicitar cambio</a>
                                        <button type="button" class="boton boton-secundario" data-asignar-docente
                                            data-url="{{ route('programacion.asignacion.horarios.profesor', $horario) }}"
                                            data-horario="{{ $horario->id }}"
                                            data-huella="{{ \App\Services\AsignacionModulosHorario::firmaDocentes($docentesHorario) }}"
                                            data-docentes-actuales="{{ $nombresDocentes }}"
                                            data-resumen="{{ $seccion->asignatura?->nombre }} · {{ $horario->dia?->nombre }} · {{ substr((string) $horario->bloqueHorario?->hora_inicio, 0, 5) }}–{{ substr((string) $horario->bloqueHorario?->hora_fin, 0, 5) }}">
                                            Asignar docente
                                        </button>
                                        <form
                                            method="POST"
                                            action="{{ route('programacion.asignacion.horarios.eliminar', $horario) }}"
                                            data-confirmar="Este módulo se quitará de la programación actual."
                                            data-confirmar-titulo="Eliminar horario"
                                            data-confirmar-accion="Eliminar"
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
                                                class="boton boton-secundario"
                                            >
                                                Eliminar
                                            </button>
                                        </form>
                                        </div>
                                        @endif

                                    </div>

                                @empty

                                    <div class="rounded-lg border border-dashed border-gray-300 px-3 py-3 text-xs text-gray-500">
                                        Este grupo no tiene horarios asignados en la programación semestral actual.
                                    </div>

                                @endforelse

                            </div>

                            @if ($editable && $progresoModulos['puede_agregar'] && $profesoresGrupo->isEmpty())
                                <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-borde bg-fondo px-4 py-3 text-sm text-texto-secundario">
                                    <p>Asigna un docente al grupo para poder agregar horarios.</p>
                                    @can('asignar profesores')
                                        <a class="boton boton-secundario" href="{{ route('programacion.asignacion.profesores', ['periodo_id' => $periodoId, 'carrera_id' => $carreraId ?: null, 'semestre' => $semestre, 'seccion_id' => $seccion->id]) }}">Asignar docentes al grupo</a>
                                    @endcan
                                </div>
                            @endif

    @if ($editable && $progresoModulos['puede_agregar'] && $profesoresGrupo->isNotEmpty())

        <form
            method="POST"
            action="{{ route('programacion.asignacion.horarios.guardar') }}"
    class="js-form-horario rounded-xl border border-borde bg-white p-5 sm:p-6"
    data-max-modulos="{{ $cantidadMaximaModulos }}"
    data-modulos-asignados="{{ $cantidadModulosAsignados }}"
    data-modulos-disponibles="{{ $cantidadModulosDisponibles }}"
    data-confirmar="Se registrarán los módulos seleccionados en el horario del grupo."
    data-confirmar-titulo="Agregar horarios al grupo"
    data-confirmar-accion="Agregar horarios"
>
                                    @csrf
                                    <input type="hidden" name="version_id" value="{{ $version->id }}">

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

                                    <div class="mb-3 grid min-w-0 grid-cols-1 gap-3 lg:grid-cols-2 [&>div]:min-w-0">

                                        <div>
                                            <label for="dia-grupo-{{ $grupo->id }}" class="mb-1 block text-xs font-semibold text-gray-600">
                                                Día
                                            </label>

                                            <select
                                                id="dia-grupo-{{ $grupo->id }}"
                                                name="dia_id"
                                                required
                                            class="min-w-0 w-full max-w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-institucional focus:outline-none focus:ring-1 focus:ring-institucional"
                                            >
                                                <option value="">
                                                    Seleccionar
                                                </option>

                                                @foreach ($dias as $dia)
                                                    <option value="{{ $dia->id }}" @selected($diaAnterior === (string) $dia->id)>
                                                        {{ $dia->nombre }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>

                                        <div>
                                            <label for="observaciones-grupo-{{ $grupo->id }}" class="mb-1 block text-xs font-semibold text-gray-600">
                                                Observaciones
                                            </label>

                                            <input
                                                id="observaciones-grupo-{{ $grupo->id }}"
                                                type="text"
                                                name="observaciones"
                                                value="{{ $observacionAnterior }}"
                                                maxlength="255"
                                                placeholder="Opcional"
                                                class="min-w-0 w-full max-w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-institucional focus:outline-none focus:ring-1 focus:ring-institucional"
                                            >
                                        </div>

                                    </div>

                                    <div class="mb-3">

    <div class="mb-2 text-xs font-semibold text-gray-600">
        Docentes para este horario
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

                    <label class="flex min-w-0 cursor-pointer items-start gap-2 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 transition hover:border-gray-300 hover:bg-white">

                        <input
                            type="checkbox"
                            name="profesores_ids[]"
                            value="{{ $grupoProfesor->profesor->id }}"
                            @checked(in_array((string) $grupoProfesor->profesor->id, $idsAnteriores('profesores_ids'), true))
                            class="mt-0.5 shrink-0 rounded border-gray-300 text-institucional focus:ring-institucional"
                        >

                        <span class="min-w-0 break-words text-xs text-gray-700">

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

        <div class="mt-1 text-xs text-gray-400">
            Selecciona uno o más docentes que dictarán los módulos elegidos.
        </div>

    @endif

</div>
                                    <div class="mb-3">

                                        <div class="mb-2 flex items-center justify-between">

                                            <label class="text-xs font-semibold text-gray-600">
                                                Módulos horarios
                                            </label>

                                            <span class="text-xs text-gray-400">
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
                                                        @checked(in_array((string) $bloque->id, $idsAnteriores('bloques_horarios'), true))
                                                        class="mt-0.5 rounded border-gray-300 text-institucional focus:ring-institucional"
                                                    >

                                                    <span class="min-w-0">

                                                        <span class="block text-xs font-semibold text-gray-700">
                                                            Módulo {{ $bloque->numero }}
                                                        </span>

                                                        <span class="block text-xs text-gray-500">
                                                            {{ substr($bloque->hora_inicio, 0, 5) }}
                                                            -
                                                            {{ substr($bloque->hora_fin, 0, 5) }}
                                                        </span>

                                                        @if ($bloque->es_almuerzo)
                                                            <span class="mt-0.5 block text-xs font-semibold text-yellow-600">
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
                                            class="boton boton-primario"
                                        >
                                            Agregar horario
                                        </button>

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

            <div class="rounded-xl border border-borde bg-white px-4 py-4 text-xs text-gray-500 shadow-sm">
                La sección seleccionada no tiene actividades registradas.
            </div>

        @endforelse

    @else

        <div class="rounded-lg border border-dashed border-gray-300 bg-white px-4 py-6 text-center text-sm text-gray-500">
            Elige una sección y pulsa Consultar para ver sus horarios.
        </div>

    @endif

    </div>
</div>

@if ($seccion && $editable)
    <x-asignar-docente-horario :profesores="$profesoresParaHorario" :version="$version" :carrera-id="$carreraId" :semestre="$semestre" />
@endif

<script>
document.addEventListener('DOMContentLoaded', () => {
    const filtros = document.getElementById('filtros-horarios');
    const resultado = document.getElementById('resultado-asignacion-horarios');
    const estado = document.getElementById('estado-filtros-horarios');
    const consultar = filtros.querySelector('button[type="submit"]');
    const { periodo_id: periodo, carrera_id: carrera, semestre, seccion_id: seccion } = filtros.elements;
    let peticion;

    const informar = (mensaje) => {
        estado.textContent = mensaje;
        estado.hidden = mensaje === '';
    };
    const opciones = (select, etiqueta, items = [], valor = '') => {
        select.replaceChildren(new Option(etiqueta, ''), ...items.map(([id, texto]) => new Option(texto, id)));
        select.value = items.some(([id]) => String(id) === valor) ? valor : '';
    };

    async function cargarOpciones(origen) {
        peticion?.abort();
        peticion = null;
        if (origen === periodo) opciones(carrera, 'Todas las carreras');
        if (origen !== semestre) opciones(semestre, 'Todos');
        opciones(seccion, 'Seleccionar sección');
        resultado.hidden = true;
        consultar.disabled = Boolean(periodo.value);
        if (!periodo.value) {
            filtros.removeAttribute('aria-busy');
            informar('Selecciona un período para ver sus secciones.');
            return;
        }

        const solicitud = new AbortController();
        peticion = solicitud;
        const contexto = new URLSearchParams({ periodo_id: periodo.value, carrera_id: carrera.value, semestre: semestre.value });
        filtros.setAttribute('aria-busy', 'true');
        informar('Actualizando secciones…');

        try {
            const respuesta = await fetch(filtros.dataset.urlOpciones + '?' + contexto, {
                headers: { Accept: 'application/json' }, signal: solicitud.signal,
            });
            if (!respuesta.ok) throw new Error('No se pudieron obtener las secciones.');
            const datos = await respuesta.json();
            if (peticion !== solicitud) return;
            opciones(carrera, 'Todas las carreras', datos.carreras.map(i => [i.id, i.nombre]), contexto.get('carrera_id'));
            opciones(semestre, 'Todos', datos.semestres.map(i => [i, 'Semestre ' + i]), contexto.get('semestre'));
            opciones(seccion, 'Seleccionar sección', datos.secciones.map(i => [i.id, i.texto]));
            informar(datos.secciones.length ? 'Elige una sección y pulsa Consultar.' : 'No hay secciones para estos filtros.');
        } catch {
            if (peticion === solicitud) informar('No se pudieron actualizar las secciones. Pulsa Consultar para volver a cargar las opciones.');
        } finally {
            if (peticion === solicitud) {
                peticion = null;
                filtros.removeAttribute('aria-busy');
                consultar.disabled = false;
            }
        }
    }

    [periodo, carrera, semestre].forEach(select => select.addEventListener('change', () => cargarOpciones(select)));
    seccion.addEventListener('change', () => {
        resultado.hidden = true;
        informar('Pulsa Consultar para ver los horarios de esta sección.');
    });
    filtros.addEventListener('submit', evento => {
        if (peticion) evento.preventDefault();
    });

    document.querySelectorAll('.js-form-horario').forEach(formulario => {
        const disponibles = Number(formulario.dataset.modulosDisponibles);
        const modulos = [...formulario.querySelectorAll('input[name="bloques_horarios[]"]')];
        const actualizarSeleccion = () => {
            const elegidos = modulos.filter(modulo => modulo.checked).length;
            for (const modulo of modulos) modulo.disabled = elegidos >= disponibles && !modulo.checked;
        };
        modulos.forEach(modulo => modulo.addEventListener('change', actualizarSeleccion));
        actualizarSeleccion();
    });
});
</script>
@endsection

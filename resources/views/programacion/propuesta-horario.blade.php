@extends('layouts.app')

@section('content')
@php
    $periodoAnterior = old('periodo_id');
    $seleccionAnterior = old('disponibilidades');
    $esMismoPeriodo = is_scalar($periodoAnterior)
        && (string) $periodoAnterior === (string) $periodo->id;
    $seleccionActual = $esMismoPeriodo && is_array($seleccionAnterior)
        ? array_values(array_filter($seleccionAnterior, 'is_string'))
        : $seleccionados->keys()->all();
    $diaAnterior = old('dia_activo');
    $diaRestaurado = $esMismoPeriodo && is_scalar($diaAnterior)
        ? $dias->first(fn ($dia) => (string) $dia->id === (string) $diaAnterior)
        : null;
    $diaInicial = $diaRestaurado?->id ?? $dias->first()?->id;
@endphp

<div class="min-w-0 w-full px-2 py-2">

    <div class="mb-2 rounded-lg border border-gray-200 bg-white px-4 py-3 shadow-sm">
        <h1 class="text-base font-bold text-gray-800">
            Propuesta de horario
        </h1>

        <p class="mt-0.5 text-xs text-gray-500">
            Selecciona los bloques horarios en los que tienes disponibilidad para realizar clases.
        </p>
    </div>

    @if (session('success'))
        <div class="mb-2 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-xs font-medium text-green-700">
            {{ session('success') }}
        </div>
    @endif

    @if ($errors->any())
        <div role="alert" class="mb-2 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-xs text-red-700">
            {{ $errors->first() }}
        </div>
    @endif

    <div class="mb-2 grid min-w-0 grid-cols-1 gap-2 break-words md:grid-cols-3 [&>div]:min-w-0">

        <div class="rounded-lg border border-gray-200 bg-white px-4 py-3 shadow-sm">
            <div class="text-[10px] font-bold uppercase tracking-wide text-gray-400">
                Profesor
            </div>

            <div class="mt-1 text-sm font-semibold text-gray-800">
                {{ trim($profesor->nombre . ' ' . $profesor->apellido) }}
            </div>

            <div class="mt-0.5 text-xs text-gray-500">
                RUT: {{ $profesor->rut }}
            </div>
        </div>

        <div class="rounded-lg border border-gray-200 bg-white px-4 py-3 shadow-sm">
            <div class="text-[10px] font-bold uppercase tracking-wide text-gray-400">
                Período académico
            </div>

            <div class="mt-1 text-sm font-semibold text-gray-800">
                {{ $periodo->nombre }}
            </div>

            <div class="mt-0.5 text-xs text-gray-500">
                Semestre {{ $periodo->numero_semestre }} de {{ $periodo->anio }}
            </div>
        </div>

        <div class="rounded-lg border border-gray-200 bg-white px-4 py-3 shadow-sm">
            <div class="text-[10px] font-bold uppercase tracking-wide text-gray-400">
                Unidad académica
            </div>

            <div class="mt-1 text-sm font-semibold text-gray-800">
                {{ $profesor->unidad_academica ?? 'Sin información' }}
            </div>

            <div class="mt-0.5 text-xs text-gray-500">
                Marca todos los bloques en los que puedes realizar clases.
            </div>
        </div>

    </div>

    <form
        method="POST"
        action="{{ route('programacion.propuesta.guardar') }}"
        id="form-propuesta"
    >
        @csrf
        <input type="hidden" name="periodo_id" value="{{ $periodo->id }}">

        <div class="mb-2 rounded-lg border border-gray-200 bg-white px-4 py-3 shadow-sm">

            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">

                <div>
                    <div class="text-sm font-semibold text-gray-800">
                        Disponibilidad semanal
                    </div>

                    <div class="mt-0.5 text-xs text-gray-500">
                        Puedes modificar tu propuesta y volver a guardarla cuando sea necesario.
                    </div>
                </div>

                <div class="flex flex-wrap gap-2">

                    <button
                        type="button"
                        id="seleccionar-todos"
                        class="min-h-11 rounded-md border border-gray-300 bg-white px-3 text-xs font-medium text-gray-700 transition hover:bg-gray-50"
                    >
                        Marcar semana
                    </button>

                    <button
                        type="button"
                        id="limpiar-seleccion"
                        class="min-h-11 rounded-md border border-gray-300 bg-white px-3 text-xs font-medium text-gray-700 transition hover:bg-gray-50"
                    >
                        Limpiar semana
                    </button>

                    <button
                        type="submit"
                        class="min-h-11 rounded-md bg-red-700 px-4 text-xs font-semibold text-white transition hover:bg-red-800"
                    >
                        Guardar propuesta
                    </button>

                </div>

            </div>

        </div>

        <div id="calendario-disponibilidad" data-calendario-disponibilidad class="min-w-0 w-full overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">

            <div id="controles-disponibilidad-diaria" class="border-b border-gray-200 bg-gray-50 px-3 py-3" hidden>
                <label for="dia-disponibilidad" class="mb-1 block text-xs font-semibold text-gray-700">Día a editar</label>
                <div class="flex items-center gap-2">
                    <button type="button" id="dia-anterior" aria-label="Día anterior" class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg border border-gray-300 bg-white text-gray-700 disabled:cursor-not-allowed disabled:opacity-40">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m15 18-6-6 6-6" /></svg>
                    </button>
                    <select id="dia-disponibilidad" name="dia_activo" class="h-11 min-w-0 flex-1 rounded-lg border border-gray-300 bg-white px-3 text-sm text-gray-800 focus:border-red-600 focus:ring-1 focus:ring-red-600">
                        @foreach ($dias as $dia)
                            <option value="{{ $dia->id }}" @selected($diaInicial === $dia->id)>{{ $dia->nombre }}</option>
                        @endforeach
                    </select>
                    <button type="button" id="dia-siguiente" aria-label="Día siguiente" class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg border border-gray-300 bg-white text-gray-700 disabled:cursor-not-allowed disabled:opacity-40">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m9 5 6 7-6 7" /></svg>
                    </button>
                </div>
                <p class="mt-2 text-[11px] text-gray-500">Los bloques marcados se conservan al cambiar de día.</p>
                <div class="mt-2 flex flex-wrap gap-2">
                    <button type="button" id="marcar-dia" class="min-h-11 rounded-lg border border-gray-300 bg-white px-3 text-xs font-medium text-gray-700 hover:bg-gray-100">Marcar día</button>
                    <button type="button" id="limpiar-dia" class="min-h-11 rounded-lg border border-gray-300 bg-white px-3 text-xs font-medium text-gray-700 hover:bg-gray-100">Limpiar día</button>
                </div>
                <p id="resumen-dia" class="mt-2 text-xs font-medium text-gray-700" aria-live="polite" aria-atomic="true"></p>
            </div>

            <p id="ayuda-calendario" class="border-b border-gray-200 px-3 py-2 text-[11px] text-gray-500 xl:hidden">Desliza la tabla para ver todos los días.</p>

            <div id="region-disponibilidad" class="min-w-0 max-w-full overflow-x-auto focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-institucional" role="region" aria-label="Disponibilidad semanal del profesor" tabindex="0">

                <table class="w-full min-w-[900px] table-fixed border-collapse">

                    <colgroup>
                        <col class="w-[95px]">

                        @foreach ($dias as $dia)
                            <col>
                        @endforeach
                    </colgroup>

                    <thead>

                        <tr class="bg-gray-100">

                            <th scope="col" class="sticky left-0 z-20 border-b border-r border-gray-200 bg-gray-100 px-2 py-2 text-left text-[10px] font-bold uppercase tracking-wide text-gray-600">
                                Bloque
                            </th>

                            @foreach ($dias as $dia)

                                <th scope="col" data-dia-columna="{{ $dia->id }}" class="border-b border-r border-gray-200 px-2 py-2 text-center text-xs font-bold uppercase tracking-wide text-gray-700 last:border-r-0 lg:text-[10px]">
                                    {{ $dia->nombre }}
                                </th>

                            @endforeach

                        </tr>

                    </thead>

                    <tbody>

                        @foreach ($bloques as $bloque)

                            <tr>

                                <th scope="row" class="sticky left-0 z-10 border-b border-r border-gray-200 bg-gray-50 px-2 py-1 text-left font-normal">

                                    <div class="text-xs font-bold text-gray-800">
                                        Bloque {{ $bloque->numero }}
                                    </div>

                                    <div class="mt-0.5 whitespace-nowrap text-[10px] leading-tight text-gray-500">
                                        {{ \Carbon\Carbon::parse($bloque->hora_inicio)->format('H:i') }}
                                        -
                                        {{ \Carbon\Carbon::parse($bloque->hora_fin)->format('H:i') }}
                                    </div>

                                    @if ($bloque->es_almuerzo)
                                        <div class="mt-0.5 text-[10px] font-semibold uppercase text-gray-400">
                                            Almuerzo
                                        </div>
                                    @endif

                                </th>

                                @foreach ($dias as $dia)

                                    @php
                                        $clave = $dia->id . '-' . $bloque->id;

                                        $seleccionado = in_array(
                                            $clave,
                                            $seleccionActual,
                                            true
                                        );
                                    @endphp

                                    <td data-dia-columna="{{ $dia->id }}" class="border-b border-r border-gray-200 p-1 text-center last:border-r-0">

                                        <label
                                            class="grupo-bloque flex min-h-[44px] cursor-pointer items-center justify-center rounded-md border border-gray-200 bg-white transition hover:border-red-300 hover:bg-red-50"
                                        >

                                            <input
                                                type="checkbox"
                                                name="disponibilidades[]"
                                                value="{{ $clave }}"
                                                data-dia="{{ $dia->id }}"
                                                aria-label="{{ $dia->nombre }}, módulo {{ $bloque->numero }}, de {{ substr($bloque->hora_inicio, 0, 5) }} a {{ substr($bloque->hora_fin, 0, 5) }}"
                                                class="checkbox-disponibilidad h-4 w-4 rounded border-gray-300 text-red-700 focus:ring-red-600"
                                                @checked($seleccionado)
                                            >

                                        </label>

                                    </td>

                                @endforeach

                            </tr>

                        @endforeach

                    </tbody>

                </table>

            </div>

        </div>

        <div class="mt-2 flex flex-col gap-2 rounded-lg border border-gray-200 bg-white px-4 py-3 shadow-sm sm:flex-row sm:items-center sm:justify-between">

            <div class="text-xs text-gray-500">
                Bloques seleccionados:

                <span
                    id="cantidad-seleccionados"
                    class="font-bold text-gray-700"
                >
                    0
                </span>
            </div>

            <button
                type="submit"
                class="min-h-11 rounded-md bg-red-700 px-5 text-xs font-semibold text-white transition hover:bg-red-800"
            >
                Guardar propuesta
            </button>

        </div>

    </form>

</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const formulario = document.getElementById('form-propuesta');
        const checkboxes = Array.from(formulario.querySelectorAll('.checkbox-disponibilidad'));
        const cantidad = document.getElementById('cantidad-seleccionados');
        const calendario = document.getElementById('calendario-disponibilidad');
        const controlesDiarios = document.getElementById('controles-disponibilidad-diaria');
        const selectorDia = document.getElementById('dia-disponibilidad');
        const anterior = document.getElementById('dia-anterior');
        const siguiente = document.getElementById('dia-siguiente');
        const resumenDia = document.getElementById('resumen-dia');
        const region = document.getElementById('region-disponibilidad');
        const ayuda = document.getElementById('ayuda-calendario');
        const columnas = calendario.querySelectorAll('[data-dia-columna]');
        const pantallaDiaria = window.matchMedia('(width < 64rem)');

        function actualizarCantidad() {
            cantidad.textContent = checkboxes.filter(checkbox => checkbox.checked).length;
            const dia = selectorDia.selectedOptions[0];
            if (!dia) return;

            const marcados = checkboxes.filter(checkbox => checkbox.dataset.dia === dia.value && checkbox.checked).length;
            const texto = `${dia.textContent.trim()}: ${marcados} ${marcados === 1 ? 'bloque seleccionado' : 'bloques seleccionados'}.`;
            if (resumenDia.textContent !== texto) resumenDia.textContent = texto;
        }

        function actualizarVista() {
            const diaria = pantallaDiaria.matches && selectorDia.options.length > 0;
            calendario.dataset.vista = diaria ? 'diaria' : 'semanal';
            controlesDiarios.hidden = !diaria;
            ayuda.hidden = diaria;

            // Ocultar columnas conserva habilitadas sus casillas para enviar toda la semana.
            columnas.forEach(function (columna) {
                columna.hidden = diaria && columna.dataset.diaColumna !== selectorDia.value;
            });

            anterior.disabled = selectorDia.selectedIndex <= 0;
            siguiente.disabled = selectorDia.selectedIndex < 0 || selectorDia.selectedIndex >= selectorDia.options.length - 1;
            const nombreDia = selectorDia.selectedOptions[0]?.textContent.trim().toLowerCase();
            region.setAttribute('aria-label', diaria ? `Disponibilidad del ${nombreDia}` : 'Disponibilidad semanal del profesor');
            actualizarCantidad();
        }

        function cambiarDia(paso) {
            const destino = selectorDia.selectedIndex + paso;
            if (destino < 0 || destino >= selectorDia.options.length) return;
            selectorDia.selectedIndex = destino;
            actualizarVista();
        }

        function marcarCasillas(marcar, soloDia = false) {
            checkboxes.forEach(function (checkbox) {
                if (!soloDia || checkbox.dataset.dia === selectorDia.value) checkbox.checked = marcar;
            });
            actualizarCantidad();
        }

        checkboxes.forEach(checkbox => checkbox.addEventListener('change', actualizarCantidad));
        selectorDia.addEventListener('change', actualizarVista);
        anterior.addEventListener('click', () => cambiarDia(-1));
        siguiente.addEventListener('click', () => cambiarDia(1));
        document.getElementById('marcar-dia').addEventListener('click', () => marcarCasillas(true, true));
        document.getElementById('limpiar-dia').addEventListener('click', () => marcarCasillas(false, true));
        document.getElementById('seleccionar-todos').addEventListener('click', () => marcarCasillas(true));
        document.getElementById('limpiar-seleccion').addEventListener('click', () => marcarCasillas(false));
        pantallaDiaria.addEventListener('change', actualizarVista);
        window.addEventListener('pageshow', actualizarVista);

        actualizarVista();
    });
</script>
@endsection

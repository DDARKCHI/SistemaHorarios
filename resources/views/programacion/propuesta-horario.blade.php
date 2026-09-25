@extends('layouts.app')

@section('content')
@php
    $seleccionActual = old(
        'disponibilidades',
        $seleccionados->keys()->all()
    );
@endphp

<div class="w-full px-2 py-2">

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
        <div class="mb-2 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-xs text-red-700">
            {{ $errors->first() }}
        </div>
    @endif

    <div class="mb-2 grid grid-cols-1 gap-2 md:grid-cols-3">

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
                        class="h-9 rounded-md border border-gray-300 bg-white px-3 text-xs font-medium text-gray-700 transition hover:bg-gray-50"
                    >
                        Seleccionar todos
                    </button>

                    <button
                        type="button"
                        id="limpiar-seleccion"
                        class="h-9 rounded-md border border-gray-300 bg-white px-3 text-xs font-medium text-gray-700 transition hover:bg-gray-50"
                    >
                        Limpiar
                    </button>

                    <button
                        type="submit"
                        class="h-9 rounded-md bg-red-700 px-4 text-xs font-semibold text-white transition hover:bg-red-800"
                    >
                        Guardar propuesta
                    </button>

                </div>

            </div>

        </div>

        <div class="w-full overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">

            <div class="overflow-x-auto">

                <table class="w-full min-w-[800px] table-fixed border-collapse">

                    <colgroup>
                        <col class="w-[95px]">

                        @foreach ($dias as $dia)
                            <col>
                        @endforeach
                    </colgroup>

                    <thead>

                        <tr class="bg-gray-100">

                            <th class="sticky left-0 z-20 border-b border-r border-gray-200 bg-gray-100 px-2 py-2 text-left text-[10px] font-bold uppercase tracking-wide text-gray-600">
                                Bloque
                            </th>

                            @foreach ($dias as $dia)

                                <th class="border-b border-r border-gray-200 px-2 py-2 text-center text-[10px] font-bold uppercase tracking-wide text-gray-700 last:border-r-0">
                                    {{ $dia->nombre }}
                                </th>

                            @endforeach

                        </tr>

                    </thead>

                    <tbody>

                        @foreach ($bloques as $bloque)

                            <tr>

                                <td class="sticky left-0 z-10 border-b border-r border-gray-200 bg-gray-50 px-2 py-1">

                                    <div class="text-xs font-bold text-gray-800">
                                        Bloque {{ $bloque->numero }}
                                    </div>

                                    <div class="mt-0.5 whitespace-nowrap text-[9px] leading-tight text-gray-500">
                                        {{ \Carbon\Carbon::parse($bloque->hora_inicio)->format('H:i') }}
                                        -
                                        {{ \Carbon\Carbon::parse($bloque->hora_fin)->format('H:i') }}
                                    </div>

                                    @if ($bloque->es_almuerzo)
                                        <div class="mt-0.5 text-[8px] font-semibold uppercase text-gray-400">
                                            Almuerzo
                                        </div>
                                    @endif

                                </td>

                                @foreach ($dias as $dia)

                                    @php
                                        $clave = $dia->id . '-' . $bloque->id;

                                        $seleccionado = in_array(
                                            $clave,
                                            $seleccionActual,
                                            true
                                        );
                                    @endphp

                                    <td class="border-b border-r border-gray-200 p-1 text-center last:border-r-0">

                                        <label
                                            class="grupo-bloque flex min-h-[36px] cursor-pointer items-center justify-center rounded-md border border-gray-200 bg-white transition hover:border-red-300 hover:bg-red-50"
                                        >

                                            <input
                                                type="checkbox"
                                                name="disponibilidades[]"
                                                value="{{ $clave }}"
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
                class="h-9 rounded-md bg-red-700 px-5 text-xs font-semibold text-white transition hover:bg-red-800"
            >
                Guardar propuesta
            </button>

        </div>

    </form>

</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const checkboxes = document.querySelectorAll(
            '.checkbox-disponibilidad'
        );

        const cantidad = document.getElementById(
            'cantidad-seleccionados'
        );

        const seleccionarTodos = document.getElementById(
            'seleccionar-todos'
        );

        const limpiarSeleccion = document.getElementById(
            'limpiar-seleccion'
        );

        function actualizarCantidad() {
            let total = 0;

            checkboxes.forEach(function (checkbox) {
                if (checkbox.checked) {
                    total++;
                }
            });

            cantidad.textContent = total;
        }

        checkboxes.forEach(function (checkbox) {
            checkbox.addEventListener(
                'change',
                actualizarCantidad
            );
        });

        seleccionarTodos.addEventListener('click', function () {
            checkboxes.forEach(function (checkbox) {
                checkbox.checked = true;
            });

            actualizarCantidad();
        });

        limpiarSeleccion.addEventListener('click', function () {
            checkboxes.forEach(function (checkbox) {
                checkbox.checked = false;
            });

            actualizarCantidad();
        });

        actualizarCantidad();
    });
</script>
@endsection
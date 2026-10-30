@extends('layouts.app')

@section('content')

@php
    $editando = $sala->exists;
    $campo = 'h-11 w-full min-w-0 rounded-lg border border-gray-300 bg-white px-3 text-base text-gray-800 focus:border-institucional focus:outline-none focus:ring-1 focus:ring-institucional sm:text-sm';
    $valor = static function ($nombre, $predeterminado = null) {
        $anterior = old($nombre, $predeterminado);
        return is_scalar($anterior) || $anterior === null ? $anterior : $predeterminado;
    };
@endphp

<x-encabezado-pagina :titulo="$editando ? 'Editar sala' : 'Crear sala'">
    {{ $editando ? 'Actualiza los datos de la sala '.$sala->codigo.'.' : 'Registra un espacio físico para incorporarlo al catálogo de salas.' }}
</x-encabezado-pagina>

<div class="mx-auto w-full min-w-0 max-w-4xl space-y-3">

    @if ($errors->any())
        <div role="alert" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
            <p class="font-semibold">Revisa los datos de la sala.</p>
            <ul class="mt-1 list-inside list-disc space-y-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ $editando ? route('salas.update', $sala) : route('salas.store') }}" method="POST" class="space-y-5 rounded-lg border border-gray-200 bg-white p-4 shadow-sm sm:p-5">
        @csrf
        @if ($editando) @method('PUT') @endif

        <p class="text-xs text-gray-500">Los campos con * son obligatorios.</p>

        <fieldset class="min-w-0">
            <legend class="mb-3 text-sm font-bold text-gray-800">Datos de la sala</legend>
            <div class="grid min-w-0 grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label for="codigo-sala" class="mb-1.5 block text-sm font-semibold text-gray-700">Código *</label>
                    <input id="codigo-sala" name="codigo" type="text" maxlength="30" required value="{{ $valor('codigo', $sala->codigo) }}" class="{{ $campo }}" aria-describedby="codigo-ayuda">
                    <p id="codigo-ayuda" class="mt-1 text-xs text-gray-500">Debe ser único dentro de la sede.</p>
                </div>
                <div>
                    <label for="nombre-sala" class="mb-1.5 block text-sm font-semibold text-gray-700">Nombre</label>
                    <input id="nombre-sala" name="nombre" type="text" maxlength="100" value="{{ $valor('nombre', $sala->nombre) }}" class="{{ $campo }}">
                </div>
                <div>
                    <label for="sede-sala" class="mb-1.5 block text-sm font-semibold text-gray-700">Sede *</label>
                    <select id="sede-sala" name="sede_id" data-select-buscable aria-required="true" class="{{ $campo }}">
                        <option value="">Seleccionar sede</option>
                        @foreach ($sedes as $sede)
                            <option value="{{ $sede->id }}" @selected((string) $valor('sede_id', $sala->sede_id) === (string) $sede->id)>{{ $sede->nombre }}{{ $sede->estado ? '' : ' (inactiva)' }}</option>
                        @endforeach
                    </select>
                    @if ($sedes->isEmpty())<p class="mt-1 text-xs text-red-700">Se necesita una sede activa para registrar salas.</p>@endif
                </div>
                <div>
                    <label for="tipo-sala" class="mb-1.5 block text-sm font-semibold text-gray-700">Tipo de sala *</label>
                    <select id="tipo-sala" name="tipo_sala_id" data-select-buscable aria-required="true" class="{{ $campo }}">
                        <option value="">Seleccionar tipo</option>
                        @foreach ($tiposSala as $tipo)
                            <option value="{{ $tipo->id }}" @selected((string) $valor('tipo_sala_id', $sala->tipo_sala_id) === (string) $tipo->id)>{{ $tipo->nombre }}{{ $tipo->estado ? '' : ' (inactivo)' }}</option>
                        @endforeach
                    </select>
                    @if ($tiposSala->isEmpty())<p class="mt-1 text-xs text-red-700">Se necesita un tipo de sala activo para registrar salas.</p>@endif
                </div>
                <div>
                    <label for="capacidad-sala" class="mb-1.5 block text-sm font-semibold text-gray-700">Capacidad *</label>
                    <input id="capacidad-sala" name="capacidad" type="number" min="0" max="65535" step="1" inputmode="numeric" required value="{{ $valor('capacidad', $sala->capacidad) }}" class="{{ $campo }}" aria-describedby="capacidad-ayuda">
                    <p id="capacidad-ayuda" class="mt-1 text-xs text-gray-500">Cantidad de personas. Usa 0 si aún no se ha informado.</p>
                </div>
                <div>
                    <label for="edificio-sala" class="mb-1.5 block text-sm font-semibold text-gray-700">Edificio</label>
                    <input id="edificio-sala" name="edificio" type="text" maxlength="100" value="{{ $valor('edificio', $sala->edificio) }}" class="{{ $campo }}">
                </div>
                <div>
                    <label for="piso-sala" class="mb-1.5 block text-sm font-semibold text-gray-700">Piso</label>
                    <input id="piso-sala" name="piso" type="text" maxlength="30" value="{{ $valor('piso', $sala->piso) }}" class="{{ $campo }}">
                </div>
                <div class="sm:col-span-2">
                    <label for="descripcion-sala" class="mb-1.5 block text-sm font-semibold text-gray-700">Descripción</label>
                    <textarea id="descripcion-sala" name="descripcion" maxlength="5000" rows="3" class="w-full min-w-0 rounded-lg border border-gray-300 bg-white px-3 py-2 text-base text-gray-800 focus:border-institucional focus:outline-none focus:ring-1 focus:ring-institucional sm:text-sm">{{ $valor('descripcion', $sala->descripcion) }}</textarea>
                </div>
            </div>
        </fieldset>

        <fieldset class="min-w-0 border-t border-gray-100 pt-4">
            <legend class="px-1 text-sm font-bold text-gray-800">Estado y uso</legend>
            <div class="grid min-w-0 grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label for="estado-sala" class="mb-1.5 block text-sm font-semibold text-gray-700">Estado del registro *</label>
                    <select id="estado-sala" name="estado" class="{{ $campo }}" aria-describedby="estado-ayuda">
                        <option value="1" @selected((string) $valor('estado', (int) $sala->estado) === '1')>Activo</option>
                        <option value="0" @selected((string) $valor('estado', (int) $sala->estado) === '0')>Inactivo</option>
                    </select>
                    <p id="estado-ayuda" class="mt-1 text-xs text-gray-500">Los registros inactivos se conservan en el catálogo.</p>
                </div>
                <div>
                    <label for="disponible-sala" class="mb-1.5 block text-sm font-semibold text-gray-700">Habilitación de uso *</label>
                    <select id="disponible-sala" name="disponible" class="{{ $campo }}">
                        <option value="1" @selected((string) $valor('disponible', (int) $sala->disponible) === '1')>Uso habilitado</option>
                        <option value="0" @selected((string) $valor('disponible', (int) $sala->disponible) === '0')>Uso deshabilitado</option>
                    </select>
                </div>
            </div>
            @if ($editando)
                <p class="mt-4 text-xs leading-5 text-gray-600">Los horarios existentes conservan sus asignaciones. Si cambias la capacidad o deshabilitas la sala, revisa los horarios que la utilizan.</p>
            @endif
        </fieldset>

        <div class="flex flex-col-reverse gap-2 border-t border-gray-100 pt-4 sm:flex-row sm:justify-end">
            <a href="{{ route('salas.index') }}" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Cancelar</a>
            <button type="submit" @disabled($sedes->isEmpty() || $tiposSala->isEmpty()) class="inline-flex min-h-11 items-center justify-center rounded-lg bg-institucional px-5 py-2 text-sm font-semibold text-white hover:bg-institucional-oscuro disabled:cursor-not-allowed disabled:opacity-50">{{ $editando ? 'Guardar cambios' : 'Crear sala' }}</button>
        </div>
    </form>
</div>

@endsection

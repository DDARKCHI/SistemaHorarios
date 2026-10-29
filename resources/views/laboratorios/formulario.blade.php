@extends('layouts.app')

@section('content')
@php
    $editando = $laboratorio->exists;
    $etiquetaTipo = $tipo === 'fijo' ? 'fijo' : 'móvil';
    $titulo = ($editando ? 'Editar' : 'Crear').' laboratorio '.$etiquetaTipo;
    $campo = 'h-11 w-full min-w-0 rounded-lg border border-gray-300 bg-white px-3 text-base text-gray-800 focus:border-institucional focus:outline-none focus:ring-1 focus:ring-institucional sm:text-sm';
    $valor = static function ($nombre, $predeterminado = null) {
        $anterior = old($nombre, $predeterminado);
        return is_scalar($anterior) || $anterior === null ? $anterior : $predeterminado;
    };
@endphp

<div class="mx-auto w-full min-w-0 max-w-4xl space-y-3 px-2 py-2">
    <header class="rounded-lg border border-gray-200 bg-white px-4 py-4 shadow-sm">
        <h1 class="text-base font-bold text-gray-800">{{ $titulo }}</h1>
        <p class="mt-1 text-sm text-gray-600">{{ $tipo === 'fijo' ? 'Registra el laboratorio y la sala física que lo contiene.' : 'Registra el recurso móvil y su sede de origen.' }}</p>
    </header>

    @if ($errors->any())
        <div role="alert" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
            <p class="font-semibold">Revisa los datos del laboratorio.</p>
            <ul class="mt-1 list-inside list-disc space-y-1">
                @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
    @endif

    <form action="{{ $editando ? route('laboratorios.update', ['tipo' => $tipo, 'id' => $laboratorio->id]) : route('laboratorios.store', ['tipo' => $tipo]) }}" method="POST" class="space-y-5 rounded-lg border border-gray-200 bg-white p-4 shadow-sm sm:p-5">
        @csrf
        @if ($editando) @method('PUT') @endif
        <p class="text-xs text-gray-500">Los campos con * son obligatorios.</p>

        <fieldset class="min-w-0">
            <legend class="mb-3 text-sm font-bold text-gray-800">Datos del laboratorio</legend>
            <div class="grid min-w-0 grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label for="codigo-laboratorio" class="mb-1.5 block text-sm font-semibold text-gray-700">Código{{ $tipo === 'movil' ? ' *' : '' }}</label>
                    <input id="codigo-laboratorio" name="codigo" type="text" maxlength="30" @required($tipo === 'movil') value="{{ $valor('codigo', $laboratorio->codigo) }}" class="{{ $campo }}" aria-describedby="codigo-laboratorio-ayuda">
                    <p id="codigo-laboratorio-ayuda" class="mt-1 text-xs text-gray-500">{{ $tipo === 'fijo' ? 'Si lo informas, debe ser único dentro de la sala.' : 'Debe ser único dentro de la sede.' }}</p>
                </div>
                <div>
                    <label for="nombre-laboratorio" class="mb-1.5 block text-sm font-semibold text-gray-700">Nombre *</label>
                    <input id="nombre-laboratorio" name="nombre" type="text" maxlength="100" required value="{{ $valor('nombre', $laboratorio->nombre) }}" class="{{ $campo }}">
                </div>
                <div class="sm:col-span-2">
                    <label for="ubicacion-laboratorio" class="mb-1.5 block text-sm font-semibold text-gray-700">{{ $tipo === 'fijo' ? 'Sala física *' : 'Sede de origen *' }}</label>
                    <select id="ubicacion-laboratorio" name="{{ $campoUbicacion }}" data-select-buscable aria-required="true" class="{{ $campo }}">
                        <option value="">{{ $tipo === 'fijo' ? 'Seleccionar sala' : 'Seleccionar sede' }}</option>
                        @foreach ($ubicaciones as $ubicacion)
                            <option value="{{ $ubicacion->id }}" @selected((string) $valor($campoUbicacion, $laboratorio->{$campoUbicacion}) === (string) $ubicacion->id)>
                                @if ($tipo === 'fijo')
                                    {{ $ubicacion->codigo }} · {{ $ubicacion->nombre ?: 'Sin nombre' }} · {{ $ubicacion->sede?->nombre ?? 'Sin sede' }} · {{ $ubicacion->capacidad > 0 ? $ubicacion->capacidad.' personas' : 'Sin capacidad informada' }}{{ $ubicacion->estado && $ubicacion->disponible && $ubicacion->sede?->estado ? '' : ' (inactiva o deshabilitada)' }}
                                @else
                                    {{ $ubicacion->nombre }}{{ $ubicacion->estado ? '' : ' (inactiva)' }}
                                @endif
                            </option>
                        @endforeach
                    </select>
                    @if ($ubicaciones->isEmpty())
                        <p class="mt-2 text-xs text-red-700">{{ $tipo === 'fijo' ? 'Necesitas una sala activa y habilitada en una sede activa para registrar el laboratorio.' : 'Necesitas una sede activa para registrar el laboratorio móvil.' }}</p>
                        @if ($tipo === 'fijo')<a href="{{ route('salas.create') }}" class="mt-2 inline-block text-sm font-semibold text-institucional underline">Crear una sala</a>@endif
                    @endif
                </div>
                <div>
                    <label for="capacidad-laboratorio" class="mb-1.5 block text-sm font-semibold text-gray-700">Capacidad *</label>
                    <input id="capacidad-laboratorio" name="capacidad" type="number" min="0" max="65535" step="1" inputmode="numeric" required value="{{ $valor('capacidad', $laboratorio->capacidad) }}" class="{{ $campo }}" aria-describedby="capacidad-laboratorio-ayuda">
                    <p id="capacidad-laboratorio-ayuda" class="mt-1 text-xs text-gray-500">Personas que puede atender. Usa 0 si aún no se ha informado.{{ $tipo === 'fijo' ? ' No puede superar la capacidad conocida de su sala.' : '' }}</p>
                </div>
                <div class="sm:col-span-2">
                    <label for="descripcion-laboratorio" class="mb-1.5 block text-sm font-semibold text-gray-700">Descripción</label>
                    <textarea id="descripcion-laboratorio" name="descripcion" maxlength="5000" rows="3" class="w-full min-w-0 rounded-lg border border-gray-300 bg-white px-3 py-2 text-base text-gray-800 focus:border-institucional focus:outline-none focus:ring-1 focus:ring-institucional sm:text-sm">{{ $valor('descripcion', $laboratorio->descripcion) }}</textarea>
                </div>
            </div>
        </fieldset>

        <fieldset class="min-w-0 border-t border-gray-100 pt-4">
            <legend class="px-1 text-sm font-bold text-gray-800">Estado y uso</legend>
            <div class="grid min-w-0 grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label for="estado-laboratorio" class="mb-1.5 block text-sm font-semibold text-gray-700">Estado del registro *</label>
                    <select id="estado-laboratorio" name="estado" class="{{ $campo }}">
                        <option value="1" @selected((string) $valor('estado', (int) $laboratorio->estado) === '1')>Activo</option>
                        <option value="0" @selected((string) $valor('estado', (int) $laboratorio->estado) === '0')>Inactivo</option>
                    </select>
                </div>
                <div>
                    <label for="disponible-laboratorio" class="mb-1.5 block text-sm font-semibold text-gray-700">Habilitación de uso *</label>
                    <select id="disponible-laboratorio" name="disponible" class="{{ $campo }}">
                        <option value="1" @selected((string) $valor('disponible', (int) $laboratorio->disponible) === '1')>Uso habilitado</option>
                        <option value="0" @selected((string) $valor('disponible', (int) $laboratorio->disponible) === '0')>Uso deshabilitado</option>
                    </select>
                </div>
            </div>
            <p class="mt-4 text-xs leading-5 text-gray-600">{{ $editando ? 'Los cambios conservan los horarios y asignaciones existentes. Revisa su uso si cambias la capacidad o deshabilitas el recurso.' : 'Los registros inactivos se conservan en el catálogo.' }}</p>
        </fieldset>

        <div class="flex flex-col-reverse gap-2 border-t border-gray-100 pt-4 sm:flex-row sm:justify-end">
            <a href="{{ route('laboratorios.index', ['tipo' => $tipo]) }}" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Cancelar</a>
            <button type="submit" @disabled($ubicaciones->isEmpty()) class="inline-flex min-h-11 items-center justify-center rounded-lg bg-institucional px-5 py-2 text-sm font-semibold text-white hover:bg-institucional-oscuro disabled:cursor-not-allowed disabled:opacity-50">{{ $editando ? 'Guardar cambios' : 'Crear laboratorio' }}</button>
        </div>
    </form>
</div>
@endsection

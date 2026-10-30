@extends('layouts.app')
@section('content')
@php
    $editando = $equipamiento->exists;
    $campo = 'h-11 w-full min-w-0 rounded-lg border border-gray-300 bg-white px-3 text-base text-gray-800 focus:border-institucional focus:outline-none focus:ring-1 focus:ring-institucional sm:text-sm';
    $valor = static function ($nombre, $predeterminado = null) {
        $anterior = old($nombre, $predeterminado);
        return is_scalar($anterior) || $anterior === null ? $anterior : $predeterminado;
    };
@endphp
<x-encabezado-pagina :titulo="$editando ? 'Editar equipamiento' : 'Registrar equipamiento'">
    {{ $editando ? 'Actualiza el registro y su cantidad en el inventario institucional.' : 'Registra un recurso y la cantidad de unidades que tiene la institución.' }}
</x-encabezado-pagina>

<div class="mx-auto w-full min-w-0 max-w-4xl space-y-3">

    @if ($errors->any())
        <div role="alert" class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800"><p class="font-semibold">Revisa los datos del equipamiento.</p><ul class="mt-1 list-inside list-disc space-y-1">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif
    <form method="POST" action="{{ $editando ? route('recursos.update', $equipamiento) : route('recursos.store') }}" class="space-y-5 rounded-lg border border-gray-200 bg-white p-4 shadow-sm sm:p-5">
        @csrf
        @if ($editando) @method('PUT') @endif
        <p class="text-xs text-gray-500">Los campos con * son obligatorios.</p>
        <fieldset class="min-w-0">
            <legend class="mb-3 text-sm font-bold text-gray-800">Datos del equipamiento</legend>
            <div class="grid min-w-0 grid-cols-1 gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2"><label for="institucion-equipamiento" class="mb-1.5 block text-sm font-semibold text-gray-700">Institución *</label>
                    <select id="institucion-equipamiento" name="universidad_id" data-select-buscable aria-required="true" class="{{ $campo }}"><option value="">Seleccionar institución</option>@foreach ($instituciones as $institucion)<option value="{{ $institucion->id }}" @selected((string) $valor('universidad_id', $equipamiento->universidad_id) === (string) $institucion->id)>{{ $institucion->sigla }} · {{ $institucion->nombre }}{{ $institucion->estado ? '' : ' (inactiva)' }}</option>@endforeach</select>
                    @if ($instituciones->isEmpty())<p class="mt-2 text-xs text-red-700">Se necesita una institución activa para registrar equipamiento.</p>@endif
                </div>
                <div><label for="nombre-equipamiento" class="mb-1.5 block text-sm font-semibold text-gray-700">Nombre *</label><input id="nombre-equipamiento" name="nombre" type="text" maxlength="100" required value="{{ $valor('nombre', $equipamiento->nombre) }}" class="{{ $campo }}"></div>
                <div><label for="tipo-equipamiento" class="mb-1.5 block text-sm font-semibold text-gray-700">Tipo *</label>
                    <input id="tipo-equipamiento" name="tipo" type="text" maxlength="100" list="tipos-equipamiento" required value="{{ $valor('tipo', $equipamiento->tipo) }}" class="{{ $campo }}" aria-describedby="tipo-equipamiento-ayuda">
                    <datalist id="tipos-equipamiento">@foreach ($tipos as $tipo)<option value="{{ $tipo }}"></option>@endforeach</datalist>
                    <p id="tipo-equipamiento-ayuda" class="mt-1 text-xs text-gray-500">Elige un tipo registrado o escribe uno nuevo.</p>
                </div>
                <div><label for="cantidad-equipamiento" class="mb-1.5 block text-sm font-semibold text-gray-700">Cantidad registrada *</label><input id="cantidad-equipamiento" name="cantidad" type="number" min="0" max="65535" step="1" inputmode="numeric" required value="{{ $valor('cantidad', $equipamiento->cantidad) }}" class="{{ $campo }}" aria-describedby="cantidad-equipamiento-ayuda"><p id="cantidad-equipamiento-ayuda" class="mt-1 text-xs text-gray-500">Número de unidades del recurso. Usa 0 si no hay unidades registradas.</p></div>
                <div class="sm:col-span-2"><label for="descripcion-equipamiento" class="mb-1.5 block text-sm font-semibold text-gray-700">Descripción</label><textarea id="descripcion-equipamiento" name="descripcion" maxlength="255" rows="3" class="w-full min-w-0 rounded-lg border border-gray-300 bg-white px-3 py-2 text-base text-gray-800 focus:border-institucional focus:outline-none focus:ring-1 focus:ring-institucional sm:text-sm">{{ $valor('descripcion', $equipamiento->descripcion) }}</textarea><p class="mt-1 text-xs text-gray-500">Hasta 255 caracteres.</p></div>
            </div>
        </fieldset>
        <fieldset class="min-w-0 border-t border-gray-100 pt-4">
            <legend class="px-1 text-sm font-bold text-gray-800">Estado y uso</legend>
            <div class="grid min-w-0 grid-cols-1 gap-4 sm:grid-cols-2">
                <div><label for="estado-equipamiento" class="mb-1.5 block text-sm font-semibold text-gray-700">Estado del registro *</label><select id="estado-equipamiento" name="estado" class="{{ $campo }}"><option value="1" @selected((string) $valor('estado', (int) $equipamiento->estado) === '1')>Activo</option><option value="0" @selected((string) $valor('estado', (int) $equipamiento->estado) === '0')>Inactivo</option></select></div>
                <div><label for="uso-equipamiento" class="mb-1.5 block text-sm font-semibold text-gray-700">Habilitación de uso *</label><select id="uso-equipamiento" name="disponible" class="{{ $campo }}"><option value="1" @selected((string) $valor('disponible', (int) $equipamiento->disponible) === '1')>Uso habilitado</option><option value="0" @selected((string) $valor('disponible', (int) $equipamiento->disponible) === '0')>Uso deshabilitado</option></select></div>
            </div>
            <p class="mt-4 text-xs leading-5 text-gray-600">Los registros inactivos se conservan en el inventario. La cantidad registrada corresponde al stock general de la institución.</p>
        </fieldset>
        <div class="flex flex-col-reverse gap-2 border-t border-gray-100 pt-4 sm:flex-row sm:justify-end"><a href="{{ route('recursos.index') }}" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Cancelar</a><button type="submit" @disabled($instituciones->isEmpty()) class="inline-flex min-h-11 items-center justify-center rounded-lg bg-institucional px-5 py-2 text-sm font-semibold text-white hover:bg-institucional-oscuro disabled:cursor-not-allowed disabled:opacity-50">{{ $editando ? 'Guardar cambios' : 'Registrar equipamiento' }}</button></div>
    </form>
</div>
@endsection

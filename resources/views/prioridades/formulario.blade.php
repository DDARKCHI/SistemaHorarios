@extends('layouts.app')
@section('title', ($prioridad->exists ? 'Editar prioridad' : 'Crear prioridad').' | PROSEC')
@section('content')
@php
    $editando = $prioridad->exists;
    $campo = 'h-11 w-full min-w-0 rounded-lg border border-gray-300 bg-white px-3 text-sm text-gray-800';
    $valor = static function ($nombre, $predeterminado = null) { $anterior = old($nombre, $predeterminado); return is_scalar($anterior) || $anterior === null ? $anterior : $predeterminado; };
@endphp
<x-encabezado-pagina :titulo="$editando ? 'Editar prioridad' : 'Crear prioridad'">Define el nivel de preferencia que podrá utilizarse en los requerimientos de actividades.</x-encabezado-pagina>
<div class="mx-auto w-full min-w-0 max-w-4xl space-y-4">
    @if ($errors->any())<div role="alert" class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800"><p class="font-semibold">Revisa los datos de la prioridad.</p><ul class="mt-1 list-inside list-disc">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <form method="POST" action="{{ $editando ? route('prioridades.update', $prioridad) : route('prioridades.store') }}" aria-label="Guardar prioridad" class="space-y-5 rounded-lg border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
        @csrf
        @if ($editando)
            @method('PUT')
        @endif
        <div class="grid min-w-0 gap-4 sm:grid-cols-2">
            <div class="sm:col-span-2"><label for="nombre-prioridad" class="mb-1.5 block text-sm font-semibold text-gray-700">Nombre *</label><input id="nombre-prioridad" name="nombre" type="text" maxlength="100" required value="{{ $valor('nombre', $prioridad->nombre) }}" class="{{ $campo }}"></div>
            <div><label for="nivel-prioridad" class="mb-1.5 block text-sm font-semibold text-gray-700">Nivel *</label><input id="nivel-prioridad" name="nivel" type="number" min="1" max="255" step="1" required value="{{ $valor('nivel', $prioridad->nivel) }}" class="{{ $campo }}"><p class="mt-1 text-xs leading-5 text-gray-500">Del 1 al 255. El nivel 1 tiene mayor prioridad que el 2; se permiten niveles compartidos.</p></div>
            <div><label for="estado-prioridad" class="mb-1.5 block text-sm font-semibold text-gray-700">Estado del registro *</label><select id="estado-prioridad" name="estado" data-select-buscable class="{{ $campo }}"><option value="1" @selected((string) $valor('estado', (int) $prioridad->estado) === '1')>Activa</option><option value="0" @selected((string) $valor('estado', (int) $prioridad->estado) === '0')>Inactiva</option></select><p class="mt-1 text-xs leading-5 text-gray-500">Las prioridades inactivas se conservan en los requerimientos que ya las utilizan.</p></div>
            <div class="sm:col-span-2"><label for="descripcion-prioridad" class="mb-1.5 block text-sm font-semibold text-gray-700">Descripción</label><textarea id="descripcion-prioridad" name="descripcion" rows="3" maxlength="255" class="w-full min-w-0 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-800">{{ $valor('descripcion', $prioridad->descripcion) }}</textarea><p class="mt-1 text-xs text-gray-500">Hasta 255 caracteres.</p></div>
        </div>
        <p class="text-sm leading-6 text-gray-500">Este catálogo es compartido por las actividades. Cambiar el nivel no reasigna clases ni equipos; su aplicación automática en el generador sigue pendiente.</p>
        <div class="flex flex-col-reverse gap-2 border-t border-gray-100 pt-4 sm:flex-row sm:justify-end"><a href="{{ route('prioridades.index') }}" class="boton boton-secundario">Cancelar</a><button type="submit" class="boton boton-primario">{{ $editando ? 'Guardar cambios' : 'Crear prioridad' }}</button></div>
    </form>
</div>
@endsection

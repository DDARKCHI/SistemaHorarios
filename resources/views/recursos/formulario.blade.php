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

<div class="panel-editor">

    @if ($errors->any())
        <div role="alert" class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800"><p class="font-semibold">Revisa los datos del equipamiento.</p><ul class="mt-1 list-inside list-disc space-y-1">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif
    <form method="POST" action="{{ $editando ? route('recursos.update', $equipamiento) : route('recursos.store') }}" class="panel-formulario">
        @csrf
        @if ($editando) @method('PUT') @endif
        <div class="panel-layout">
            <div class="panel-columna">
                <x-seccion-formulario titulo="Datos del equipamiento" id="datos-equipamiento">
                    <div class="panel-campos">
                        <div class="panel-campo--completo"><label for="institucion-equipamiento" class="mb-1.5 block text-sm font-semibold text-gray-700">Institución *</label>
                            <select id="institucion-equipamiento" name="universidad_id" data-select-buscable aria-required="true" class="{{ $campo }}"><option value="">Seleccionar institución</option>@foreach ($instituciones as $institucion)<option value="{{ $institucion->id }}" @selected((string) $valor('universidad_id', $equipamiento->universidad_id ?? ($instituciones->count() === 1 ? $instituciones->first()->id : null)) === (string) $institucion->id)>{{ $institucion->sigla }} · {{ $institucion->nombre }}{{ $institucion->estado ? '' : ' (inactiva)' }}</option>@endforeach</select>
                            @if ($instituciones->isEmpty())<p class="mt-2 text-xs text-red-700">Se necesita una institución activa para registrar equipamiento.</p>@endif
                        </div>
                        <div class="panel-campo--medio"><label for="nombre-equipamiento" class="mb-1.5 block text-sm font-semibold text-gray-700">Nombre *</label><input id="nombre-equipamiento" name="nombre" type="text" maxlength="100" required value="{{ $valor('nombre', $equipamiento->nombre) }}" class="{{ $campo }}"></div>
                        <div class="panel-campo--medio"><label for="tipo-equipamiento" class="mb-1.5 block text-sm font-semibold text-gray-700">Tipo *</label>
                            <input id="tipo-equipamiento" name="tipo" type="text" maxlength="100" list="tipos-equipamiento" required value="{{ $valor('tipo', $equipamiento->tipo) }}" class="{{ $campo }}" aria-describedby="tipo-equipamiento-ayuda">
                            <datalist id="tipos-equipamiento">@foreach ($tipos as $tipo)<option value="{{ $tipo }}"></option>@endforeach</datalist>
                            <p id="tipo-equipamiento-ayuda" class="mt-1 text-xs text-gray-500">Elige un tipo registrado o escribe uno nuevo.</p>
                        </div>
                        <div class="panel-campo--completo"><label for="descripcion-equipamiento" class="mb-1.5 block text-sm font-semibold text-gray-700">Descripción</label><textarea id="descripcion-equipamiento" name="descripcion" maxlength="255" rows="3" class="w-full min-w-0 rounded-lg border border-gray-300 bg-white px-3 py-2 text-base text-gray-800 focus:border-institucional focus:outline-none focus:ring-1 focus:ring-institucional sm:text-sm">{{ $valor('descripcion', $equipamiento->descripcion) }}</textarea><p class="mt-1 text-xs text-gray-500">Hasta 255 caracteres.</p></div>
                    </div>
                </x-seccion-formulario>
            </div>
            <div class="panel-columna">
                <x-seccion-formulario titulo="Inventario" id="inventario-equipamiento">
                    <div class="panel-campos">
                        <div class="panel-campo--completo"><label for="cantidad-equipamiento" class="mb-1.5 block text-sm font-semibold text-gray-700">Cantidad registrada *</label><input id="cantidad-equipamiento" name="cantidad" type="number" min="0" max="65535" step="1" inputmode="numeric" required value="{{ $valor('cantidad', $equipamiento->cantidad) }}" class="{{ $campo }}" aria-describedby="cantidad-equipamiento-ayuda"><p id="cantidad-equipamiento-ayuda" class="mt-1 text-xs text-gray-500">Número de unidades del recurso. Usa 0 si no hay unidades registradas.</p></div>
                    </div>
                </x-seccion-formulario>
                <x-seccion-formulario titulo="Estado y uso" id="estado-uso-equipamiento">
                    <div class="panel-pareja">
                        <div class="panel-campo--completo"><label for="estado-equipamiento" class="mb-1.5 block text-sm font-semibold text-gray-700">Estado del registro *</label><select id="estado-equipamiento" name="estado" class="{{ $campo }}"><option value="1" @selected((string) $valor('estado', (int) $equipamiento->estado) === '1')>Activo</option><option value="0" @selected((string) $valor('estado', (int) $equipamiento->estado) === '0')>Inactivo</option></select></div>
                        <div class="panel-campo--completo"><label for="uso-equipamiento" class="mb-1.5 block text-sm font-semibold text-gray-700">Habilitación de uso *</label><select id="uso-equipamiento" name="disponible" class="{{ $campo }}"><option value="1" @selected((string) $valor('disponible', (int) $equipamiento->disponible) === '1')>Uso habilitado</option><option value="0" @selected((string) $valor('disponible', (int) $equipamiento->disponible) === '0')>Uso deshabilitado</option></select></div>
                    </div>
                    <p class="panel-nota">Los registros inactivos se conservan en el inventario. La cantidad registrada corresponde al stock general de la institución.</p>
                </x-seccion-formulario>
            </div>
        </div>
        @if (!$editando)
            <x-distribucion-inicial-equipamiento :sedes="$sedes" :espacios="$espacios" />
        @else
            <p class="text-sm text-gray-500">Puedes repartir este inventario entre todas las sedes de su institución. <a class="font-semibold text-institucional underline" href="{{ route('recursos.ubicaciones.index', $equipamiento) }}">Distribuir entre sedes</a></p>
        @endif
        <x-acciones-formulario :cancelar="route('recursos.index')">
            <button type="submit" @disabled($instituciones->isEmpty()) class="boton boton-primario">{{ $editando ? 'Guardar cambios' : 'Registrar equipamiento' }}</button>
        </x-acciones-formulario>
    </form>
</div>
@endsection

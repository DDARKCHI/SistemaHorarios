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

<div class="panel-editor">

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

    <form action="{{ $editando ? route('salas.update', $sala) : route('salas.store') }}" method="POST" class="panel-formulario">
        @csrf
        @if ($editando) @method('PUT') @endif
        <div class="panel-layout">
            <div class="panel-columna">
                <x-seccion-formulario titulo="Datos de la sala" id="datos-sala">
                    <div class="panel-campos">
                        <div class="panel-campo--corto">
                            <label for="codigo-sala" class="mb-1.5 block text-sm font-semibold text-gray-700">Código *</label>
                            <input id="codigo-sala" name="codigo" type="text" maxlength="30" required value="{{ $valor('codigo', $sala->codigo) }}" class="{{ $campo }}" aria-describedby="codigo-ayuda">
                            <p id="codigo-ayuda" class="mt-1 text-xs text-gray-500">Debe ser único dentro de la sede.</p>
                        </div>
                        <div class="panel-campo--largo">
                            <label for="nombre-sala" class="mb-1.5 block text-sm font-semibold text-gray-700">Nombre</label>
                            <input id="nombre-sala" name="nombre" type="text" maxlength="100" value="{{ $valor('nombre', $sala->nombre) }}" class="{{ $campo }}">
                        </div>
                        <div class="{{ $ocultarTipoSala ? 'panel-campo--completo' : 'panel-campo--medio' }}">
                            <label for="sede-sala" class="mb-1.5 block text-sm font-semibold text-gray-700">Sede *</label>
                            <select id="sede-sala" name="sede_id" data-select-buscable aria-required="true" class="{{ $campo }}">
                                <option value="">Seleccionar sede</option>
                                @foreach ($sedes as $sede)
                                    <option value="{{ $sede->id }}" @selected((string) $valor('sede_id', $sala->sede_id) === (string) $sede->id)>{{ $sede->nombre }}{{ $sede->estado ? '' : ' (inactiva)' }}</option>
                                @endforeach
                            </select>
                            @if ($sedes->isEmpty())<p class="mt-1 text-xs text-red-700">Se necesita una sede activa para registrar salas.</p>@endif
                        </div>
                        @if (!$ocultarTipoSala)
<div class="panel-campo--medio">
                            <label for="tipo-sala" class="mb-1.5 block text-sm font-semibold text-gray-700">Tipo de sala *</label>
                            <select id="tipo-sala" name="tipo_sala_id" data-select-buscable aria-required="true" class="{{ $campo }}">
                                <option value="">Seleccionar tipo</option>
                                @foreach ($tiposSala as $tipo)
                                    <option value="{{ $tipo->id }}" @selected((string) $valor('tipo_sala_id', $sala->tipo_sala_id) === (string) $tipo->id)>{{ $tipo->nombre }}{{ $tipo->estado ? '' : ' (inactivo)' }}</option>
                                @endforeach
                            </select>
                            @if ($tiposSala->isEmpty())<p class="mt-1 text-xs text-red-700">Se necesita un tipo de sala activo para registrar salas.</p>@endif
                        </div>
@else
<input type="hidden" name="tipo_sala_id" value="{{ $tipoSalaBase }}">
@endif
                        <div class="panel-campo--completo rounded-lg border border-gray-200 bg-gray-50 p-3">
                            @if ($laboratorioVinculado)
                                <p class="text-sm font-semibold text-gray-800">Esta sala ya está registrada como laboratorio.</p>
                                <a href="{{ route('laboratorios.edit', ['tipo' => 'fijo', 'id' => $laboratorioVinculado->id]) }}" class="mt-1 inline-block text-sm font-semibold text-institucional underline">Ver laboratorio</a>
                            @else
                                <input type="hidden" name="registrar_laboratorio" value="0">
                                <label for="registrar-laboratorio-sala" class="flex cursor-pointer items-start gap-2 font-semibold text-gray-800"><input id="registrar-laboratorio-sala" type="checkbox" name="registrar_laboratorio" value="1" @checked((string) $valor('registrar_laboratorio', '0') === '1') class="mt-0.5 h-4 w-4 shrink-0 rounded border-gray-300 accent-institucional">Esta sala es un laboratorio</label>
                                <p class="mt-1 pl-6 text-xs leading-5 text-gray-500">Al guardar, se registrará en Laboratorios con el mismo código, nombre y capacidad.</p>
                            @endif
                        </div>
                        <div class="panel-campo--completo">
                            <label for="descripcion-sala" class="mb-1.5 block text-sm font-semibold text-gray-700">Descripción</label>
                            <textarea id="descripcion-sala" name="descripcion" maxlength="5000" rows="3" class="w-full min-w-0 rounded-lg border border-gray-300 bg-white px-3 py-2 text-base text-gray-800 focus:border-institucional focus:outline-none focus:ring-1 focus:ring-institucional sm:text-sm">{{ $valor('descripcion', $sala->descripcion) }}</textarea>
                        </div>
                    </div>
                </x-seccion-formulario>
            </div>
            <div class="panel-columna">
                <x-seccion-formulario titulo="Ubicación y capacidad" id="ubicacion-sala">
                    <div class="panel-campos">
                        <div class="panel-campo--completo">
                            <label for="edificio-sala" class="mb-1.5 block text-sm font-semibold text-gray-700">Edificio</label>
                            <input id="edificio-sala" name="edificio" type="text" maxlength="100" value="{{ $valor('edificio', $sala->edificio) }}" class="{{ $campo }}">
                        </div>
                    </div>
                    <div class="panel-pareja">
                        <div class="panel-campo--completo">
                            <label for="piso-sala" class="mb-1.5 block text-sm font-semibold text-gray-700">Piso</label>
                            <input id="piso-sala" name="piso" type="text" maxlength="30" value="{{ $valor('piso', $sala->piso) }}" class="{{ $campo }}">
                        </div>
                        <div class="panel-campo--completo">
                            <label for="capacidad-sala" class="mb-1.5 block text-sm font-semibold text-gray-700">Capacidad *</label>
                            <input id="capacidad-sala" name="capacidad" type="number" min="0" max="65535" step="1" inputmode="numeric" required value="{{ $valor('capacidad', $sala->capacidad) }}" class="{{ $campo }}" aria-describedby="capacidad-ayuda">
                            <p id="capacidad-ayuda" class="mt-1 text-xs text-gray-500">Cantidad de personas. Usa 0 si aún no se ha informado.</p>
                        </div>
                    </div>
                </x-seccion-formulario>
                <x-seccion-formulario titulo="Estado y uso" id="estado-uso-sala">
                    <div class="panel-pareja">
                        <div class="panel-campo--completo">
                            <label for="estado-sala" class="mb-1.5 block text-sm font-semibold text-gray-700">Estado del registro *</label>
                            <select id="estado-sala" name="estado" class="{{ $campo }}" aria-describedby="estado-ayuda">
                                <option value="1" @selected((string) $valor('estado', (int) $sala->estado) === '1')>Activo</option>
                                <option value="0" @selected((string) $valor('estado', (int) $sala->estado) === '0')>Inactivo</option>
                            </select>
                            <p id="estado-ayuda" class="mt-1 text-xs text-gray-500">Los registros inactivos se conservan en el catálogo.</p>
                        </div>
                        <div class="panel-campo--completo">
                            <label for="disponible-sala" class="mb-1.5 block text-sm font-semibold text-gray-700">Habilitación de uso *</label>
                            <select id="disponible-sala" name="disponible" class="{{ $campo }}">
                                <option value="1" @selected((string) $valor('disponible', (int) $sala->disponible) === '1')>Uso habilitado</option>
                                <option value="0" @selected((string) $valor('disponible', (int) $sala->disponible) === '0')>Uso deshabilitado</option>
                            </select>
                        </div>
                    </div>
                    @if ($editando)
                    <p class="panel-nota">Los horarios existentes conservan sus asignaciones. Si cambias la capacidad o deshabilitas la sala, revisa los horarios que la utilizan.</p>
                    @endif
                </x-seccion-formulario>
            </div>
        </div>
        <x-acciones-formulario :cancelar="route('salas.index')">
            <button type="submit" @disabled($sedes->isEmpty() || ($tiposSala->isEmpty() && !$tipoSalaBase)) class="boton boton-primario">{{ $editando ? 'Guardar cambios' : 'Crear sala' }}</button>
        </x-acciones-formulario>
    </form>
</div>

@endsection

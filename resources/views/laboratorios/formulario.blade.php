@extends('layouts.app')

@section('content')
@php
    $editando = $laboratorio->exists;
    $titulo = ($editando ? 'Editar' : 'Crear').' laboratorio'.($tipo === 'movil' ? ' móvil' : '');
    $campo = 'h-11 w-full min-w-0 rounded-lg border border-gray-300 bg-white px-3 text-base text-gray-800 focus:border-institucional focus:outline-none focus:ring-1 focus:ring-institucional sm:text-sm';
    $valor = static function ($nombre, $predeterminado = null) {
        $anterior = old($nombre, $predeterminado);
        return is_scalar($anterior) || $anterior === null ? $anterior : $predeterminado;
    };
    $salaSeleccionada = $tipo === 'fijo' ? $ubicaciones->firstWhere('id', (int) $valor('sala_id', $laboratorio->sala_id)) : null;
    $nombreSala = $salaSeleccionada ? (filled($salaSeleccionada->nombre) ? $salaSeleccionada->nombre : $salaSeleccionada->codigo) : '';
    $usarNombreSala = $tipo === 'fijo' && (bool) $valor('usar_nombre_sala', !$editando || $laboratorio->nombre === $nombreSala);
@endphp

<x-encabezado-pagina :titulo="$titulo">
    {{ $tipo === 'fijo' ? 'Registra el laboratorio y la sala física que lo contiene.' : 'Registra el recurso móvil y su sede de origen.' }}
</x-encabezado-pagina>

<div class="panel-editor">

    @if ($errors->any())
        <div role="alert" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
            <p class="font-semibold">Revisa los datos del laboratorio.</p>
            <ul class="mt-1 list-inside list-disc space-y-1">
                @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
    @endif

    <form action="{{ $editando ? route('laboratorios.update', ['tipo' => $tipo, 'id' => $laboratorio->id]) : route('laboratorios.store', ['tipo' => $tipo]) }}" method="POST" class="panel-formulario">
        @csrf
        @if ($editando) @method('PUT') @endif
        <div class="panel-layout">
            <div class="panel-columna">
                <x-seccion-formulario titulo="Datos del laboratorio" id="datos-laboratorio">
                    <div class="panel-campos">
                        <div class="panel-campo--completo">
                            <label for="ubicacion-laboratorio" class="mb-1.5 block text-sm font-semibold text-gray-700">{{ $tipo === 'fijo' ? 'Sala física *' : 'Sede de origen *' }}</label>
                            <select id="ubicacion-laboratorio" name="{{ $campoUbicacion }}" data-select-buscable aria-required="true" class="{{ $campo }}">
                                <option value="">{{ $tipo === 'fijo' ? 'Seleccionar sala' : 'Seleccionar sede' }}</option>
                                @foreach ($ubicaciones as $ubicacion)
                                    <option value="{{ $ubicacion->id }}" @if ($tipo === 'fijo') data-nombre="{{ filled($ubicacion->nombre) ? $ubicacion->nombre : $ubicacion->codigo }}" data-codigo="{{ $ubicacion->codigo }}" data-capacidad="{{ $ubicacion->capacidad }}" @endif @selected((string) $valor($campoUbicacion, $laboratorio->{$campoUbicacion}) === (string) $ubicacion->id)>
                                        @if ($tipo === 'fijo')
                                            {{ $ubicacion->codigo }} @if (filled($ubicacion->nombre) && $ubicacion->nombre !== $ubicacion->codigo)· {{ $ubicacion->nombre }}@endif · {{ $ubicacion->sede?->nombre ?? 'Sin sede' }} · {{ $ubicacion->capacidad > 0 ? $ubicacion->capacidad.' personas' : 'Sin capacidad informada' }}{{ $ubicacion->estado && $ubicacion->disponible && $ubicacion->sede?->estado ? '' : ' (inactiva o deshabilitada)' }}
                                        @else
                                            {{ $ubicacion->nombre }}{{ $ubicacion->estado ? '' : ' (inactiva)' }}
                                        @endif
                                    </option>
                                @endforeach
                            </select>
                            @if ($tipo === 'fijo')
                                <input type="hidden" name="usar_nombre_sala" value="0">
                                <label for="usar-nombre-sala" class="mt-3 flex cursor-pointer items-start gap-2 text-sm text-gray-600"><input id="usar-nombre-sala" type="checkbox" name="usar_nombre_sala" value="1" @checked($usarNombreSala) class="mt-0.5 h-4 w-4 shrink-0 rounded border-gray-300 accent-institucional">Usar automáticamente el nombre de la sala</label>
                            @endif
                            @if ($ubicaciones->isEmpty())
                                <p class="mt-2 text-xs text-red-700">{{ $tipo === 'fijo' ? 'Necesitas una sala activa y habilitada en una sede activa para registrar el laboratorio.' : 'Necesitas una sede activa para registrar el laboratorio móvil.' }}</p>
                                @if ($tipo === 'fijo')<a href="{{ route('salas.create') }}" class="boton boton-secundario mt-2">Crear una sala</a>@endif
                            @endif
                        </div>
                        <div class="panel-campo--corto">
                            <label for="codigo-laboratorio" class="mb-1.5 block text-sm font-semibold text-gray-700">Código{{ $tipo === 'movil' ? ' *' : '' }}</label>
                            <input id="codigo-laboratorio" name="codigo" type="text" maxlength="30" @required($tipo === 'movil') value="{{ $valor('codigo', $laboratorio->codigo) }}" class="{{ $campo }}" aria-describedby="codigo-laboratorio-ayuda">
                            <p id="codigo-laboratorio-ayuda" class="mt-1 text-xs text-gray-500">{{ $tipo === 'fijo' ? 'Si lo informas, debe ser único dentro de la sala.' : 'Debe ser único dentro de la sede.' }}</p>
                        </div>
                        <div class="panel-campo--largo">
                            <label for="nombre-laboratorio" class="mb-1.5 block text-sm font-semibold text-gray-700">Nombre *</label>
                            <input id="nombre-laboratorio" name="nombre" type="text" maxlength="100" @required(!$usarNombreSala) @readonly($usarNombreSala) value="{{ $valor('nombre', $usarNombreSala ? $nombreSala : $laboratorio->nombre) }}" class="{{ $campo }}">
                        </div>

                        <div class="panel-campo--completo">
                            <label for="descripcion-laboratorio" class="mb-1.5 block text-sm font-semibold text-gray-700">Descripción</label>
                            <textarea id="descripcion-laboratorio" name="descripcion" maxlength="5000" rows="3" class="w-full min-w-0 rounded-lg border border-gray-300 bg-white px-3 py-2 text-base text-gray-800 focus:border-institucional focus:outline-none focus:ring-1 focus:ring-institucional sm:text-sm">{{ $valor('descripcion', $laboratorio->descripcion) }}</textarea>
                        </div>
                    </div>
                </x-seccion-formulario>
            </div>
            <div class="panel-columna">
                <x-seccion-formulario titulo="Capacidad" id="aforo-laboratorio">
                    <div class="panel-campos">
                        <div class="panel-campo--completo">
                            <label for="capacidad-laboratorio" class="mb-1.5 block text-sm font-semibold text-gray-700">Capacidad *</label>
                            <input id="capacidad-laboratorio" name="capacidad" type="number" min="0" max="65535" step="1" inputmode="numeric" required value="{{ $valor('capacidad', $laboratorio->capacidad) }}" class="{{ $campo }}" aria-describedby="capacidad-laboratorio-ayuda">
                            <p id="capacidad-laboratorio-ayuda" class="mt-1 text-xs text-gray-500">Personas que puede atender. Usa 0 si aún no se ha informado.{{ $tipo === 'fijo' ? ' No puede superar la capacidad conocida de su sala.' : '' }}</p>
                        </div>
                    </div>
                </x-seccion-formulario>
                <x-seccion-formulario titulo="Estado y uso" id="estado-uso-laboratorio">
                    <div class="panel-pareja">
                        <div class="panel-campo--completo">
                            <label for="estado-laboratorio" class="mb-1.5 block text-sm font-semibold text-gray-700">Estado del registro *</label>
                            <select id="estado-laboratorio" name="estado" class="{{ $campo }}">
                                <option value="1" @selected((string) $valor('estado', (int) $laboratorio->estado) === '1')>Activo</option>
                                <option value="0" @selected((string) $valor('estado', (int) $laboratorio->estado) === '0')>Inactivo</option>
                            </select>
                        </div>
                        <div class="panel-campo--completo">
                            <label for="disponible-laboratorio" class="mb-1.5 block text-sm font-semibold text-gray-700">Habilitación de uso *</label>
                            <select id="disponible-laboratorio" name="disponible" class="{{ $campo }}">
                                <option value="1" @selected((string) $valor('disponible', (int) $laboratorio->disponible) === '1')>Uso habilitado</option>
                                <option value="0" @selected((string) $valor('disponible', (int) $laboratorio->disponible) === '0')>Uso deshabilitado</option>
                            </select>
                        </div>
                    </div>
                    <p class="panel-nota">{{ $editando ? 'Los cambios conservan los horarios y asignaciones existentes. Revisa su uso si cambias la capacidad o deshabilitas el recurso.' : 'Los registros inactivos se conservan en el catálogo.' }}</p>
                </x-seccion-formulario>
            </div>
        </div>
        <x-acciones-formulario :cancelar="route('laboratorios.index', ['tipo' => $tipo])">
            <button type="submit" @disabled($ubicaciones->isEmpty()) class="boton boton-primario">{{ $editando ? 'Guardar cambios' : 'Crear laboratorio' }}</button>
        </x-acciones-formulario>
    </form>
</div>
@if ($tipo === 'fijo')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const sala = document.getElementById('ubicacion-laboratorio');
    const nombre = document.getElementById('nombre-laboratorio');
    const usarNombre = document.getElementById('usar-nombre-sala');
    const codigo = document.getElementById('codigo-laboratorio');
    const capacidad = document.getElementById('capacidad-laboratorio');
    const sugerir = @json(!$editando && !session()->hasOldInput());
    let codigoAnterior = codigo.value;
    let capacidadAnterior = capacidad.value;
    const actualizar = () => {
        const opcion = sala.selectedOptions[0];
        nombre.readOnly = usarNombre.checked;
        nombre.required = !usarNombre.checked;
        if (usarNombre.checked) nombre.value = opcion?.dataset.nombre || '';
        if (sugerir && opcion?.value) {
            if (!codigo.value || codigo.value === codigoAnterior) {
                codigo.value = opcion.dataset.codigo || ''; codigoAnterior = codigo.value;
            }
            if (!capacidad.value || capacidad.value === capacidadAnterior) {
                capacidad.value = opcion.dataset.capacidad || '0'; capacidadAnterior = capacidad.value;
            }
        }
    };
    sala.addEventListener('change', actualizar); usarNombre.addEventListener('change', actualizar);
    codigo.addEventListener('input', () => { codigoAnterior = null; });
    capacidad.addEventListener('input', () => { capacidadAnterior = null; });
    actualizar();
});
</script>
@endif
@endsection

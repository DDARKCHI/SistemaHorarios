@extends('layouts.app')
@section('title', ($prioridad->exists ? 'Editar prioridad' : 'Crear prioridad').' | '.$identidad['nombre'])
@section('content')
@php
    $editando = $prioridad->exists;
    $porCurso = !$editando || $prioridad->asignatura_id !== null;
    $campo = 'h-11 w-full min-w-0 rounded-lg border border-gray-300 bg-white px-3 text-base text-gray-800 focus:border-institucional focus:outline-none focus:ring-1 focus:ring-institucional sm:text-sm';
    $valor = static function ($nombre, $predeterminado = null) { $anterior = old($nombre, $predeterminado); return is_scalar($anterior) || $anterior === null ? $anterior : $predeterminado; };
@endphp
<x-encabezado-pagina :titulo="$editando ? 'Editar prioridad' : 'Crear prioridad'">Define qué curso tiene preferencia cuando necesita un recurso compartido.</x-encabezado-pagina>
<div class="panel-editor">
    @if ($errors->any())<div role="alert" class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800"><p class="font-semibold">Revisa los datos de la prioridad.</p><ul class="mt-1 list-inside list-disc">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <form method="POST" action="{{ $editando ? route('prioridades.update', $prioridad) : route('prioridades.store') }}" aria-label="Guardar prioridad" class="panel-formulario">
        @csrf
        @if ($editando) @method('PUT') @endif
        @if ($porCurso)<input type="hidden" name="alcance" value="curso">@endif
        <div class="panel-layout">
            <div class="panel-columna">
                <x-seccion-formulario :titulo="$porCurso ? 'Curso y recurso' : 'Datos de la prioridad'" id="datos-prioridad">
                    <div class="panel-campos">
                        @if ($porCurso)
                        <div class="panel-campo--completo">
                            <label for="curso-prioridad" class="mb-1.5 block text-sm font-semibold text-gray-700">Curso o ramo *</label>
                            <select id="curso-prioridad" name="asignatura_id" data-select-buscable aria-required="true" class="{{ $campo }}"><option value="">Seleccionar curso</option>@foreach ($asignaturas as $curso)<option value="{{ $curso->id }}" @selected((string) $valor('asignatura_id', $prioridad->asignatura_id) === (string) $curso->id)>{{ $curso->codigo }} · {{ $curso->nombre }}{{ $curso->estado ? '' : ' (inactivo)' }}</option>@endforeach</select>
                            <p class="mt-1 text-xs leading-5 text-gray-500">Abarca todas las secciones y actividades de este ramo.</p>
                        </div>
                        <div class="panel-campo--completo">
                            <label for="recurso-prioridad" class="mb-1.5 block text-sm font-semibold text-gray-700">Tipo de recurso *</label>
                            <select id="recurso-prioridad" name="tipo_recurso" class="{{ $campo }}" required>@foreach (\App\Models\PrioridadRecurso::RECURSOS as $clave => $etiqueta)<option value="{{ $clave }}" @selected($valor('tipo_recurso', $prioridad->tipo_recurso) === $clave)>{{ $etiqueta }}</option>@endforeach</select>
                        </div>
                        @if ($tiposSala->isNotEmpty())
                        <div class="panel-campo--completo" data-tipo-espacio-prioridad>
                            <label for="tipo-sala-prioridad" class="mb-1.5 block text-sm font-semibold text-gray-700">Tipo de sala o laboratorio</label>
                            <select id="tipo-sala-prioridad" name="tipo_sala_id" class="{{ $campo }}"><option value="">Todos los tipos</option>@foreach ($tiposSala as $tipo)<option value="{{ $tipo->id }}" @selected((string) $valor('tipo_sala_id', $prioridad->tipo_sala_id) === (string) $tipo->id)>{{ $tipo->nombre }}{{ $tipo->estado ? '' : ' (inactivo)' }}</option>@endforeach</select>
                            <p class="mt-1 text-xs leading-5 text-gray-500">Puedes limitar la preferencia, por ejemplo, a laboratorios de computación.</p>
                        </div>
                        @endif
                        <div class="panel-campo--completo" data-tipo-equipo-prioridad>
                            <label for="tipo-equipo-prioridad" class="mb-1.5 block text-sm font-semibold text-gray-700">Tipo de equipamiento</label>
                            <select id="tipo-equipo-prioridad" name="tipo_equipamiento" class="{{ $campo }}"><option value="">Todos los tipos</option>@foreach ($tiposEquipamiento as $tipo)<option value="{{ $tipo }}" @selected($valor('tipo_equipamiento', $prioridad->tipo_equipamiento) === $tipo)>{{ $tipo }}</option>@endforeach</select>
                            <p class="mt-1 text-xs leading-5 text-gray-500">Para priorizar laboratorios de computación, elige el tipo de computadores registrado en el inventario. Se comprueba el equipamiento ubicado en su sala.</p>
                        </div>
                        @else
                        <div class="panel-campo--completo"><label for="nombre-prioridad" class="mb-1.5 block text-sm font-semibold text-gray-700">Nombre *</label><input placeholder="Ej. Uso prioritario de computación" id="nombre-prioridad" name="nombre" type="text" maxlength="100" required value="{{ $valor('nombre', $prioridad->nombre) }}" class="{{ $campo }}"></div>
                        <p class="panel-campo--completo text-xs leading-5 text-gray-500">Esta prioridad se conserva para sus requerimientos actuales. Las nuevas reglas se definen por curso y recurso.</p>
                        @endif
                        <div class="panel-campo--completo"><label for="descripcion-prioridad" class="mb-1.5 block text-sm font-semibold text-gray-700">Descripción</label><textarea placeholder="Ej. El ramo requiere computadores durante sus clases prácticas." id="descripcion-prioridad" name="descripcion" rows="3" maxlength="255" class="w-full min-w-0 rounded-lg border border-gray-300 bg-white px-3 py-2 text-base text-gray-800 focus:border-institucional focus:outline-none focus:ring-1 focus:ring-institucional sm:text-sm">{{ $valor('descripcion', $prioridad->descripcion) }}</textarea><p class="mt-1 text-xs text-gray-500">Hasta 255 caracteres.</p></div>
                    </div>
                </x-seccion-formulario>
            </div>
            <div class="panel-columna">
                <x-seccion-formulario titulo="Nivel y estado" id="nivel-estado-prioridad">
                    <div class="panel-pareja">
                        <div><label for="nivel-prioridad" class="mb-1.5 block text-sm font-semibold text-gray-700">Nivel *</label><input placeholder="Ej. 1" id="nivel-prioridad" name="nivel" type="number" min="1" max="255" step="1" required value="{{ $valor('nivel', $prioridad->nivel) }}" class="{{ $campo }}"><p class="mt-1 text-xs leading-5 text-gray-500">Del 1 al 255. El 1 tiene mayor prioridad.</p></div>
                        <div><label for="estado-prioridad" class="mb-1.5 block text-sm font-semibold text-gray-700">Estado del registro *</label><select id="estado-prioridad" name="estado" class="{{ $campo }}"><option value="1" @selected((string) $valor('estado', (int) $prioridad->estado) === '1')>Activa</option><option value="0" @selected((string) $valor('estado', (int) $prioridad->estado) === '0')>Inactiva</option></select></div>
                    </div>
                    <p class="panel-nota">Por ejemplo: Backend, laboratorio de computación, nivel 1. Otro ramo con menor necesidad de computadores puede tener nivel 2.</p>
                    <p class="panel-nota">El generador aplica esta preferencia al asignar los horarios y espacios pendientes. Las asignaciones de la versión base se conservan.</p>
                </x-seccion-formulario>
            </div>
        </div>
        <x-acciones-formulario :cancelar="route('prioridades.index')"><button type="submit" class="boton boton-primario">{{ $editando ? 'Guardar cambios' : 'Crear prioridad' }}</button></x-acciones-formulario>
    </form>
</div>
@if ($porCurso)
<script>
document.addEventListener('DOMContentLoaded', () => {
    const recurso = document.getElementById('recurso-prioridad');
    const espacio = document.querySelector('[data-tipo-espacio-prioridad]');
    const equipo = document.querySelector('[data-tipo-equipo-prioridad]');
    const actualizar = () => {
        if (espacio) { espacio.hidden = !['sala', 'laboratorio'].includes(recurso.value); espacio.querySelector('select').disabled = espacio.hidden; }
        equipo.hidden = !['equipamiento', 'laboratorio'].includes(recurso.value); equipo.querySelector('select').disabled = equipo.hidden;
    };
    recurso.addEventListener('change', actualizar); actualizar();
});
</script>
@endif
@endsection

@extends('layouts.app')
@section('title', ($requerimiento->exists ? 'Editar requerimiento' : 'Agregar requerimiento').' | PROSEC')
@section('content')
@php
    $editando = $requerimiento->exists;
    $campo = 'h-11 w-full min-w-0 rounded-lg border border-gray-300 bg-white px-3 text-base text-gray-800 focus:border-institucional focus:outline-none focus:ring-1 focus:ring-institucional sm:text-sm';
    $valor = static function ($nombre, $predeterminado = null) { $anterior = old($nombre, $predeterminado); return is_scalar($anterior) || $anterior === null ? $anterior : $predeterminado; };
@endphp
<x-encabezado-pagina :titulo="$editando ? 'Editar requerimiento' : 'Agregar requerimiento'">Configura los recursos necesarios para esta actividad.
    @can('gestionar salas y recursos')
        <x-slot:acciones><a href="{{ route('prioridades.index') }}" class="boton boton-secundario">Prioridades de recursos</a></x-slot:acciones>
    @endcan
</x-encabezado-pagina>
<div class="panel-editor">
    <div class="panel-contexto"><h2 class="break-words text-base font-semibold text-gray-800">{{ $actividad->seccion->asignatura?->codigo }} · {{ $actividad->seccion->asignatura?->nombre }}</h2><p class="mt-1 text-sm text-gray-600">{{ $actividad->seccion->periodoAcademico->nombre }} · Sección {{ $actividad->seccion->codigo }} · {{ $actividad->tipo }}</p><p class="mt-3 text-sm leading-6 text-gray-500">Este requerimiento se usará al revisar todas las versiones del período. Los horarios asignados se conservan.</p></div>
    @if ($errors->any())<div role="alert" class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800"><p class="font-semibold">Revisa los datos del requerimiento.</p><ul class="mt-1 list-inside list-disc space-y-1">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <form method="POST" action="{{ $editando ? route('programacion.requerimientos.update', ['actividad' => $actividad->id, 'requerimiento' => $requerimiento->id]) : route('programacion.requerimientos.store', ['actividad' => $actividad->id]) }}" class="panel-formulario" aria-label="Guardar requerimiento">
        @csrf
        @if ($editando)
            @method('PUT')
        @endif
        @if (isset($contexto['version_id']))<input type="hidden" name="version_id" value="{{ $contexto['version_id'] }}">@endif
        <div class="panel-layout">
            <div class="panel-columna">
                <x-seccion-formulario titulo="Recursos necesarios" id="recursos-requerimiento">
                    <div class="panel-campos">
                        <div class="panel-campo--medio"><label for="tipo-requerimiento" class="mb-1.5 block text-sm font-semibold text-gray-700">Tipo de requerimiento *</label><select id="tipo-requerimiento" name="tipo_requerimiento" data-select-buscable required class="{{ $campo }}">@foreach ($tipos as $tipo => $etiqueta)<option value="{{ $tipo }}" data-tipo-normalizado="{{ \App\Models\RequerimientoActividad::tipoNormalizado($tipo) }}" @selected((string) $valor('tipo_requerimiento', $requerimiento->tipo_requerimiento) === (string) $tipo)>{{ $etiqueta }}</option>@endforeach</select></div>
                        <div class="panel-campo--medio"><label for="cantidad-requerimiento" class="mb-1.5 block text-sm font-semibold text-gray-700">Cantidad de recursos *</label><input id="cantidad-requerimiento" name="cantidad" type="number" min="1" max="65535" step="1" inputmode="numeric" required value="{{ $valor('cantidad', $requerimiento->cantidad) }}" class="{{ $campo }}"></div>
                        @if (!$ocultarTipoSala)
<div class="panel-campo--medio"><label for="sala-requerimiento" class="mb-1.5 block text-sm font-semibold text-gray-700">Tipo de sala</label><select id="sala-requerimiento" name="tipo_sala_id" data-select-buscable class="{{ $campo }}"><option value="">Sin tipo específico</option>@foreach ($tiposSala as $tipo)<option value="{{ $tipo->id }}" @selected((string) $valor('tipo_sala_id', $requerimiento->tipo_sala_id) === (string) $tipo->id)>{{ $tipo->nombre }}{{ $tipo->estado ? '' : ' (inactivo)' }}</option>@endforeach</select><p class="mt-1 text-xs text-gray-500">Es obligatorio para laboratorio móvil e indica el tipo de sala física que necesita.</p></div>
@else
<select id="sala-requerimiento" name="tipo_sala_id" hidden><option value="{{ $tipoSalaBase }}"></option></select>
@endif
                        <div class="panel-campo--medio"><label for="capacidad-requerimiento" class="mb-1.5 block text-sm font-semibold text-gray-700">Capacidad mínima por recurso</label><input id="capacidad-requerimiento" name="capacidad_minima" type="number" min="1" max="65535" step="1" inputmode="numeric" value="{{ $valor('capacidad_minima', $requerimiento->capacidad_minima) }}" class="{{ $campo }}"><p class="mt-1 text-xs text-gray-500">Déjala vacía si no hay un mínimo adicional.</p></div>
                        <div class="panel-campo--completo"><label for="equipo-requerimiento" class="mb-1.5 block text-sm font-semibold text-gray-700">Equipamiento del inventario</label><select id="equipo-requerimiento" name="equipamiento_id" data-select-buscable class="{{ $campo }}"><option value="">Sin vínculo: revisar por nombre</option>@foreach ($equipamientos as $equipo)<option value="{{ $equipo->id }}" data-nombre="{{ $equipo->nombre }}" @selected((string) $valor('equipamiento_id', $requerimiento->equipamiento_id) === (string) $equipo->id)>{{ $equipo->nombre }} · {{ $equipo->tipo }}{{ $equipo->estado && $equipo->disponible ? '' : ' (inactivo o no habilitado)' }}</option>@endforeach</select><p class="mt-1 text-xs text-gray-500">La revisión contará las unidades de este equipo ubicadas en los espacios asignados al módulo.</p></div>
                        <div class="panel-campo--completo"><label for="nombre-requerimiento" class="mb-1.5 block text-sm font-semibold text-gray-700">Recurso específico</label><input id="nombre-requerimiento" name="nombre_recurso" type="text" maxlength="100" value="{{ $valor('nombre_recurso', $requerimiento->nombre_recurso) }}" class="{{ $campo }}"><p class="mt-1 text-xs leading-5 text-gray-500">Al vincular un equipo se utiliza su nombre del inventario. Los recursos solicitados solo por nombre requieren revisión manual.</p></div>
                    </div>
                </x-seccion-formulario>
            </div>
            <div class="panel-columna">
                <x-seccion-formulario titulo="Prioridad y exigencia" id="criterios-requerimiento">
                    <div class="panel-campos">
                        <div class="panel-campo--completo"><label for="prioridad-requerimiento" class="mb-1.5 block text-sm font-semibold text-gray-700">Prioridad</label><select id="prioridad-requerimiento" name="prioridad_recurso_id" data-select-buscable class="{{ $campo }}"><option value="">Sin prioridad</option>@foreach ($prioridades as $prioridad)<option value="{{ $prioridad->id }}" @selected((string) $valor('prioridad_recurso_id', $requerimiento->prioridad_recurso_id) === (string) $prioridad->id)>{{ $prioridad->nombre }}{{ $prioridad->estado ? '' : ' (inactiva)' }} · Nivel {{ $prioridad->nivel }}</option>@endforeach</select><p class="mt-1 text-xs text-gray-500">La prioridad del curso se utiliza al revisar cruces de recursos. El nivel 1 tiene mayor preferencia que el 2.</p></div>
                    </div>
                    <div class="panel-pareja">
                        <div class="panel-campo--completo"><label for="obligatorio-requerimiento" class="mb-1.5 block text-sm font-semibold text-gray-700">Exigencia *</label><select id="obligatorio-requerimiento" name="obligatorio" class="{{ $campo }}"><option value="1" @selected((string) $valor('obligatorio', (int) $requerimiento->obligatorio) === '1')>Obligatorio</option><option value="0" @selected((string) $valor('obligatorio', (int) $requerimiento->obligatorio) === '0')>Opcional</option></select></div>
                        <div class="panel-campo--completo"><label for="estado-requerimiento" class="mb-1.5 block text-sm font-semibold text-gray-700">Estado del registro *</label><select id="estado-requerimiento" name="estado" class="{{ $campo }}"><option value="1" @selected((string) $valor('estado', (int) $requerimiento->estado) === '1')>Activo</option><option value="0" @selected((string) $valor('estado', (int) $requerimiento->estado) === '0')>Inactivo</option></select><p class="mt-1 text-xs text-gray-500">Al desactivarlo se conserva el registro y deja de exigirse en la revisión.</p></div>
                    </div>
                </x-seccion-formulario>
                <x-seccion-formulario titulo="Observaciones" id="notas-requerimiento">
                    <div class="panel-campos">
                        <div class="panel-campo--completo"><label for="observaciones-requerimiento" class="sr-only">Observaciones</label><textarea id="observaciones-requerimiento" name="observaciones" maxlength="2000" rows="3" class="w-full min-w-0 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-800">{{ $valor('observaciones', $requerimiento->observaciones) }}</textarea></div>
                    </div>
                </x-seccion-formulario>
            </div>
        </div>
        <x-acciones-formulario :cancelar="route('programacion.requerimientos.index', $contexto)">
            <button type="submit" class="boton boton-primario">{{ $editando ? 'Guardar cambios' : 'Agregar requerimiento' }}</button>
        </x-acciones-formulario>
    </form>
</div>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const tipo = document.getElementById('tipo-requerimiento');
    const sala = document.getElementById('sala-requerimiento');
    const nombre = document.getElementById('nombre-requerimiento');
    const equipo = document.getElementById('equipo-requerimiento');
    const capacidad = document.getElementById('capacidad-requerimiento');
    let nombreDelInventario = false;
    const actualizar = () => {
        const elegido = tipo.selectedOptions[0]?.dataset.tipoNormalizado || tipo.value;
        const movil = elegido === 'Laboratorio móvil';
        sala.required = movil && !sala.hidden;
        sala.disabled = elegido === 'Equipamiento';
        equipo.disabled = elegido !== 'Equipamiento';
        capacidad.disabled = elegido === 'Equipamiento';
        nombre.disabled = elegido === 'Equipamiento' && !!equipo.value;
        nombre.required = elegido === 'Equipamiento' && !equipo.value;
        if (nombre.disabled) { nombre.value = equipo.selectedOptions[0]?.dataset.nombre || ''; nombreDelInventario = true; }
        else if (nombreDelInventario && elegido !== 'Equipamiento') { nombre.value = ''; nombreDelInventario = false; }
        const buscador = document.getElementById('sala-requerimiento-busqueda');
        if (buscador) {
            buscador.setAttribute('aria-required', String(movil));
            buscador.setCustomValidity(movil && !sala.value ? 'Selecciona un tipo de sala para el laboratorio móvil.' : '');
        }
    };
    tipo.addEventListener('change', actualizar); sala.addEventListener('change', actualizar); equipo.addEventListener('change', actualizar);
    actualizar(); requestAnimationFrame(actualizar);
});
</script>
@endsection

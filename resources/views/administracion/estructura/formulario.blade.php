@extends('layouts.app')
@section('title', ($registro->exists ? 'Editar ' : 'Crear ').($tipo === 'sedes' ? 'sede' : 'carrera').' | '.$identidad['nombre'])
@section('content')
@php
    $valor = static function ($campo, $base = null) {
        $anterior = old($campo, $base);
        return is_scalar($anterior) || $anterior === null ? $anterior : $base;
    };
    $universidadId = is_scalar($universidadId) ? $universidadId : null;
@endphp
<div class="panel-editor">
    <x-encabezado-pagina :titulo="($registro->exists ? 'Editar ' : 'Crear ').($tipo === 'sedes' ? 'sede' : 'carrera')">{{ $tipo === 'sedes' ? 'Configura los datos de la sede para los catálogos y la planificación.' : 'Configura la carrera y las sedes en las que se imparte.' }}</x-encabezado-pagina>
    @include('disponibilidad.avisos')
    <form method="post" action="{{ $registro->exists ? route('administracion.estructura.update', ['tipo' => $tipo, 'id' => $registro->id]) : route('administracion.estructura.store', $tipo) }}" class="panel-formulario" id="formulario-estructura">@csrf @if ($registro->exists) @method('PUT') @endif
        <div class="panel-layout"><div class="panel-columna">
            <x-seccion-formulario titulo="Datos del registro" id="datos-estructura">
                <div class="panel-campos">
                    <div class="panel-campo--largo"><label for="universidad_id" class="mb-1.5 block text-sm font-semibold">Institución *</label>@if ($registro->exists)<input id="universidad_id" type="hidden" name="universidad_id" value="{{ $registro->universidad_id }}"><p class="flex min-h-11 items-center rounded-lg border border-borde bg-fondo px-3 text-sm">{{ $registro->universidad->nombre }}</p>@else<select id="universidad_id" name="universidad_id" required class="h-11 w-full rounded-lg border border-borde px-3"><option value="">Seleccionar institución</option>@foreach ($universidades as $u)<option value="{{ $u->id }}" @selected($universidadId == $u->id)>{{ $u->nombre }}</option>@endforeach</select>@endif</div>
                    <div class="panel-campo--corto"><label for="codigo" class="mb-1.5 block text-sm font-semibold">Código</label><input placeholder="{{ $tipo === 'sedes' ? 'Ej. TH' : 'Ej. INF' }}" id="codigo" name="codigo" maxlength="{{ $tipo === 'sedes' ? 20 : 30 }}" value="{{ $valor('codigo', $registro->codigo) }}" class="h-11 w-full rounded-lg border border-borde px-3"><p class="mt-1 text-xs text-texto-secundario">Opcional. Debe ser único dentro de la institución.</p></div>
                    <div class="{{ $tipo === 'sedes' ? 'panel-campo--medio' : 'panel-campo--largo' }}"><label for="nombre" class="mb-1.5 block text-sm font-semibold">Nombre *</label><input placeholder="{{ $tipo === 'sedes' ? 'Ej. Talcahuano' : 'Ej. Ingeniería en Informática' }}" id="nombre" name="nombre" maxlength="{{ $tipo === 'sedes' ? 100 : 150 }}" required value="{{ $valor('nombre', $registro->nombre) }}" class="h-11 w-full rounded-lg border border-borde px-3"></div>
                    @if ($tipo === 'sedes')<div class="panel-campo--medio"><label for="direccion" class="mb-1.5 block text-sm font-semibold">Dirección</label><input placeholder="Ej. Avenida Principal 123" id="direccion" name="direccion" maxlength="200" value="{{ $valor('direccion', $registro->direccion) }}" class="h-11 w-full rounded-lg border border-borde px-3"></div>@else
                    <div class="panel-campo--corto"><label for="regimen" class="mb-1.5 block text-sm font-semibold">Régimen</label><input placeholder="Ej. Semestral" id="regimen" name="regimen" maxlength="50" value="{{ $valor('regimen', $registro->regimen) }}" class="h-11 w-full rounded-lg border border-borde px-3"></div><div class="panel-campo--medio"><label for="tipo_estudio" class="mb-1.5 block text-sm font-semibold">Tipo de estudio</label><input placeholder="Ej. Pregrado" id="tipo_estudio" name="tipo_estudio" maxlength="100" value="{{ $valor('tipo_estudio', $registro->tipo_estudio) }}" class="h-11 w-full rounded-lg border border-borde px-3"></div>
                    <div class="panel-campo--medio"><label for="facultad_instituto_id" class="mb-1.5 block text-sm font-semibold">Facultad o instituto</label><select id="facultad_instituto_id" name="facultad_instituto_id" class="h-11 w-full rounded-lg border border-borde px-3"><option value="">Sin asociación</option>@foreach ($facultades as $f)<option value="{{ $f->id }}" data-institucion="{{ $f->universidad_id }}" @selected($valor('facultad_instituto_id', $registro->facultad_instituto_id) == $f->id)>{{ $f->nombre }}{{ $f->estado ? '' : ' · Inactivo' }}</option>@endforeach</select></div>
                    @endif
                </div>
            </x-seccion-formulario>
            @if ($tipo === 'carreras')
            <x-seccion-formulario titulo="Sedes de la carrera" id="sedes-carrera"><div class="space-y-4"><div><label for="sede_id" class="mb-1.5 block text-sm font-semibold">Sede principal *</label><select id="sede_id" name="sede_id" required class="h-11 w-full rounded-lg border border-borde px-3"><option value="">Seleccionar sede principal</option>@foreach ($sedes as $s)<option value="{{ $s->id }}" data-institucion="{{ $s->universidad_id }}" @selected($valor('sede_id', $registro->sede_id) == $s->id)>{{ $s->nombre }}{{ $s->estado ? '' : ' · Inactiva' }}</option>@endforeach</select><p class="mt-1 text-xs text-texto-secundario">La sede principal se incluye siempre entre las sedes de la carrera.</p></div><fieldset><legend class="mb-3 text-sm font-semibold">Otras sedes donde se imparte</legend><div class="grid gap-2 @min-[24rem]/seccion:grid-cols-2">@foreach ($sedes as $s)<label class="flex items-center gap-3 rounded-lg border border-borde p-3 text-sm" data-sede-institucion="{{ $s->universidad_id }}"><input type="checkbox" name="sedes[]" value="{{ $s->id }}" @checked(in_array($s->id, is_array(old('sedes', $sedesElegidas)) ? old('sedes', $sedesElegidas) : [])) class="h-4 w-4 accent-institucional">{{ $s->nombre }}{{ $s->estado ? '' : ' · Inactiva' }}</label>@endforeach</div></fieldset><p class="text-xs text-texto-secundario">Si hay secciones registradas, conserva las sedes anteriores para mantener las consultas históricas. Puedes añadir nuevas.</p></div></x-seccion-formulario>
            @endif
        </div><aside class="panel-columna"><x-seccion-formulario titulo="Estado y conservación" id="estado-estructura"><div><label for="estado" class="mb-1.5 block text-sm font-semibold">Estado *</label><select id="estado" name="estado" required class="h-11 w-full rounded-lg border border-borde px-3"><option value="1" @selected((string) $valor('estado', (int) $registro->estado) === '1')>Activo</option><option value="0" @selected((string) $valor('estado', (int) $registro->estado) === '0')>Inactivo</option></select></div><p class="mt-4 text-sm text-texto-secundario">{{ $tipo === 'sedes' ? 'Para desactivar una sede, revisa primero las carreras, salas y laboratorios móviles activos que dependen de ella.' : 'Para desactivar una carrera, resuelve primero sus secciones activas de períodos vigentes.' }}</p><p class="mt-3 text-xs text-texto-secundario">Los registros se conservan para consulta. Las modificaciones quedan registradas en el historial.</p></x-seccion-formulario></aside></div>
        <x-acciones-formulario :cancelar="route('administracion.estructura.index', $tipo)"><button class="boton boton-primario" type="submit">{{ $registro->exists ? 'Guardar cambios' : ($tipo === 'sedes' ? 'Crear sede' : 'Crear carrera') }}</button></x-acciones-formulario>
    </form>
</div>
@if ($tipo === 'carreras')
<script>
(() => {
    const institucion = document.getElementById('universidad_id') ?? document.querySelector('input[name="universidad_id"]');
    const filtrar = () => {
        document.querySelectorAll('select option[data-institucion]').forEach(opcion => {
            const coincide = opcion.dataset.institucion === institucion.value;
            opcion.hidden = !coincide; opcion.disabled = !coincide;
            if (!coincide) opcion.selected = false;
        });
        document.querySelectorAll('[data-sede-institucion]').forEach(label => {
            const coincide = label.dataset.sedeInstitucion === institucion.value;
            label.hidden = !coincide;
            label.querySelector('input').disabled = !coincide;
        });
    };
    institucion?.addEventListener('change', filtrar); filtrar();
})();
</script>
@endif
@endsection

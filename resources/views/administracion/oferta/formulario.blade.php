@extends('layouts.app')
@section('title', ($registro->exists ? 'Editar ' : 'Crear ').$singular.' | '.$identidad['nombre'])
@section('content')
@php
    $valor = static function ($campo, $base = null) { $anterior = old($campo, $base); return is_scalar($anterior) || $anterior === null ? $anterior : $base; };
@endphp
<div class="panel-editor">
    <x-encabezado-pagina :titulo="($registro->exists ? 'Editar ' : 'Crear ').$singular">{{ $tipo === 'asignaturas' ? 'Registra el ramo y su referencia académica sin modificar las secciones existentes.' : 'Define el semestre y sus fechas. Archivar conserva la programación y el historial.' }}</x-encabezado-pagina>
    @include('disponibilidad.avisos')
    <form method="post" action="{{ route('administracion.oferta.'.($registro->exists ? 'update' : 'store'), ['tipo' => $tipo, 'id' => $registro->id]) }}" class="panel-formulario">
        @csrf @if ($registro->exists) @method('PUT') @endif
        <div class="panel-layout"><div class="panel-columna">
            <x-seccion-formulario titulo="Identificación" id="datos-oferta"><div class="panel-campos">
                <div class="{{ $tipo === 'asignaturas' ? 'panel-campo--largo' : 'panel-campo--completo' }}"><label for="universidad_id" class="mb-1.5 block text-sm font-semibold">Institución *</label>@if ($registro->exists)<input type="hidden" id="universidad_id" name="universidad_id" value="{{ $registro->universidad_id }}"><p class="rounded-lg border border-borde bg-fondo px-3 py-3 text-sm">{{ $registro->universidad->nombre }}</p>@else<select id="universidad_id" name="universidad_id" required class="h-11 w-full rounded-lg border border-borde px-3"><option value="">Seleccionar institución</option>@foreach ($universidades as $u)<option value="{{ $u->id }}" @selected($valor('universidad_id', $universidades->count() === 1 ? $u->id : null) == $u->id)>{{ $u->nombre }}</option>@endforeach</select>@endif</div>
                @if ($tipo === 'asignaturas')
                <div class="panel-campo--corto"><label for="codigo" class="mb-1.5 block text-sm font-semibold">Código del ramo *</label><input placeholder="Ej. INF101" id="codigo" name="codigo" required maxlength="30" value="{{ $valor('codigo', $registro->codigo) }}" class="h-11 w-full rounded-lg border border-borde px-3"><p class="mt-1 text-xs text-texto-secundario">Único por institución. Se conserva si el ramo ya tiene registros vinculados.</p></div>
                @endif
                <div class="panel-campo--completo"><label for="nombre" class="mb-1.5 block text-sm font-semibold">Nombre *</label><input placeholder="{{ $tipo === 'asignaturas' ? 'Ej. Desarrollo de aplicaciones' : 'Ej. 2027-1' }}" id="nombre" name="nombre" required maxlength="{{ $tipo === 'asignaturas' ? 200 : 50 }}" value="{{ $valor('nombre', $registro->nombre) }}" class="h-11 w-full rounded-lg border border-borde px-3"></div>
                @if ($tipo === 'periodos')
                <div class="panel-campo--medio"><label for="anio" class="mb-1.5 block text-sm font-semibold">Año *</label><input placeholder="Ej. 2027" type="number" id="anio" name="anio" min="1900" max="2200" required value="{{ $valor('anio', $registro->anio) }}" class="h-11 w-full rounded-lg border border-borde px-3"></div>
                <div class="panel-campo--medio"><label for="numero_semestre" class="mb-1.5 block text-sm font-semibold">Semestre *</label><select id="numero_semestre" name="numero_semestre" required class="h-11 w-full rounded-lg border border-borde px-3"><option value="">Seleccionar semestre</option>@foreach ([1, 2] as $semestre)<option value="{{ $semestre }}" @selected($valor('numero_semestre', $registro->numero_semestre) == $semestre)>Semestre {{ $semestre }}</option>@endforeach</select></div>
                <div class="panel-campo--medio"><label for="fecha_inicio" class="mb-1.5 block text-sm font-semibold">Fecha de inicio</label><input type="date" id="fecha_inicio" name="fecha_inicio" value="{{ $valor('fecha_inicio', $registro->fecha_inicio?->format('Y-m-d')) }}" class="h-11 w-full rounded-lg border border-borde px-3"></div>
                <div class="panel-campo--medio"><label for="fecha_fin" class="mb-1.5 block text-sm font-semibold">Fecha de término</label><input type="date" id="fecha_fin" name="fecha_fin" value="{{ $valor('fecha_fin', $registro->fecha_fin?->format('Y-m-d')) }}" class="h-11 w-full rounded-lg border border-borde px-3"></div>
                @endif
            </div></x-seccion-formulario>
            @if ($tipo === 'asignaturas')
            <x-seccion-formulario titulo="Módulos de referencia" id="modulos-referencia"><p class="mb-4 text-sm text-texto-secundario">Cantidad de módulos por actividad. La carga efectiva y sus grupos se conservan en las secciones; guardar esta ficha no los recalcula.</p><div class="panel-campos">@foreach ($modulos as $campo => $etiqueta)<div class="panel-campo--corto"><label for="{{ $campo }}" class="mb-1.5 block text-sm font-semibold">{{ $etiqueta }} *</label><input placeholder="Ej. 2" type="number" id="{{ $campo }}" name="{{ $campo }}" min="0" max="255" required value="{{ $valor($campo, $registro->$campo) }}" class="h-11 w-full rounded-lg border border-borde px-3"></div>@endforeach</div></x-seccion-formulario>
            @endif
        </div><aside class="panel-columna">
            <x-seccion-formulario :titulo="$tipo === 'asignaturas' ? 'Créditos y estado' : 'Estado y conservación'" id="estado-oferta">
                <div class="panel-pareja">
                    @if ($tipo === 'asignaturas')
                        <div><label for="creditos" class="mb-1.5 block text-sm font-semibold">Créditos</label><input placeholder="Ej. 5" type="number" id="creditos" name="creditos" min="0" max="255" value="{{ $valor('creditos', $registro->creditos) }}" class="h-11 w-full rounded-lg border border-borde px-3"></div>
                    @endif
                    <div><label for="estado" class="mb-1.5 block text-sm font-semibold">Estado *</label><select id="estado" name="estado" required class="h-11 w-full rounded-lg border border-borde px-3"><option value="1" @selected((string) $valor('estado', (int) $registro->estado) === '1')>Activo</option><option value="0" @selected((string) $valor('estado', (int) $registro->estado) === '0')>{{ $tipo === 'periodos' ? 'Archivado' : 'Inactivo' }}</option></select></div>
                </div>
                <p class="mt-4 text-sm text-texto-secundario">{{ $tipo === 'asignaturas' ? 'Un ramo con secciones activas en períodos vigentes debe conservarse activo.' : 'Archivar retira el período de los flujos de edición. No elimina secciones, versiones ni publicaciones.' }}</p>
                @if ($tipo === 'periodos' && $registro->exists)
                @if ($referencias['utilizado'])<p class="mt-3 text-xs text-texto-secundario">Este período tiene información vinculada; su año y semestre deben conservarse.</p>@endif
                @if ($referencias['versiones_abiertas'] || $referencias['solicitudes_abiertas'])<p class="mt-3 rounded-lg bg-amber-50 p-3 text-sm text-amber-800">Hay programación semestral actual sin publicar o solicitudes pendientes. Resuélvelas antes de archivar.</p>@endif
                @if ($registro->estado)<label class="mt-4 flex items-start gap-3 rounded-lg border border-borde p-3 text-sm"><input type="checkbox" name="confirmar_archivo" value="1" @checked((string) $valor('confirmar_archivo') === '1') class="mt-1 h-4 w-4 accent-institucional"><span>Confirmo el archivo si cambio el estado a Archivado.</span></label>@endif
                @endif
                <p class="mt-3 text-xs text-texto-secundario">Las modificaciones quedan registradas en el historial.</p>
            </x-seccion-formulario>
            @if ($tipo === 'asignaturas')
            <x-seccion-formulario titulo="Características del ramo" id="caracteristicas-ramo"><div class="space-y-4"><div class="panel-pareja">@foreach (['modalidad' => 'Modalidad', 'categoria' => 'Categoría'] as $campo => $etiqueta)<div><label for="{{ $campo }}" class="mb-1.5 block text-sm font-semibold">{{ $etiqueta }}</label><input placeholder="{{ ['modalidad' => 'Ej. Presencial', 'categoria' => 'Ej. Obligatoria'][$campo] }}" id="{{ $campo }}" name="{{ $campo }}" maxlength="50" value="{{ $valor($campo, $registro->$campo) }}" class="h-11 w-full rounded-lg border border-borde px-3"></div>@endforeach
            </div>
            <div><label for="tipo_dictacion" class="mb-1.5 block text-sm font-semibold">Tipo de dictación</label><input placeholder="Ej. Semestral" id="tipo_dictacion" name="tipo_dictacion" maxlength="50" value="{{ $valor('tipo_dictacion', $registro->tipo_dictacion) }}" class="h-11 w-full rounded-lg border border-borde px-3"></div>
            <div class="panel-pareja">@foreach (['es_libre_eleccion' => 'Libre elección', 'ramo_terminal' => 'Ramo terminal'] as $campo => $etiqueta)<div><input type="hidden" name="{{ $campo }}" value="0"><label class="flex items-center gap-3 text-sm"><input type="checkbox" name="{{ $campo }}" value="1" @checked((string) $valor($campo, (int) $registro->$campo) === '1') class="h-4 w-4 accent-institucional">{{ $etiqueta }}</label></div>@endforeach</div></div></x-seccion-formulario>
            @endif
        </aside></div>
        <x-acciones-formulario :cancelar="route('administracion.oferta.index', $tipo)"><button class="boton boton-primario" type="submit">{{ $registro->exists ? 'Guardar cambios' : 'Crear '.$singular }}</button></x-acciones-formulario>
    </form>
</div>
@endsection

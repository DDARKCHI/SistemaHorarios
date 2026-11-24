@extends('layouts.app')
@section('title', ($registro->exists ? 'Editar ' : 'Crear ').$singular.' | '.$identidad['nombre'])
@section('content')
@php
    $valor = static function ($campo, $base = null) { $anterior = old($campo, $base); return is_scalar($anterior) || $anterior === null ? $anterior : $base; };
@endphp
<div class="panel-editor">
    <x-encabezado-pagina :titulo="($registro->exists ? 'Editar ' : 'Crear ').$singular">{{ $tipo === 'facultades' ? 'Identifica la unidad académica a la que pertenecen las carreras.' : ($tipo === 'planes' ? 'Registra la identificación y vigencia del plan de estudio de la carrera.' : 'Define el semestre de la carrera y conserva la continuidad de sus secciones.') }}</x-encabezado-pagina>
    @include('disponibilidad.avisos')
    <form method="post" action="{{ route('administracion.academicos.'.($registro->exists ? 'update' : 'store'), ['tipo' => $tipo, 'id' => $registro->id]) }}" class="panel-formulario">
        @csrf @if ($registro->exists) @method('PUT') @endif
        <div class="panel-layout"><div class="panel-columna">
            <x-seccion-formulario titulo="Datos del registro" id="datos-catalogo">
                <div class="panel-campos">
                    <div class="panel-campo--completo">
                        @if ($tipo === 'facultades')
                        <label for="universidad_id" class="mb-1.5 block text-sm font-semibold">Institución *</label>
                        @if ($registro->exists)<input type="hidden" name="universidad_id" value="{{ $registro->universidad_id }}"><p class="rounded-lg border border-borde bg-fondo px-3 py-3 text-sm">{{ $registro->universidad->nombre }}</p>
                        @else<select id="universidad_id" name="universidad_id" required class="h-11 w-full rounded-lg border border-borde px-3"><option value="">Seleccionar institución</option>@foreach ($universidades as $u)<option value="{{ $u->id }}" @selected($valor('universidad_id', $universidades->count() === 1 ? $u->id : null) == $u->id)>{{ $u->nombre }}</option>@endforeach</select>@endif
                        @else
                        <label for="carrera_id" class="mb-1.5 block text-sm font-semibold">Carrera *</label>
                        @if ($registro->exists)<input type="hidden" name="carrera_id" value="{{ $registro->carrera_id }}"><p class="rounded-lg border border-borde bg-fondo px-3 py-3 text-sm">{{ $registro->carrera->nombre }}{{ $registro->carrera->codigo ? ' · '.$registro->carrera->codigo : '' }} · {{ $registro->carrera->universidad->sigla }}</p>
                        @else<select id="carrera_id" name="carrera_id" required class="h-11 w-full rounded-lg border border-borde px-3"><option value="">Seleccionar carrera</option>@foreach ($carreras as $c)<option value="{{ $c->id }}" @selected($valor('carrera_id') == $c->id)>{{ $c->nombre }}{{ $c->codigo ? ' · '.$c->codigo : '' }} · {{ $c->universidad->sigla }}</option>@endforeach</select>@endif
                        @endif
                    </div>
                    @if ($tipo !== 'niveles')
                    <div class="panel-campo--corto"><label for="codigo" class="mb-1.5 block text-sm font-semibold">Código</label><input placeholder="{{ $tipo === 'facultades' ? 'Ej. IT' : 'Ej. INF-2027' }}" id="codigo" name="codigo" maxlength="{{ $tipo === 'facultades' ? 20 : 30 }}" value="{{ $valor('codigo', $registro->codigo) }}" class="h-11 w-full rounded-lg border border-borde px-3"><p class="mt-1 text-xs text-texto-secundario">Opcional. Debe ser único en {{ $tipo === 'facultades' ? 'la institución' : 'la carrera' }}.</p></div>
                    @else
                    <div class="panel-campo--corto"><label for="numero" class="mb-1.5 block text-sm font-semibold">Semestre de la carrera *</label><input placeholder="Ej. 2" type="number" id="numero" name="numero" min="1" max="255" required value="{{ $valor('numero', $registro->numero) }}" class="h-11 w-full rounded-lg border border-borde px-3"><p class="mt-1 text-xs text-texto-secundario">Corresponde al semestre registrado en las secciones.</p></div>
                    @endif
                    @if ($tipo === 'facultades')<div class="panel-campo--largo"><label for="tipo" class="mb-1.5 block text-sm font-semibold">Tipo de unidad *</label><input placeholder="Ej. Instituto" id="tipo" name="tipo" maxlength="30" required list="tipos-unidad" value="{{ $valor('tipo', $registro->tipo ?? 'Instituto') }}" class="h-11 w-full rounded-lg border border-borde px-3"><datalist id="tipos-unidad"><option value="Facultad"><option value="Instituto"><option value="Escuela"></datalist></div>@endif
                    @if ($tipo === 'planes')<div class="panel-campo--largo"><label for="version" class="mb-1.5 block text-sm font-semibold">Versión</label><input placeholder="Ej. 2027" id="version" name="version" maxlength="30" value="{{ $valor('version', $registro->version) }}" class="h-11 w-full rounded-lg border border-borde px-3"></div>@endif
                    <div class="{{ $tipo === 'niveles' ? 'panel-campo--largo' : 'panel-campo--completo' }}"><label for="nombre" class="mb-1.5 block text-sm font-semibold">Nombre *</label><input placeholder="{{ ['facultades' => 'Ej. Instituto Tecnológico', 'planes' => 'Ej. Plan de Informática', 'niveles' => 'Ej. Segundo semestre'][$tipo] }}" id="nombre" name="nombre" maxlength="{{ $tipo === 'niveles' ? 100 : 150 }}" required value="{{ $valor('nombre', $registro->nombre) }}" class="h-11 w-full rounded-lg border border-borde px-3"></div>
                </div>
            </x-seccion-formulario>
        </div><aside class="panel-columna"><x-seccion-formulario titulo="Estado y conservación" id="estado-catalogo">
            <div class="panel-pareja">
                @if ($tipo === 'planes')<div><label for="anio_inicio" class="mb-1.5 block text-sm font-semibold">Año de inicio</label><input placeholder="Ej. 2027" type="number" id="anio_inicio" name="anio_inicio" min="1900" max="2200" value="{{ $valor('anio_inicio', $registro->anio_inicio) }}" class="h-11 w-full rounded-lg border border-borde px-3"></div>@endif
                <div><label for="estado" class="mb-1.5 block text-sm font-semibold">Estado *</label><select id="estado" name="estado" required class="h-11 w-full rounded-lg border border-borde px-3"><option value="1" @selected((string) $valor('estado', (int) $registro->estado) === '1')>Activo</option><option value="0" @selected((string) $valor('estado', (int) $registro->estado) === '0')>Inactivo</option></select></div>
            </div>
            <p class="mt-4 text-sm text-texto-secundario">{{ $tipo === 'facultades' ? 'Una unidad con carreras activas debe conservarse activa.' : ($tipo === 'niveles' ? 'Si hay secciones registradas, el número del semestre se conserva. La actividad de un período vigente impide desactivarlo.' : 'Los planes se conservan para consulta. Una versión nueva se registra como otro plan de la misma carrera.') }}</p>
            <p class="mt-3 text-xs text-texto-secundario">Los cambios quedan registrados en el historial. {{ $tipo === 'facultades' ? 'La institución' : 'La carrera' }} no puede cambiarse al editar.</p>
        </x-seccion-formulario></aside></div>
        <x-acciones-formulario :cancelar="route('administracion.academicos.index', $tipo)"><button type="submit" class="boton boton-primario">{{ $registro->exists ? 'Guardar cambios' : 'Crear '.$singular }}</button></x-acciones-formulario>
    </form>
</div>
@endsection

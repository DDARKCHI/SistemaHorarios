@extends('layouts.app')
@section('title', ($profesor->exists ? 'Editar' : 'Registrar').' docente | '.$identidad['nombre'])
@section('content')
@php
    $valor = static function ($campo, $base = null) {
        $anterior = old($campo, $base);

        return is_scalar($anterior) || $anterior === null ? $anterior : $base;
    };
    $campo = 'h-11 w-full rounded-lg border border-borde bg-superficie px-3 focus:border-institucional focus:outline-none focus:ring-1 focus:ring-institucional';
@endphp
<div class="panel-editor">
    <x-encabezado-pagina :titulo="$profesor->exists ? 'Editar docente' : 'Registrar docente'">Gestiona sus datos para la disponibilidad y la asignación de clases.</x-encabezado-pagina>
    @include('disponibilidad.avisos')

    <form method="post" action="{{ $profesor->exists ? route('administracion.docentes.update', $profesor) : route('administracion.docentes.store') }}" class="panel-formulario">
        @csrf
        @if ($profesor->exists)
            @method('PUT')
            <input type="hidden" name="revision" value="{{ $valor('revision', \App\Http\Controllers\DocenteController::firma($profesor)) }}">
        @endif

        <div class="panel-layout">
            <x-seccion-formulario titulo="Identificación y contacto" id="identificacion-docente">
                <div class="panel-campos">
                    <div class="panel-campo--medio">
                        <label for="nombre" class="mb-1.5 block font-semibold">Nombres *</label>
                        <input placeholder="Ej. María Elena" id="nombre" name="nombre" maxlength="100" required value="{{ $valor('nombre', $profesor->nombre) }}" class="{{ $campo }}">
                    </div>
                    <div class="panel-campo--medio">
                        <label for="apellido" class="mb-1.5 block font-semibold">Apellidos *</label>
                        <input placeholder="Ej. González Pérez" id="apellido" name="apellido" maxlength="100" required value="{{ $valor('apellido', $profesor->apellido) }}" class="{{ $campo }}">
                    </div>
                    <div class="panel-campo--largo">
                        @if ($profesor->exists)
                            <p class="mb-1.5 text-sm font-semibold">Institución</p>
                            <input type="hidden" name="universidad_id" value="{{ $profesor->universidad_id }}">
                            <p class="flex min-h-11 items-center rounded-lg border border-borde bg-fondo px-3 py-2 text-sm">{{ $profesor->universidad->nombre }}</p>
                        @else
                            <label for="universidad_id" class="mb-1.5 block font-semibold">Institución *</label>
                            <select id="universidad_id" name="universidad_id" required class="{{ $campo }}">
                                <option value="">Seleccionar institución</option>
                                @foreach ($universidades as $universidad)
                                    <option value="{{ $universidad->id }}" @selected($valor('universidad_id', $universidades->count() === 1 ? $universidad->id : null) == $universidad->id)>
                                        {{ $universidad->nombre }}
                                    </option>
                                @endforeach
                            </select>
                        @endif
                    </div>
                    <div class="panel-campo--corto">
                        <label for="rut" class="mb-1.5 block font-semibold">RUT o identificador</label>
                        <input placeholder="Ej. 12345678-5" id="rut" name="rut" maxlength="12" value="{{ $valor('rut', $profesor->rut) }}" aria-describedby="identificador-docente" class="{{ $campo }}">
                    </div>
                    <div class="panel-campo--medio">
                        <label for="correo_institucional" class="mb-1.5 block font-semibold">Correo institucional</label>
                        <input placeholder="Ej. nombre@institucion.cl" type="email" id="correo_institucional" name="correo_institucional" maxlength="150" value="{{ $valor('correo_institucional', $profesor->correo_institucional) }}" aria-describedby="identificador-docente" class="{{ $campo }}">
                    </div>
                    <div class="panel-campo--medio">
                        <label for="correo_personal" class="mb-1.5 block font-semibold">Correo personal</label>
                        <input placeholder="Ej. nombre@correo.cl" type="email" id="correo_personal" name="correo_personal" maxlength="150" value="{{ $valor('correo_personal', $profesor->correo_personal) }}" class="{{ $campo }}">
                    </div>
                </div>
                <p id="identificador-docente" class="mt-2 text-xs text-texto-secundario">{{ $profesor->exists ? 'Al corregir el RUT, se actualizan su acceso y los registros vinculados al docente.' : 'Ingresa un RUT, un identificador institucional o un correo institucional.' }}</p>
            </x-seccion-formulario>

            <x-seccion-formulario titulo="Vinculación académica y estado" id="vinculacion-docente">
                <div class="space-y-4">
                    <div>
                        <label for="unidad_academica" class="mb-1.5 block font-semibold">Unidad académica</label>
                        <input placeholder="Ej. Instituto Tecnológico" id="unidad_academica" name="unidad_academica" maxlength="150" value="{{ $valor('unidad_academica', $profesor->unidad_academica) }}" class="{{ $campo }}">
                    </div>
                    <div class="panel-pareja">
                        @foreach (['tipo_contrato' => 'Tipo de contrato', 'tipo_profesor' => 'Tipo de docente'] as $nombre => $etiqueta)
                            <div>
                                <label for="{{ $nombre }}" class="mb-1.5 block font-semibold">{{ $etiqueta }}</label>
                                <input placeholder="{{ $nombre === 'tipo_contrato' ? 'Ej. Planta' : 'Ej. Jornada parcial' }}" id="{{ $nombre }}" name="{{ $nombre }}" maxlength="50" value="{{ $valor($nombre, $profesor->$nombre) }}" class="{{ $campo }}">
                            </div>
                        @endforeach
                    </div>
                    <div>
                        <label for="estado" class="mb-1.5 block font-semibold">Estado *</label>
                        <select id="estado" name="estado" class="{{ $campo }}">
                            <option value="1" @selected((string) $valor('estado', (int) $profesor->estado) === '1')>Activo</option>
                            <option value="0" @selected((string) $valor('estado', (int) $profesor->estado) === '0')>Inactivo</option>
                        </select>
                    </div>
                </div>
                @if ($profesor->exists)
                    <p class="panel-nota">Antes de desactivar al docente, resuelve sus clases actuales y solicitudes pendientes.</p>
                @endif
            </x-seccion-formulario>
        </div>

        <x-acciones-formulario :cancelar="route('administracion.docentes.index')">
            <button type="submit" class="boton boton-primario">{{ $profesor->exists ? 'Guardar cambios' : 'Registrar docente' }}</button>
        </x-acciones-formulario>
    </form>
</div>
@endsection

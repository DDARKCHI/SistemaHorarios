@extends('layouts.app')
@section('title', 'Configuración institucional | '.$identidad['nombre'])
@section('content')
@php
    $valor = static function ($campo, $base = null) { $anterior = old($campo, $base); return is_scalar($anterior) || $anterior === null ? $anterior : $base; };
@endphp
<div class="panel-editor">
    <x-encabezado-pagina titulo="Configuración institucional">Administra la identificación de la institución y el calendario compartido de {{ $identidad['nombre'] }}.<x-slot:acciones><div class="flex flex-wrap gap-3"><a class="boton boton-secundario" href="{{ route('configuracion.calendario') }}">Calendario académico</a>@can('administrar usuarios')<a class="boton boton-primario" href="{{ route('configuracion.index', ['nueva' => 1]) }}">Nueva institución</a>@endcan</div></x-slot:acciones></x-encabezado-pagina>
    @include('disponibilidad.avisos')
    @if ($universidades->isNotEmpty())<form method="get" class="flex flex-col gap-3 rounded-xl border border-borde bg-superficie p-5 sm:flex-row sm:items-end"><div class="min-w-0 flex-1"><label for="universidad_id" class="mb-2 block text-sm font-semibold">Institución a consultar</label><select id="universidad_id" name="universidad_id" class="h-11 w-full rounded-lg border border-borde px-3">@foreach ($universidades as $u)<option value="{{ $u->id }}" @selected($institucion?->id == $u->id)>{{ $u->sigla }} · {{ $u->nombre }}{{ $u->estado ? '' : ' · Inactiva' }}</option>@endforeach</select></div><button class="boton boton-secundario">Consultar</button></form>@endif
    @if ($institucion)
    <form
        method="post"
        action="{{ $institucion->exists ? route('configuracion.institucion.update', $institucion->id) : route('configuracion.institucion.store') }}"
        class="space-y-5"
        @can('administrar usuarios')
            data-confirmar="Se guardarán los datos institucionales que se muestran en la plataforma."
            data-confirmar-titulo="{{ $institucion->exists ? 'Guardar datos institucionales' : 'Crear institución' }}"
            data-confirmar-accion="{{ $institucion->exists ? 'Guardar cambios' : 'Crear institución' }}"
        @endcan
    >
        @csrf
        @if ($institucion->exists) @method('PUT') @endif
        <div class="panel-layout"><div class="panel-columna"><x-seccion-formulario :titulo="$institucion->exists ? 'Datos de la institución' : 'Nueva institución'" id="identidad-institucion"><fieldset @disabled(! auth()->user()->can('administrar usuarios')) class="space-y-4"><div><label for="nombre" class="mb-2 block text-sm font-semibold">Nombre institucional *</label><input placeholder="Ej. Universidad Católica de la Santísima Concepción" id="nombre" name="nombre" maxlength="150" required value="{{ $valor('nombre', $institucion->nombre) }}" class="h-11 w-full rounded-lg border border-borde px-3"></div><div><label for="sigla" class="mb-2 block text-sm font-semibold">Sigla *</label><input placeholder="Ej. UCSC" id="sigla" name="sigla" maxlength="20" required value="{{ $valor('sigla', $institucion->sigla) }}" class="h-11 w-full rounded-lg border border-borde px-3"></div></fieldset></x-seccion-formulario></div>
        <aside class="panel-columna"><x-seccion-formulario titulo="Identidad visual" id="identidad-plataforma"><img src="{{ $identidad['principal_url'] }}" alt="{{ $identidad['nombre'] }}" class="max-h-20 max-w-full object-contain"><p class="mt-4 text-sm text-texto-secundario">Personaliza el nombre, los colores, los logos y el favicon para tu institución.</p><a class="boton boton-secundario mt-4" href="{{ route('configuracion.identidad', ['universidad_id' => $institucion->id]) }}">Personalizar identidad</a></x-seccion-formulario></aside></div>
        @can('administrar usuarios')<x-acciones-formulario :cancelar="route('configuracion.index')"><button class="boton boton-primario">{{ $institucion->exists ? 'Guardar datos institucionales' : 'Crear institución' }}</button></x-acciones-formulario>@else<p class="text-sm text-texto-secundario">Tu perfil permite consultar esta configuración. La edición requiere permisos de administración.</p>@endcan
    </form>
    @else<p class="rounded-xl border border-borde bg-superficie p-6 text-sm text-texto-secundario">No hay instituciones registradas.</p>@endif
</div>
@endsection

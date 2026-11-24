@extends('layouts.app')
@section('content')
@php
    $seleccion = old('_enviado') ? old('permisos', []) : ($perfil?->permissions->pluck('name')->all() ?? ['ver panel inicio', 'ver programacion semestral', 'ver programacion anterior']);
    $adicionales = $perfil?->permissions->whereNotIn('name', \App\Support\PermisosSistema::NOMBRES) ?? collect();
@endphp
<div class="panel-editor">
<x-encabezado-pagina :titulo="$protegido ? 'Permisos de Administrador' : ($perfil ? 'Editar perfil' : 'Crear perfil')">
    {{ $protegido ? 'Perfil central de administración. Sus permisos están protegidos.' : 'Selecciona las funciones del perfil y revisa su alcance antes de guardar.' }}
    <x-slot:acciones><a href="{{ route('administracion.perfiles.index') }}" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-borde bg-white px-4 py-2 text-sm font-semibold focus-visible:outline-institucional">Volver a perfiles</a></x-slot:acciones>
</x-encabezado-pagina>
@if($errors->any())
<div role="alert" class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800"><p class="font-semibold">Revisa los datos del perfil</p><ul class="mt-2 list-inside list-disc space-y-1">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
@endif
<form method="POST" action="{{ $perfil ? route('administracion.perfiles.update', $perfil) : route('administracion.perfiles.store') }}" class="panel-formulario" data-confirmar="Los permisos seleccionados se aplicarán a las cuentas que utilicen este perfil." data-confirmar-titulo="Guardar perfil y permisos" data-confirmar-accion="Guardar perfil">
@csrf
@if($perfil) @method('PUT') <input type="hidden" name="firma" value="{{ old('firma', $firma) }}"> @endif
<input type="hidden" name="_enviado" value="1">
<div class="panel-layout">
    <div class="panel-columna">
        <section class="panel-seccion">
            <h2 class="panel-titulo">Identificación</h2>
            <label for="nombre-perfil" class="mb-1.5 block text-sm font-semibold">Nombre del perfil <span aria-hidden="true">*</span></label>
            <input placeholder="Ej. Coordinación académica" id="nombre-perfil" name="name" type="text" maxlength="80" required value="{{ old('name', $perfil?->name) }}" @readonly($protegido) class="min-h-11 w-full rounded-lg border border-borde bg-white px-3 py-2 text-sm focus:border-institucional focus:outline-none focus:ring-1 focus:ring-institucional">
            <p class="mt-2 text-xs text-texto-secundario">Por ejemplo: Consulta académica o Coordinación de horarios.</p>
        </section>
        <section class="panel-seccion">
            <h2 class="text-sm font-semibold">Funciones permitidas <span aria-hidden="true">*</span></h2>
            <p class="mt-1 text-sm text-texto-secundario">Las funciones de programación requieren su consulta correspondiente. Importar Excel se autoriza por separado.</p>
            <div class="mt-5 space-y-6">
            @foreach($grupos as $grupo => $permisos)
                <fieldset @disabled($protegido)>
                    <legend class="mb-3 w-full border-b border-borde pb-2 text-sm font-semibold">{{ $grupo }}</legend>
                    <div class="grid gap-3 @min-[28rem]/seccion:grid-cols-2">
                    @foreach($permisos as $permiso => [$etiqueta, $detalle])
                        <label class="flex min-w-0 cursor-pointer items-start gap-3 rounded-lg border border-borde p-3 transition hover:bg-gray-50 focus-within:border-institucional">
                            <input type="checkbox" name="permisos[]" value="{{ $permiso }}" @checked(in_array($permiso, $seleccion, true)) class="mt-0.5 h-4 w-4 shrink-0 rounded border-gray-300 text-institucional accent-institucional focus:ring-institucional">
                            <span class="min-w-0"><span class="block text-sm font-semibold">{{ $etiqueta }}</span><span class="mt-1 block text-xs leading-5 text-texto-secundario">{{ $detalle }}</span></span>
                        </label>
                    @endforeach
                    </div>
                </fieldset>
            @endforeach
            </div>
            @if($adicionales->isNotEmpty())<div class="mt-5 rounded-lg bg-gray-50 p-4 text-sm text-texto-secundario"><h3 class="font-semibold">Permisos adicionales conservados</h3><ul class="mt-2 list-inside list-disc">@foreach($adicionales as $p)<li>{{ $p->name }}</li>@endforeach</ul></div>@endif
        </section>
    </div>
    <aside class="panel-columna">
        <section class="panel-seccion">
            <h2 class="text-sm font-semibold">Alcance del acceso</h2>
            <p class="mt-3 text-sm leading-6 text-texto-secundario">Los permisos abarcan todas las carreras de la institución. Un perfil llamado Jefatura de carrera también tendrá este alcance.</p>
            <p class="mt-3 text-sm leading-6 text-texto-secundario">El permiso de cuentas, perfiles y catálogos permite administrar los accesos de otras personas y toda la oferta académica.</p>
        </section>
        <section class="panel-seccion">
            <h2 class="text-sm font-semibold">{{ $cuentas->count() }} {{ $cuentas->count() === 1 ? 'cuenta vinculada' : 'cuentas vinculadas' }}</h2>
            @if($cuentas->isEmpty())<p class="mt-3 text-sm text-texto-secundario">Puedes asignar el perfil al crear o editar una cuenta.</p>
            @else
                <p class="mt-2 text-sm text-texto-secundario">Los cambios se aplican también a las cuentas que ya utilizan este perfil.</p>
                <ul class="mt-4 space-y-2 text-sm">@foreach($cuentas->take(10) as $cuenta)<li class="break-words"><a href="{{ route('usuarios.edit', $cuenta) }}" class="underline decoration-gray-300 underline-offset-4 hover:text-institucional focus-visible:outline-institucional">{{ $cuenta->name }}</a></li>@endforeach</ul>
                @if($cuentas->count() > 10)<p class="mt-3 text-xs text-texto-secundario">Y {{ $cuentas->count() - 10 }} cuentas más. Consulta el listado de Administración.</p>@endif
            @endif
        </section>
    </aside>
</div>
@unless($protegido)
<div class="space-y-4">
    @if($cuentas->isNotEmpty())<label class="panel-contexto flex items-start gap-3 text-sm"><input type="checkbox" name="confirmar" data-consentimiento-confirmacion value="1" required @checked(old('confirmar')) class="mt-0.5 h-4 w-4 rounded border-gray-300 text-institucional accent-institucional focus:ring-institucional"><span>He revisado los permisos y su efecto sobre las {{ $cuentas->count() }} cuentas vinculadas.</span></label>@endif
    <x-acciones-formulario :cancelar="route('administracion.perfiles.index')">
        <button type="submit" class="boton boton-primario">{{ $perfil ? 'Guardar cambios' : 'Crear perfil' }}</button>
    </x-acciones-formulario>
</div>
@endunless
</form>
</div>
@endsection

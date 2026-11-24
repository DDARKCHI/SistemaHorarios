@extends('layouts.app')
@section('title', 'Cambiar contraseña | '.$identidad['nombre'])
@section('content')
<x-encabezado-pagina titulo="Cambiar contraseña">Actualiza la contraseña de tu cuenta. Al guardar deberás iniciar sesión nuevamente.</x-encabezado-pagina>
@if (auth()->user()->debe_cambiar_password)
    <div role="status" class="mb-5 rounded-xl border border-sky-100 bg-sky-50 p-4 text-sm text-sky-800">
        <p class="font-semibold">Activa tu acceso docente</p>
        <p class="mt-1">Estás usando una contraseña temporal. Crea una contraseña personal para continuar y registrar tu disponibilidad horaria.</p>
    </div>
@endif
<form method="post" action="{{ route('cuenta.password.update') }}" class="grid items-start gap-5 lg:grid-cols-[minmax(0,1.5fr)_minmax(0,1fr)]" data-confirmar="Al guardar la contraseña, tendrás que volver a ingresar con la nueva." data-confirmar-titulo="Cambiar contraseña" data-confirmar-accion="Guardar contraseña">@csrf @method('PUT')
<section class="rounded-xl border border-borde bg-white p-5 shadow-sm sm:p-6"><h2 class="mb-5 font-semibold">Seguridad de tu cuenta</h2>
@if($errors->any())<ul role="alert" class="mb-5 list-inside list-disc space-y-1 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>@endif
@foreach(['current_password' => ['Contraseña actual', 'current-password'], 'password' => ['Nueva contraseña', 'new-password'], 'password_confirmation' => ['Confirmar nueva contraseña', 'new-password']] as $campo => [$etiqueta, $autocomplete])
<div class="mb-4"><label for="{{ $campo }}" class="mb-2 block font-semibold">{{ $etiqueta }} *</label><input id="{{ $campo }}" name="{{ $campo }}" type="password" autocomplete="{{ $autocomplete }}" required class="h-11 w-full rounded-lg border border-borde px-3 focus:border-institucional focus:outline-none focus:ring-1 focus:ring-institucional"></div>
@endforeach
<div class="mt-6 flex flex-col-reverse gap-3 border-t border-borde pt-5 sm:flex-row sm:justify-end">@unless(auth()->user()->debe_cambiar_password)<a href="{{ route(\App\Support\PermisosSistema::entrada(auth()->user()) ?? 'login') }}" class="boton boton-secundario">Cancelar</a>@endunless<button class="boton boton-primario">Guardar contraseña</button></div></section>
<aside class="rounded-xl border border-borde bg-white p-5 shadow-sm sm:p-6"><h2 class="font-semibold">Una contraseña segura</h2><p class="mt-3 text-sm leading-6 text-texto-secundario">Usa al menos 12 caracteres, con letras y números. Elige una contraseña diferente de la actual y evita compartirla.</p><p class="mt-4 text-xs text-texto-secundario">Los campos con * son obligatorios.</p></aside></form>
@endsection

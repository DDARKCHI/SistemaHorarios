@extends('layouts.app')
@section('content')
<x-encabezado-pagina titulo="Perfiles y permisos">
    Define las funciones que cada perfil puede utilizar en {{ $identidad['nombre'] }}.
    <x-slot:acciones><div class="flex flex-wrap gap-2">
        <a href="{{ route('administracion.index') }}" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-borde bg-white px-4 py-2 text-sm font-semibold focus-visible:outline-institucional">Administración</a>
        <a href="{{ route('administracion.perfiles.create') }}" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-institucional px-4 py-2 text-sm font-semibold text-white hover:opacity-90 focus-visible:outline-institucional">Crear perfil</a>
    </div></x-slot:acciones>
</x-encabezado-pagina>
@if(session('success'))<div role="status" class="mb-5 rounded-lg border border-green-200 bg-green-50 p-4 text-sm text-green-800">{{ session('success') }}</div>@endif
<div class="mb-5 rounded-xl border border-borde bg-white p-4 text-sm text-texto-secundario">
    <strong class="text-texto">Alcance institucional.</strong> Estos perfiles permiten acceder a datos de todas las carreras. El nombre del perfil no limita el acceso a una carrera ni lo vincula a la ficha de un docente.
</div>
<div class="grid min-w-0 gap-4 md:grid-cols-2 2xl:grid-cols-3">
@forelse($perfiles as $perfil)
    @php($protegido = app(\App\Services\GestionPerfiles::class)->protegido($perfil))
    <article class="flex min-w-0 flex-col rounded-xl border border-borde bg-white p-5 shadow-sm">
        <div class="flex flex-wrap items-start justify-between gap-2"><h2 class="break-words text-lg font-semibold">{{ $perfil->name }}</h2>
            @if($protegido)<span class="rounded-full bg-gray-100 px-2.5 py-1 text-xs font-semibold text-texto-secundario">Protegido</span>@endif
        </div>
        <p class="mt-2 text-sm text-texto-secundario">{{ $perfil->users_count }} {{ $perfil->users_count === 1 ? 'cuenta' : 'cuentas' }} · {{ $perfil->permissions->count() }} {{ $perfil->permissions->count() === 1 ? 'permiso' : 'permisos' }}</p>
        <ul class="my-4 flex-1 space-y-1.5 text-sm text-texto-secundario">
            @php($etiquetas = collect(\App\Support\PermisosSistema::grupos())->collapse())
            @forelse($perfil->permissions->sortBy('name')->take(5) as $permiso)
                <li class="flex gap-2"><span aria-hidden="true" class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-institucional"></span><span>{{ $etiquetas[$permiso->name][0] ?? $permiso->name }}</span></li>
            @empty <li>Sin permisos de acceso.</li> @endforelse
            @if($perfil->permissions->count() > 5)<li class="pt-1 text-xs">Y {{ $perfil->permissions->count() - 5 }} permisos más</li>@endif
        </ul>
        <a href="{{ route('administracion.perfiles.edit', $perfil) }}" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-borde px-4 py-2 text-sm font-semibold hover:border-institucional hover:text-institucional focus-visible:outline-institucional">{{ $protegido ? 'Consultar permisos' : 'Revisar y editar' }}<span class="sr-only"> de {{ $perfil->name }}</span></a>
    </article>
@empty
    <p class="rounded-xl border border-borde bg-white p-6 text-sm text-texto-secundario md:col-span-2">Todavía no hay perfiles. Crea uno para asignarlo a las cuentas.</p>
@endforelse
</div>
@endsection

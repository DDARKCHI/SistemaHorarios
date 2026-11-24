@extends('layouts.app')
@section('title', 'Activar versión | '.$identidad['nombre'])
@section('content')
<div class="space-y-6">
    <x-encabezado-pagina titulo="Activar versión semestral">Revisa el borrador antes de utilizarlo como programación actual.</x-encabezado-pagina>
    @include('disponibilidad.avisos')
    <section class="grid gap-4 md:grid-cols-2">
        <div class="rounded-xl border border-borde bg-superficie p-5 shadow-sm"><p class="text-sm text-texto-secundario">Versión seleccionada</p><h2 class="mt-2 text-lg font-semibold">{{ $version->nombre }} · V{{ $version->version }}</h2><p class="mt-2 text-sm text-texto-secundario">{{ $version->periodoAcademico->nombre }} · {{ $version->estado }}</p></div>
        <div class="rounded-xl border border-borde bg-superficie p-5 shadow-sm"><p class="text-sm text-texto-secundario">Programación actual del período</p>@forelse ($actuales as $actual)<p class="mt-2 font-semibold">{{ $actual->nombre }} · V{{ $actual->version }}</p>@empty<p class="mt-2 font-semibold">Sin versión actual</p>@endforelse<p class="mt-2 text-sm text-texto-secundario">Al activar otra versión, esta seguirá disponible para consulta y recuperación.</p></div>
    </section>
    <section class="grid gap-4 sm:grid-cols-3" aria-label="Resultado de la revisión">
        @foreach (['Módulos activos' => $reporte['modulos'], 'Módulos requeridos' => $reporte['carga']['requeridos'], 'Topes horarios' => $reporte['conflictos']] as $titulo => $valor)<div class="rounded-xl border border-borde bg-superficie p-5 shadow-sm"><p class="text-sm text-texto-secundario">{{ $titulo }}</p><p class="mt-2 text-2xl font-bold">{{ number_format($valor, 0, ',', '.') }}</p></div>@endforeach
    </section>
    <section class="space-y-4 rounded-xl border border-borde bg-superficie p-5 shadow-sm">
        <h2 class="font-semibold">{{ $bloqueos ? 'Revisa las observaciones antes de activar' : 'El borrador supera las comprobaciones actuales' }}</h2>
        @if ($bloqueos)<ul class="list-inside list-disc space-y-2 text-sm text-red-800">@foreach ($bloqueos as $bloqueo)<li>{{ $bloqueo }}</li>@endforeach</ul>@endif
        <p class="text-sm text-texto-secundario">La activación cambia la versión utilizada por defecto y habilita su gestión como borrador actual. Conserva los horarios de las demás versiones. No publica el horario ni envía notificaciones.</p>
        @can('gestionar conflictos')<a class="boton boton-secundario" href="{{ route('programacion.validacion', ['periodo_id' => $version->periodo_academico_id, 'version_id' => $version->id]) }}">Ver revisión detallada</a>@endcan
    </section>
    <form method="post" action="{{ route('programacion.version.activar', $version) }}" class="space-y-4 rounded-xl border border-borde bg-superficie p-5 shadow-sm" data-confirmar="La versión V{{ $version->version }} quedará como programación actual de {{ $version->periodoAcademico->nombre }}." data-confirmar-titulo="Activar borrador" data-confirmar-accion="Activar versión">
        @csrf<input type="hidden" name="firma" value="{{ $firma }}">
        @if (!$bloqueos)<label class="flex items-start gap-3 text-sm"><input type="checkbox" name="confirmar" data-consentimiento-confirmacion value="1" required class="mt-0.5 h-4 w-4 accent-institucional">Confirmo que V{{ $version->version }} debe ser la programación actual de {{ $version->periodoAcademico->nombre }}.</label>@endif
        <div class="flex flex-wrap justify-between gap-3"><a class="boton boton-secundario" href="{{ route('programacion.semestral', ['periodo_id' => $version->periodo_academico_id, 'version_id' => $version->id]) }}">Volver a programación</a><button @disabled(count($bloqueos) > 0) class="h-11 rounded-lg bg-institucional px-5 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-50">Activar esta versión</button></div>
    </form>
</div>
@endsection

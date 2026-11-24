@extends('layouts.app')
@section('title', 'Aprobación y publicación | '.$identidad['nombre'])
@section('content')
<div class="space-y-6">
    <x-encabezado-pagina titulo="Aprobación y publicación">{{ $version->nombre }} · V{{ $version->version }} · {{ $version->periodoAcademico->nombre }}</x-encabezado-pagina>
    @include('disponibilidad.avisos')
    <ol class="grid gap-4 sm:grid-cols-3" aria-label="Etapas del horario">
        @foreach (['Borrador' => 'Preparación y ajustes', 'Aprobado' => 'Revisión global completada', 'Publicado' => 'Copia disponible para consulta'] as $estado => $descripcion)<li class="rounded-xl border p-5 shadow-sm {{ $version->estado === $estado ? 'border-institucional bg-red-50' : 'border-borde bg-superficie' }}"><p class="font-semibold">{{ $estado }}</p><p class="mt-1 text-sm text-texto-secundario">{{ $descripcion }}</p></li>@endforeach
    </ol>
    <section class="space-y-3 rounded-xl border border-borde bg-superficie p-5 shadow-sm"><h2 class="font-semibold">Revisión actual</h2><p class="text-sm text-texto-secundario">{{ $reporte['modulos'] }} módulos activos · {{ $reporte['carga']['requeridos'] }} requeridos · {{ $reporte['conflictos'] }} topes.</p>
        @if ($version->estado === 'Publicado')<p class="text-sm">Este horario ya está publicado. Su copia de consulta conserva los datos del momento de publicación.</p><a class="boton boton-primario" href="{{ route('programacion.version.publicada', $version) }}">Consultar horario publicado</a>
        @elseif ($bloqueos)<ul class="list-inside list-disc space-y-2 text-sm text-red-800">@foreach ($bloqueos as $bloqueo)<li>{{ $bloqueo }}</li>@endforeach</ul>
        @else<p class="text-sm">La versión supera las comprobaciones de carga, asignaciones, disponibilidad, capacidad y recursos.</p>@endif
        @can('gestionar conflictos')<a class="boton boton-secundario" href="{{ route('programacion.validacion', ['periodo_id' => $version->periodo_academico_id, 'version_id' => $version->id]) }}">Ver revisión detallada</a>@endcan
    </section>
    @if ($version->estado !== 'Publicado')
    <form method="post" action="{{ route('programacion.version.cerrar', $version) }}" class="space-y-4 rounded-xl border border-borde bg-superficie p-5 shadow-sm" data-confirmar="{{ $etapa === 'aprobar' ? 'Los horarios de V'.$version->version.' quedarán cerrados a modificaciones.' : 'La copia de V'.$version->version.' quedará disponible para los usuarios autorizados.' }}" data-confirmar-titulo="{{ $etapa === 'aprobar' ? 'Aprobar horario' : 'Publicar horario' }}" data-confirmar-accion="{{ $etapa === 'aprobar' ? 'Aprobar' : 'Publicar' }}">@csrf<input type="hidden" name="firma" value="{{ $firma }}"><input type="hidden" name="etapa" value="{{ $etapa }}">
        <h2 class="font-semibold">{{ $etapa === 'aprobar' ? 'Aprobar horario' : 'Publicar horario' }}</h2>
        <p class="text-sm text-texto-secundario">{{ $etapa === 'aprobar' ? 'Al aprobar, los horarios y sus docentes quedan cerrados a modificaciones. Para realizar ajustes posteriores, crea una nueva versión.' : 'La publicación conserva una copia del horario para consulta por los usuarios autorizados de '.$identidad['nombre'].', aunque cambien los catálogos después.' }}</p><p class="text-xs text-texto-secundario">No se enviarán correos ni se dará acceso público sin iniciar sesión.</p>
        @if (!$bloqueos)<label class="flex items-start gap-3 text-sm"><input class="mt-0.5 h-4 w-4 accent-institucional" type="checkbox" name="confirmar" data-consentimiento-confirmacion value="1" required>Confirmo que deseo {{ $etapa }} la versión V{{ $version->version }}.</label>@endif
        <div class="flex flex-wrap justify-end gap-3"><button @disabled(count($bloqueos) > 0) class="h-11 rounded-lg bg-institucional px-5 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-50">{{ $etapa === 'aprobar' ? 'Aprobar horario' : 'Publicar horario' }}</button></div>
    </form>
    @endif
    <section class="rounded-xl border border-borde bg-superficie p-5 shadow-sm"><h2 class="font-semibold">Registro de cierre</h2>@forelse ($cierres as $cierre)<div class="mt-3 flex flex-wrap justify-between gap-2 border-t border-borde pt-3 text-sm"><p class="font-medium">{{ $cierre->etapa }} · {{ $cierre->usuario?->name ?? 'Usuario no disponible' }}</p><p class="text-texto-secundario">{{ $cierre->created_at->format('d/m/Y H:i') }}</p></div>@empty<p class="mt-3 text-sm text-texto-secundario">Todavía no hay aprobaciones ni publicaciones para esta versión.</p>@endforelse</section>
    <a class="boton boton-secundario" href="{{ route('programacion.semestral', ['periodo_id' => $version->periodo_academico_id, 'version_id' => $version->id]) }}">Volver a programación</a>
</div>
@endsection

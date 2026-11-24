@extends('layouts.app')
@section('title', 'Revisar disponibilidad | '.$identidad['nombre'])
@section('content')
<div class="panel-editor">
    <x-encabezado-pagina titulo="Solicitud de disponibilidad">
        {{ $solicitud->profesor->apellido }}, {{ $solicitud->profesor->nombre }} · {{ $solicitud->periodoAcademico->nombre }}
        <x-slot:acciones>
            <a href="{{ route('disponibilidad.index', ['periodo_id' => $solicitud->periodo_academico_id]) }}" class="boton boton-secundario">Volver a disponibilidad</a>
        </x-slot:acciones>
    </x-encabezado-pagina>
    @include('disponibilidad.avisos')
    <section class="min-w-0 space-y-4 rounded-xl border border-borde bg-superficie p-4 shadow-sm sm:p-5">
        @include('disponibilidad.contenido-solicitud')
    </section>
</div>
@endsection

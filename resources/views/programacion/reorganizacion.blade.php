@extends('layouts.app')
@section('title', 'Propuesta automática | '.$identidad['nombre'])
@section('content')
<div class="space-y-6">
    <x-encabezado-pagina titulo="Propuesta automática de horarios">Reorganización global a partir de {{ $version->nombre }} · V{{ $version->version }}.</x-encabezado-pagina>
    @include('disponibilidad.avisos')
    <section class="grid gap-4 sm:grid-cols-3" aria-label="Resumen de la propuesta">
        @foreach (['Módulos comprobados' => $total, 'Módulos que cambian' => $cambios, 'Topes en la propuesta' => 0] as $titulo => $valor)<div class="rounded-xl border border-borde bg-superficie p-5 shadow-sm"><p class="text-sm text-texto-secundario">{{ $titulo }}</p><p class="mt-2 text-2xl font-bold">{{ number_format($valor, 0, ',', '.') }}</p></div>@endforeach
    </section>
    <section class="space-y-2 rounded-xl border border-borde bg-superficie p-4 shadow-sm sm:p-5">
        <h2 class="font-semibold">Revisa antes de guardar</h2>
        <p class="text-sm text-texto-secundario">La propuesta reorganiza las clases de la versión base según la disponibilidad aprobada. Al guardar se crea un borrador independiente con los mismos docentes, espacios, equipos y duración por clase.</p>
        <p class="text-sm text-texto-secundario">La propuesta vence en 30 minutos. Si cambian los datos, genera una nueva.</p>
    </section>
    <section class="overflow-hidden rounded-xl border border-borde bg-superficie shadow-sm"><h2 class="border-b border-borde p-5 font-semibold">Distribución propuesta</h2><div class="listado-adaptable overflow-x-auto"><table role="table" class="w-full min-w-[850px] text-left text-sm"><thead role="rowgroup" class="bg-fondo text-xs uppercase text-texto-secundario"><tr role="row"><th role="columnheader" scope="col" class="p-4">Clase y docentes</th><th role="columnheader" scope="col" class="p-4">Horario base</th><th role="columnheader" scope="col" class="p-4">Horario propuesto</th></tr></thead><tbody role="rowgroup" class="divide-y divide-borde">
        @foreach ($horarios as $h)<tr role="row"><td role="cell" data-principal-listado class="p-4"><p class="font-semibold">{{ $h['curso'] }}</p><p class="mt-1 text-xs text-texto-secundario">Sección {{ $h['seccion'] }} · {{ $h['actividad'] }} · {{ $h['grupo'] }}</p><p class="mt-1 text-xs text-texto-secundario">{{ $h['docentes'] }}</p></td><td role="cell" class="p-4"><span class="etiqueta-listado" aria-hidden="true">Horario base</span><div class="dato-listado">{{ $h['antes'] }}</div></td><td role="cell" class="p-4 {{ $h['cambio'] ? 'bg-sky-50' : '' }}"><span class="etiqueta-listado" aria-hidden="true">Horario propuesto</span><div class="dato-listado"><p class="font-medium">{{ $h['despues'] }}</p><span class="mt-1 inline-block text-xs text-texto-secundario">{{ $h['cambio'] ? 'Cambia de ubicación horaria' : 'Conserva su horario' }}</span></div></td></tr>@endforeach
    </tbody></table></div><div class="border-t border-borde p-4">{{ $horarios->links() }}</div></section>
    <div class="flex flex-col gap-3 rounded-xl border border-borde bg-superficie p-4 shadow-sm sm:flex-row sm:items-center sm:justify-between sm:p-5">
        <a href="{{ route('programacion.preparacion', ['periodo_id' => $version->periodo_academico_id, 'version_id' => $version->id]) }}" class="boton boton-secundario">Volver al diagnóstico</a>
        <form method="post" action="{{ route('programacion.reorganizacion.guardar', $token) }}" data-confirmar="La propuesta se guardará como un nuevo borrador de programación." data-confirmar-titulo="Guardar propuesta de reorganización" data-confirmar-accion="Guardar borrador">
            @csrf
            <button class="boton boton-primario w-full sm:w-auto">Guardar como borrador nuevo</button>
        </form>
    </div>
</div>
@endsection

@extends('layouts.app')
@section('title', 'Manual de usuario | '.$identidad['nombre'])
@section('content')
<div data-manual-usuario data-vista-inicial="{{ $vista }}">
<x-encabezado-pagina titulo="Manual de usuario">
    <x-slot:acciones><a class="boton boton-primario" href="{{ route('ayuda.pdf') }}">Descargar PDF completo</a></x-slot:acciones>
</x-encabezado-pagina>
<nav aria-label="Vistas del manual" class="mb-5 flex flex-wrap gap-2">
    <a data-vista-manual="guia" href="{{ route('ayuda.manual') }}" @if($vista === 'guia') aria-current="page" @endif class="boton {{ $vista === 'guia' ? 'border-institucional/20 bg-institucional/5 text-institucional' : 'boton-secundario' }}">Guía de mi perfil</a>
    <a data-vista-manual="pdf" href="{{ route('ayuda.manual', ['vista' => 'pdf']) }}" @if($vista === 'pdf') aria-current="page" @endif class="boton {{ $vista === 'pdf' ? 'border-institucional/20 bg-institucional/5 text-institucional' : 'boton-secundario' }}">Leer PDF completo</a>
</nav>
    <section data-panel-manual="pdf" @if($vista !== 'pdf') hidden @endif aria-label="Lector del manual completo" class="overflow-hidden rounded-xl border border-borde bg-superficie shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-borde px-5 py-4">
            <h2 class="font-semibold">Manual completo de {{ $identidad['nombre'] }}</h2>
            <a href="{{ route('ayuda.pdf.ver') }}" target="_blank" rel="noopener" class="text-sm font-semibold text-institucional underline underline-offset-2">Abrir en otra pestaña</a>
        </div>
        <iframe data-pdf-manual="{{ route('ayuda.pdf.ver') }}#view=FitH" @if($vista === 'pdf') src="{{ route('ayuda.pdf.ver') }}#view=FitH" @endif title="Manual de usuario completo en PDF" class="block h-[75dvh] min-h-96 w-full border-0"></iframe>
        <p class="border-t border-borde px-5 py-3 text-xs leading-5 text-texto-secundario">Si tu dispositivo no muestra el documento, utiliza Abrir en otra pestaña o Descargar PDF completo.</p>
    </section>
<section data-panel-manual="guia" @if($vista !== 'guia') hidden @endif aria-label="Guía de mi perfil">
<div class="mb-5 rounded-xl border border-borde bg-white p-5">
    <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
        <label for="buscar-ayuda" class="font-semibold">Buscar en la guía</label>
        @if($perfiles)<span class="rounded-full bg-fondo px-3 py-1 text-xs font-medium text-texto-secundario">{{ $perfiles }}</span>@endif
    </div>
    <input type="search" id="buscar-ayuda" placeholder="Ej. disponibilidad docente" class="h-11 w-full rounded-lg border border-borde px-3 text-sm">
</div>
<div class="space-y-3" id="temas-manual">
@foreach($manual['capitulos'] as $capitulo)
<details class="group rounded-xl border border-borde bg-white shadow-sm" data-tema-manual>
    <summary class="flex cursor-pointer items-center justify-between gap-3 px-5 py-3 font-semibold">
        <span>{{ $capitulo['titulo'] }}</span>
        <svg aria-hidden="true" viewBox="0 0 20 20" class="size-4 shrink-0 text-texto-secundario group-open:rotate-180" fill="none" stroke="currentColor" stroke-width="1.5"><path d="m4 7 6 6 6-6" stroke-linecap="round" stroke-linejoin="round"/></svg>
    </summary>
    <div class="border-t border-borde p-5 sm:p-6">
    @isset($capitulo['enlace'])
        <a href="{{ $capitulo['enlace'] }}" class="text-xs font-semibold text-institucional underline underline-offset-2">{{ $capitulo['ruta'] }}</a>
    @else
        <p class="text-xs font-semibold text-institucional">{{ $capitulo['ruta'] }}</p>
    @endisset
    <p class="mt-3 text-sm leading-6">{{ $capitulo['objetivo'] }}</p>
    @foreach($capitulo['bloques'] ?? [] as $bloque)
        <section class="mt-5"><h2 class="text-sm font-semibold">{{ $bloque['titulo'] }}</h2>
        @if(isset($bloque['texto']))<p class="mt-2 text-sm leading-6 text-texto-secundario">{{ $bloque['texto'] }}</p>@endif
        @if(isset($bloque['pasos']))<ol class="mt-2 list-outside list-decimal space-y-2 pl-5 text-sm leading-6 text-texto-secundario">@foreach($bloque['pasos'] as $paso)<li class="pl-1">{{ $paso }}</li>@endforeach</ol>@endif</section>
    @endforeach
    @if(isset($capitulo['tabla']))<div class="mt-5 overflow-x-auto"><table class="w-full text-left text-sm"><thead class="bg-fondo"><tr>@foreach($capitulo['tabla'][0] as $celda)<th class="p-3">{{ $celda }}</th>@endforeach</tr></thead><tbody class="divide-y divide-borde">@foreach(array_slice($capitulo['tabla'], 1) as $fila)<tr>@foreach($fila as $celda)<td class="p-3 align-top leading-6">{{ $celda }}</td>@endforeach</tr>@endforeach</tbody></table></div>@endif
    @if(isset($capitulo['nota']))<p class="mt-5 rounded-lg bg-fondo p-4 text-sm leading-6 text-texto-secundario">{{ $capitulo['nota'] }}</p>@endif
    </div>
</details>
@endforeach
</div><p id="sin-temas-manual" hidden class="rounded-xl border border-borde bg-white p-6 text-sm text-texto-secundario">No hay temas con ese texto. Prueba con el nombre de una función.</p>
</section>
</div>
@endsection

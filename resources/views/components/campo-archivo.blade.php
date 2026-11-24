@props(['id', 'name', 'ayuda' => null])

<div data-campo-archivo class="relative min-w-0 rounded-lg focus-within:outline-2 focus-within:outline-offset-2 focus-within:outline-institucional has-[:disabled]:opacity-60">
    <input
        id="{{ $id }}"
        name="{{ $name }}"
        type="file"
        aria-describedby="{{ trim($ayuda.' '.$id.'-seleccion') }}"
        {{ $attributes->class('absolute inset-0 z-10 h-full w-full cursor-pointer opacity-0 disabled:cursor-not-allowed') }}
    >
    <div class="flex min-w-0 flex-wrap items-center gap-x-3 gap-y-2 rounded-lg border border-borde bg-superficie p-2">
        <span aria-hidden="true" class="boton boton-secundario">Seleccionar archivo</span>
        <span id="{{ $id }}-seleccion" data-archivo-seleccionado class="min-w-0 px-1 text-sm text-texto-secundario">Ningún archivo seleccionado</span>
    </div>
</div>

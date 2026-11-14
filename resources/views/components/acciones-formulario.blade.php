@props(['cancelar'])

<div class="panel-acciones">
    <p class="panel-acciones-ayuda">Los campos con * son obligatorios.</p>
    <div class="panel-botones">
        <a href="{{ $cancelar }}" class="boton boton-secundario">Cancelar</a>
        {{ $slot }}
    </div>
</div>

@props(['sedes', 'espacios'])
@php
    $anteriores = old('ubicaciones', [[]]);
    $filas = is_array($anteriores) ? array_values(array_filter($anteriores, 'is_array')) : [[]];
@endphp
<x-seccion-formulario titulo="Distribución inicial por sede" id="distribucion-inicial">
    <p class="mb-4 text-sm leading-6 text-gray-500">Opcional: reparte unidades entre cualquiera de las sedes de la institución. La suma corresponde al inventario total; no se multiplica por sede. También puedes distribuirlas después de registrar el equipo.</p>
    <div class="grid gap-3" data-ubicaciones-iniciales>
        @foreach ($filas as $indice => $fila) @include('recursos.fila-ubicacion') @endforeach
    </div>
    <template data-plantilla-ubicacion>@include('recursos.fila-ubicacion', ['indice' => '__INDEX__', 'fila' => []])</template>
    <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
        <button type="button" data-agregar-ubicacion class="boton boton-secundario">Añadir otra sede o espacio</button>
        <p data-resumen-distribucion role="status" class="text-sm text-gray-500"></p>
    </div>
</x-seccion-formulario>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const contenedor = document.querySelector('[data-ubicaciones-iniciales]');
    const plantilla = document.querySelector('[data-plantilla-ubicacion]');
    const agregar = document.querySelector('[data-agregar-ubicacion]');
    const institucion = document.getElementById('institucion-equipamiento');
    const cantidad = document.getElementById('cantidad-equipamiento');
    const resumen = document.querySelector('[data-resumen-distribucion]');
    let indice = contenedor.children.length;
    const actualizar = () => {
        let total = 0;
        for (const fila of contenedor.querySelectorAll('[data-fila-ubicacion]')) {
            const sede = fila.querySelector('[data-sede-inicial]');
            const espacio = fila.querySelector('[data-espacio-inicial]');
            for (const opcion of sede.options) {
                opcion.hidden = opcion.disabled = !!opcion.value && (!institucion.value || opcion.dataset.universidad !== institucion.value);
            }
            if (sede.selectedOptions[0]?.disabled) sede.value = '';
            for (const opcion of espacio.options) {
                opcion.hidden = opcion.disabled = !!opcion.value && (!institucion.value || opcion.dataset.universidad !== institucion.value || (!!sede.value && opcion.dataset.sede !== sede.value));
            }
            if (espacio.selectedOptions[0]?.disabled) espacio.value = '';
            total += Math.max(0, Number(fila.querySelector('[data-cantidad-inicial]').value) || 0);
        }
        const stock = Number(cantidad.value) || 0;
        resumen.textContent = total ? `${total} de ${stock} unidades distribuidas` : 'Sin distribución inicial';
        resumen.classList.toggle('text-red-700', total > stock);
        resumen.classList.toggle('font-semibold', total > stock);
    };
    agregar.addEventListener('click', () => {
        if (contenedor.children.length >= 100) return;
        contenedor.insertAdjacentHTML('beforeend', plantilla.innerHTML.replaceAll('__INDEX__', String(indice++)));
        actualizar();
    });
    contenedor.addEventListener('click', event => {
        const quitar = event.target.closest('[data-quitar-ubicacion]');
        if (quitar) { quitar.closest('[data-fila-ubicacion]').remove(); actualizar(); }
    });
    contenedor.addEventListener('change', actualizar); contenedor.addEventListener('input', actualizar);
    institucion.addEventListener('change', actualizar); cantidad.addEventListener('input', actualizar);
    actualizar();
});
</script>

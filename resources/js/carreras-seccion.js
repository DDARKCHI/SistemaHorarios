import { normalizar } from './select-buscable';

document.addEventListener('DOMContentLoaded', () => {
    const selector = document.querySelector('[data-carreras-seccion]');
    const lista = selector?.querySelector('#opciones-carreras');
    if (!lista) return;

    const periodo = selector.closest('form').elements.periodo_academico_id;
    const busqueda = selector.querySelector('#buscar-carreras');
    const estado = selector.querySelector('[data-estado-carreras]');
    const total = selector.querySelector('[data-total-carreras]');
    const resumen = selector.querySelector('[data-resumen-carreras]');
    const opciones = Array.from(lista.querySelectorAll('[data-carrera-institucion]'), (fila) => ({
        fila,
        casilla: fila.querySelector('input'),
        nombre: fila.querySelector('[data-nombre-carrera]').textContent.trim(),
        texto: normalizar(fila.textContent),
    }));

    function actualizar() {
        const institucion = periodo.selectedOptions?.[0]?.dataset.institucion ?? periodo.dataset.institucion;
        const palabras = normalizar(busqueda.value).split(/\s+/);
        const elegidas = [];
        let visibles = 0;

        for (const { fila, casilla, nombre, texto } of opciones) {
            const disponible = fila.dataset.carreraInstitucion === institucion;
            casilla.disabled = !disponible;
            fila.hidden = !disponible || !palabras.every((palabra) => texto.includes(palabra));
            if (!fila.hidden) visibles++;
            if (disponible && casilla.checked) elegidas.push(nombre);
        }

        busqueda.disabled = !institucion;
        lista.hidden = visibles === 0;
        estado.hidden = visibles > 0;
        estado.textContent = !institucion
            ? 'Selecciona un período para ver sus carreras.'
            : palabras[0] ? 'No se encontraron carreras.' : 'No hay carreras disponibles para este período.';
        total.textContent = elegidas.length === 1 ? '1 seleccionada' : elegidas.length + ' seleccionadas';
        resumen.textContent = elegidas.join(' · ');
        resumen.hidden = elegidas.length === 0;
    }

    busqueda.addEventListener('input', actualizar);
    busqueda.addEventListener('keydown', (event) => {
        if (event.key === 'Enter') event.preventDefault();
    });
    periodo.addEventListener('change', () => {
        busqueda.value = '';
        actualizar();
    });
    lista.addEventListener('change', actualizar);
    selector.closest('form').addEventListener('reset', () => queueMicrotask(actualizar));
    window.addEventListener('pageshow', actualizar);
    selector.querySelector('[data-buscador-carreras]').hidden = false;
    actualizar();
});

document.addEventListener('DOMContentLoaded', () => {
    const dialogo = document.getElementById('confirmacion-prosec');

    if (!dialogo || typeof dialogo.showModal !== 'function') {
        return;
    }

    const titulo = document.getElementById('confirmacion-titulo');
    const mensaje = document.getElementById('confirmacion-mensaje');
    const aceptar = dialogo.querySelector('[data-confirmacion-aceptar]');
    const detalle = dialogo.querySelector('[data-confirmacion-detalle]');
    const etiquetaDetalle = dialogo.querySelector('[data-confirmacion-etiqueta]');
    const valor = dialogo.querySelector('[data-confirmacion-valor]');
    let pendiente = null;
    let confirmado = null;

    for (const casilla of document.querySelectorAll('form[data-confirmar] [data-consentimiento-confirmacion]')) {
        casilla.required = false;
        casilla.checked = false;
        casilla.closest('label').hidden = true;
    }

    document.addEventListener('submit', (evento) => {
        const formulario = evento.target;
        const boton = evento.submitter;

        if (evento.defaultPrevented || formulario === confirmado) {
            return;
        }

        const origen = boton?.hasAttribute('data-confirmar') ? boton : formulario;
        if (!origen.hasAttribute('data-confirmar')) {
            return;
        }

        evento.preventDefault();
        evento.stopPropagation();

        if (pendiente) {
            return;
        }

        const accion = origen.dataset.confirmarAccion || boton?.textContent.trim() || 'Confirmar';
        const campo = origen.dataset.confirmarSeleccion;
        const opcion = campo ? formulario.elements.namedItem(campo)?.selectedOptions?.[0] : null;
        const seleccion = origen.dataset.confirmarDetalle || opcion?.textContent.replace(/\s+/g, ' ').trim() || '';
        const consentimiento = formulario.querySelector('[data-consentimiento-confirmacion]');

        if (consentimiento) {
            consentimiento.checked = false;
        }

        titulo.textContent = origen.dataset.confirmarTitulo || accion;
        mensaje.textContent = origen.dataset.confirmar;
        aceptar.textContent = accion;
        if (etiquetaDetalle) etiquetaDetalle.textContent = origen.dataset.confirmarEtiqueta || 'Selección';
        valor.textContent = seleccion;
        detalle.hidden = !seleccion;
        dialogo.returnValue = 'cancelar';
        pendiente = { formulario, boton, consentimiento };
        dialogo.showModal();
    }, true);

    dialogo.addEventListener('close', () => {
        const envio = pendiente;
        pendiente = null;

        if (!envio || dialogo.returnValue !== 'confirmar') {
            return;
        }

        const { formulario, boton, consentimiento } = envio;
        if (consentimiento) {
            consentimiento.checked = true;
        }

        confirmado = formulario;
        try {
            if (boton) {
                formulario.requestSubmit(boton);
            } else {
                formulario.requestSubmit();
            }
        } finally {
            confirmado = null;
        }
    });
});

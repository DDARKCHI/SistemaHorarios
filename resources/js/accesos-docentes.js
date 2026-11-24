document.addEventListener('DOMContentLoaded', () => {
    const formulario = document.querySelector('[data-formulario-accesos]');
    if (!formulario) return;
    const filtroSede = document.getElementById('sede-accesos');
    document.getElementById('periodo-accesos')?.addEventListener('change', () => {
        if (filtroSede) filtroSede.value = '';
    });
    const casillas = Array.from(formulario.querySelectorAll('[data-docente-acceso]:not(:disabled)'));
    const pagina = formulario.querySelector('[data-seleccionar-pagina-accesos]');
    const todos = formulario.querySelector('[data-todos-accesos]');
    const exclusiones = formulario.querySelector('[data-exclusiones-accesos]');
    const estado = formulario.querySelector('[data-total-accesos]');
    const enviar = formulario.querySelector('[data-enviar-accesos]');
    const seleccionTodos = formulario.querySelector('[data-seleccionar-todos-accesos]');
    const limpiar = formulario.querySelector('[data-limpiar-accesos]');
    if (!estado) return;
    const cantidadConfirmada = formulario.elements.namedItem('cantidad_confirmada');
    const consentimiento = formulario.elements.namedItem('confirmar_envio');
    const fechaLimite = formulario.elements.namedItem('fecha_limite');
    const formato = new Intl.NumberFormat('es-CL');
    const correoDisponible = formulario.dataset.correoDisponible === '1';
    let enviando = false;
    const excluidos = new Set(Array.from(exclusiones.querySelectorAll('input'), (input) => input.value));

    function actualizar() {
        const seleccionados = casillas.filter((casilla) => casilla.checked).length;
        const total = todos.value === '1' ? Number(estado.dataset.totalResultados) - excluidos.size : seleccionados;
        pagina.checked = casillas.length > 0 && seleccionados === casillas.length;
        pagina.indeterminate = seleccionados > 0 && seleccionados < casillas.length;
        estado.textContent = total === 1 ? '1 docente seleccionado' : total + ' docentes seleccionados';
        enviar.disabled = total <= 0 || enviando;
        cantidadConfirmada.value = String(Math.max(0, total));
        consentimiento.checked = false;
        const cantidad = formato.format(total);
        const solicitud = total === 1 ? 'solicitud' : 'solicitudes';
        const docentes = total === 1 ? 'docente' : 'docentes';
        const [anio, mes, dia] = fechaLimite.value.split('-');
        const fecha = dia && mes && anio ? dia + '/' + mes + '/' + anio : 'Por indicar';
        formulario.dataset.confirmar = correoDisponible
            ? 'Se enviará' + (total === 1 ? '' : 'n') + ' ' + cantidad + ' ' + solicitud + ' de disponibilidad docente por correo. Quienes no respondan recibirán recordatorios antes del plazo.'
            : 'Se preparará' + (total === 1 ? '' : 'n') + ' ' + cantidad + ' ' + solicitud + ' de disponibilidad docente. El correo quedará pendiente hasta configurar el envío.';
        formulario.dataset.confirmarAccion = (correoDisponible ? 'Enviar ' : 'Preparar ') + cantidad + ' ' + solicitud;
        formulario.dataset.confirmarDetalle = (todos.value === '1' ? 'Todos los resultados del filtro' : 'Selección de esta página')
            + ' · ' + cantidad + ' ' + docentes + String.fromCharCode(10) + 'Período ' + formulario.dataset.periodoEnvio
            + String.fromCharCode(10) + 'Sede: ' + (formulario.dataset.sedeEnvio || 'Todas las sedes')
            + String.fromCharCode(10) + 'Responder hasta ' + fecha;
        formulario.dataset.confirmarEtiqueta = 'Destinatarios y plazo';
        exclusiones.replaceChildren();
        for (const id of excluidos) {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'excluir[]';
            input.value = id;
            exclusiones.append(input);
        }
    }

    function cambiar(casilla, valor) {
        casilla.checked = valor;
        if (todos.value === '1') {
            if (valor) excluidos.delete(casilla.value);
            else excluidos.add(casilla.value);
        }
    }

    casillas.forEach((casilla) => casilla.addEventListener('change', () => {
        cambiar(casilla, casilla.checked);
        actualizar();
    }));
    pagina.addEventListener('change', () => {
        casillas.forEach((casilla) => cambiar(casilla, pagina.checked));
        actualizar();
    });
    seleccionTodos.addEventListener('click', () => {
        todos.value = '1';
        excluidos.clear();
        casillas.forEach((casilla) => { casilla.checked = true; });
        actualizar();
    });
    limpiar.addEventListener('click', () => {
        todos.value = '0';
        excluidos.clear();
        casillas.forEach((casilla) => { casilla.checked = false; });
        actualizar();
    });
    if (todos.value === '1') casillas.forEach((casilla) => { casilla.checked = !excluidos.has(casilla.value); });
    fechaLimite.addEventListener('change', actualizar);
    formulario.addEventListener('submit', evento => {
        if (evento.defaultPrevented) return;
        if (enviando || Number(cantidadConfirmada.value) < 1) {
            evento.preventDefault();
            return;
        }
        enviando = true;
        enviar.disabled = true;
        formulario.setAttribute('aria-busy', 'true');
    });
    window.addEventListener('pageshow', evento => {
        if (evento.persisted) {
            enviando = false;
            formulario.removeAttribute('aria-busy');
            actualizar();
        }
    });
    actualizar();
});

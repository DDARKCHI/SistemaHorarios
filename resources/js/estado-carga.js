document.addEventListener('DOMContentLoaded', () => {
    const dialogo = document.getElementById('estado-carga-prosec');
    if (!dialogo || typeof dialogo.showModal !== 'function') return;

    const operaciones = {
        analisis: ['Analizando programación', 'Lectura del archivo, validación de datos y comparación de cambios.'],
        importacion: ['Cargando programación', 'Comprobación del archivo y creación de la nueva versión con sus asignaciones.'],
        consulta: ['Consultando horario', 'Búsqueda de clases y preparación del horario según los filtros elegidos.'],
        revision: ['Revisando programación', 'Comprobación de topes, disponibilidad docente, capacidades y carga de los grupos.'],
        generacion: ['Generando programación', 'Asignación de horarios y espacios según la disponibilidad, la capacidad y las prioridades.'],
        completar: ['Preparando módulos pendientes', 'Búsqueda de horarios y espacios para los grupos seleccionados.'],
        reorganizar: ['Preparando la reorganización', 'Búsqueda de nuevos horarios para las clases de la versión base.'],
    };
    const rutas = new Map(Object.entries(JSON.parse(dialogo.dataset.cargaRutas))
        .map(([url, operacion]) => [new URL(url, location.href).pathname, operacion]));
    const titulo = dialogo.querySelector('#estado-carga-titulo');
    const mensaje = dialogo.querySelector('#estado-carga-mensaje');
    const espera = dialogo.querySelector('[data-carga-espera]');
    const tiempo = dialogo.querySelector('[data-carga-tiempo]');
    let activo = false;
    let reloj;
    let demora;
    let origen;
    let foco;
    let ocupadoAnterior;

    function operacionDe(destino) {
        const url = new URL(destino, location.href);
        return url.origin === location.origin ? rutas.get(url.pathname) : null;
    }

    function iniciar(operacion, elemento) {
        if (activo || !operaciones[operacion]) return false;
        activo = true;
        origen = elemento;
        foco = document.activeElement;
        ocupadoAnterior = origen.getAttribute('aria-busy');
        origen.setAttribute('aria-busy', 'true');
        [titulo.textContent, mensaje.textContent] = operaciones[operacion];
        espera.textContent = 'El resultado aparecerá al finalizar.';
        tiempo.textContent = '00:00';
        const inicio = Date.now();
        const actualizarTiempo = () => {
            const segundos = Math.floor((Date.now() - inicio) / 1000);
            tiempo.textContent = `${String(Math.floor(segundos / 60)).padStart(2, '0')}:${String(segundos % 60).padStart(2, '0')}`;
            if (segundos >= 20) espera.textContent = 'Todavía esperamos el resultado. El tiempo depende de la cantidad de datos.';
        };
        // Una consulta rápida conserva la navegación sin un destello del diálogo.
        demora = setTimeout(() => {
            dialogo.showModal();
            titulo.focus();
        }, operacion === 'consulta' ? 180 : 0);
        reloj = setInterval(actualizarTiempo, 1000);
        return true;
    }

    function restablecer() {
        clearInterval(reloj);
        clearTimeout(demora);
        if (dialogo.open) dialogo.close();
        if (origen) {
            if (ocupadoAnterior === null) origen.removeAttribute('aria-busy');
            else origen.setAttribute('aria-busy', ocupadoAnterior);
        }
        if (activo && foco?.isConnected) foco.focus({preventScroll: true});
        activo = false;
        origen = null;
    }

    document.addEventListener('submit', evento => {
        const formulario = evento.target;
        if (evento.defaultPrevented || formulario.method === 'dialog' || formulario.target && formulario.target !== '_self') return;
        const boton = evento.submitter;
        if (boton?.formTarget && boton.formTarget !== '_self') return;
        const operacion = operacionDe(boton?.hasAttribute('formaction') ? boton.formAction : formulario.action);
        if (!operacion) return;
        if (activo) evento.preventDefault();
        else iniciar(operacion, formulario);
    });

    document.addEventListener('click', evento => {
        if (evento.defaultPrevented || evento.button !== 0 || evento.ctrlKey || evento.metaKey || evento.shiftKey || evento.altKey) return;
        const enlace = evento.target.closest('a[href]');
        if (!enlace || enlace.hasAttribute('download') || enlace.target && enlace.target !== '_self') return;
        const destino = new URL(enlace.href, location.href);
        if (destino.pathname === location.pathname && destino.search === location.search) return;
        const operacion = operacionDe(destino);
        if (!operacion) return;
        if (activo) evento.preventDefault();
        else iniciar(operacion, enlace);
    });

    dialogo.addEventListener('cancel', evento => evento.preventDefault());
    // El navegador puede conservar el documento al usar Atrás o Adelante.
    window.addEventListener('pageshow', evento => {
        if (evento.persisted) restablecer();
    });
    window.addEventListener('pagehide', () => {
        clearInterval(reloj);
        clearTimeout(demora);
    });
});

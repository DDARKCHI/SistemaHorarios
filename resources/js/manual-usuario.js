document.addEventListener('DOMContentLoaded', () => {
    const manual = document.querySelector('[data-manual-usuario]');
    if (!manual) return;

    const enlaces = [...manual.querySelectorAll('[data-vista-manual]')];
    const paneles = [...manual.querySelectorAll('[data-panel-manual]')];
    const pdf = manual.querySelector('[data-pdf-manual]');
    const descripcion = manual.querySelector('[data-descripcion-manual]');
    const clasesActivas = ['border-institucional/20', 'bg-institucional/5', 'text-institucional'];

    function mostrar(vista) {
        for (const panel of paneles) panel.hidden = panel.dataset.panelManual !== vista;
        for (const enlace of enlaces) {
            const activo = enlace.dataset.vistaManual === vista;
            if (activo) enlace.setAttribute('aria-current', 'page');
            else enlace.removeAttribute('aria-current');
            clasesActivas.forEach(clase => enlace.classList.toggle(clase, activo));
            enlace.classList.toggle('boton-secundario', !activo);
        }
        descripcion.textContent = vista === 'pdf'
            ? 'Consulta el documento completo de la plataforma.'
            : 'Instrucciones de las funciones habilitadas para tu cuenta.';
        // Cargar una sola vez y conservar la posición del lector entre vistas.
        if (vista === 'pdf' && !pdf.hasAttribute('src')) pdf.src = pdf.dataset.pdfManual;
    }

    manual.addEventListener('click', evento => {
        if (evento.defaultPrevented || evento.button !== 0 || evento.ctrlKey || evento.metaKey || evento.shiftKey || evento.altKey) return;
        const enlace = evento.target.closest('[data-vista-manual]');
        if (!enlace || enlace.target && enlace.target !== '_self') return;
        const destino = new URL(enlace.href, location.href);
        if (destino.origin !== location.origin || destino.pathname !== location.pathname) return;
        const vista = enlace.dataset.vistaManual;
        if (!['guia', 'pdf'].includes(vista)) return;
        if (destino.href !== location.href) history.pushState(null, '', destino);
        evento.preventDefault();
        mostrar(vista);
    });

    window.addEventListener('popstate', () => {
        mostrar(new URL(location.href).searchParams.get('vista') === 'pdf' ? 'pdf' : 'guia');
    });
    mostrar(manual.dataset.vistaInicial);

    const campo = manual.querySelector('#buscar-ayuda');
    const normalizar = texto => texto.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
    campo.addEventListener('input', () => {
        const texto = normalizar(campo.value.trim());
        let visibles = 0;
        manual.querySelectorAll('[data-tema-manual]').forEach(tema => {
            tema.hidden = !normalizar(tema.textContent).includes(texto);
            if (!tema.hidden) visibles++;
        });
        manual.querySelector('#sin-temas-manual').hidden = visibles > 0;
    });
});

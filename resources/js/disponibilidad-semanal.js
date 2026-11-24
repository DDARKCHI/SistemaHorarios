document.querySelectorAll('[data-disponibilidad-semanal]').forEach(calendario => {
    const selector = calendario.querySelector('[data-elegir-dia]');
    const celdas = calendario.querySelectorAll('[data-dia]');

    const mostrarDia = () => {
        celdas.forEach(celda => {
            celda.toggleAttribute('data-otro-dia', celda.dataset.dia !== selector.value);
        });
    };

    mostrarDia();
    selector.addEventListener('change', mostrarDia);
    calendario.dataset.diaPreparado = '';
});

document.querySelectorAll('[data-campo-archivo]').forEach(campo => {
    const archivo = campo.querySelector('input');
    const nombre = campo.querySelector('[data-archivo-seleccionado]');
    const actualizar = () => {
        nombre.textContent = archivo.files[0]?.name ?? 'Ningún archivo seleccionado';
    };

    actualizar();
    archivo.addEventListener('change', actualizar);
    window.addEventListener('pageshow', actualizar);
});

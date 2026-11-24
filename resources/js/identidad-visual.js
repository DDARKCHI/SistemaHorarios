document.addEventListener('DOMContentLoaded', () => {
    const editor = document.querySelector('[data-identidad-editor]');
    if (!editor) return;
    const nombre = editor.querySelector('[data-identidad-nombre]');
    const lema = editor.querySelector('[data-identidad-lema]');
    const vista = (clave) => editor.querySelectorAll(`[data-vista-${clave}]`);
    const fuentes = new Map();
    const temporales = new Map();
    editor.querySelectorAll('[data-vista-imagen]').forEach(img => fuentes.set(img.dataset.vistaImagen, !img.hidden ? img.src : null));
    const originales = new Map(fuentes);
    const iniciales = texto => {
        if (texto.toUpperCase() === 'PROSEC') return 'PS';
        const palabras = texto.trim().split(/[^\p{L}\p{N}]+/u).filter(Boolean);
        return (palabras.length > 1 ? palabras[0][0] + palabras[1][0] : (palabras[0] || 'SI').slice(0, 2)).toUpperCase();
    };
    const luminancia = hex => {
        const rgb = hex.slice(1).match(/../g).map(v => {
            const n = parseInt(v, 16) / 255;
            return n <= 0.04045 ? n / 12.92 : ((n + 0.055) / 1.055) ** 2.4;
        });
        return .2126 * rgb[0] + .7152 * rgb[1] + .0722 * rgb[2];
    };
    function actualizar() {
        const titulo = nombre.value.trim() || 'Nombre de la plataforma';
        vista('nombre').forEach(e => e.textContent = titulo);
        vista('lema').forEach(e => e.textContent = lema.value);
        vista('iniciales').forEach(e => e.textContent = iniciales(titulo));
        vista('compacto').forEach(e => e.textContent = iniciales(titulo));
        for (const input of editor.querySelectorAll('[data-identidad-color]')) {
            if (!/^#[a-f\d]{6}$/i.test(input.value)) continue;
            editor.querySelector(`[data-color-selector="${input.dataset.identidadColor}"]`).value = input.value;
            if (input.name === 'color_principal') vista('boton').forEach(e => {
                e.style.backgroundColor = input.value;
                const luz = luminancia(input.value);
                const blanco = 1.05 / (luz + .05);
                e.style.color = blanco >= 4.5 ? '#ffffff' : '#000000';
            });
            if (input.name === 'color_secundario') ['nombre', 'texto', 'compacto', 'iniciales', 'institucion-texto'].forEach(k => vista(k).forEach(e => e.style.color = input.value));
            if (input.name === 'color_fondo') vista('fondo').forEach(e => e.style.backgroundColor = input.value);
        }
        const fondo = editor.querySelector('[name=color_fondo]').value;
        const tinta = editor.querySelector('[name=color_secundario]').value;
        const aviso = editor.querySelector('[data-identidad-contraste]');
        let problema = '';
        if (/^#[a-f\d]{6}$/i.test(fondo) && /^#[a-f\d]{6}$/i.test(tinta)) {
            const a = luminancia(fondo), b = luminancia(tinta);
            if (a < .75) problema = 'Elige un fondo claro para mantener legibles los formularios y las tablas.';
            else if (Math.min((Math.max(a, b) + .05) / (Math.min(a, b) + .05), 1.05 / (b + .05)) < 4.5) problema = 'El texto necesita mayor contraste. Elige un color de texto más oscuro.';
        }
        aviso.hidden = !problema;
        aviso.textContent = problema;
        for (const campo of ['logo', 'logo_compacto', 'logo_institucion', 'favicon']) {
            const quitar = editor.querySelector(`[data-quitar-imagen="${campo}"]`)?.checked;
            const src = quitar ? null : fuentes.get(campo);
            const img = editor.querySelector(`[data-vista-imagen="${campo}"]`);
            img.hidden = !src;
            if (src) img.src = src;
            const texto = {logo: 'logo-texto', logo_compacto: 'compacto', logo_institucion: 'institucion-texto', favicon: 'iniciales'}[campo];
            vista(texto).forEach(e => e.hidden = !!src);
        }
    }
    editor.addEventListener('input', event => {
        if (event.target.matches('[data-color-selector]')) editor.querySelector(`[data-identidad-color="${event.target.dataset.colorSelector}"]`).value = event.target.value.toUpperCase();
        actualizar();
    });
    editor.querySelectorAll('[data-identidad-imagen]').forEach(input => input.addEventListener('change', () => {
        const campo = input.dataset.identidadImagen;
        if (temporales.has(campo)) URL.revokeObjectURL(temporales.get(campo));
        const file = input.files[0];
        if (file && ['image/png', 'image/jpeg', 'image/webp'].includes(file.type)) {
            const url = URL.createObjectURL(file); temporales.set(campo, url); fuentes.set(campo, url);
            const quitar = editor.querySelector(`[data-quitar-imagen="${campo}"]`); if (quitar) quitar.checked = false;
        } else fuentes.set(campo, originales.get(campo));
        actualizar();
    }));
    actualizar();
});

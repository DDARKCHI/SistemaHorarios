const normalizar = (texto) => String(texto ?? '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().trim();

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('select[data-select-buscable]').forEach((select) => {
        const contenedor = document.createElement('div');
        contenedor.className = 'relative min-w-0 w-full';
        const input = document.createElement('input');
        input.id = `${select.id}-busqueda`;
        input.type = 'text';
        input.autocomplete = 'off';
        input.spellcheck = false;
        input.className = `${select.className} border pr-9`;
        input.setAttribute('role', 'combobox');
        input.setAttribute('aria-autocomplete', 'list');
        input.setAttribute('aria-expanded', 'false');
        const lista = document.createElement('div');
        lista.id = `${select.id}-opciones`;
        lista.className = 'absolute left-0 right-0 top-full z-50 mt-1 hidden max-h-60 overflow-y-auto rounded-lg border border-gray-200 bg-white py-1 shadow-lg';
        lista.setAttribute('role', 'listbox');
        input.setAttribute('aria-controls', lista.id);
        const etiqueta = document.querySelector(`label[for="${select.id}"]`);
        if (etiqueta) {
            etiqueta.htmlFor = input.id;
            lista.setAttribute('aria-label', etiqueta.textContent.trim());
        }
        const flecha = document.createElement('span');
        flecha.className = 'pointer-events-none absolute right-3 top-3 text-gray-400';
        flecha.textContent = '⌄';
        flecha.setAttribute('aria-hidden', 'true');
        const estado = document.createElement('p');
        estado.className = 'sr-only';
        estado.setAttribute('role', 'status');
        contenedor.append(input, flecha, lista, estado);
        select.after(contenedor);
        select.hidden = true;
        let opciones = [];
        let activo = -1;
        let abierto = false;
        const seleccionado = () => select.selectedOptions[0]?.textContent.trim() ?? '';
        const sincronizar = () => {
            input.value = seleccionado();
            input.disabled = select.disabled;
        };
        const cerrar = () => {
            abierto = false;
            lista.classList.add('hidden');
            input.setAttribute('aria-expanded', 'false');
            input.removeAttribute('aria-activedescendant');
            activo = -1;
            sincronizar();
        };
        const elegir = (opcion) => {
            if (!opcion || opcion.disabled) return;
            select.value = opcion.value;
            select.dispatchEvent(new Event('change', { bubbles: true }));
            cerrar();
        };
        const destacar = (indice) => {
            activo = indice;
            const items = lista.querySelectorAll('[role="option"]');
            items.forEach((item, i) => item.classList.toggle('bg-gray-100', i === activo));
            const item = items[activo];
            if (item) {
                input.setAttribute('aria-activedescendant', item.id);
                item.scrollIntoView({ block: 'nearest' });
            } else input.removeAttribute('aria-activedescendant');
        };
        const abrir = (busqueda = '') => {
            const termino = normalizar(busqueda);
            opciones = Array.from(select.options).filter((opcion) => !opcion.disabled && !opcion.parentElement.disabled
                && (!termino || (opcion.value !== '' && termino.split(/\s+/).every((palabra) => normalizar(`${opcion.textContent} ${opcion.parentElement.label ?? ''}`).includes(palabra)))));
            lista.replaceChildren();
            activo = -1;
            input.removeAttribute('aria-activedescendant');
            let ultimoGrupo = null;
            opciones.forEach((opcion, i) => {
                const grupo = opcion.parentElement.tagName === 'OPTGROUP' ? opcion.parentElement.label : '';
                if (grupo && grupo !== ultimoGrupo) {
                    const titulo = document.createElement('p');
                    titulo.className = 'px-3 py-2 text-xs font-semibold text-gray-500';
                    titulo.textContent = grupo;
                    lista.append(titulo);
                }
                ultimoGrupo = grupo;
                const item = document.createElement('button');
                item.type = 'button';
                item.tabIndex = -1;
                item.id = `${lista.id}-${i}`;
                item.className = 'block min-h-11 w-full break-words px-3 py-2 text-left text-sm text-gray-700 hover:bg-gray-50';
                item.setAttribute('role', 'option');
                item.setAttribute('aria-selected', String(opcion.selected));
                if (opcion.selected) item.classList.add('bg-red-50', 'font-semibold', 'text-red-700');
                item.textContent = opcion.textContent.trim();
                item.addEventListener('mousedown', (event) => event.preventDefault());
                item.addEventListener('click', () => { elegir(opcion); input.focus(); cerrar(); });
                lista.append(item);
            });
            estado.textContent = opciones.length ? '' : 'No se encontraron resultados';
            if (!opciones.length) {
                const vacio = document.createElement('p');
                vacio.className = 'px-3 py-3 text-sm text-gray-500';
                vacio.textContent = 'No se encontraron resultados';
                lista.append(vacio);
            }
            abierto = true;
            lista.classList.remove('hidden');
            input.setAttribute('aria-expanded', 'true');
        };
        input.addEventListener('focus', () => { input.select(); abrir(); });
        input.addEventListener('click', () => { if (!abierto) abrir(); });
        input.addEventListener('input', () => abrir(input.value));
        input.addEventListener('keydown', (event) => {
            if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                event.preventDefault();
                if (!abierto) abrir();
                if (opciones.length) destacar(event.key === 'ArrowDown' ? Math.min(activo + 1, opciones.length - 1) : (activo < 0 ? opciones.length - 1 : Math.max(activo - 1, 0)));
            } else if (event.key === 'Enter' && abierto) {
                event.preventDefault();
                if (opciones.length) elegir(opciones[activo < 0 ? 0 : activo]);
            } else if (event.key === 'Escape' && abierto) {
                event.preventDefault();
                event.stopPropagation();
                cerrar();
            } else if (event.key === 'Tab') cerrar();
        });
        input.addEventListener('blur', (event) => { if (!contenedor.contains(event.relatedTarget)) cerrar(); });
        document.addEventListener('click', (event) => { if (abierto && !contenedor.contains(event.target)) cerrar(); });
        select.addEventListener('change', sincronizar);
        new MutationObserver(() => { cerrar(); }).observe(select, { childList: true, subtree: true, attributes: true });
        select.form?.addEventListener('reset', () => queueMicrotask(cerrar));
        sincronizar();
    });
});

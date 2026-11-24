document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('[data-filtros-programacion-anterior]');
    if (!form) return;

    const campos = [...form.querySelectorAll('select[name]')];
    const academicos = ['sede_id', 'carrera_id', 'semestre', 'seccion_numero', 'asignatura_id', 'profesor_id'];
    const aplicar = form.querySelector('[type="submit"]');
    const estado = form.querySelector('[data-estado-filtros]');
    let prioridad = academicos.filter(nombre => form.elements.namedItem(nombre).value);
    let solicitud = 0;
    let controlador;
    let pendiente = false;

    const mostrarEstado = (texto, error = false) => {
        estado.textContent = texto;
        estado.classList.toggle('hidden', !texto);
        estado.classList.toggle('text-red-700', error);
        estado.classList.toggle('text-gray-500', !error);
    };

    const listas = {
        periodo_id: ['periodos', 'Sin períodos disponibles', item => [item.id, `${item.anio}-${item.numero_semestre}`]],
        version_id: ['versiones', 'Sin versiones disponibles', item => [item.id, `V${Number(item.version).toFixed(1)}${item.es_actual ? ' — Actual' : ''}`]],
        sede_id: ['sedes', 'Todas las sedes', item => [item.id, item.nombre]],
        carrera_id: ['carreras', 'Todas las carreras', item => [item.id, item.nombre]],
        semestre: ['semestres', 'Todos', item => [item, String(item)]],
        seccion_numero: ['secciones', 'Todas', item => [item, String(item)]],
        asignatura_id: ['asignaturas', 'Todos los ramos', item => [item.id, `${item.codigo} — ${item.nombre}`]],
        profesor_id: ['profesores', 'Todos los docentes', item => [item.id, item.nombre]],
    };

    async function actualizar(cambiado) {
        const numero = ++solicitud;
        controlador?.abort();
        const actual = new AbortController();
        controlador = actual;
        const signal = actual.signal;
        const temporizador = setTimeout(() => actual.abort(), 15000);
        const parametros = new URLSearchParams();
        campos.forEach(select => {
            if (select.value !== '') parametros.set(select.name, select.value);
        });
        parametros.set('cambiado', cambiado);
        prioridad.forEach(nombre => parametros.append('prioridad[]', nombre));
        pendiente = true;
        aplicar.disabled = true;
        form.setAttribute('aria-busy', 'true');
        estado.classList.add('estado-filtros-cargando');
        mostrarEstado('Actualizando opciones disponibles…');

        try {
            const respuesta = await fetch(`${form.dataset.filtrosUrl}?${parametros}`, {
                signal, headers: { Accept: 'application/json' },
            });
            if (!respuesta.ok) throw new Error('No fue posible actualizar las opciones.');
            const datos = await respuesta.json();
            if (numero !== solicitud) return;

            // Preparar todas las listas antes de modificar la selección visible.
            const nuevas = campos.map(select => {
                const [clave, vacio, opcion] = listas[select.name];
                const valores = datos[clave];
                if (!Array.isArray(valores) || !datos.seleccionados) throw new Error('Respuesta incompleta.');
                const fragmento = document.createDocumentFragment();
                if (academicos.includes(select.name) || !valores.length) fragmento.append(new Option(vacio, ''));
                valores.forEach(item => {
                    const [valor, texto] = opcion(item);
                    fragmento.append(new Option(texto, String(valor)));
                });
                return [select, fragmento, String(datos.seleccionados[select.name] ?? '')];
            });
            nuevas.forEach(([select, fragmento, seleccionado]) => {
                select.replaceChildren(fragmento);
                select.value = seleccionado;
            });
            prioridad = prioridad.filter(nombre => form.elements.namedItem(nombre).value !== '');
            mostrarEstado('Opciones actualizadas. Presiona Aplicar para consultar la programación.');
        } catch (error) {
            if (numero === solicitud) {
                mostrarEstado('No se pudieron actualizar las opciones. Vuelve a elegir un filtro o presiona Aplicar para consultar.', true);
            }
        } finally {
            clearTimeout(temporizador);
            if (numero === solicitud) {
                pendiente = false;
                aplicar.disabled = false;
                form.removeAttribute('aria-busy');
                estado.classList.remove('estado-filtros-cargando');
            }
        }
    }

    campos.forEach(select => select.addEventListener('change', () => {
        if (academicos.includes(select.name)) {
            prioridad = prioridad.filter(nombre => nombre !== select.name);
            if (select.value !== '') prioridad.unshift(select.name);
        }
        actualizar(select.name);
    }));

    form.addEventListener('submit', event => {
        if (pendiente) event.preventDefault();
    });
});

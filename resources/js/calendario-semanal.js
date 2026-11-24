function crearBoton(etiqueta, flecha, destino) {
    const boton = document.createElement('button');
    boton.type = 'button';
    boton.className = 'boton boton-secundario w-11 shrink-0 px-0';
    boton.setAttribute('aria-label', etiqueta);
    boton.setAttribute('aria-controls', destino);

    const icono = document.createElement('span');
    icono.textContent = flecha;
    icono.setAttribute('aria-hidden', 'true');
    boton.append(icono);

    return boton;
}

function prepararNavegacion(cuadricula) {
    const ejeHoras = cuadricula.querySelector('[data-eje-horas]');
    const tabla = cuadricula.querySelector('table');
    const dias = Array.from(cuadricula.querySelectorAll('[data-dia-semanal]'));

    if (!ejeHoras || !tabla || dias.length === 0 || !cuadricula.id) return;

    const controles = document.createElement('div');
    controles.className = 'flex flex-wrap items-center justify-between gap-2 border-b border-borde bg-white px-4 py-2';
    controles.hidden = true;

    const ayuda = document.createElement('p');
    ayuda.className = 'text-xs leading-5 text-texto-secundario';
    ayuda.textContent = 'Desliza o usa las flechas para recorrer la semana.';

    const navegacion = document.createElement('div');
    navegacion.className = 'flex min-w-0 w-full items-center gap-2 sm:w-auto';
    navegacion.setAttribute('role', 'group');
    navegacion.setAttribute('aria-label', 'Días del calendario semanal');

    const anterior = crearBoton('Ver día anterior en la semana', '←', cuadricula.id);
    const siguiente = crearBoton('Ver día siguiente en la semana', '→', cuadricula.id);
    const resumen = document.createElement('p');
    resumen.className = 'min-w-0 flex-1 text-center text-xs font-semibold text-texto sm:min-w-28';
    resumen.setAttribute('role', 'status');

    navegacion.append(anterior, resumen, siguiente);
    controles.append(ayuda, navegacion);
    cuadricula.before(controles);

    const margen = 2;
    let actualizacionPendiente = false;

    function limitesVisibles() {
        return {
            inicio: cuadricula.scrollLeft,
            anchoDias: Math.max(0, cuadricula.clientWidth - ejeHoras.getBoundingClientRect().width),
            maximo: Math.max(0, cuadricula.scrollWidth - cuadricula.clientWidth),
        };
    }

    function posicionesDias() {
        const inicioTabla = tabla.getBoundingClientRect().left;
        const anchoEje = ejeHoras.getBoundingClientRect().width;

        return dias.map((dia) => {
            const { left, width } = dia.getBoundingClientRect();
            return {
                inicio: Math.max(0, left - inicioTabla - anchoEje),
                ancho: width,
                nombre: dia.dataset.diaSemanal,
            };
        });
    }

    function actualizarControles() {
        actualizacionPendiente = false;
        const { inicio, anchoDias, maximo } = limitesVisibles();
        controles.hidden = cuadricula.clientWidth === 0 || maximo <= margen;
        if (controles.hidden) return;

        anterior.setAttribute('aria-disabled', String(inicio <= margen));
        siguiente.setAttribute('aria-disabled', String(maximo - inicio <= margen));

        const visibles = posicionesDias()
            .filter((dia) => {
                const anchoVisible = Math.min(dia.inicio + dia.ancho, inicio + anchoDias)
                    - Math.max(dia.inicio, inicio);
                return anchoVisible >= Math.min(dia.ancho, anchoDias) / 2 - margen;
            })
            .map((dia) => dia.nombre);

        const texto = visibles.length > 1 ? `${visibles[0]} a ${visibles.at(-1)}` : (visibles[0] ?? '');
        if (resumen.textContent !== texto) resumen.textContent = texto;
    }

    function solicitarActualizacion() {
        if (actualizacionPendiente) return;
        actualizacionPendiente = true;
        requestAnimationFrame(actualizarControles);
    }

    function desplazar(direccion) {
        const { inicio, maximo } = limitesVisibles();
        if ((direccion < 0 && inicio <= margen) || (direccion > 0 && maximo - inicio <= margen)) return;

        const posiciones = posicionesDias();
        const destino = direccion < 0
            ? posiciones.findLast((dia) => dia.inicio < inicio - margen)?.inicio ?? 0
            : posiciones.find((dia) => dia.inicio > inicio + margen)?.inicio ?? maximo;

        cuadricula.scrollTo({
            left: Math.min(destino, maximo),
            behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'instant' : 'smooth',
        });
    }

    anterior.addEventListener('click', () => desplazar(-1));
    siguiente.addEventListener('click', () => desplazar(1));
    cuadricula.addEventListener('scroll', solicitarActualizacion, { passive: true });

    const observador = new ResizeObserver(solicitarActualizacion);
    observador.observe(cuadricula);
    observador.observe(tabla);

    actualizarControles();
}

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-calendario-semanal]').forEach(prepararNavegacion);
});

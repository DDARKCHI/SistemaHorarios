import { normalizar } from './select-buscable';

const etiquetas = { verde: 'Sin cruces', ambar: 'Requiere revisión', rojo: 'Conflicto' };

function leerDatos(panel, selector) {
    const bloque = panel.querySelector(selector);
    return bloque ? JSON.parse(bloque.textContent) : [];
}

function habilitarFiltros(panel) {
    const periodo = panel.querySelector('[data-panel-periodo]');
    periodo?.addEventListener('change', () => {
        panel.querySelector('[data-panel-version]').disabled = true;
        panel.querySelector('[data-panel-carrera-filtro]').disabled = true;
        periodo.form.requestSubmit();
    });
    window.addEventListener('pageshow', evento => {
        if (!evento.persisted || !periodo) return;
        panel.querySelector('[data-panel-version]').disabled = false;
        panel.querySelector('[data-panel-carrera-filtro]').disabled = false;
    });
}

function habilitarCatalogos(panel) {
    const filtro = panel.querySelector('[data-panel-buscar]');
    const secciones = [...panel.querySelectorAll('[data-panel-ramo]')];
    const vacio = panel.querySelector('[data-panel-ramos-vacios]');
    const claveSeccion = `prosec.panel.seccion.${panel.dataset.carrera}.${panel.dataset.version}`;
    function conservarSeccion(origen) {
        const seccion = origen?.closest('[data-panel-ramo]');
        if (!seccion) return;
        try { sessionStorage.setItem(claveSeccion, seccion.dataset.panelRamo); }
        catch { /* La pantalla sigue disponible sin almacenamiento. */ }
    }
    try {
        const anterior = sessionStorage.getItem(claveSeccion);
        const seccion = secciones.find(item => item.dataset.panelRamo === anterior);
        if (seccion) seccion.open = true;
    } catch { /* No se conserva la sección con almacenamiento deshabilitado. */ }
    panel.addEventListener('submit', evento => conservarSeccion(evento.target));
    filtro?.addEventListener('input', () => {
        const palabras = normalizar(filtro.value).split(/\s+/);
        for (const seccion of secciones) {
            const texto = normalizar(seccion.querySelector('summary').textContent);
            seccion.hidden = !palabras.every(palabra => texto.includes(palabra));
        }
        vacio.hidden = !secciones.length || secciones.some(seccion => !seccion.hidden);
    });

    for (const [tipo, campo] of [['espacio', 'espacio'], ['docente', 'profesor_id']]) {
        const dialogo = panel.querySelector(`[data-panel-dialogo-${tipo}]`);
        if (!dialogo) continue;
        const formulario = dialogo.querySelector('form');
        const selector = formulario.elements.namedItem(campo);
        const resumen = dialogo.querySelector(`[data-panel-${tipo}-resumen]`);
        const guardar = dialogo.querySelector(`[data-panel-guardar-${tipo}]`);
        const cancelar = dialogo.querySelector(`[data-panel-cerrar-${tipo}]`);
        let origen;
        let enviando = false;
        for (const boton of panel.querySelectorAll(`[data-panel-elegir-${tipo}]`)) {
            boton.addEventListener('click', () => {
                origen = boton;
                formulario.action = boton.dataset.panelAccion;
                resumen.textContent = boton.dataset.panelDescripcion;
                selector.value = boton.dataset.panelPrincipal || '';
                selector.dispatchEvent(new Event('change'));
                dialogo.showModal();
                cancelar.focus();
            });
        }
        selector.addEventListener('change', () => { guardar.disabled = !selector.value; });
        formulario.addEventListener('submit', evento => {
            if (enviando || !selector.value || !origen) evento.preventDefault();
            else {
                enviando = true;
                conservarSeccion(origen);
            }
        });
        cancelar.addEventListener('click', () => dialogo.close());
        dialogo.addEventListener('close', () => origen?.focus({ preventScroll: true }));
        window.addEventListener('pageshow', () => { enviando = false; });
    }
}

function actualizarCeldas(grilla, horas, extra = () => ({})) {
    for (const celda of grilla.querySelectorAll('[data-panel-celda]')) {
        const { estado, disponibilidad, espera } = extra(celda.dataset.panelCelda);
        for (const tarjeta of celda.querySelectorAll('[data-panel-horario]')) {
            tarjeta.hidden = !horas.has(String(tarjeta.dataset.panelHorario));
        }
        if (disponibilidad) celda.dataset.disponibilidad = disponibilidad;
        else delete celda.dataset.disponibilidad;
        celda.toggleAttribute('data-espera', Boolean(espera));
        const aviso = celda.querySelector('[data-panel-estado-celda]');
        if (aviso) {
            aviso.textContent = estado || '';
            aviso.hidden = !estado;
        }
    }
}

function habilitarLaboratorios(panel) {
    const selector = panel.querySelector('[data-panel-laboratorio]');
    if (!selector) return;
    const laboratorios = new Map(leerDatos(panel, '[data-panel-laboratorios]').map(lab => [`${lab.tipo}:${lab.id}`, lab]));
    const grilla = panel.querySelector('[data-panel-grilla="laboratorios"]');
    const cargar = () => {
        const lab = laboratorios.get(selector.value);
        if (!lab) return;
        const datos = {
            nombre: lab.nombre,
            sede: [lab.tipo === 'fijo' ? 'Laboratorio fijo' : 'Laboratorio móvil', lab.sede, lab.disponible === false ? 'Inhabilitado' : null].filter(Boolean).join(' · '),
            capacidad: lab.capacidad || 'Sin aforo registrado',
            ocupados: lab.ocupados,
            libres: lab.libres,
        };
        for (const [campo, valor] of Object.entries(datos)) {
            panel.querySelector(`[data-panel-lab-${campo}]`).textContent = valor;
        }
        const horarios = new Set(Object.values(lab.bloques).flatMap(bloque => bloque.horarios.map(String)));
        actualizarCeldas(grilla, horarios, clave => {
            const bloque = lab.bloques[clave];
            if (bloque?.otras_carreras || (bloque?.ocupado && !bloque.horarios.length)) return { estado: 'Ocupado por otra carrera' };
            return {};
        });
    };
    selector.addEventListener('change', cargar);
    cargar();
}

function habilitarDocentes(panel, limpiarSeleccion) {
    const selector = panel.querySelector('[data-panel-docente]');
    if (!selector) return;
    const docentes = new Map(leerDatos(panel, '[data-panel-docentes]').map(docente => [String(docente.id), docente]));
    const grilla = panel.querySelector('[data-panel-grilla="docentes"]');
    const titulo = panel.querySelector('#panel-semana-titulo');
    const resumen = panel.querySelector('[data-panel-resumen-docente]');
    const buscador = panel.querySelector('[data-panel-buscar-docente]');
    const resultados = panel.querySelector('[data-panel-resultados-docentes]');
    const sinResultados = panel.querySelector('[data-panel-sin-docentes]');
    const filas = [...panel.querySelectorAll('[data-panel-fila-docente]')].map(fila => ({
        fila,
        nombre: normalizar(fila.querySelector('[data-panel-ver-docente]').textContent),
    }));
    buscador?.addEventListener('input', () => {
        const termino = normalizar(buscador.value);
        const palabras = termino.split(/\s+/);
        let visibles = 0;
        for (const registro of filas) {
            registro.fila.hidden = !palabras.every(palabra => registro.nombre.includes(palabra));
            if (!registro.fila.hidden) visibles++;
        }
        sinResultados.hidden = visibles !== 0;
        resultados.textContent = termino
            ? `${visibles} de ${filas.length} docentes`
            : (filas.length === 1 ? '1 docente' : `${filas.length} docentes`);
    });
    const cargar = () => {
        const docente = docentes.get(selector.value);
        if (!docente) return;
        limpiarSeleccion();
        titulo.textContent = docente.nombre;
        resumen.textContent = `${docente.dias} ${docente.dias === 1 ? 'día de clase' : 'días de clase'} · ${docente.modulos} módulos · ${docente.huecos} ${docente.huecos === 1 ? 'hueco' : 'huecos'}. Se considera toda la versión.`;
        const esperas = new Set(docente.esperas);
        actualizarCeldas(grilla, new Set(docente.horarios.map(String)), clave => {
            const disponibilidad = docente.calendario[clave];
            const externa = Boolean(docente.ocupaciones_externas?.[clave]);
            const espera = esperas.has(clave);
            const leyenda = disponibilidad === 'fuera' ? 'Fuera de disponibilidad' : disponibilidad === 'disponible' ? 'Disponible' : '';
            return { disponibilidad, espera, estado: [externa ? 'Clase de otra carrera' : '', espera ? 'Hueco entre clases' : '', leyenda].filter(Boolean).join(' · ') };
        });
    };
    selector.addEventListener('change', cargar);
    for (const boton of panel.querySelectorAll('[data-panel-ver-docente]')) {
        boton.addEventListener('click', () => {
            selector.value = boton.dataset.panelVerDocente;
            selector.dispatchEvent(new Event('change', { bubbles: true }));
            titulo.focus({ preventScroll: true });
            titulo.closest('section').scrollIntoView({ block: 'start' });
        });
    }
    cargar();
}

function habilitarMovimientos(panel) {
    const dialogo = panel.querySelector('[data-panel-dialogo]');
    if (!dialogo) return () => {};
    const celdas = new Map([...panel.querySelectorAll('[data-panel-celda]')].map(celda => [celda.dataset.panelCelda, celda]));
    const mensaje = panel.querySelector('[data-panel-mensaje]');
    const feedback = panel.querySelector('[data-panel-feedback]');
    const cancelar = panel.querySelector('[data-panel-cancelar]');
    const deshacer = panel.querySelector('[data-panel-deshacer]');
    const guardar = dialogo.querySelector('[data-panel-guardar]');
    const volver = dialogo.querySelector('[data-panel-volver]');
    const error = dialogo.querySelector('[data-panel-error]');
    const riesgo = dialogo.querySelector('[data-panel-riesgo]');
    const destinoResumen = dialogo.querySelector('[data-panel-destino]');
    const motivoLista = dialogo.querySelector('[data-panel-motivos]');
    const claveGuardada = `prosec.panel.movimiento.${panel.dataset.carrera}.${panel.dataset.version}`;
    let seleccionado = null;
    let pendiente = null;
    let anterior = null;
    let consultando = null;
    let guardando = false;

    function avisar(texto, nivel = '') {
        feedback.hidden = !texto;
        feedback.dataset.nivel = nivel;
        mensaje.textContent = texto;
    }

    function recuperarAnterior() {
        try {
            anterior = JSON.parse(sessionStorage.getItem(claveGuardada) || 'null');
            if (anterior && Date.now() - anterior.guardado > 15 * 60 * 1000) guardarAnterior(null);
        } catch {
            anterior = null;
        }
        deshacer.hidden = !anterior;
    }

    function guardarAnterior(movimiento) {
        anterior = movimiento;
        try {
            if (anterior) sessionStorage.setItem(claveGuardada, JSON.stringify(anterior));
            else sessionStorage.removeItem(claveGuardada);
        } catch {
            // El guardado del horario vive en el servidor aunque no haya almacenamiento local.
        }
        deshacer.hidden = !anterior;
    }

    function limpiar({ conservarMensaje = false } = {}) {
        consultando?.abort();
        consultando = null;
        seleccionado?.boton.setAttribute('aria-pressed', 'false');
        seleccionado = null;
        cancelar.hidden = true;
        for (const celda of celdas.values()) {
            for (const atributo of ['data-origen', 'data-destino', 'data-sobre']) celda.removeAttribute(atributo);
            const boton = celda.querySelector('[data-panel-destino-celda]');
            if (boton) boton.hidden = true;
        }
        if (!conservarMensaje) avisar(anterior ? anterior.mensaje : '');
    }

    async function pedir(url, opciones = {}) {
        let respuesta;
        try {
            respuesta = await fetch(url, {
                ...opciones,
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', ...opciones.headers },
            });
        } catch (problema) {
            if (problema.name === 'AbortError') throw problema;
            throw new Error('Se perdió la conexión. Inténtalo nuevamente.');
        }
        const datos = await respuesta.json().catch(() => null);
        if (respuesta.status === 419 || respuesta.status === 401) throw new Error('La sesión venció. Recarga la página para continuar.');
        if (!respuesta.ok || !datos) {
            const validacion = Object.values(datos?.errors || {}).flat().find(texto => typeof texto === 'string');
            throw new Error(validacion || datos?.message || 'No se pudo consultar el horario. Inténtalo nuevamente.');
        }
        return datos;
    }

    function pintarDestinos() {
        if (!seleccionado?.opciones) return;
        for (const [clave, celda] of celdas) {
            const evaluacion = seleccionado.opciones[clave];
            const boton = celda.querySelector('[data-panel-destino-celda]');
            if (!boton || !evaluacion || clave === seleccionado.origen) continue;
            const etiqueta = evaluacion.bloqueado ? 'No disponible' : etiquetas[evaluacion.nivel] || 'Requiere revisión';
            celda.dataset.destino = evaluacion.nivel;
            const pista = document.createElement('small');
            pista.textContent = etiqueta;
            boton.replaceChildren('Mover aquí', pista);
            boton.setAttribute('aria-label', `Mover a ${celda.dataset.panelRotulo}. ${etiqueta}`);
            boton.hidden = false;
        }
    }

    async function elegir(boton, focoDestino = false) {
        if (guardando || !boton) return null;
        limpiar();
        const origen = boton.closest('[data-panel-celda]');
        const tarjeta = boton.closest('[data-panel-horario]');
        const modulo = {
            boton,
            id: tarjeta.dataset.panelHorario,
            titulo: boton.querySelector('strong').textContent,
            origen: origen.dataset.panelCelda,
            evaluar: boton.dataset.panelEvaluar,
            guardar: boton.dataset.panelGuardar,
        };
        seleccionado = modulo;
        boton.setAttribute('aria-pressed', 'true');
        origen.dataset.origen = '';
        cancelar.hidden = false;
        avisar(`Comprobando destinos para ${modulo.titulo}…`);
        consultando = new AbortController();
        try {
            const respuesta = await pedir(modulo.evaluar, { signal: consultando.signal });
            if (seleccionado !== modulo) return null;
            modulo.opciones = respuesta.opciones || respuesta;
            pintarDestinos();
            avisar(`${modulo.titulo} seleccionado. Elige un destino para revisar el cambio.`);
            if (modulo.destinoPendiente) proponer(modulo.destinoPendiente);
            else if (focoDestino) panel.querySelector('[data-panel-destino-celda]:not([hidden])')?.focus();
            return modulo;
        } catch (problema) {
            if (problema.name === 'AbortError' || seleccionado !== modulo) return null;
            limpiar({ conservarMensaje: true });
            avisar(problema.message, 'rojo');
            return null;
        }
    }

    function proponer(celda, reversa = false) {
        if (!seleccionado || guardando || !celda || dialogo.open) return;
        const clave = celda.dataset.panelCelda;
        if (clave === seleccionado.origen) return;
        const evaluacion = seleccionado.opciones?.[clave];
        if (!evaluacion) return;
        pendiente = { modulo: seleccionado, clave, evaluacion, reversa };
        riesgo.dataset.nivel = evaluacion.nivel;
        riesgo.textContent = evaluacion.bloqueado ? 'Movimiento no disponible' : etiquetas[evaluacion.nivel];
        destinoResumen.textContent = `${seleccionado.titulo} → ${celda.dataset.panelRotulo}`;
        motivoLista.replaceChildren();
        for (const texto of evaluacion.motivos || []) {
            const motivo = document.createElement('li');
            motivo.textContent = texto;
            motivoLista.append(motivo);
        }
        motivoLista.hidden = !motivoLista.childElementCount;
        error.hidden = true;
        guardar.disabled = Boolean(evaluacion.bloqueado);
        guardar.textContent = reversa ? 'Deshacer movimiento' : evaluacion.nivel === 'rojo' ? 'Guardar con conflicto' : 'Guardar movimiento';
        dialogo.showModal();
    }

    async function guardarMovimiento() {
        if (!pendiente || guardando || pendiente.evaluacion.bloqueado) return;
        const { modulo, clave, evaluacion, reversa } = pendiente;
        const [dia, bloque] = clave.split(':').map(Number);
        const [diaOrigen, bloqueOrigen] = modulo.origen.split(':').map(Number);
        const etiqueta = guardar.textContent;
        guardando = true;
        guardar.disabled = volver.disabled = true;
        guardar.textContent = 'Guardando…';
        error.hidden = true;
        try {
            const datos = await pedir(modulo.guardar, {
                method: 'PATCH',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': panel.dataset.token },
                body: JSON.stringify({
                    carrera_id: Number(panel.dataset.carrera), version_id: Number(panel.dataset.version),
                    dia_id: dia, bloque_id: bloque, dia_origen: diaOrigen, bloque_origen: bloqueOrigen,
                    confirmar: evaluacion.nivel === 'rojo',
                }),
            });
            guardarAnterior(reversa ? null : {
                id: modulo.id, origen: modulo.origen, destino: clave, guardado: Date.now(),
                mensaje: datos.mensaje || 'Movimiento guardado. Puedes volver al horario anterior.',
            });
            window.location.reload();
        } catch (problema) {
            error.textContent = problema.message;
            error.hidden = false;
            guardando = guardar.disabled = volver.disabled = false;
            guardar.textContent = etiqueta;
        }
    }

    panel.addEventListener('click', evento => {
        const boton = evento.target.closest('[data-panel-mover]');
        if (boton) {
            if (seleccionado?.boton === boton) limpiar();
            else elegir(boton, evento.detail === 0);
        }
        const destino = evento.target.closest('[data-panel-destino-celda]');
        if (destino) proponer(destino.closest('[data-panel-celda]'));
    });
    panel.addEventListener('dragstart', evento => {
        const tarjeta = evento.target.closest('[data-panel-horario][draggable="true"]');
        if (!tarjeta) return;
        if (guardando) {
            evento.preventDefault();
            return;
        }
        evento.dataTransfer.effectAllowed = 'move';
        evento.dataTransfer.setData('text/plain', tarjeta.dataset.panelHorario);
        elegir(tarjeta.querySelector('[data-panel-mover]'));
    });
    panel.addEventListener('dragover', evento => {
        const celda = evento.target.closest('[data-panel-celda]');
        if (!celda || !seleccionado) return;
        evento.preventDefault();
        evento.dataTransfer.dropEffect = 'move';
        panel.querySelector('[data-sobre]')?.removeAttribute('data-sobre');
        celda.dataset.sobre = '';
    });
    panel.addEventListener('dragleave', evento => {
        const celda = evento.target.closest('[data-panel-celda]');
        if (!celda?.contains(evento.relatedTarget)) celda?.removeAttribute('data-sobre');
    });
    panel.addEventListener('drop', evento => {
        const celda = evento.target.closest('[data-panel-celda]');
        if (!celda || !seleccionado || evento.dataTransfer.getData('text/plain') !== seleccionado.id) return;
        evento.preventDefault();
        celda.removeAttribute('data-sobre');
        if (seleccionado.opciones) proponer(celda);
        else seleccionado.destinoPendiente = celda;
    });
    panel.addEventListener('dragend', () => panel.querySelector('[data-sobre]')?.removeAttribute('data-sobre'));
    cancelar.addEventListener('click', () => {
        const origen = seleccionado?.boton;
        limpiar();
        origen?.focus({ preventScroll: true });
    });
    document.addEventListener('keydown', evento => {
        if (evento.key === 'Escape' && seleccionado && !dialogo.open) limpiar();
    });
    volver.addEventListener('click', () => dialogo.close());
    dialogo.addEventListener('cancel', evento => { if (guardando) evento.preventDefault(); });
    dialogo.addEventListener('close', () => { pendiente = null; });
    guardar.addEventListener('click', guardarMovimiento);
    deshacer.addEventListener('click', async () => {
        const movimiento = anterior;
        if (!movimiento) return;
        const celda = celdas.get(movimiento.destino);
        const tarjeta = [...(celda?.querySelectorAll('[data-panel-horario]') || [])].find(item => item.dataset.panelHorario === movimiento.id);
        const boton = tarjeta?.querySelector('[data-panel-mover]');
        if (!boton || !celdas.has(movimiento.origen)) {
            avisar('El módulo cambió desde el último movimiento. Revisa su horario actual.', 'rojo');
            guardarAnterior(null);
            return;
        }
        if (await elegir(boton)) proponer(celdas.get(movimiento.origen), true);
    });

    recuperarAnterior();
    if (anterior) avisar(anterior.mensaje);
    return limpiar;
}

function habilitarFiltrosHorario(panel, limpiarSeleccion) {
    const semestre = panel.querySelector('[data-panel-semestre]');
    const docente = panel.querySelector('[data-panel-horario-docente]');
    const vacio = panel.querySelector('[data-panel-horario-vacio]');
    const modulos = panel.querySelectorAll('[data-panel-grilla="horario"] [data-panel-horario]');
    const filtrar = () => {
        limpiarSeleccion();
        let hayResultados = false;
        for (const modulo of modulos) {
            const coincideSemestre = !semestre?.value || semestre.value === modulo.dataset.semestre;
            const coincideDocente = !docente?.value || modulo.dataset.panelDocentesIds.split(' ').includes(docente.value);
            modulo.hidden = !coincideSemestre || !coincideDocente;
            hayResultados ||= !modulo.hidden;
        }
        vacio.hidden = hayResultados;
    };
    semestre?.addEventListener('change', filtrar);
    docente?.addEventListener('change', filtrar);
}

document.addEventListener('DOMContentLoaded', () => {
    const panel = document.querySelector('[data-panel-carrera]');
    if (!panel) return;
    habilitarFiltros(panel);
    habilitarCatalogos(panel);
    const limpiarSeleccion = habilitarMovimientos(panel);
    habilitarDocentes(panel, limpiarSeleccion);
    habilitarLaboratorios(panel);
    habilitarFiltrosHorario(panel, limpiarSeleccion);
});

import './bootstrap';

console.log('APP.JS CARGADO');

document.addEventListener('DOMContentLoaded', function () {

    // =========================================================
    // MENÚ MÓVIL
    // =========================================================

    const botonMenuMovil = document.getElementById('boton-menu-movil');
    const sidebarMovil = document.getElementById('sidebar-movil');
    const botonCerrarMenuMovil = document.getElementById('cerrar-menu-movil');
    const fondoSidebarMovil = document.getElementById('fondo-sidebar-movil');


    // Abrir menú móvil
    function abrirMenuMovil() {

        if (sidebarMovil) {
            sidebarMovil.classList.remove('hidden');
            sidebarMovil.setAttribute('aria-hidden', 'false');
        }

        if (botonMenuMovil) {
            botonMenuMovil.setAttribute('aria-expanded', 'true');
        }

    }


    // Cerrar menú móvil
    function cerrarMenuMovil() {

        if (sidebarMovil) {
            sidebarMovil.classList.add('hidden');
            sidebarMovil.setAttribute('aria-hidden', 'true');
        }

        if (botonMenuMovil) {
            botonMenuMovil.setAttribute('aria-expanded', 'false');
        }

    }


    // Evento para abrir el menú
    if (botonMenuMovil) {
        botonMenuMovil.addEventListener('click', abrirMenuMovil);
    }


    // Evento para cerrar el menú
    if (botonCerrarMenuMovil) {
        botonCerrarMenuMovil.addEventListener('click', cerrarMenuMovil);
    }


    // Evento para cerrar al pulsar el fondo
    if (fondoSidebarMovil) {
        fondoSidebarMovil.addEventListener('click', cerrarMenuMovil);
    }


    // =========================================================
    // SIDEBAR ESCRITORIO
    // =========================================================

    const sidebarEscritorio = document.getElementById('sidebar-escritorio');
    const botonSidebar = document.getElementById('boton-sidebar');
    const iconoSidebar = document.getElementById('icono-sidebar');


    // Comprobar si existe el sidebar de escritorio
    if (sidebarEscritorio && botonSidebar) {

        // Recuperar estado guardado
        const sidebarColapsado =
            localStorage.getItem('sidebarColapsado') === 'true';


        // Aplicar estado inicial
        if (sidebarColapsado) {
            colapsarSidebar(false);
        }


        // Evento del botón
        botonSidebar.addEventListener('click', function () {

            const estaColapsado =
                sidebarEscritorio.classList.contains('w-20');

            if (estaColapsado) {
                expandirSidebar(true);
            } else {
                colapsarSidebar(true);
            }

        });

    }


    // =========================================================
    // FUNCIONES DEL SIDEBAR
    // =========================================================

    function colapsarSidebar(guardarEstado = true) {

        if (!sidebarEscritorio) {
            return;
        }


        // Cambiar ancho
        sidebarEscritorio.classList.remove('w-64');
        sidebarEscritorio.classList.add('w-20');
        // Ajustar contenido principal
const contenidoPrincipal =
    document.getElementById('contenido-principal');

if (contenidoPrincipal) {

    contenidoPrincipal.classList.remove('lg:ml-64');
    contenidoPrincipal.classList.add('lg:ml-20');

}


        // Ocultar textos
        const textosSidebar = sidebarEscritorio.querySelectorAll(
            'nav span, nav p'
        );

        textosSidebar.forEach(function (texto) {

            texto.classList.add('hidden');

        });


        // Centrar elementos del menú
        const enlacesSidebar =
            sidebarEscritorio.querySelectorAll('nav a');

        enlacesSidebar.forEach(function (enlace) {

            enlace.classList.remove('gap-3');
            enlace.classList.add('justify-center');

        });


        // Ajustar navegación
        const navegacion =
            sidebarEscritorio.querySelector('nav');

        if (navegacion) {

            navegacion.classList.remove('px-4');
            navegacion.classList.add('px-2');

        }


        // =====================================================
        // CAMBIAR LOGO PROSEC
        // =====================================================

        const logoProsecCompleto =
            document.getElementById('logo-prosec-completo');

        const logoProsecMini =
            document.getElementById('logo-prosec-mini');

        const contenedorLogoProsec =
            document.getElementById('contenedor-logo-prosec');


        // Ocultar logo completo
        if (logoProsecCompleto) {

            logoProsecCompleto.classList.add('hidden');

        }


        // Mostrar logo reducido
        if (logoProsecMini) {

            logoProsecMini.classList.remove('hidden');

        }


        // Reducir espacio lateral
        if (contenedorLogoProsec) {

            contenedorLogoProsec.classList.remove('px-5');
            contenedorLogoProsec.classList.add('px-2');

        }


        // Cambiar flecha a derecha
        cambiarIconoSidebar('derecha');


        // Actualizar accesibilidad
        botonSidebar.setAttribute(
            'aria-label',
            'Expandir menú lateral'
        );

        botonSidebar.setAttribute(
            'aria-expanded',
            'false'
        );


        // Guardar preferencia
        if (guardarEstado) {

            localStorage.setItem(
                'sidebarColapsado',
                'true'
            );

        }

    }


    function expandirSidebar(guardarEstado = true) {

        if (!sidebarEscritorio) {
            return;
        }


        // Cambiar ancho
        sidebarEscritorio.classList.remove('w-20');
        sidebarEscritorio.classList.add('w-64');
        // Ajustar contenido principal
const contenidoPrincipal =
    document.getElementById('contenido-principal');

if (contenidoPrincipal) {

    contenidoPrincipal.classList.remove('lg:ml-20');
    contenidoPrincipal.classList.add('lg:ml-64');

}

        // Mostrar textos
        const textosSidebar = sidebarEscritorio.querySelectorAll(
            'nav span, nav p'
        );

        textosSidebar.forEach(function (texto) {

            texto.classList.remove('hidden');

        });


        // Recuperar distribución normal
        const enlacesSidebar =
            sidebarEscritorio.querySelectorAll('nav a');

        enlacesSidebar.forEach(function (enlace) {

            enlace.classList.remove('justify-center');
            enlace.classList.add('gap-3');

        });


        // Recuperar navegación
        const navegacion =
            sidebarEscritorio.querySelector('nav');

        if (navegacion) {

            navegacion.classList.remove('px-2');
            navegacion.classList.add('px-4');

        }


        // =====================================================
        // CAMBIAR LOGO PROSEC
        // =====================================================

        const logoProsecCompleto =
            document.getElementById('logo-prosec-completo');

        const logoProsecMini =
            document.getElementById('logo-prosec-mini');

        const contenedorLogoProsec =
            document.getElementById('contenedor-logo-prosec');


        // Mostrar logo completo
        if (logoProsecCompleto) {

            logoProsecCompleto.classList.remove('hidden');

        }


        // Ocultar logo reducido
        if (logoProsecMini) {

            logoProsecMini.classList.add('hidden');

        }


        // Recuperar espacio lateral
        if (contenedorLogoProsec) {

            contenedorLogoProsec.classList.remove('px-2');
            contenedorLogoProsec.classList.add('px-5');

        }


        // Cambiar flecha a izquierda
        cambiarIconoSidebar('izquierda');


        // Actualizar accesibilidad
        botonSidebar.setAttribute(
            'aria-label',
            'Contraer menú lateral'
        );

        botonSidebar.setAttribute(
            'aria-expanded',
            'true'
        );


        // Guardar preferencia
        if (guardarEstado) {

            localStorage.setItem(
                'sidebarColapsado',
                'false'
            );

        }

    }


    // =========================================================
    // CAMBIAR ICONO DE LA FLECHA
    // =========================================================

    function cambiarIconoSidebar(direccion) {

        if (!iconoSidebar) {
            return;
        }


        if (direccion === 'derecha') {

            iconoSidebar.innerHTML = `
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M9 6l6 6-6 6"
                />
            `;

        } else {

            iconoSidebar.innerHTML = `
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M15 6l-6 6 6 6"
                />
            `;

        }

    }

});
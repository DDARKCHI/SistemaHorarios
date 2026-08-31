import './bootstrap';

console.log('APP.JS CARGADO');

document.addEventListener('DOMContentLoaded', function () {

    // Elementos del menú móvil
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

});
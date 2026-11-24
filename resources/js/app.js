import './bootstrap';
import './select-buscable';
import './carreras-seccion';
import './filtros-programacion-anterior';
import './identidad-visual';
import './disponibilidad-semanal';
import './campo-archivo';
import './calendario-semanal';
import './confirmaciones';
import './accesos-docentes';
import './estado-carga';
import './manual-usuario';

document.addEventListener('DOMContentLoaded', function () {
    const menuAcciones = document.querySelector('[data-menu-acciones]');
    if (menuAcciones) {
        document.addEventListener('click', (evento) => {
            if (!menuAcciones.contains(evento.target)) {
                menuAcciones.open = false;
            }
        });
        menuAcciones.addEventListener('keydown', (evento) => {
            if (evento.key === 'Escape' && menuAcciones.open) {
                menuAcciones.open = false;
                menuAcciones.querySelector('summary').focus();
            }
        });
    }

    const botonMenuMovil = document.getElementById('boton-menu-movil');
    const sidebarMovil = document.getElementById('sidebar-movil');
    const botonCerrarMenuMovil = document.getElementById('cerrar-menu-movil');
    const fondoSidebarMovil = document.getElementById('fondo-sidebar-movil');

    const sidebarEscritorio = document.getElementById('sidebar-escritorio');
    const botonSidebar = document.getElementById('boton-sidebar');
    const iconoSidebar = document.getElementById('icono-sidebar');
    const navEscritorio = sidebarEscritorio?.querySelector('nav');
    const navMovil = sidebarMovil?.querySelector('nav');

    const flyout = document.getElementById('sidebar-flyout');
    const flyoutTitulo = document.getElementById('sidebar-flyout-title');
    const flyoutContenido = document.getElementById('sidebar-flyout-content');

    let botonFlyoutActivo = null;

    function modoSidebarEscritorio() {
        return sidebarEscritorio?.classList.contains('w-20') ? 'contraido' : 'escritorio';
    }

    function guardarPosicionMenu(navegacion, modo) {
        if (!navegacion?.clientHeight) {
            return;
        }

        try {
            sessionStorage.setItem(`prosec.sidebar.posicion.${modo}`, navegacion.scrollTop);
        } catch {
            // El almacenamiento puede estar deshabilitado por el navegador.
        }
    }

    function restaurarPosicionMenu(navegacion, modo) {
        if (!navegacion?.clientHeight) {
            return;
        }

        try {
            const posicion = Number(sessionStorage.getItem(`prosec.sidebar.posicion.${modo}`));
            navegacion.scrollTop = Number.isFinite(posicion) ? Math.max(0, posicion) : 0;
        } catch {
            // El almacenamiento puede estar deshabilitado por el navegador.
        }
    }

    window.addEventListener('pagehide', function () {
        guardarPosicionMenu(navEscritorio, modoSidebarEscritorio());
        guardarPosicionMenu(navMovil, 'movil');
    });

    window.addEventListener('pageshow', function (evento) {
        if (evento.persisted) {
            restaurarPosicionMenu(navEscritorio, modoSidebarEscritorio());
            restaurarPosicionMenu(navMovil, 'movil');
        }
    });

    function abrirMenuMovil() {
        if (sidebarMovil) {
            sidebarMovil.hidden = false;
            sidebarMovil.classList.remove('hidden');
            sidebarMovil.setAttribute('aria-hidden', 'false');
            restaurarPosicionMenu(navMovil, 'movil');
        }

        if (botonMenuMovil) {
            botonMenuMovil.setAttribute('aria-expanded', 'true');
        }
    }

    function cerrarMenuMovil() {
        if (sidebarMovil) {
            guardarPosicionMenu(navMovil, 'movil');
            sidebarMovil.hidden = true;
            sidebarMovil.classList.add('hidden');
            sidebarMovil.setAttribute('aria-hidden', 'true');
        }

        if (botonMenuMovil) {
            botonMenuMovil.setAttribute('aria-expanded', 'false');
        }
    }

    if (botonMenuMovil) {
        botonMenuMovil.addEventListener('click', abrirMenuMovil);
    }

    if (botonCerrarMenuMovil) {
        botonCerrarMenuMovil.addEventListener('click', cerrarMenuMovil);
    }

    if (fondoSidebarMovil) {
        fondoSidebarMovil.addEventListener('click', cerrarMenuMovil);
    }

    if (sidebarMovil) {
        sidebarMovil.querySelectorAll('a[href]').forEach(function (enlace) {
            enlace.addEventListener('click', cerrarMenuMovil);
        });
    }

    const botonesDesplegables =
        document.querySelectorAll('[data-sidebar-toggle]');

    botonesDesplegables.forEach(function (boton) {
        boton.addEventListener('click', function (evento) {
            const contenedor =
                boton.closest('#sidebar-escritorio, #sidebar-movil');

            const targetId = boton.dataset.target;
            const submenu = document.getElementById(targetId);

            if (!contenedor || !submenu) {
                return;
            }

            const esMovil =
                contenedor.id === 'sidebar-movil';

            if (esMovil) {
                evento.preventDefault();

                const estaAbierto =
                    !submenu.classList.contains('hidden');

                if (estaAbierto) {
                    submenu.classList.add('hidden');
                    boton.setAttribute('aria-expanded', 'false');

                    const chevron =
                        boton.querySelector('[data-sidebar-chevron]');

                    if (chevron) {
                        chevron.classList.remove('rotate-180');
                    }

                    desactivarBotonPadre(boton);
                    restaurarMenuRuta(contenedor);

                    return;
                }

                cerrarOtrosMenus(contenedor, boton);

                submenu.classList.remove('hidden');
                boton.setAttribute('aria-expanded', 'true');

                const chevron =
                    boton.querySelector('[data-sidebar-chevron]');

                if (chevron) {
                    chevron.classList.add('rotate-180');
                }

                activarBotonPadre(boton);

                return;
            }

            const estaContraido =
                boton.closest('#sidebar-escritorio')
                && sidebarEscritorio
                && sidebarEscritorio.classList.contains('w-20');

            if (estaContraido) {
                evento.preventDefault();

                if (
                    botonFlyoutActivo === boton
                    && flyout
                    && !flyout.classList.contains('hidden')
                ) {
                    cerrarFlyout();
                    restaurarMenuRuta(contenedor);

                    return;
                }

                cerrarOtrosMenus(contenedor, boton);
                activarBotonPadre(boton);
                abrirFlyout(boton, submenu);

                return;
            }

            const estaAbierto =
                !submenu.classList.contains('hidden');

            const primeraUrl = boton.dataset.firstUrl;

            if (!estaAbierto && primeraUrl) {
                const actual = window.location.pathname.replace(/\/$/, '');
                const destino = new URL(primeraUrl, window.location.origin).pathname.replace(/\/$/, '');

                if (actual !== destino) {
                    window.location.href = primeraUrl;
                    return;
                }
            }

            cerrarFlyout();

            if (estaAbierto) {
                submenu.classList.add('hidden');
                boton.setAttribute('aria-expanded', 'false');

                const chevron =
                    boton.querySelector('[data-sidebar-chevron]');

                if (chevron) {
                    chevron.classList.remove('rotate-180');
                }

                desactivarBotonPadre(boton);
                restaurarMenuRuta(contenedor);

                return;
            }

            cerrarOtrosMenus(contenedor, boton);

            submenu.classList.remove('hidden');
            boton.setAttribute('aria-expanded', 'true');

            const chevron =
                boton.querySelector('[data-sidebar-chevron]');

            if (chevron) {
                chevron.classList.add('rotate-180');
            }

            activarBotonPadre(boton);

        });
    });

    function abrirFlyout(boton, submenu) {
        if (!flyout || !flyoutTitulo || !flyoutContenido) {
            return;
        }

        botonFlyoutActivo = boton;

        flyoutTitulo.textContent =
            boton.dataset.sidebarTitle || '';

        flyoutContenido.innerHTML =
            submenu.innerHTML;

        flyoutContenido
            .querySelectorAll('span')
            .forEach(function (span) {
                span.classList.remove('hidden');
            });

        flyoutContenido
            .querySelectorAll('a[href]')
            .forEach(function (enlace) {
                enlace.addEventListener('click', cerrarFlyout);
            });

        flyout.classList.remove('hidden');

        const rectBoton =
            boton.getBoundingClientRect();

        const rectSidebar =
            sidebarEscritorio.getBoundingClientRect();

        flyout.style.left =
            `${rectSidebar.right + 12}px`;

        flyout.style.top =
            `${Math.max(12, rectBoton.top)}px`;

        requestAnimationFrame(function () {
            const rectFlyout =
                flyout.getBoundingClientRect();

            const limiteInferior =
                window.innerHeight - 12;

            if (rectFlyout.bottom > limiteInferior) {
                const nuevaPosicion =
                    Math.max(
                        12,
                        limiteInferior - rectFlyout.height
                    );

                flyout.style.top =
                    `${nuevaPosicion}px`;
            }
        });
    }

    function cerrarFlyout() {
        if (!flyout) {
            return;
        }

        flyout.classList.add('hidden');

        if (botonFlyoutActivo) {
            desactivarBotonPadre(botonFlyoutActivo);
        }

        botonFlyoutActivo = null;
    }

    document.addEventListener('click', function (evento) {
        if (!flyout || flyout.classList.contains('hidden')) {
            return;
        }

        const clicDentroFlyout =
            flyout.contains(evento.target);

        const clicEnBoton =
            evento.target.closest('[data-sidebar-toggle]');

        if (!clicDentroFlyout && !clicEnBoton) {
            const contenedor =
                sidebarEscritorio;

            cerrarFlyout();

            if (contenedor) {
                restaurarMenuRuta(contenedor);
            }
        }
    });

    window.addEventListener('resize', function () {
        cerrarFlyout();

        if (sidebarEscritorio) {
            restaurarMenuRuta(sidebarEscritorio);
        }
    });

    if (navEscritorio) {
        navEscritorio.addEventListener('scroll', function () {
            cerrarFlyout();
            restaurarMenuRuta(sidebarEscritorio);
        });
    }

    function cerrarOtrosMenus(contenedor, botonActual) {
        contenedor
            .querySelectorAll('[data-sidebar-toggle]')
            .forEach(function (boton) {
                if (boton === botonActual) {
                    return;
                }

                const targetId =
                    boton.dataset.target;

                const submenu =
                    document.getElementById(targetId);

                if (submenu) {
                    submenu.classList.add('hidden');
                }

                boton.setAttribute(
                    'aria-expanded',
                    'false'
                );

                const chevron =
                    boton.querySelector(
                        '[data-sidebar-chevron]'
                    );

                if (chevron) {
                    chevron.classList.remove('rotate-180');
                }

                desactivarBotonPadre(boton);
            });

        contenedor
            .querySelectorAll('[data-sidebar-main]')
            .forEach(function (elemento) {
                if (elemento === botonActual) {
                    return;
                }

                desactivarBotonPadre(elemento);
            });
    }

    function restaurarMenuRuta(contenedor) {
        if (!contenedor) {
            return;
        }

        const menuRutaActivo =
            contenedor.querySelector(
                '[data-sidebar-toggle][data-route-active="true"]'
            );

        if (menuRutaActivo) {
            activarBotonPadre(menuRutaActivo);

            return;
        }

        const inicio =
            contenedor.querySelector(
                'a[data-sidebar-main]'
            );

        if (
            inicio
            && window.location.pathname.replace(/\/$/, '') === ''
        ) {
            activarBotonPadre(inicio);
        }
    }

    function activarBotonPadre(boton) {
        boton.classList.remove(
            'text-texto',
            'hover:bg-fondo',
            'bg-fondo',
            'text-institucional'
        );

        boton.classList.add(
            'bg-institucional',
            'text-white'
        );
    }

    function desactivarBotonPadre(boton) {
        boton.classList.remove(
            'bg-institucional',
            'text-white',
            'bg-fondo',
            'text-institucional'
        );

        boton.classList.add(
            'text-texto',
            'hover:bg-fondo'
        );
    }

    if (sidebarEscritorio && botonSidebar) {
        const sidebarColapsado =
            document.documentElement.dataset.sidebarInicial === 'contraido';

        if (sidebarColapsado) {
            colapsarSidebar(false);
        }
        delete document.documentElement.dataset.sidebarInicial;
        restaurarPosicionMenu(navEscritorio, modoSidebarEscritorio());

        botonSidebar.addEventListener('click', function () {
            const estaColapsado =
                sidebarEscritorio.classList.contains('w-20');

            guardarPosicionMenu(navEscritorio, modoSidebarEscritorio());
            cerrarFlyout();

            if (estaColapsado) {
                expandirSidebar(true);
            } else {
                colapsarSidebar(true);
            }

            restaurarPosicionMenu(navEscritorio, modoSidebarEscritorio());
        });
    }

    function colapsarSidebar(guardarEstado = true) {
        if (!sidebarEscritorio) {
            return;
        }

        cerrarFlyout();

        sidebarEscritorio.classList.remove('w-64');
        sidebarEscritorio.classList.add('w-20');

        const contenidoPrincipal =
            document.getElementById('contenido-principal');

        if (contenidoPrincipal) {
            contenidoPrincipal.classList.remove('lg:ml-64');
            contenidoPrincipal.classList.add('lg:ml-20');
        }

        sidebarEscritorio
            .querySelectorAll('[data-sidebar-submenu]')
            .forEach(function (submenu) {
                submenu.dataset.estabaAbierto =
                    submenu.classList.contains('hidden')
                        ? 'false'
                        : 'true';

                submenu.classList.add('hidden');
            });

        sidebarEscritorio
            .querySelectorAll('nav span')
            .forEach(function (texto) {
                texto.classList.add('hidden');
            });

        sidebarEscritorio
            .querySelectorAll('[data-sidebar-row]')
            .forEach(function (fila) {
                fila.classList.remove('gap-3');
                fila.classList.add('justify-center');
            });

        sidebarEscritorio
            .querySelectorAll('[data-sidebar-chevron]')
            .forEach(function (flecha) {
                flecha.classList.add('hidden');
            });

        const navegacion =
            sidebarEscritorio.querySelector('nav');

        if (navegacion) {
            navegacion.classList.remove('px-4');
            navegacion.classList.add('px-2');
        }

        const logoCompleto =
            document.getElementById('logo-prosec-completo');

        const logoMini =
            document.getElementById('logo-prosec-mini');

        const contenedorLogo =
            document.getElementById('contenedor-logo-prosec');

        if (logoCompleto) {
            logoCompleto.hidden = true;
            logoCompleto.classList.add('hidden');
        }

        if (logoMini) {
            logoMini.hidden = false;
            logoMini.classList.remove('hidden');
        }

        if (contenedorLogo) {
            contenedorLogo.classList.remove('px-5');
            contenedorLogo.classList.add('px-2');
        }

        cambiarIconoSidebar('derecha');

        if (botonSidebar) {
            botonSidebar.setAttribute(
                'aria-label',
                'Expandir menú lateral'
            );

            botonSidebar.setAttribute(
                'aria-expanded',
                'false'
            );
        }

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

        cerrarFlyout();

        sidebarEscritorio.classList.remove('w-20');
        sidebarEscritorio.classList.add('w-64');

        const contenidoPrincipal =
            document.getElementById('contenido-principal');

        if (contenidoPrincipal) {
            contenidoPrincipal.classList.remove('lg:ml-20');
            contenidoPrincipal.classList.add('lg:ml-64');
        }

        sidebarEscritorio
            .querySelectorAll('nav span')
            .forEach(function (texto) {
                texto.classList.remove('hidden');
            });

        sidebarEscritorio
            .querySelectorAll('[data-sidebar-row]')
            .forEach(function (fila) {
                fila.classList.remove('justify-center');
                fila.classList.add('gap-3');
            });

        sidebarEscritorio
            .querySelectorAll('[data-sidebar-chevron]')
            .forEach(function (flecha) {
                flecha.classList.remove('hidden');
            });

        sidebarEscritorio
            .querySelectorAll('[data-sidebar-submenu]')
            .forEach(function (submenu) {
                if (submenu.dataset.estabaAbierto === 'true') {
                    submenu.classList.remove('hidden');
                }
            });

        const navegacion =
            sidebarEscritorio.querySelector('nav');

        if (navegacion) {
            navegacion.classList.remove('px-2');
            navegacion.classList.add('px-4');
        }

        const logoCompleto =
            document.getElementById('logo-prosec-completo');

        const logoMini =
            document.getElementById('logo-prosec-mini');

        const contenedorLogo =
            document.getElementById('contenedor-logo-prosec');

        if (logoCompleto) {
            logoCompleto.hidden = false;
            logoCompleto.classList.remove('hidden');
        }

        if (logoMini) {
            logoMini.hidden = true;
            logoMini.classList.add('hidden');
        }

        if (contenedorLogo) {
            contenedorLogo.classList.remove('px-2');
            contenedorLogo.classList.add('px-5');
        }

        cambiarIconoSidebar('izquierda');

        if (botonSidebar) {
            botonSidebar.setAttribute(
                'aria-label',
                'Contraer menú lateral'
            );

            botonSidebar.setAttribute(
                'aria-expanded',
                'true'
            );
        }

        restaurarMenuRuta(sidebarEscritorio);

        if (guardarEstado) {
            localStorage.setItem(
                'sidebarColapsado',
                'false'
            );
        }
    }

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

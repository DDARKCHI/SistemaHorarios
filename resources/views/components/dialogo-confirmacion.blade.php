<dialog
    id="confirmacion-prosec"
    aria-labelledby="confirmacion-titulo"
    aria-describedby="confirmacion-mensaje"
    class="fixed inset-0 m-auto max-h-[calc(100dvh_-_2rem)] w-[calc(100%_-_2rem)] max-w-md overflow-y-auto rounded-xl border border-borde bg-superficie text-texto shadow-xl backdrop:bg-gray-900/40"
>
    <div class="flex items-start gap-3 px-5 pb-5 pt-6 sm:px-6">
        <span class="inline-flex size-10 shrink-0 items-center justify-center rounded-lg bg-institucional/10 text-institucional" aria-hidden="true">
            <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round">
                <circle cx="12" cy="12" r="9"/>
                <path d="M12 7.5v5M12 16.5h.01"/>
            </svg>
        </span>
        <div class="min-w-0">
            <h2 id="confirmacion-titulo" class="text-lg font-semibold leading-6">Confirmar acción</h2>
            <p id="confirmacion-mensaje" class="mt-1.5 text-sm leading-6 text-texto-secundario"></p>
            <div data-confirmacion-detalle class="mt-3 rounded-lg bg-fondo px-3 py-2" hidden>
                <p data-confirmacion-etiqueta class="text-xs font-medium text-texto-secundario">Selección</p>
                <p data-confirmacion-valor class="mt-0.5 whitespace-pre-line text-sm font-semibold leading-6"></p>
            </div>
        </div>
    </div>
    <form method="dialog" class="flex flex-wrap justify-end gap-2 border-t border-borde bg-fondo px-5 py-4 sm:px-6">
        <button type="submit" value="cancelar" autofocus class="boton boton-secundario max-sm:flex-1">Volver</button>
        <button type="submit" value="confirmar" data-confirmacion-aceptar class="boton boton-primario max-sm:flex-1">Confirmar</button>
    </form>
</dialog>

<dialog
    id="estado-carga-prosec"
    aria-labelledby="estado-carga-titulo"
    aria-describedby="estado-carga-mensaje"
    data-carga-rutas="{{ json_encode([
        route('programacion.importar.analizar') => 'analisis',
        route('programacion.importar.confirmar') => 'importacion',
        route('programacion.index') => 'consulta',
        route('programacion.semestral') => 'consulta',
        route('salas.horario') => 'consulta',
        route('programacion.validacion') => 'revision',
        route('programacion.semestral.generar') => 'generacion',
    ]) }}"
    class="fixed inset-0 m-auto max-h-[calc(100dvh_-_2rem)] w-[calc(100%_-_2rem)] max-w-md overflow-y-auto rounded-xl border border-borde bg-superficie p-0 text-texto shadow-xl backdrop:bg-gray-900/40"
>
    <div class="p-5 sm:p-6">
        <div class="flex items-start gap-3">
            <span class="inline-flex size-10 shrink-0 items-center justify-center rounded-lg bg-institucional/10 text-institucional" aria-hidden="true">
                <svg class="size-5 motion-safe:animate-spin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M21 12a9 9 0 1 1-9-9"/></svg>
            </span>
            <div class="min-w-0">
                <h2 id="estado-carga-titulo" class="text-lg font-semibold leading-6" tabindex="-1">Procesando solicitud</h2>
                <p id="estado-carga-mensaje" class="mt-1.5 text-sm leading-6 text-texto-secundario"></p>
            </div>
        </div>
        <div class="barra-carga mt-5" role="progressbar" aria-label="Operación en curso">
            <span aria-hidden="true"></span>
        </div>
        <p data-carga-espera class="mt-4 text-sm leading-6 text-texto-secundario" role="status">El resultado aparecerá al finalizar.</p>
    </div>
    <div class="flex items-center justify-between gap-3 border-t border-borde bg-fondo px-5 py-3 text-xs text-texto-secundario sm:px-6">
        <span>Mantén esta pestaña abierta.</span>
        <span data-carga-tiempo class="shrink-0 tabular-nums" aria-hidden="true">00:00</span>
    </div>
</dialog>

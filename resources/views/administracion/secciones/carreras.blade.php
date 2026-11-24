<x-seccion-formulario titulo="Carreras de la sección *" id="carreras-seccion" data-carreras-seccion>
    <p id="ayuda-carreras" class="mb-3 text-sm text-texto-secundario">Elige una o varias carreras. El semestre indicado se aplica a todas.</p>

    <details class="peer" @if ($errors->has('carreras') || $errors->has('carreras.*')) open @endif>
        <summary class="boton boton-secundario w-full flex-wrap justify-start text-left">
            <span>Elegir carreras</span>
            <span data-total-carreras aria-live="polite" class="ml-auto shrink-0 text-xs font-medium text-texto-secundario"></span>
        </summary>
        <div class="mt-3 space-y-2">
            <div data-buscador-carreras hidden>
                <label for="buscar-carreras" class="sr-only">Buscar carreras por nombre o código</label>
                <input type="search" id="buscar-carreras" placeholder="Buscar por nombre o código" autocomplete="off" aria-controls="opciones-carreras" class="{{ $campo }}">
            </div>
            <p data-estado-carreras role="status" class="rounded-lg bg-fondo px-3 py-2 text-sm text-texto-secundario" hidden></p>
            @if ($carreras->isNotEmpty())
                <fieldset id="opciones-carreras" aria-describedby="ayuda-carreras" class="min-w-0">
                    <legend class="sr-only">Carreras disponibles</legend>
                    <div class="max-h-52 divide-y divide-borde overflow-y-auto rounded-lg border border-borde">
                        @foreach ($carreras as $carrera)
                            <label data-carrera-institucion="{{ $carrera->universidad_id }}" class="flex min-h-11 cursor-pointer items-center gap-3 px-3 py-2 hover:bg-fondo has-checked:bg-institucional/5 focus-within:bg-fondo">
                                <input type="checkbox" name="carreras[]" value="{{ $carrera->id }}" @checked(in_array((string) $carrera->id, $elegidas, true)) class="size-4 shrink-0 accent-institucional">
                                <span class="min-w-0">
                                    <span data-nombre-carrera class="block break-words font-medium">{{ $carrera->nombre }}</span>
                                    <span class="block text-xs text-texto-secundario">{{ implode(' · ', array_filter([$carrera->codigo, $carrera->universidad->sigla, $carrera->estado ? null : 'Inactiva'])) }}</span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                </fieldset>
            @else
                <p class="rounded-lg bg-fondo px-3 py-2 text-sm text-texto-secundario">No hay carreras registradas.</p>
            @endif
        </div>
    </details>
    <p data-resumen-carreras class="mt-2 max-h-16 overflow-y-auto break-words text-xs leading-5 text-texto-secundario peer-open:hidden" hidden></p>
</x-seccion-formulario>

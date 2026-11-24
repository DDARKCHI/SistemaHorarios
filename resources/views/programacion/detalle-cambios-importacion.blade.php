<div class="@container/cambios space-y-3" data-detalle-importacion>
    @if ($gruposCambios->count() > 6)
        <div>
            <label for="buscar-cambios-importacion" class="sr-only">Buscar en los cambios del archivo</label>
            <input id="buscar-cambios-importacion" type="search" autocomplete="off" placeholder="Buscar por nombre, dato o valor" class="h-11 w-full rounded-lg border border-borde bg-superficie px-3 text-sm focus:border-institucional focus:outline-none focus:ring-1 focus:ring-institucional">
        </div>
        <p data-cambios-sin-resultados role="status" class="rounded-lg bg-fondo px-4 py-3 text-sm text-texto-secundario" hidden>No se encontraron cambios para esa búsqueda.</p>
    @endif

    @foreach ($gruposCambios as $cambios)
        @php
            $registro = $cambios->first();
            $abierto = $gruposCambios->count() <= 6;
        @endphp
        <details data-registro-cambio class="min-w-0 overflow-hidden rounded-lg border border-borde" @if ($abierto) open @endif>
            <summary class="cursor-pointer px-4 py-3 text-sm marker:text-texto-secundario focus-visible:outline-2 focus-visible:outline-institucional focus-visible:outline-offset-[-2px]">
                <span class="ml-1 inline-flex max-w-[calc(100%_-_1.5rem)] flex-wrap items-baseline gap-x-2 gap-y-1 align-top">
                    <span class="text-xs font-semibold text-texto-secundario">{{ $registro['entidad'] }}</span>
                    <span class="min-w-0 break-words font-semibold">{{ $registro['nombre'] }}</span>
                    @if ($cambios->count() > 1)
                        <span class="text-xs text-texto-secundario">{{ $cambios->count() }} datos</span>
                    @endif
                </span>
            </summary>
            <dl class="divide-y divide-borde border-t border-borde">
                @foreach ($cambios as $cambio)
                    @php
                        $tipo = $cambio['tipo'];
                        $anterior = $cambio['anterior'] ?? null;
                        $nuevo = $cambio['nuevo'] ?? null;
                    @endphp
                    <div class="grid min-w-0 gap-3 p-4 @min-[42rem]/cambios:grid-cols-[minmax(0,0.8fr)_minmax(0,2fr)] @min-[42rem]/cambios:gap-5">
                        <dt class="flex flex-wrap items-start gap-2 @min-[42rem]/cambios:flex-col">
                            <span class="text-sm font-medium">{{ $cambio['campo'] === 'Tipo de profesor' ? 'Tipo de docente' : $cambio['campo'] }}</span>
                            <span @class([
                                'rounded-md px-2 py-0.5 text-xs font-semibold',
                                'bg-green-50 text-green-800' => $tipo === 'agregado',
                                'bg-institucional/5 text-institucional' => $tipo === 'modificado',
                                'bg-red-50 text-red-800' => $tipo === 'eliminado',
                            ])>{{ match ($tipo) { 'agregado' => 'Agregado', 'eliminado' => 'Retirado del archivo', default => 'Modificado' } }}</span>
                        </dt>
                        <dd class="grid min-w-0 gap-3 @min-[32rem]/cambios:grid-cols-2">
                            @if ($tipo !== 'agregado')
                                <div @class(['min-w-0 rounded-lg bg-fondo px-3 py-2.5', '@min-[32rem]/cambios:col-span-2' => $tipo === 'eliminado'])>
                                    <p class="text-xs font-medium text-texto-secundario">Anterior</p>
                                    <p class="mt-1 whitespace-pre-line break-words text-sm">{{ filled($anterior) ? $anterior : 'Sin dato' }}</p>
                                </div>
                            @endif
                            @if ($tipo !== 'eliminado')
                                <div @class(['min-w-0 rounded-lg bg-institucional/5 px-3 py-2.5', '@min-[32rem]/cambios:col-span-2' => $tipo === 'agregado'])>
                                    <p class="text-xs font-medium text-texto-secundario">Archivo nuevo</p>
                                    <p class="mt-1 whitespace-pre-line break-words text-sm">{{ filled($nuevo) ? $nuevo : 'Sin dato' }}</p>
                                </div>
                            @endif
                        </dd>
                    </div>
                @endforeach
            </dl>
        </details>
    @endforeach
</div>

@if ($gruposCambios->count() > 6)
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const contenedor = document.querySelector('[data-detalle-importacion]');
            const buscador = contenedor.querySelector('#buscar-cambios-importacion');
            const registros = Array.from(contenedor.querySelectorAll('[data-registro-cambio]'));
            const estado = contenedor.querySelector('[data-cambios-sin-resultados]');
            const normalizar = (valor) => valor.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLocaleLowerCase('es');

            buscador.addEventListener('input', () => {
                const palabras = normalizar(buscador.value.trim()).split(/\s+/);
                let visibles = 0;
                for (const registro of registros) {
                    registro.hidden = !palabras.every((palabra) => normalizar(registro.textContent).includes(palabra));
                    registro.open = buscador.value.trim() !== '' && !registro.hidden;
                    if (!registro.hidden) visibles++;
                }
                estado.hidden = visibles > 0;
            });
        });
    </script>
@endif

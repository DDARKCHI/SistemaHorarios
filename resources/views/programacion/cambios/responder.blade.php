<!DOCTYPE html>
<html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="referrer" content="no-referrer"><title>Responder cambio de horario | {{ $identidad['nombre'] }}</title>@vite(['resources/css/app.css', 'resources/js/app.js'])<x-favicon />
    <x-identidad-estilos /></head><body class="min-h-screen bg-fondo text-texto">
<header class="border-b border-borde bg-superficie"><div class="mx-auto max-w-4xl px-4 py-5 sm:px-6"><img src="{{ $identidad['principal_url'] }}" alt="{{ $identidad['nombre'] }}" class="w-40"></div></header>
<main class="mx-auto max-w-4xl space-y-6 px-4 py-6 sm:px-6">
    <x-encabezado-pagina titulo="Solicitud de cambio de horario">
        {{ $respuesta->profesor->nombre }} {{ $respuesta->profesor->apellido }}
    </x-encabezado-pagina>

    @include('disponibilidad.avisos')

    <section class="space-y-2 rounded-xl border border-borde bg-superficie p-4 shadow-sm sm:p-5">
        <h2 class="font-semibold">{{ $solicitud->origen['resumen'] ?? 'Motivo del cambio' }}</h2>
        @if (filled($solicitud->origen['actual'] ?? null))
            <p class="text-sm text-texto-secundario"><strong class="font-semibold">Horario de origen</strong> · {{ $solicitud->origen['actual'] }}</p>
        @endif
        <p class="text-sm">{{ $solicitud->motivo }}</p>
        @if ($solicitud->descripcion)
            <p class="whitespace-pre-line text-sm text-texto-secundario">{{ $solicitud->descripcion }}</p>
        @endif
    </section>

    @if ($abierta)
        <form method="post" action="{{ request()->fullUrl() }}" class="space-y-5 rounded-xl border border-borde bg-superficie p-4 shadow-sm sm:p-5">
            @csrf
            <fieldset>
                <legend class="font-semibold">Elige la alternativa que aceptas</legend>
                <div class="mt-3 space-y-3">
                    @foreach ($solicitud->opciones as $opcion)
                        <label class="flex cursor-pointer items-center gap-3 rounded-lg border border-borde p-3 text-sm has-[:checked]:border-institucional has-[:checked]:bg-red-50">
                            <input type="radio" name="opcion_id" value="{{ $opcion->id }}" @checked(old('opcion_id') == $opcion->id) class="shrink-0 accent-institucional">
                            <span>{{ $opcion->datos['nombre'] ?? 'Alternativa' }}</span>
                        </label>
                    @endforeach
                </div>
            </fieldset>
            <p class="text-sm text-texto-secundario">La coordinación académica revisará las respuestas antes de aplicar el cambio.</p>
            <div>
                <label for="observaciones" class="mb-2 block text-sm font-semibold">Comentarios</label>
                <textarea placeholder="Ej. Puedo realizar la clase en la alternativa seleccionada." id="observaciones" name="observaciones" rows="3" maxlength="2000" aria-describedby="ayuda-observaciones" class="w-full rounded-lg border border-borde p-3">{{ old('observaciones') }}</textarea>
                <p id="ayuda-observaciones" class="mt-1 text-xs text-texto-secundario">Indica un motivo si rechazas las alternativas.</p>
            </div>
            <div class="flex flex-col justify-between gap-3 border-t border-borde pt-4 lg:flex-row lg:items-center">
                <p class="text-xs text-texto-secundario">Envía tu única respuesta hasta el {{ $solicitud->fecha_limite->format('d/m/Y H:i') }}.</p>
                <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:justify-end">
                    <button type="submit" name="decision" value="rechazar" data-confirmar="Se registrará tu rechazo de las alternativas de horario." data-confirmar-titulo="Rechazar alternativas de horario" data-confirmar-accion="Enviar rechazo" class="boton boton-secundario">Rechazar alternativas</button>
                    <button type="submit" name="decision" value="aceptar" data-confirmar="Se registrará tu aceptación de la alternativa que seleccionaste." data-confirmar-titulo="Aceptar horario propuesto" data-confirmar-accion="Enviar aceptación" class="boton boton-primario">Aceptar la seleccionada</button>
                </div>
            </div>
        </form>
    @else
        <section role="status" class="space-y-2 rounded-xl border border-borde bg-superficie p-4 shadow-sm sm:p-5">
            <h2 class="font-semibold">{{ $respuesta->estado !== 'Pendiente' ? 'Tu respuesta ya fue recibida' : 'Esta solicitud ya no está disponible' }}</h2>
            <p class="text-sm text-texto-secundario">Si necesitas comunicar cambios, contacta a la coordinación académica.</p>
        </section>
    @endif
</main>
    <x-dialogo-confirmacion />
</body></html>

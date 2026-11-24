@php
    $vencida = $solicitud->estado === 'Pendiente' && $solicitud->fecha_limite?->copy()->endOfDay()->isPast();
    $estado = $vencida ? 'Vencida' : $solicitud->estado;
@endphp

<div class="space-y-3">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <p @class([
            'inline-flex rounded-md px-2 py-1 text-xs font-semibold',
            'bg-green-50 text-green-800' => $estado === 'Aprobada',
            'bg-sky-50 text-sky-800' => $estado === 'En revisión',
            'bg-amber-50 text-amber-800' => in_array($estado, ['Pendiente', 'Vencida'], true),
            'bg-fondo text-texto-secundario' => in_array($estado, ['Cancelada', 'Rechazada'], true),
        ])>{{ $estado === 'Pendiente' ? 'Pendiente de respuesta' : $estado }}</p>
        @if ($solicitud->fecha_limite)
            <p class="text-sm text-texto-secundario">Fecha límite {{ $solicitud->fecha_limite->format('d/m/Y') }}</p>
        @endif
    </div>
    @if ($solicitud->observaciones)
        <p class="whitespace-pre-line text-sm">{{ $solicitud->observaciones }}</p>
    @endif
    @if ($enlace)
        <div>
            <label for="enlace-docente" class="mb-2 block text-sm font-semibold">Enlace privado para el docente</label>
            <div class="flex flex-col gap-3 sm:flex-row">
                <input id="enlace-docente" value="{{ $enlace }}" readonly class="h-11 min-w-0 flex-1 rounded-lg border border-borde bg-fondo px-3 text-sm">
                <button type="button" id="copiar-enlace" class="boton boton-secundario shrink-0">Copiar enlace</button>
            </div>
            <p id="estado-copia" role="status" class="mt-2 text-xs text-texto-secundario">Comparte el enlace únicamente con este docente.</p>
        </div>
    @elseif ($estado === 'Pendiente' && $usaCuenta)
        <p class="text-sm text-texto-secundario">El docente puede responder desde Mi disponibilidad.</p>
    @endif
    @if ($estado === 'Pendiente')
        <form method="post" action="{{ route('disponibilidad.cancelar', $solicitud) }}" class="border-t border-borde pt-3" data-confirmar="El docente ya no podrá responder a esta solicitud." data-confirmar-titulo="Cancelar solicitud de disponibilidad" data-confirmar-accion="Cancelar solicitud">
            @csrf
            <button type="submit" class="boton boton-secundario w-full sm:w-auto">Cancelar solicitud</button>
        </form>
    @endif
</div>

@if ($respuesta)
    <div class="space-y-3 border-t border-borde pt-4">
        <div class="flex flex-wrap justify-between gap-2">
            <h3 class="font-semibold">Disponibilidad informada</h3>
            @if ($respuesta->fecha_respuesta)
                <p class="text-sm text-texto-secundario">Enviada el {{ $respuesta->fecha_respuesta->format('d/m/Y H:i') }}</p>
            @endif
        </div>
        @if ($respuesta->bloques)
            @if (empty($respuesta->bloques['disponibles']))
                <p class="text-sm text-texto-secundario">El docente declaró que no tiene disponibilidad en este período.</p>
            @endif
            @include('disponibilidad.calendario', ['editable' => false])
        @else
            <p class="text-sm text-texto-secundario">Esta respuesta anterior no contiene un calendario para revisión.</p>
        @endif
        @if ($respuesta->observaciones)
            <p class="whitespace-pre-line text-sm">{{ $respuesta->observaciones }}</p>
        @endif
        @if ($respuesta->revisado_en)
            <p class="text-sm text-texto-secundario">{{ $respuesta->respuesta }} el {{ $respuesta->revisado_en->format('d/m/Y H:i') }}.</p>
            @if ($respuesta->motivo_revision)
                <p class="whitespace-pre-line text-sm">{{ $respuesta->motivo_revision }}</p>
            @endif
        @endif
    </div>
    @if ($estado === 'En revisión')
        <form method="post" action="{{ route('disponibilidad.revisar', $solicitud) }}" class="space-y-4 border-t border-borde pt-4">
            @csrf
            <div>
                <h3 class="font-semibold">Revisar respuesta</h3>
                <p class="mt-1 text-sm text-texto-secundario">Al aprobar, esta respuesta pasará a ser la disponibilidad vigente del docente.</p>
            </div>
            <div>
                <label for="motivo" class="mb-2 block text-sm font-semibold">Motivo o comentario</label>
                <textarea placeholder="Ej. Revisa los bloques del lunes y envía nuevamente tu disponibilidad." id="motivo" name="motivo" rows="2" maxlength="2000" aria-describedby="ayuda-motivo" class="w-full rounded-lg border border-borde p-3">{{ old('motivo') }}</textarea>
                <p id="ayuda-motivo" class="mt-1 text-xs text-texto-secundario">Obligatorio si rechazas la respuesta.</p>
            </div>
            <div class="flex flex-col gap-3 border-t border-borde pt-4 sm:flex-row sm:flex-wrap sm:justify-end">
                <button type="submit" name="decision" value="rechazar" data-confirmar="La respuesta quedará rechazada y se guardará el motivo indicado." data-confirmar-titulo="Rechazar disponibilidad" data-confirmar-accion="Rechazar" class="boton boton-secundario">Rechazar respuesta</button>
                <button type="submit" name="decision" value="aprobar" data-confirmar="La respuesta seleccionada pasará a ser la disponibilidad vigente de este docente." data-confirmar-titulo="Aprobar disponibilidad" data-confirmar-accion="Aprobar" class="boton boton-primario">Aprobar disponibilidad</button>
            </div>
        </form>
    @endif
@endif

@if ($enlace)
    <script>
    document.getElementById('copiar-enlace')?.addEventListener('click', async () => {
        const input = document.getElementById('enlace-docente');
        const estado = document.getElementById('estado-copia');
        try {
            await navigator.clipboard.writeText(input.value);
            estado.textContent = 'Enlace copiado.';
        } catch {
            input.select();
            estado.textContent = 'Enlace seleccionado. Usa Copiar para compartirlo.';
        }
    });
    </script>
@endif

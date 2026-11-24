@php
    $recordatorios = $solicitud->recordatorios->keyBy('dias_antes');
    $programados = $invitacion && $solicitud->estado === 'Pendiente'
        && $solicitud->fecha_limite?->isAfter(today()) && $periodo?->estado
        && $periodo->universidad->estado && $solicitud->respuestas->isEmpty();
    $hitos = collect(\App\Models\RecordatorioDisponibilidad::DIAS_ANTES)
        ->mapWithKeys(fn ($dias) => [$dias => $recordatorios->get($dias)?->enviar_el ?? $solicitud->fecha_limite?->copy()->subDays($dias)])
        ->filter(fn ($fecha, $dias) => $recordatorios->has($dias)
            || ($programados && $fecha?->gte(today()) && $invitacion->enviado_en->lt($fecha)));
@endphp
@if ($hitos->isNotEmpty())
    <div class="mt-2 space-y-1 border-t border-borde pt-2 text-xs">
        <p class="font-medium">Recordatorios</p>
        <ul class="space-y-1.5 text-texto-secundario">
            @foreach ($hitos as $diasAntes => $fechaRecordatorio)
                @php($recordatorio = $recordatorios->get($diasAntes))
                <li>
                    <span>{{ $diasAntes === 1 ? '1 día antes' : '3 días antes' }}</span>
                    <span class="{{ $recordatorio?->estado === 'enviado' ? 'font-medium text-green-800' : ($recordatorio?->estado === 'fallido' ? 'font-medium text-red-800' : '') }}">
                        · {{ match ($recordatorio?->estado) {
                            'enviado' => 'Enviado',
                            'fallido' => 'Error de envío',
                            'omitido' => 'Omitido',
                            'en_proceso' => 'En proceso',
                            'pendiente' => 'En cola',
                            default => 'Previsto',
                        } }}
                    </span>
                    <span class="whitespace-nowrap">{{ ($recordatorio?->enviado_en ?? $fechaRecordatorio)?->format('d/m') }}</span>
                </li>
            @endforeach
        </ul>
    </div>
@endif

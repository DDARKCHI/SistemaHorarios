@php
    $nombreModulo = $horario['ramo_codigo'].' · '.$horario['ramo'];
    $informacionModulo = ucfirst($horario['actividad']).' · Sección '.$horario['seccion'].($horario['grupo'] ? ' · Grupo '.$horario['grupo'] : ' · Sin grupo');
    $motivosModulo = $horario['motivos'] ?? [];
    $elementoModulo = $modoEdicion ? 'button' : 'div';
    $docentesModulo = collect($horario['docentes'])->pluck('nombre')->implode(', ') ?: 'Docente pendiente';
    $espaciosModulo = collect($horario['espacios'])->pluck('nombre')->implode(' · ') ?: 'Espacio pendiente';
    $rotuloNivel = match ($horario['nivel'] ?? null) {
        'ambar' => 'Requiere revisión', 'rojo' => 'Conflicto', default => null,
    };
@endphp
<article class="panel-modulo" data-panel-horario="{{ $horario['id'] }}" data-semestre="{{ $horario['semestre'] }}"
    data-panel-docentes-ids="{{ collect($horario['docentes'])->pluck('id')->implode(' ') }}"
    @if ($modoEdicion) draggable="true" @endif
    data-nivel="{{ $horario['nivel'] ?? 'verde' }}" @if (! $mostrarModulo) hidden @endif>
    <{{ $elementoModulo }} class="panel-modulo-control"
        @if ($modoEdicion)
            type="button" data-panel-mover aria-pressed="false"
            data-panel-evaluar="{{ route('programacion.panel-carrera.evaluar', ['horario' => $horario['id'], 'carrera_id' => $carrera->id]) }}"
            data-panel-guardar="{{ route('programacion.panel-carrera.mover', ['horario' => $horario['id']]) }}"
            aria-label="Mover {{ $nombreModulo }}, {{ $informacionModulo }}. {{ $docentesModulo }}. {{ $espaciosModulo }}. {{ $rotuloNivel }}"
        @endif>
        <span class="panel-modulo-seleccion">
            <span><strong>{{ $horario['ramo_codigo'] }}</strong><span>{{ $horario['ramo'] }}</span></span>
            @if ($modoEdicion)
                <svg viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path d="M5.5 3a1 1 0 1 0 0-2 1 1 0 0 0 0 2Zm5 0a1 1 0 1 0 0-2 1 1 0 0 0 0 2Zm-5 4a1 1 0 1 0 0-2 1 1 0 0 0 0 2Zm5 0a1 1 0 1 0 0-2 1 1 0 0 0 0 2Zm-5 4a1 1 0 1 0 0-2 1 1 0 0 0 0 2Zm5 0a1 1 0 1 0 0-2 1 1 0 0 0 0 2Zm-5 4a1 1 0 1 0 0-2 1 1 0 0 0 0 2Zm5 0a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z"/></svg>
            @endif
        </span>
        <span class="panel-detalle-modulo">
            <span>{{ $informacionModulo }}</span>
            <span>{{ $docentesModulo }}</span>
            <span>{{ $espaciosModulo }}</span>
            @if ($rotuloNivel)<span class="panel-chip" data-nivel="{{ $horario['nivel'] }}" title="{{ implode(' ', $motivosModulo) }}">{{ $rotuloNivel }}</span>@endif
        </span>
    </{{ $elementoModulo }}>
</article>

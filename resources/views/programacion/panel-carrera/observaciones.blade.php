<details class="panel-observaciones panel-observaciones-horario">
    <summary><span>Revisar observaciones</span><span class="panel-chip">{{ number_format(count($panel['observaciones']), 0, ',', '.') }}</span></summary>
    @foreach ($panel['observaciones'] as $observacion)
        <article data-panel-observacion="{{ implode(' ', $observacion['horarios']) }}">
            <h3><span class="panel-chip" data-nivel="{{ $observacion['nivel'] ?? 'ambar' }}">{{ ($observacion['nivel'] ?? '') === 'rojo' ? 'Conflicto' : 'Revisión' }}</span> {{ $observacion['titulo'] }}</h3>
            <p>{{ $observacion['dia'] }} {{ $observacion['intervalo'] }} · {{ $observacion['mensaje'] }}</p>
        </article>
    @endforeach
</details>

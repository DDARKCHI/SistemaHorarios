<section class="panel-tarjeta panel-calendario" aria-labelledby="panel-laboratorios-titulo">
    <header class="panel-seccion-cabecera">
        <div>
            <h2 id="panel-laboratorios-titulo">Ocupación de laboratorios</h2>
            @if ($panel['laboratorios'])
                <p>Incluye las clases de otras carreras.</p>
            @endif
        </div>
        @if ($panel['laboratorios'])
            <label class="panel-campo panel-campo-acotado" for="panel-laboratorio">Laboratorio
                <select id="panel-laboratorio" data-panel-laboratorio>
                    @foreach ($panel['laboratorios'] as $laboratorio)
                        <option value="{{ $laboratorio['tipo'] }}:{{ $laboratorio['id'] }}">{{ $laboratorio['nombre'] }} · {{ $laboratorio['tipo'] === 'fijo' ? 'Fijo' : 'Móvil' }}</option>
                    @endforeach
                </select>
            </label>
        @endif
    </header>
    @if ($panel['laboratorios'])
        @php
            $primerLaboratorio = $panel['laboratorios'][0];
            $modulosVisibles = array_fill_keys(collect($primerLaboratorio['bloques'])->pluck('horarios')->flatten()->all(), true);
        @endphp
        <div class="panel-resumen-grilla" data-panel-detalle-laboratorio>
            <div><h3 data-panel-lab-nombre>{{ $primerLaboratorio['nombre'] }}</h3><p data-panel-lab-sede>{{ $primerLaboratorio['sede'] }}</p></div>
            <dl>
                <div><dt>Ocupados</dt><dd data-panel-lab-ocupados>{{ $primerLaboratorio['ocupados'] }}</dd></div>
                <div><dt>Libres</dt><dd data-panel-lab-libres>{{ $primerLaboratorio['libres'] }}</dd></div>
                <div><dt>Capacidad</dt><dd data-panel-lab-capacidad>{{ $primerLaboratorio['capacidad'] ?: 'Sin aforo registrado' }}</dd></div>
            </dl>
        </div>
        @include('programacion.panel-carrera.grilla', ['tipoGrilla' => 'laboratorios', 'tituloGrilla' => 'Ocupación semanal de laboratorios', 'modulosVisibles' => $modulosVisibles])
        <script type="application/json" data-panel-laboratorios>@json($panel['laboratorios'])</script>
        @php($moviles = collect($panel['horarios'])->filter(fn ($h) => collect($h['espacios'])->contains(fn ($e) => in_array($e['tipo'], ['movil', 'laboratorio_movil'], true))))
        @if ($moviles->isNotEmpty())
            <details class="panel-observaciones">
                <summary><span>Clases de la carrera con laboratorio móvil</span><span class="panel-chip">{{ $moviles->count() }}</span></summary>
                @foreach ($moviles as $horario)
                    <article>
                        <h3>{{ $horario['ramo_codigo'] }} · {{ $horario['ramo'] }}</h3>
                        <p>Sección {{ $horario['seccion'] }} · {{ collect($horario['espacios'])->pluck('nombre')->implode(' · ') }}</p>
                        @foreach (collect($horario['espacios'])->where('tipo', 'movil')->pluck('motivo')->filter()->unique() as $motivo)
                            <p>{{ $motivo }}</p>
                        @endforeach
                    </article>
                @endforeach
            </details>
        @endif
    @else
        <div class="panel-vacio"><h3>Sin laboratorios en las sedes de esta carrera</h3></div>
    @endif
</section>

<div class="panel-grilla-scroll" tabindex="0" role="region" aria-label="{{ $tituloGrilla }}. Desplaza para ver los días.">
    <table class="panel-grilla" data-panel-grilla="{{ $tipoGrilla }}">
        <caption class="sr-only">{{ $tituloGrilla }}</caption>
        <thead>
            <tr>
                <th scope="col">Módulo</th>
                @foreach ($panel['dias'] as $dia)
                    <th scope="col">{{ ucfirst(mb_strtolower($dia->nombre)) }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($panel['bloques'] as $bloque)
                @php($hora = substr($bloque->hora_inicio, 0, 5).'–'.substr($bloque->hora_fin, 0, 5))
                <tr>
                    <th scope="row">
                        <strong>{{ $bloque->numero }}</strong>
                        <small>{{ $hora }}</small>
                        @if ($bloque->es_almuerzo)<small>Almuerzo</small>@endif
                    </th>
                    @foreach ($panel['dias'] as $dia)
                        @php($claveCelda = $dia->id.':'.$bloque->id)
                        @php($rotuloCelda = ucfirst(mb_strtolower($dia->nombre)).', módulo '.$bloque->numero.' · '.$hora)
                        <td data-panel-celda="{{ $claveCelda }}" data-panel-rotulo="{{ $rotuloCelda }}">
                            <div class="panel-modulos" data-panel-modulos>
                                @foreach ($horariosPorCelda->get($claveCelda, []) as $horario)
                                    @include('programacion.panel-carrera.modulo', ['horario' => $horario,
                                        'modoEdicion' => $puedeEditarHorarios && $tipoGrilla !== 'laboratorios' && $horario['grupo_id'],
                                        'mostrarModulo' => ! isset($modulosVisibles) || isset($modulosVisibles[$horario['id']])])
                                @endforeach
                            </div>
                            @if ($tipoGrilla !== 'horario')
                                <p class="panel-estado-celda" data-panel-estado-celda hidden></p>
                            @endif
                            @if ($puedeEditarHorarios && $tipoGrilla !== 'laboratorios')
                                <button type="button" class="panel-destino-boton" data-panel-destino-celda hidden
                                    aria-label="Mover a {{ $rotuloCelda }}">Mover aquí</button>
                            @endif
                        </td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
</div>

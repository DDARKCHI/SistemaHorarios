<section class="panel-tarjeta panel-calendario" aria-labelledby="panel-horario-titulo">
    <header class="panel-seccion-cabecera">
        <div>
            <h2 id="panel-horario-titulo">Horario semanal</h2>
            @if ($puedeEditarHorarios && $panel['horarios'])
                <p>Arrastra un módulo o selecciónalo y elige el destino.</p>
            @endif
        </div>
        @if ($panel['horarios'])
            <div class="panel-filtros-horario">
                @if (count($panel['semestres']) > 1)
                    <label class="panel-campo" for="panel-semestre">Semestre
                        <select id="panel-semestre" data-panel-semestre>
                            <option value="">Todos los semestres</option>
                            @foreach ($panel['semestres'] as $semestre)
                                <option value="{{ $semestre }}">{{ $semestre > 0 ? 'Semestre '.$semestre : 'Sin semestre' }}</option>
                            @endforeach
                        </select>
                    </label>
                @endif
                @if ($panel['docentes'])
                    <div class="panel-campo">
                        <label for="panel-horario-docente">Docente</label>
                        <select id="panel-horario-docente" data-panel-horario-docente data-select-buscable>
                            <option value="">Todos los docentes</option>
                            @foreach ($panel['docentes'] as $docente)<option value="{{ $docente['id'] }}">{{ $docente['nombre'] }}</option>@endforeach
                        </select>
                    </div>
                @endif
            </div>
        @endif
    </header>

    @if ($panel['horarios'] && ($puedeEditarHorarios || $panel['observaciones']))
        <div class="panel-leyenda" aria-label="Colores del horario">
            <span class="panel-chip" data-nivel="verde">Sin cruces</span>
            <span class="panel-chip" data-nivel="ambar">Requiere revisión</span>
            <span class="panel-chip" data-nivel="rojo">Conflicto</span>
        </div>
    @endif

    @if ($panel['observaciones'])
        @include('programacion.panel-carrera.observaciones')
    @endif

    @if ($panel['horarios'])
        <p class="panel-resultado-horario" data-panel-horario-vacio role="status" hidden>No hay clases que coincidan con los filtros.</p>
        @include('programacion.panel-carrera.grilla', ['tipoGrilla' => 'horario', 'tituloGrilla' => 'Horario semanal de la carrera'])
    @else
        <div class="panel-vacio">
            <h3>Sin módulos asignados</h3>
            @if ($puedeEditarHorarios)
                <a class="boton boton-secundario" href="{{ route('programacion.panel-carrera', array_merge($filtros, ['pestana' => 'ramos'])) }}">Agregar módulos en Ramos</a>
            @endif
        </div>
    @endif
</section>

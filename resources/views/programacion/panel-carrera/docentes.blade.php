<section class="panel-tarjeta" aria-labelledby="panel-docentes-titulo">
    <header class="panel-seccion-cabecera">
        <h2 id="panel-docentes-titulo">Carga docente</h2>
        <div class="panel-acciones-grupo">
            @can('asignar profesores')
                <a class="boton boton-secundario" href="{{ route('disponibilidad.index', ['periodo_id' => $periodo->id]) }}">Consultar disponibilidad</a>
            @endcan
            @can('administrar usuarios')
                <a class="boton boton-secundario" href="{{ route('administracion.docentes.index') }}">Catálogo docente</a>
            @endcan
        </div>
    </header>
    @if ($panel['docentes'])
        <div class="panel-busqueda-docentes">
            <label class="panel-campo panel-campo-acotado" for="panel-buscar-docente">Buscar docente
                <input type="search" id="panel-buscar-docente" data-panel-buscar-docente placeholder="Nombre o apellido" autocomplete="off" aria-controls="panel-tabla-docentes">
            </label>
            <p data-panel-resultados-docentes role="status">{{ count($panel['docentes']) === 1 ? '1 docente' : count($panel['docentes']).' docentes' }}</p>
        </div>
        <div class="panel-tabla-scroll">
            <table class="panel-tabla" id="panel-tabla-docentes">
                <caption class="sr-only">Indicadores semanales por docente</caption>
                <thead><tr><th scope="col">Docente</th><th scope="col">Días de clase</th><th scope="col">Módulos</th><th scope="col">Huecos</th><th scope="col">Días con 1 o 2 módulos</th><th scope="col">Disponibilidad</th></tr></thead>
                <tbody>
                    @foreach ($panel['docentes'] as $docente)
                        <tr data-panel-fila-docente>
                            <th scope="row"><button class="panel-enlace" type="button" data-panel-ver-docente="{{ $docente['id'] }}">{{ $docente['nombre'] }}</button></th>
                            <td>{{ $docente['dias'] }}</td><td>{{ $docente['modulos'] }}</td><td>{{ $docente['huecos'] }}</td><td>{{ $docente['dias_cortos'] }}</td>
                            <td><span class="panel-chip" @if ($docente['fuera']) data-nivel="rojo" @elseif ($docente['disponibilidad'] === 'aprobada') data-nivel="verde" @endif>
                                @if ($docente['fuera']){{ $docente['fuera'] }} fuera
                                @else{{ match ($docente['disponibilidad']) { 'aprobada' => 'Aprobada', 'registrada' => 'Registrada', default => 'Sin declarar' } }}@endif
                            </span></td>
                        </tr>
                    @endforeach
                    <tr data-panel-sin-docentes hidden><td colspan="6">No hay docentes que coincidan con la búsqueda.</td></tr>
                </tbody>
            </table>
        </div>
    @else
        <div class="panel-vacio">
            <h3>Sin docentes asignados</h3>
            @if ($puedeAsignarDocentes)
                <a class="boton boton-secundario" href="{{ route('programacion.panel-carrera', array_merge($filtros, ['pestana' => 'ramos'])) }}">Asignar docentes en Ramos</a>
            @endif
        </div>
    @endif
</section>

@if ($panel['docentes'])
    <section id="panel-semana-docente" class="panel-tarjeta panel-calendario scroll-mt-24" aria-labelledby="panel-semana-titulo">
        <header class="panel-seccion-cabecera">
            <div><h2 id="panel-semana-titulo" tabindex="-1">Semana del docente</h2><p data-panel-resumen-docente>Las métricas consideran las clases del docente en toda la versión.</p></div>
            <div class="panel-campo panel-campo-acotado">
                <label for="panel-docente">Docente</label>
                <select id="panel-docente" data-panel-docente data-select-buscable>
                    @foreach ($panel['docentes'] as $docente)<option value="{{ $docente['id'] }}">{{ $docente['nombre'] }}</option>@endforeach
                </select>
            </div>
        </header>
        <div class="panel-leyenda" aria-label="Estados de disponibilidad">
            <span class="panel-chip" data-nivel="verde">Disponible</span>
            <span class="panel-chip" data-nivel="rojo">Fuera de disponibilidad</span>
            <span class="panel-chip panel-hueco">Hueco entre clases</span>
        </div>
        @include('programacion.panel-carrera.grilla', ['tipoGrilla' => 'docentes', 'tituloGrilla' => 'Horario semanal del docente', 'modulosVisibles' => array_fill_keys($panel['docentes'][0]['horarios'], true)])
        <script type="application/json" data-panel-docentes>@json($panel['docentes'])</script>
    </section>
@endif

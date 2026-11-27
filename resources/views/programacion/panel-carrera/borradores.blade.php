<section class="panel-tarjeta" aria-labelledby="panel-borradores-titulo">
    <header class="panel-seccion-cabecera">
        <h2 id="panel-borradores-titulo">Versiones del período</h2>
        @if ($puedeGestionarVersiones)
            <a class="boton boton-primario" href="{{ route('programacion.version.copia', ['version' => $version, 'carrera_id' => $carrera->id]) }}">Copiar V{{ $version->version }} como borrador</a>
        @endif
    </header>
    <div class="panel-versiones">
        @foreach ($versiones as $opcion)
            <article>
                <div><h3>{{ $opcion->nombre }} · V{{ $opcion->version }}</h3><p>{{ $opcion->estado }}{{ $opcion->es_actual ? ' · Versión actual' : '' }}</p></div>
                <div class="panel-acciones-grupo">
                    @if ($opcion->id === $version->id)
                        <span class="panel-chip" data-nivel="verde">En consulta</span>
                    @else
                        <a class="boton boton-secundario" href="{{ route('programacion.panel-carrera', array_merge($filtros, ['version_id' => $opcion->id, 'pestana' => 'horario'])) }}" aria-label="Consultar V{{ $opcion->version }}">Consultar</a>
                    @endif
                    @if ($puedeGestionarVersiones && ! $opcion->es_actual && $opcion->estado === 'Borrador')
                        <a class="boton boton-secundario" href="{{ route('programacion.version.revisar', $opcion) }}">Revisar para activar</a>
                    @endif
                </div>
            </article>
        @endforeach
    </div>
</section>

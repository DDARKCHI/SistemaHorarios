@extends('layouts.app')
@section('title', 'Panel de carrera | '.$identidad['nombre'])

@section('content')
<div class="panel-carrera space-y-5" data-panel-carrera
    data-carrera="{{ $carrera?->id }}" data-version="{{ $version?->id }}" data-pestana="{{ $pestana }}"
    data-token="{{ csrf_token() }}">
    <x-encabezado-pagina titulo="Panel de carrera">
        @if ($panel)
            <x-slot:acciones>
                <a class="boton boton-secundario" href="{{ route('programacion.panel-carrera.exportar', $filtros) }}">
                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3v12m-4-4 4 4 4-4M5 16v4h14v-4"/></svg>
                    Exportar a Excel
                </a>
            </x-slot:acciones>
        @endif
    </x-encabezado-pagina>
    @include('disponibilidad.avisos')

    <form method="get" action="{{ route('programacion.panel-carrera') }}" class="panel-filtros panel-tarjeta" data-panel-filtros>
        <input type="hidden" name="pestana" value="{{ $pestana }}">
        <label class="panel-campo" for="panel-periodo">Período
            <select id="panel-periodo" name="periodo_id" data-panel-periodo>
                @forelse ($periodos as $opcion)
                    <option value="{{ $opcion->id }}" @selected($periodo?->id === $opcion->id)>{{ $opcion->nombre }}</option>
                @empty
                    <option value="">Sin períodos</option>
                @endforelse
            </select>
        </label>
        <label class="panel-campo" for="panel-carrera-filtro">Carrera
            <select id="panel-carrera-filtro" name="carrera_id" data-panel-carrera-filtro>
                @forelse ($carreras as $opcion)
                    <option value="{{ $opcion->id }}" @selected($carrera?->id === $opcion->id)>{{ $opcion->nombre }}</option>
                @empty
                    <option value="">Sin carreras</option>
                @endforelse
            </select>
        </label>
        <label class="panel-campo" for="panel-version">Versión
            <select id="panel-version" name="version_id" data-panel-version>
                @forelse ($versiones as $opcion)
                    <option value="{{ $opcion->id }}" @selected($version?->id === $opcion->id)>V{{ $opcion->version }} · {{ $opcion->estado }}{{ $opcion->es_actual ? ' · Actual' : '' }}</option>
                @empty
                    <option value="">Sin versiones</option>
                @endforelse
            </select>
        </label>
        <button type="submit" class="boton boton-secundario" @disabled(! $periodo)>Consultar</button>
    </form>

    @if (! $panel)
        <div class="panel-tarjeta panel-vacio">
            <h2>{{ ! $periodo ? 'Sin períodos académicos' : (! $version ? 'Sin programación semestral' : 'Sin carreras con secciones en este período') }}</h2>
            @if ($puedeGestionarVersiones && $periodo)
                <a class="boton boton-secundario" href="{{ route('programacion.preparacion', ['periodo_id' => $periodo->id]) }}">Preparar programación</a>
            @endif
        </div>
    @else
        @php
            $indicadores = $panel['indicadores'];
            $completas = (int) ($indicadores['completas'] ?? 0);
            $total = (int) $indicadores['secciones'];
            $porcentaje = $total ? round(min($completas, $total) * 100 / $total) : 0;
            $pestanas = ['horario' => 'Horario', 'ramos' => 'Ramos', 'laboratorios' => 'Laboratorios', 'docentes' => 'Carga docente', 'borradores' => 'Versiones'];
            $puedeMoverModulos = $puedeEditarHorarios && in_array($pestana, ['horario', 'docentes'], true);
            $horariosPorCelda = collect($panel['horarios'])->groupBy(fn ($h) => $h['dia_id'].':'.$h['bloque_id']);
            $horariosPorGrupo = collect($panel['horarios'])->groupBy('grupo_id');
        @endphp
        <nav class="panel-pestanas" aria-label="Herramientas de la carrera">
            @foreach ($pestanas as $clave => $nombre)
                <a href="{{ route('programacion.panel-carrera', array_merge($filtros, ['pestana' => $clave])) }}" @if ($clave === $pestana) aria-current="page" @endif>{{ $nombre }}</a>
            @endforeach
        </nav>

        @if (! $editable && $pestana !== 'borradores')
            @php($borradorActual = $versiones->first(fn ($opcion) => $opcion->es_actual && $opcion->estado === 'Borrador' && $opcion->id !== $version->id))
            <div class="panel-estado-version">
                <span>Versión de solo consulta</span>
                @if ($borradorActual)
                    <a href="{{ route('programacion.panel-carrera', array_merge($filtros, ['version_id' => $borradorActual->id, 'pestana' => $pestana])) }}">Abrir borrador actual</a>
                @endif
            </div>
        @endif

        @if ($pestana === 'horario')
            <section class="panel-resumen panel-tarjeta" aria-label="Avance de la carrera">
                <div class="panel-avance">
                    <div class="panel-avance-titulo">
                        <h2>{{ $carrera->nombre }}</h2>
                        <div class="panel-avance-cifra">
                            <span>{{ $completas }} de {{ $total }} secciones listas</span>
                            <strong aria-hidden="true">{{ $porcentaje }}<small>%</small></strong>
                        </div>
                    </div>
                    <progress class="panel-progreso" max="100" value="{{ $porcentaje }}" aria-label="Progreso de la carrera">{{ $porcentaje }} %</progress>
                </div>
                <dl class="panel-metricas">
                    @foreach ([
                        [$indicadores['con_docente'], 'Con docente asignado'],
                        [$indicadores['confirmadas'] ?? 0, 'Con docentes confirmados'],
                        [$indicadores['carga_completa'], 'Con carga completa'],
                        [$indicadores['pendientes'], 'Módulos pendientes'],
                    ] as [$valor, $etiqueta])
                        <div><dt>{{ $etiqueta }}</dt><dd>{{ number_format($valor, 0, ',', '.') }}</dd></div>
                    @endforeach
                </dl>
            </section>
        @endif

        @if ($puedeMoverModulos)
            <div class="panel-feedback" data-panel-feedback hidden>
                <p data-panel-mensaje role="status" aria-live="polite"></p>
                <button type="button" class="boton boton-secundario" data-panel-cancelar hidden>Cancelar selección</button>
                <button type="button" class="boton boton-secundario" data-panel-deshacer hidden>Deshacer movimiento</button>
            </div>
        @endif

        @include('programacion.panel-carrera.'.$pestana)

        @if ($puedeMoverModulos)
            <dialog class="panel-dialogo" data-panel-dialogo aria-labelledby="panel-mover-titulo" aria-describedby="panel-destino">
                <div class="panel-dialogo-cuerpo">
                    <span class="panel-chip" data-panel-riesgo></span>
                    <h2 id="panel-mover-titulo">Mover módulo</h2>
                    <p id="panel-destino" data-panel-destino></p>
                    <ul class="panel-motivos" data-panel-motivos></ul>
                    <p class="panel-error" data-panel-error role="alert" hidden></p>
                </div>
                <div class="panel-dialogo-acciones">
                    <button type="button" class="boton boton-secundario" data-panel-volver autofocus>Volver</button>
                    <button type="button" class="boton boton-primario" data-panel-guardar>Guardar movimiento</button>
                </div>
            </dialog>
        @endif

        @if ($puedeEditarHorarios && $pestana === 'ramos' && $espaciosDisponibles)
            <dialog class="panel-dialogo" data-panel-dialogo-espacio aria-labelledby="panel-espacio-titulo">
                <form method="post" data-panel-form-espacio class="panel-espacio-form">
                    @csrf
                    @include('programacion.panel-carrera.contexto')
                    <div class="panel-dialogo-cuerpo">
                        <h2 id="panel-espacio-titulo">Asignar espacio</h2>
                        <p data-panel-espacio-resumen></p>
                        <div class="panel-campo">
                            <label for="panel-espacio">Sala o laboratorio</label>
                            <select id="panel-espacio" name="espacio" required data-select-buscable>
                                <option value="">Seleccionar espacio</option>
                                @foreach ($espaciosDisponibles as $clave => $nombre)
                                    <option value="{{ $clave }}">{{ $nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="panel-dialogo-acciones">
                        <button type="button" class="boton boton-secundario" data-panel-cerrar-espacio>Cancelar</button>
                        <button type="submit" class="boton boton-primario" data-panel-guardar-espacio disabled>Asignar espacio</button>
                    </div>
                </form>
            </dialog>
        @endif
    @endif
</div>
@endsection

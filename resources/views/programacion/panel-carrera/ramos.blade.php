<section class="panel-tarjeta" aria-labelledby="panel-ramos-titulo">
    <header class="panel-seccion-cabecera">
        <h2 id="panel-ramos-titulo">Ramos y secciones</h2>
        <label class="panel-campo panel-campo-acotado" for="panel-buscar-ramo">Buscar ramo
            <input id="panel-buscar-ramo" type="search" placeholder="Código, nombre o sección" data-panel-buscar>
        </label>
    </header>
    @if (! empty($indicadores['por_actividad']))
        <ul class="panel-carga-tipos" aria-label="Carga por tipo de actividad">
            @foreach ($indicadores['por_actividad'] as $actividad)
                <li><strong>{{ $actividad['tipo'] }}</strong><span>{{ $actividad['asignados'] }} / {{ $actividad['requeridos'] }} módulos</span></li>
            @endforeach
        </ul>
    @endif
    <div class="panel-lista-ramos">
        @forelse ($panel['secciones'] as $seccion)
            <details class="panel-ramo" data-panel-ramo="{{ $seccion['id'] }}" @if (count($panel['secciones']) === 1) open @endif>
                <summary>
                    <span class="panel-ramo-titulo">
                        <span data-panel-texto-ramo>{{ $seccion['ramo_codigo'] }} · {{ $seccion['ramo'] }}</span>
                        <small>Sección {{ $seccion['codigo'] }}{{ $seccion['semestre'] > 0 ? ' · Semestre '.$seccion['semestre'] : '' }} · {{ $seccion['inscritos'] }} inscritos</small>
                    </span>
                    <span class="panel-chip" data-nivel="{{ $seccion['carga_completa'] ? 'verde' : 'ambar' }}">{{ $seccion['carga_completa'] ? 'Carga completa' : 'Carga por revisar' }}</span>
                </summary>
                <div class="panel-ramo-cuerpo">
                    @can('administrar usuarios')
                        <a class="panel-enlace" href="{{ route('administracion.secciones.show', $seccion['id']) }}">Ver ficha académica</a>
                    @endcan
                    @forelse ($seccion['grupos'] as $grupo)
                        @php
                            $grupoId = $grupo['id'];
                            $plantilla = collect($grupo['plantilla'] ?? $grupo['docentes']);
                            $principalId = $grupo['principal_id'];
                            $docentesGrupo = $plantilla->isNotEmpty() ? $plantilla : collect($grupo['docentes']);
                            $confirmado = $panel['confirmaciones'][$grupoId] ?? false;
                            $horariosGrupo = $horariosPorGrupo->get($grupoId, collect());
                            $tituloGrupo = $grupo['actividad'].' · Grupo '.$grupo['codigo'];
                        @endphp
                        <article class="panel-grupo">
                            <div class="panel-grupo-cabecera">
                                <div>
                                    <h3>{{ $tituloGrupo }}</h3>
                                    <p>{{ $docentesGrupo->pluck('nombre')->implode(', ') ?: 'Docente pendiente' }}</p>
                                </div>
                                <div class="panel-chips">
                                    <span class="panel-chip">{{ $grupo['asignados'] }} / {{ $grupo['requeridos'] }} módulos</span>
                                    @if ($confirmado)<span class="panel-chip" data-nivel="verde">Docente confirmado</span>@endif
                                    @if ($grupo['excedentes'])<span class="panel-chip" data-nivel="ambar">{{ $grupo['excedentes'] }} en exceso</span>@endif
                                </div>
                            </div>
                            @if ($puedeAsignarDocentes)
                                <div class="panel-acciones-grupo">
                                    <button type="button" class="boton boton-secundario" data-panel-elegir-docente
                                        data-panel-accion="{{ route('programacion.panel-carrera.asignar', ['grupo' => $grupoId]) }}"
                                        data-panel-descripcion="{{ $seccion['ramo'] }} · {{ $tituloGrupo }}"
                                        data-panel-principal="{{ $principalId }}">Asignar docente al grupo</button>
                                    @if ($principalId)
                                        <form method="post" action="{{ route('programacion.panel-carrera.confirmar', ['grupo' => $grupoId]) }}">
                                            @csrf
                                            @include('programacion.panel-carrera.contexto')
                                            <input type="hidden" name="profesor_id" value="{{ $principalId }}">
                                            <input type="hidden" name="confirmado" value="{{ $confirmado ? '0' : '1' }}">
                                            <button class="boton boton-secundario" type="submit">{{ $confirmado ? 'Quitar confirmación' : 'Confirmar docente' }}</button>
                                        </form>
                                    @endif
                                </div>
                            @endif
                            @if ($horariosGrupo->isNotEmpty())
                                <ul class="panel-modulos-grupo">
                                    @foreach ($horariosGrupo as $horario)
                                        @php
                                            $diaHorario = $panel['dias']->firstWhere('id', $horario['dia_id']);
                                            $bloqueHorario = $panel['bloques']->firstWhere('id', $horario['bloque_id']);
                                            $rotuloHorario = ucfirst(mb_strtolower($diaHorario?->nombre ?? 'Día inactivo')).' · Módulo '.($bloqueHorario?->numero ?? 'inactivo');
                                            $espaciosModulo = collect($horario['espacios'])->pluck('nombre')->implode(' · ');
                                            $docentesModulo = collect($horario['docentes']);
                                        @endphp
                                        <li>
                                            <div>
                                                <strong>{{ $rotuloHorario }}</strong>
                                                <span>{{ $espaciosModulo ?: 'Espacio pendiente' }}</span>
                                                @if ($docentesModulo->pluck('id')->sort()->values()->all() !== $docentesGrupo->pluck('id')->sort()->values()->all())
                                                    <span>{{ $docentesModulo->pluck('nombre')->implode(', ') ?: 'Docente pendiente' }}</span>
                                                @endif
                                            </div>
                                            @if ($puedeEditarHorarios && $diaHorario && $bloqueHorario)
                                                <div class="panel-acciones-grupo">
                                                    @if ($espaciosDisponibles)
                                                        <button type="button" class="boton boton-secundario" data-panel-elegir-espacio
                                                            data-panel-accion="{{ route('programacion.panel-carrera.espacio', ['horario' => $horario['id']]) }}"
                                                            data-panel-descripcion="{{ $seccion['ramo'] }} · {{ $rotuloHorario }}">Asignar espacio</button>
                                                    @endif
                                                    <form method="post" action="{{ route('programacion.panel-carrera.quitar', ['horario' => $horario['id']]) }}"
                                                        data-confirmar="Se quitará este módulo del borrador de trabajo." data-confirmar-titulo="Quitar módulo"
                                                        data-confirmar-accion="Quitar módulo" data-confirmar-detalle="{{ $seccion['ramo'] }} · {{ $rotuloHorario }}">
                                                        @csrf
                                                        @method('DELETE')
                                                        @include('programacion.panel-carrera.contexto')
                                                        <button type="submit" class="boton boton-secundario">Quitar</button>
                                                    </form>
                                                </div>
                                            @endif
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                            @if ($puedeEditarHorarios && $grupo['pendientes'] > 0 && $panel['dias']->isNotEmpty() && $panel['bloques']->isNotEmpty())
                                <form method="post" action="{{ route('programacion.panel-carrera.agregar', ['grupo' => $grupoId]) }}" class="panel-agregar-modulo">
                                    @csrf
                                    @include('programacion.panel-carrera.contexto')
                                    <label class="panel-campo">Día
                                        <select name="dia_id" required>
                                            <option value="">Seleccionar día</option>
                                            @foreach ($panel['dias']->where('estado', true) as $dia)<option value="{{ $dia->id }}">{{ ucfirst(mb_strtolower($dia->nombre)) }}</option>@endforeach
                                        </select>
                                    </label>
                                    <label class="panel-campo">Módulo
                                        <select name="bloque_id" required>
                                            <option value="">Seleccionar módulo</option>
                                            @foreach ($panel['bloques']->where('estado', true) as $bloque)
                                                <option value="{{ $bloque->id }}">{{ $bloque->numero }} · {{ substr($bloque->hora_inicio, 0, 5) }}–{{ substr($bloque->hora_fin, 0, 5) }}{{ $bloque->es_almuerzo ? ' · Almuerzo' : '' }}</option>
                                            @endforeach
                                        </select>
                                    </label>
                                    <button class="boton boton-secundario" type="submit">Agregar módulo</button>
                                </form>
                            @endif
                        </article>
                    @empty
                        <p class="panel-nota">Esta sección todavía no tiene grupos activos.</p>
                    @endforelse
                    @foreach ($seccion['carga_sin_actividad'] ?? [] as $carga)
                        <p class="panel-nota">Falta crear la actividad para {{ $carga['pendientes'] }} módulos de {{ $carga['actividad'] }}.</p>
                    @endforeach
                    @foreach ($seccion['carga_sin_grupos'] ?? [] as $carga)
                        <p class="panel-nota">Falta un grupo para {{ $carga['pendientes'] }} módulos de {{ $carga['actividad'] }}.</p>
                    @endforeach
                </div>
            </details>
        @empty
            <div class="panel-vacio"><h3>Sin secciones en este período</h3></div>
        @endforelse
        <p class="panel-vacio" data-panel-ramos-vacios hidden>No se encontraron ramos con esa búsqueda.</p>
    </div>
</section>

@if ($puedeAsignarDocentes)
    <dialog class="panel-dialogo" data-panel-dialogo-docente aria-labelledby="panel-docente-titulo">
        <form method="post" data-panel-form-docente>
            @csrf
            @include('programacion.panel-carrera.contexto')
            <div class="panel-dialogo-cuerpo">
                <h2 id="panel-docente-titulo">Asignar docente principal</h2>
                <p data-panel-docente-resumen></p>
                <p>Los módulos con docente propio conservan su asignación.</p>
                <div class="panel-campo">
                    <label for="panel-profesor">Docente</label>
                    <select id="panel-profesor" name="profesor_id" required data-select-buscable>
                        <option value="">Seleccionar docente</option>
                        @foreach ($docentesDisponibles as $docente)
                            <option value="{{ $docente->id }}">{{ trim($docente->apellido.', '.$docente->nombre, ' ,') }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="panel-dialogo-acciones">
                <button type="button" class="boton boton-secundario" data-panel-cerrar-docente>Cancelar</button>
                <button type="submit" class="boton boton-primario" data-panel-guardar-docente disabled>Asignar docente</button>
            </div>
        </form>
    </dialog>
@endif

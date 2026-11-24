@extends('layouts.app')
@section('title', ($seccion->exists ? 'Editar' : 'Crear').' sección | '.$identidad['nombre'])
@section('content')
@php
    $valor = static function ($campo, $base = null) {
        $anterior = old($campo, $base);

        return is_scalar($anterior) || $anterior === null ? $anterior : $base;
    };
    $campo = 'h-11 w-full rounded-lg border border-borde bg-superficie px-3 focus:border-institucional focus:outline-none focus:ring-1 focus:ring-institucional';
    $carrerasAnteriores = old('carreras', session()->hasOldInput() ? [] : $elegidas);
    $elegidas = is_array($carrerasAnteriores)
        ? array_map('strval', array_filter($carrerasAnteriores, fn ($id) => is_int($id) || is_string($id)))
        : [];
@endphp
<div class="panel-editor">
    <x-encabezado-pagina :titulo="$seccion->exists ? 'Editar sección' : 'Crear sección'">Vincula un ramo y sus carreras al período académico.</x-encabezado-pagina>
    @include('disponibilidad.avisos')

    <form method="post" action="{{ $seccion->exists ? route('administracion.secciones.update', $seccion) : route('administracion.secciones.store') }}" class="panel-formulario">
        @csrf
        @if ($seccion->exists)
            @method('PUT')
            <input type="hidden" name="revision" value="{{ $valor('revision', app(\App\Services\GestionOfertaPeriodo::class)->firma($seccion)) }}">
        @endif

        <div class="panel-layout">
            <div class="panel-columna">
                <x-seccion-formulario titulo="Identificación de la sección" id="identificacion-seccion">
                    <div class="panel-campos">
                        <div class="panel-campo--corto">
                            @if ($seccion->exists)
                                <p class="mb-1.5 text-sm font-semibold">Período</p>
                                <input type="hidden" id="periodo_academico_id" name="periodo_academico_id" value="{{ $seccion->periodo_academico_id }}" data-institucion="{{ $seccion->periodoAcademico->universidad_id }}">
                                <p class="flex min-h-11 items-center rounded-lg border border-borde bg-fondo px-3 py-2 text-sm">{{ $seccion->periodoAcademico->nombre }} · {{ $seccion->periodoAcademico->universidad->sigla }}</p>
                            @else
                                <label for="periodo_academico_id" class="mb-1.5 block font-semibold">Período *</label>
                                <select id="periodo_academico_id" name="periodo_academico_id" required class="{{ $campo }}">
                                    <option value="">Seleccionar período</option>
                                    @foreach ($periodos as $periodo)
                                        <option value="{{ $periodo->id }}" data-institucion="{{ $periodo->universidad_id }}" @selected($valor('periodo_academico_id', $seccion->periodo_academico_id) == $periodo->id)>
                                            {{ $periodo->nombre }} · {{ $periodo->universidad->sigla }}
                                        </option>
                                    @endforeach
                                </select>
                            @endif
                        </div>
                        <div class="panel-campo--largo">
                            @if ($seccion->exists)
                                <p class="mb-1.5 text-sm font-semibold">Ramo</p>
                                <input type="hidden" name="asignatura_id" value="{{ $seccion->asignatura_id }}">
                                <p class="flex min-h-11 items-center rounded-lg border border-borde bg-fondo px-3 py-2 text-sm">{{ $seccion->asignatura->codigo }} · {{ $seccion->asignatura->nombre }}</p>
                            @else
                                <label for="asignatura_id" class="mb-1.5 block font-semibold">Ramo *</label>
                                <select data-select-buscable id="asignatura_id" name="asignatura_id" required class="{{ $campo }}">
                                    <option value="">Seleccionar ramo</option>
                                    @foreach ($ramos as $ramo)
                                        <option value="{{ $ramo->id }}" @selected($valor('asignatura_id') == $ramo->id)>
                                            {{ $ramo->codigo }} · {{ $ramo->nombre }} · {{ $ramo->universidad->sigla }}
                                        </option>
                                    @endforeach
                                </select>
                            @endif
                        </div>
                        <div class="panel-campo--corto">
                            <label for="codigo" class="mb-1.5 block font-semibold">Código de sección{{ $seccion->exists ? '' : ' *' }}</label>
                            <input placeholder="Ej. 11" id="codigo" name="codigo" maxlength="30" @required(! $seccion->exists) value="{{ $valor('codigo', $seccion->codigo) }}" class="{{ $campo }}">
                        </div>
                        <div class="panel-campo--corto">
                            <label for="semestre" class="mb-1.5 block font-semibold">Semestre de la carrera{{ $seccion->exists ? '' : ' *' }}</label>
                            <input placeholder="Ej. 2" type="number" id="semestre" name="semestre" min="1" max="255" @required(! $seccion->exists) value="{{ $valor('semestre', $seccion->semestre) }}" class="{{ $campo }}">
                        </div>
                        <div class="panel-campo--corto">
                            <label for="nombre" class="mb-1.5 block font-semibold">Nombre de la sección</label>
                            <input placeholder="Ej. Sección diurna" id="nombre" name="nombre" maxlength="100" value="{{ $valor('nombre', $seccion->nombre) }}" class="{{ $campo }}">
                        </div>
                    </div>
                </x-seccion-formulario>

                @include('administracion.secciones.carreras')
            </div>

            <div class="panel-columna">
                <x-seccion-formulario titulo="Matrícula y estado" id="estado-seccion">
                    <div class="space-y-4">
                        <div class="panel-pareja">
                            <div>
                                <label for="cupos" class="mb-1.5 block font-semibold">Cupos</label>
                                <input placeholder="Ej. 30" type="number" id="cupos" name="cupos" min="0" max="65535" value="{{ $valor('cupos', $seccion->cupos) }}" class="{{ $campo }}">
                            </div>
                            <div>
                                <label for="inscritos" class="mb-1.5 block font-semibold">Inscritos *</label>
                                <input placeholder="Ej. 25" type="number" id="inscritos" name="inscritos" min="0" max="65535" required value="{{ $valor('inscritos', $seccion->inscritos) }}" class="{{ $campo }}">
                            </div>
                        </div>
                        <div>
                            <label for="estado_activo" class="mb-1.5 block font-semibold">Estado *</label>
                            <select id="estado_activo" name="estado_activo" class="{{ $campo }}">
                                <option value="1" @selected((string) $valor('estado_activo', (int) $seccion->estado_activo) === '1')>Activa</option>
                                <option value="0" @selected((string) $valor('estado_activo', (int) $seccion->estado_activo) === '0')>Inactiva</option>
                            </select>
                        </div>
                    </div>
                    @if ($seccion->exists)
                        <p class="panel-nota">Al desactivar la sección se conservan sus horarios y cierres.</p>
                    @endif
                </x-seccion-formulario>

                <x-seccion-formulario titulo="Características académicas" id="caracteristicas-seccion">
                    <div class="space-y-4">
                        <div class="panel-pareja">
                            @foreach (['modalidad' => 'Modalidad', 'categoria' => 'Categoría'] as $nombre => $etiqueta)
                                <div>
                                    <label for="{{ $nombre }}" class="mb-1.5 block font-semibold">{{ $etiqueta }}</label>
                                    <input placeholder="{{ ['modalidad' => 'Ej. Presencial', 'categoria' => 'Ej. Obligatoria'][$nombre] }}" id="{{ $nombre }}" name="{{ $nombre }}" maxlength="50" value="{{ $valor($nombre, $seccion->$nombre) }}" class="{{ $campo }}">
                                </div>
                            @endforeach
                        </div>
                        <div>
                            <label for="tipo_dictacion" class="mb-1.5 block font-semibold">Tipo de dictación</label>
                            <input placeholder="Ej. Semestral" id="tipo_dictacion" name="tipo_dictacion" maxlength="50" value="{{ $valor('tipo_dictacion', $seccion->tipo_dictacion) }}" class="{{ $campo }}">
                        </div>
                    </div>
                </x-seccion-formulario>
            </div>
        </div>

        @if (! $seccion->exists)
            <div class="panel-contexto">
                <input type="hidden" name="usar_referencia" value="0">
                <label class="flex cursor-pointer items-start gap-3">
                    <input type="checkbox" name="usar_referencia" value="1" @checked((string) $valor('usar_referencia', 1) === '1') class="mt-0.5 size-4 shrink-0 accent-institucional">
                    <span>
                        <span class="block font-semibold">Crear actividades con la carga de referencia del ramo</span>
                        <span class="mt-0.5 block text-xs text-texto-secundario">Se añade un grupo inicial por actividad con módulos definidos.</span>
                    </span>
                </label>
            </div>
        @endif

        <x-acciones-formulario :cancelar="route('administracion.secciones.index', ['periodo_id' => $seccion->periodo_academico_id])">
            <button type="submit" class="boton boton-primario">{{ $seccion->exists ? 'Guardar cambios' : 'Crear sección' }}</button>
        </x-acciones-formulario>
    </form>
</div>
@endsection

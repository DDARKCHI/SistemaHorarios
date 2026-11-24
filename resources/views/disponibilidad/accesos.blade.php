@extends('layouts.app')
@section('title', 'Disponibilidad docente | '.$identidad['nombre'])
@section('content')
@php
    $campo = 'h-11 w-full rounded-lg border border-borde bg-superficie px-3 text-sm focus:border-institucional focus:outline-none focus:ring-1 focus:ring-institucional';
    $elegidos = is_array(old('profesores')) ? old('profesores') : [];
    $excluidos = is_array(old('excluir')) ? old('excluir') : [];
@endphp
<div class="panel-editor">
    <x-encabezado-pagina titulo="Disponibilidad docente">
        Solicita disponibilidad para el período, sigue los envíos y revisa los horarios recibidos.
        <x-slot:acciones>
            <a class="boton boton-secundario" href="{{ route('disponibilidad.index', $filtros + ['solicitud' => $consulta['solicitud']->id ?? null]) }}">Actualizar seguimiento</a>
        </x-slot:acciones>
    </x-encabezado-pagina>

    @include('disponibilidad.avisos')
    @if (session('aviso_accesos'))
        <p role="status" class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">{{ session('aviso_accesos') }}</p>
    @endif
    @if (! $correoDisponible)
        <div role="status" class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            <p class="font-semibold">Correo saliente pendiente de configuración</p>
            <p class="mt-1">Los envíos quedarán pendientes hasta configurar el servidor de correo de la institución.</p>
        </div>
    @endif

    <x-filtros-compactos :campos="5" :action="route('disponibilidad.index')" aria-label="Filtrar docentes">
        <div>
            <label for="periodo-accesos" class="mb-1.5 block font-semibold">Período académico</label>
            <select id="periodo-accesos" name="periodo_id" class="{{ $campo }}">
                @forelse ($periodos as $p)
                    <option value="{{ $p->id }}" @selected($periodo?->id === $p->id)>{{ $p->nombre }}{{ $p->estado ? '' : ' · Inactivo' }}</option>
                @empty
                    <option value="">Sin períodos registrados</option>
                @endforelse
            </select>
        </div>
        <div>
            <label for="sede-accesos" class="mb-1.5 block font-semibold">Sede</label>
            <select id="sede-accesos" name="sede_id" class="{{ $campo }}">
                <option value="">Todas las sedes</option>
                @foreach ($sedes as $sede)
                    <option value="{{ $sede->id }}" @selected($sedeId === $sede->id)>{{ $sede->nombre }}{{ $sede->estado ? '' : ' · Inactiva' }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="buscar-accesos" class="mb-1.5 block font-semibold">Buscar docente</label>
            <input id="buscar-accesos" type="search" name="q" maxlength="100" value="{{ $q }}" placeholder="Nombre, RUT o correo" class="{{ $campo }}">
        </div>
        <div>
            <label for="estado-accesos" class="mb-1.5 block font-semibold">Estado</label>
            <select id="estado-accesos" name="estado" class="{{ $campo }}">
                <option value="todos">Todos</option>
                <optgroup label="Correo e ingreso">
                    @foreach ($estadosSeguimiento as $valor => $etiqueta)
                        <option value="{{ $valor }}" @selected($estado === $valor)>{{ $etiqueta }}</option>
                    @endforeach
                </optgroup>
                <optgroup label="Disponibilidad">
                    @foreach ($estadosDisponibilidad as $valor => $etiqueta)
                        <option value="{{ $valor }}" @selected($estado === $valor)>{{ $etiqueta }}</option>
                    @endforeach
                </optgroup>
            </select>
        </div>
        <div>
            <label for="fecha-limite-consulta" class="mb-1.5 block font-semibold">Fecha límite</label>
            <input id="fecha-limite-consulta" type="date" name="fecha_limite" value="{{ $fechaLimite }}" class="{{ $campo }}">
        </div>
        <x-slot:acciones>
            <a href="{{ route('disponibilidad.index', ['periodo_id' => $periodo?->id]) }}" class="boton boton-secundario">Limpiar</a>
            <button type="submit" class="boton boton-primario">Consultar</button>
        </x-slot:acciones>
    </x-filtros-compactos>

    <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        @foreach (['enviados' => 'Correo enviado', 'ingresaron' => 'Ingresaron al sistema', 'pendientes' => 'Pendientes de respuesta', 'respondieron' => 'Con respuesta'] as $clave => $etiqueta)
            <div class="rounded-xl border border-borde bg-superficie px-4 py-3 shadow-sm">
                <p class="text-xs text-texto-secundario">{{ $etiqueta }}</p>
                <p class="mt-1 text-2xl font-bold">{{ number_format($resumen[$clave], 0, ',', '.') }}</p>
            </div>
        @endforeach
    </div>

    @if ($consulta)
        <section id="detalle-disponibilidad" tabindex="-1" aria-labelledby="docente-disponibilidad" class="min-w-0 space-y-4 scroll-mt-6 rounded-xl border border-borde bg-superficie p-4 shadow-sm sm:p-5">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 id="docente-disponibilidad" class="text-lg font-semibold">{{ $consulta['solicitud']->profesor->apellido }}, {{ $consulta['solicitud']->profesor->nombre }}</h2>
                    <p class="text-sm text-texto-secundario">{{ $periodo->nombre }}</p>
                </div>
                <a class="boton boton-secundario" href="{{ route('disponibilidad.index', $filtros) }}#listado-disponibilidad">Cerrar detalle</a>
            </div>
            @include('disponibilidad.contenido-solicitud', $consulta)
        </section>
    @endif

    <form id="listado-disponibilidad" method="post" action="{{ route('disponibilidad.accesos.enviar') }}" class="min-w-0 scroll-mt-6 overflow-hidden rounded-xl border border-borde bg-superficie shadow-sm" data-formulario-accesos data-periodo-envio="{{ $periodo?->nombre }}" data-sede-envio="{{ $sedeSeleccionada?->nombre ?? 'Todas las sedes' }}" data-correo-disponible="{{ $correoDisponible ? '1' : '0' }}" data-confirmar="Se prepararán los accesos y la solicitud de disponibilidad para los docentes seleccionados. Habrá recordatorios para quienes sigan sin responder." data-confirmar-titulo="Confirmar solicitudes de disponibilidad" data-confirmar-accion="Confirmar envío">
        @csrf
        <input type="hidden" name="periodo_id" value="{{ $periodo?->id }}">
        <input type="hidden" name="sede_id" value="{{ $sedeId }}">
        <input type="hidden" name="todos" value="{{ old('todos', 0) == 1 ? '1' : '0' }}" data-todos-accesos>
        <input type="hidden" name="seleccion_token" value="{{ $seleccionToken }}">
        <input type="hidden" name="cantidad_confirmada" value="0">
        <input type="hidden" name="lote" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
        <div data-exclusiones-accesos>
            @foreach ($excluidos as $excluido)
                @if (is_scalar($excluido))<input type="hidden" name="excluir[]" value="{{ $excluido }}">@endif
            @endforeach
        </div>
        <header class="flex flex-wrap items-center justify-between gap-3 border-b border-borde px-4 py-3 sm:px-5">
            <div>
                <h2 class="font-semibold">Seguimiento por docente</h2>
                <p class="mt-0.5 text-xs text-texto-secundario">{{ number_format($docentes->total(), 0, ',', '.') }} resultados</p>
            </div>
            @if ($periodo?->estado && $periodo->universidad->estado && count($seleccionables))
                <div class="flex flex-wrap gap-2">
                    <button type="button" class="boton boton-secundario" data-seleccionar-todos-accesos>Seleccionar todos los resultados</button>
                    <button type="button" class="boton boton-secundario" data-limpiar-accesos>Quitar selección</button>
                </div>
            @endif
        </header>
        @if ($sedeSeleccionada)
            <p class="border-b border-borde bg-fondo px-4 py-3 text-xs leading-5 text-texto-secundario">
                {{ $sedeSeleccionada->nombre }} ·
                @if ($versionSede)
                    Docentes con clases en salas o laboratorios de esta sede, según {{ $versionSede->tipo === 'semestral' ? 'la programación semestral actual' : 'la última programación anterior' }} del período.
                @else
                    Este período aún no tiene programación para identificar docentes por sede.
                @endif
            </p>
        @endif
        @if ($periodo && ! $periodo->estado)
            <p class="border-b border-borde bg-fondo px-4 py-3 text-sm text-texto-secundario">Este período está inactivo. Su historial está disponible para consulta.</p>
        @endif
        <div class="listado-adaptable overflow-x-auto">
            <table class="w-full text-left text-sm" role="table">
                <thead class="bg-fondo text-xs uppercase text-texto-secundario" role="rowgroup">
                    <tr role="row">
                        <th scope="col" class="p-4" role="columnheader">
                            <label class="flex items-center gap-3 text-xs">
                                <input type="checkbox" data-seleccionar-pagina-accesos aria-label="Seleccionar docentes de esta página" class="size-4 accent-institucional" @disabled(! $periodo?->estado || ! count($seleccionables))>
                                <span>Docente</span>
                            </label>
                        </th>
                        <th scope="col" class="p-4" role="columnheader">Correo</th>
                        <th scope="col" class="p-4" role="columnheader">Ingreso a la plataforma</th>
                        <th scope="col" class="p-4" role="columnheader">Disponibilidad</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-borde" role="rowgroup">
                    @forelse ($docentes as $fila)
                        @php
                            $profesor = $fila['profesor'];
                            $envio = $fila['envio'];
                            $usuario = $fila['usuario'];
                            $solicitud = $fila['solicitud'];
                            $respondida = $fila['respuesta'];
                            $envioEstado = $envio?->estado;
                        @endphp
                        <tr role="row">
                            <td class="p-4 align-top" role="cell" data-principal-listado>
                                <div class="flex min-w-0 items-start gap-2">
                                    <label class="inline-flex min-h-11 min-w-11 shrink-0 items-center justify-center">
                                        <span class="sr-only">Seleccionar {{ $profesor->nombre }} {{ $profesor->apellido }}</span>
                                        <input type="checkbox" name="profesores[]" value="{{ $profesor->id }}" data-docente-acceso @checked(in_array($profesor->id, $elegidos)) @disabled(! $fila['seleccionable'] || ! $periodo?->estado || ! $periodo->universidad->estado) class="size-4 accent-institucional">
                                    </label>
                                    <div class="min-w-0">
                                        <p class="font-semibold">{{ $profesor->apellido }}, {{ $profesor->nombre }}</p>
                                        <p class="mt-1 break-words text-xs text-texto-secundario">{{ $fila['correo'] ?: 'Sin correo registrado' }}</p>
                                        @if ($fila['rut_acceso'])<p class="mt-0.5 text-xs text-texto-secundario">{{ $fila['rut_acceso'] }}</p>@endif
                                        @if ($fila['motivo_envio'])<p class="mt-1 text-xs text-amber-800">{{ $fila['motivo_envio'] }}</p>@endif
                                    </div>
                                </div>
                            </td>
                            <td class="p-4 align-top" role="cell">
                                <span class="etiqueta-listado" aria-hidden="true">Correo</span>
                                <div class="dato-listado">
                                    <span @class([
                                        'inline-flex rounded-md px-2 py-1 text-xs font-semibold',
                                        'bg-green-50 text-green-800' => $envioEstado === 'enviado',
                                        'bg-red-50 text-red-800' => $envioEstado === 'fallido',
                                        'bg-amber-50 text-amber-800' => in_array($envioEstado, ['pendiente', 'en_proceso'], true),
                                        'bg-fondo text-texto-secundario' => ! $envio,
                                    ])>{{ match ($envioEstado) { 'enviado' => 'Invitación enviada', 'fallido' => 'Error de envío', 'en_proceso' => 'En proceso', 'pendiente' => 'Pendiente', default => 'Sin invitación' } }}</span>
                                    @if ($envio?->enviado_en)<p class="mt-1 text-xs text-texto-secundario">{{ $envio->enviado_en->format('d/m/Y H:i') }}</p>@endif
                                    @if ($solicitud)
                                        @include('disponibilidad.estado-recordatorios', ['solicitud' => $solicitud, 'periodo' => $periodo, 'invitacion' => $fila['invitacion_confirmada']])
                                    @endif
                                </div>
                            </td>
                            <td class="p-4 align-top" role="cell">
                                <span class="etiqueta-listado" aria-hidden="true">Ingreso a la plataforma</span>
                                <div class="dato-listado">
                                    @if ($usuario?->primer_ingreso_en)
                                        <p class="text-sm font-medium">Ingresó</p>
                                        <p class="mt-1 text-xs text-texto-secundario">{{ $usuario->primer_ingreso_en->format('d/m/Y H:i') }}</p>
                                        @if ($usuario->debe_cambiar_password)<p class="mt-1 text-xs text-amber-800">Cambio de contraseña pendiente</p>@endif
                                    @else
                                        <p class="text-texto-secundario">Aún no ingresa</p>
                                    @endif
                                </div>
                            </td>
                            <td class="p-4 align-top" role="cell">
                                <span class="etiqueta-listado" aria-hidden="true">Disponibilidad</span>
                                <div class="dato-listado">
                                    @if ($solicitud)
                                        <span @class([
                                            'inline-flex rounded-md px-2 py-1 text-xs font-semibold',
                                            'bg-green-50 text-green-800' => $fila['estado_solicitud'] === 'Aprobada',
                                            'bg-sky-50 text-sky-800' => $fila['estado_solicitud'] === 'En revisión',
                                            'bg-amber-50 text-amber-800' => in_array($fila['estado_solicitud'], ['Pendiente', 'Vencida'], true),
                                            'bg-fondo text-texto-secundario' => in_array($fila['estado_solicitud'], ['Cancelada', 'Rechazada'], true),
                                        ])>{{ $estadosDisponibilidad[$fila['estado_solicitud']] ?? $fila['estado_solicitud'] }}</span>
                                        @if ($solicitud->fecha_limite)
                                            <p class="mt-1 text-xs text-texto-secundario">Fecha límite {{ $solicitud->fecha_limite->format('d/m/Y') }}</p>
                                        @endif
                                        @if ($respondida?->fecha_respuesta)
                                            <p class="mt-1 text-xs text-texto-secundario">Respondió el {{ $respondida->fecha_respuesta->format('d/m/Y H:i') }}</p>
                                        @endif
                                        <a href="{{ route('disponibilidad.index', $filtros + ['solicitud' => $solicitud->id]) }}#detalle-disponibilidad" class="mt-2 inline-flex text-sm font-semibold text-institucional underline underline-offset-2">
                                            {{ $fila['estado_solicitud'] === 'En revisión' ? 'Revisar respuesta' : ($respondida ? 'Ver disponibilidad' : 'Ver solicitud') }}
                                        </a>
                                    @else
                                        <p class="text-texto-secundario">Sin solicitud</p>
                                    @endif
                                    @if ($fila['otras_solicitudes']->isNotEmpty())
                                        <details class="mt-2 rounded-lg border border-borde px-2.5 py-1.5 text-xs">
                                            <summary class="cursor-pointer font-medium text-texto">Historial de solicitudes ({{ $fila['otras_solicitudes']->count() }})</summary>
                                            <ul class="mt-1.5 space-y-1.5 pb-1">
                                                @foreach ($fila['otras_solicitudes'] as $anterior)
                                                    <li>
                                                        <a href="{{ route('disponibilidad.index', $filtros + ['solicitud' => $anterior->id]) }}#detalle-disponibilidad" class="block text-institucional underline underline-offset-2">
                                                            {{ $anterior->estado === 'Pendiente' && $anterior->fecha_limite?->copy()->endOfDay()->isPast() ? 'Vencida' : $anterior->estado }}
                                                            · {{ $anterior->fecha_solicitud?->format('d/m/Y') ?? 'Sin fecha' }}
                                                        </a>
                                                    </li>
                                                @endforeach
                                            </ul>
                                        </details>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr role="row"><td colspan="4" class="px-4 py-8 text-center text-texto-secundario" role="cell">No hay docentes para estos filtros.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($docentes->hasPages())<div class="border-t border-borde px-4 py-3">{{ $docentes->onEachSide(1)->links() }}</div>@endif
        @if ($periodo?->estado && $periodo->universidad->estado && count($seleccionables))
            <div class="grid items-end gap-4 border-t border-borde bg-fondo p-4 sm:grid-cols-[minmax(0,1fr)_minmax(10rem,14rem)_auto] sm:p-5">
                <div>
                    <p data-total-accesos role="status" class="text-sm font-medium" data-total-resultados="{{ count($seleccionables) }}">Selecciona los destinatarios.</p>
                    <p class="mt-1 text-xs text-texto-secundario">Quienes sigan sin responder recibirán un recordatorio 3 días y 1 día antes del plazo.</p>
                </div>
                <div>
                    <label for="fecha-limite-accesos" class="mb-1.5 block font-semibold">Responder hasta *</label>
                    <input id="fecha-limite-accesos" name="fecha_limite" type="date" required min="{{ today()->toDateString() }}" max="{{ today()->addYear()->toDateString() }}" value="{{ old('fecha_limite', today()->addDays(7)->toDateString()) }}" class="{{ $campo }}">
                </div>
                <button type="submit" data-enviar-accesos disabled class="boton boton-primario">{{ $correoDisponible ? 'Revisar envío' : 'Revisar solicitudes' }}</button>
            </div>
        @endif
        <label class="flex items-start gap-2 border-t border-borde px-5 py-4 text-sm">
            <input type="checkbox" name="confirmar_envio" value="1" required data-consentimiento-confirmacion class="mt-1 size-4 shrink-0 accent-institucional">
            <span>He revisado los destinatarios y confirmo el envío de las solicitudes de disponibilidad docente.</span>
        </label>
        <noscript><p class="border-t border-borde px-5 py-3 text-sm text-texto-secundario">Activa JavaScript para revisar la cantidad exacta y confirmar los destinatarios antes de enviar.</p></noscript>
    </form>

    <details class="overflow-hidden rounded-xl border border-borde bg-superficie shadow-sm" @if (request()->filled('historial_page') || session('success') || session('aviso_accesos')) open @endif>
        <summary class="cursor-pointer px-4 py-4 text-sm font-semibold sm:px-5">Historial de envíos · {{ number_format($historial->total(), 0, ',', '.') }}</summary>
        <div class="listado-adaptable overflow-x-auto border-t border-borde">
            <table class="w-full text-left text-sm" role="table">
                <thead class="bg-fondo text-xs uppercase text-texto-secundario" role="rowgroup">
                    <tr role="row">
                        <th scope="col" class="p-4" role="columnheader">Docente y correo</th>
                        <th scope="col" class="p-4" role="columnheader">Preparado</th>
                        <th scope="col" class="p-4" role="columnheader">Resultado</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-borde" role="rowgroup">
                    @forelse ($historial as $registro)
                        <tr role="row">
                            <td class="p-4 align-top" role="cell" data-principal-listado><p class="font-semibold">{{ $registro->profesor ? $registro->profesor->apellido.', '.$registro->profesor->nombre : 'Docente retirado' }}</p><p class="mt-1 break-words text-xs text-texto-secundario">{{ $registro->correo ?: 'Sin correo registrado' }}</p></td>
                            <td class="p-4 align-top" role="cell"><span class="etiqueta-listado" aria-hidden="true">Preparado</span><div class="dato-listado">{{ $registro->created_at->format('d/m/Y H:i') }}</div></td>
                            <td class="p-4 align-top" role="cell"><span class="etiqueta-listado" aria-hidden="true">Resultado</span><div class="dato-listado"><p class="font-medium">{{ match ($registro->estado) { 'enviado' => 'Enviado', 'fallido' => 'Error de envío', 'en_proceso' => 'En proceso', default => 'Pendiente' } }}</p>@if ($registro->enviado_en)<p class="mt-1 text-xs text-texto-secundario">{{ $registro->enviado_en->format('d/m/Y H:i') }}</p>@endif @if ($registro->error)<p class="mt-1 text-xs text-texto-secundario">{{ $registro->error }}</p>@endif</div></td>
                        </tr>
                    @empty
                        <tr role="row"><td colspan="3" class="px-4 py-6 text-center text-texto-secundario" role="cell">Todavía no se han preparado envíos para este período.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($historial->hasPages())<div class="border-t border-borde p-4">{{ $historial->onEachSide(1)->links() }}</div>@endif
    </details>
</div>

@endsection

@extends('layouts.app')
@section('title', 'Preparar generación | '.$identidad['nombre'])
@section('content')
<div class="space-y-6">
    <x-encabezado-pagina titulo="Preparar generación">Elige el período y la versión base para preparar un nuevo borrador.</x-encabezado-pagina>
    @include('disponibilidad.avisos')

    <form method="get" class="grid gap-4 rounded-xl border border-borde bg-superficie p-4 shadow-sm sm:p-5 lg:grid-cols-[minmax(0,1fr)_minmax(0,2fr)_auto] lg:items-end">
        <div>
            <label for="periodo_id" class="mb-2 block text-sm font-semibold">Período académico</label>
            <select id="periodo_id" name="periodo_id" class="h-11 w-full rounded-lg border border-borde px-3" onchange="this.form.querySelector('[name=version_id]').disabled=true; this.form.requestSubmit()">
                @forelse ($periodos as $p)
                    <option value="{{ $p->id }}" @selected($periodo?->id === $p->id)>{{ $p->nombre }}</option>
                @empty
                    <option value="">Sin períodos registrados</option>
                @endforelse
            </select>
        </div>
        <div>
            <label for="version_id" class="mb-2 block text-sm font-semibold">Versión base</label>
            <select id="version_id" name="version_id" class="h-11 w-full rounded-lg border border-borde px-3" onchange="this.form.requestSubmit()">
                @forelse ($versiones as $v)
                    <option value="{{ $v->id }}" @selected($version?->id === $v->id)>{{ $v->nombre }} · V{{ $v->version }} · {{ $v->estado }}</option>
                @empty
                    <option value="">Sin versiones disponibles</option>
                @endforelse
            </select>
        </div>
        <button class="boton boton-secundario" @disabled(! $periodo)>Revisar versión</button>
    </form>

    @if ($reporte)
        <section class="grid grid-cols-2 gap-3 sm:gap-4 xl:grid-cols-4" aria-label="Resumen del período">
            @foreach (['secciones' => 'Secciones activas', 'grupos' => 'Grupos por planificar', 'modulos' => 'Módulos requeridos', 'aprobados' => 'Docentes con disponibilidad apta'] as $clave => $nombre)
                <div class="rounded-xl border border-borde bg-superficie p-4 shadow-sm sm:p-5">
                    <p class="text-sm text-texto-secundario">{{ $nombre }}</p>
                    <p class="mt-1 text-2xl font-bold">
                        {{ number_format($reporte[$clave], 0, ',', '.') }}
                        @if ($clave === 'aprobados')<span class="text-sm font-normal text-texto-secundario"> / {{ $reporte['docentes'] }}</span>@endif
                    </p>
                </div>
            @endforeach
        </section>

        @if ($version)
            <section aria-labelledby="acciones-generacion" class="space-y-3">
                <h2 id="acciones-generacion" class="font-semibold">Cómo quieres preparar el horario</h2>
                <p class="text-sm text-texto-secundario">Versión revisada: {{ $version->nombre }} · V{{ $version->version }}. Cada opción guarda el resultado en un borrador independiente.</p>
                <div class="grid gap-4 xl:grid-cols-3">
                    <article class="flex flex-col items-start gap-3 rounded-xl border border-borde bg-superficie p-4 shadow-sm sm:p-5">
                        <h3 class="font-semibold">Generar para todo el período</h3>
                        <p class="text-sm text-texto-secundario">Conserva los horarios y espacios existentes y busca completar lo que falta en toda la oferta.</p>
                        <form method="post" action="{{ route('programacion.semestral.generar') }}" class="mt-auto" data-confirmar="Se creará un borrador con las asignaciones de la versión base y los nuevos horarios compatibles." data-confirmar-titulo="Generar programación" data-confirmar-accion="Generar borrador">
                            @csrf
                            <input type="hidden" name="version_base_id" value="{{ $version->id }}">
                            <input type="hidden" name="solicitud" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                            <button type="submit" class="boton boton-primario" @disabled(! $periodo->estado || ! $periodo->universidad?->estado)>Generar programación</button>
                        </form>
                    </article>
                    <article class="flex flex-col items-start gap-3 rounded-xl border border-borde bg-superficie p-4 shadow-sm sm:p-5">
                        <h3 class="font-semibold">Completar grupos específicos</h3>
                        <p class="text-sm text-texto-secundario">Elige los grupos y revisa los módulos que les faltan antes de guardar la propuesta.</p>
                        <a class="boton boton-secundario mt-auto" href="{{ route('programacion.completar.index', ['periodo_id' => $periodo->id, 'version_id' => $version->tipo === 'semestral' ? $version->id : null]) }}">Elegir grupos</a>
                    </article>
                    <article class="flex flex-col items-start gap-3 rounded-xl border border-borde bg-superficie p-4 shadow-sm sm:p-5">
                        <h3 class="font-semibold">Reorganizar clases existentes</h3>
                        <p class="text-sm text-texto-secundario">Busca nuevos horarios para las clases que ya están en la versión base. Úsalo cuando quieras moverlas.</p>
                        @if ($reporte['bloqueos'])
                            <a class="text-sm font-semibold text-institucional underline" href="#observaciones-preparacion">Revisar datos pendientes</a>
                        @endif
                        <form method="post" action="{{ route('programacion.reorganizacion.generar') }}" class="mt-auto">
                            @csrf
                            <input type="hidden" name="version_id" value="{{ $version->id }}">
                            <button type="submit" @disabled($reporte['bloqueos'] > 0) class="boton boton-secundario">Preparar reorganización</button>
                        </form>
                    </article>
                </div>
            </section>
        @endif

        <section class="scroll-mt-24 overflow-hidden rounded-xl border border-borde bg-superficie shadow-sm" aria-labelledby="observaciones-preparacion">
            <header class="space-y-2 border-b border-borde p-4 sm:p-5">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h2 id="observaciones-preparacion" class="font-semibold">Revisión para reorganizar</h2>
                    <span class="text-sm text-texto-secundario">{{ $reporte['bloqueos'] }} por resolver · {{ trans_choice('{1} :count aviso|[0,*] :count avisos', $observaciones->total() - $reporte['bloqueos']) }}</span>
                </div>
                <p class="text-sm text-texto-secundario">{{ $reporte['bloqueos'] ? 'Resuelve los puntos pendientes para poder reorganizar las clases existentes.' : 'No se detectaron bloqueos en los datos revisados.' }} Esta revisión no genera horarios.</p>
                <p class="text-sm text-texto-secundario">Las nuevas asignaciones automáticas se buscan fuera del almuerzo. Las clases que agregues manualmente pueden usar ese bloque.</p>
            </header>
            <div class="divide-y divide-borde">
                @forelse ($observaciones as $fila)
                    <article class="flex flex-col justify-between gap-3 p-4 sm:flex-row sm:items-center sm:p-5">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $fila['tipo'] === 'bloqueo' ? 'bg-red-50 text-red-800' : 'bg-sky-50 text-sky-800' }}">{{ $fila['tipo'] === 'bloqueo' ? 'Por resolver' : 'Aviso' }}</span>
                                <h3 class="text-sm font-semibold">{{ $fila['titulo'] }}</h3>
                            </div>
                            <p class="mt-2 text-sm text-texto-secundario">{{ $fila['detalle'] }}</p>
                        </div>
                        @if ($fila['url'])<a href="{{ $fila['url'] }}" class="boton boton-secundario shrink-0 self-start sm:self-center">Revisar</a>@endif
                    </article>
                @empty
                    <p class="p-5 text-sm text-texto-secundario">No se encontraron observaciones.</p>
                @endforelse
            </div>
            @if ($observaciones->hasPages())<div class="border-t border-borde p-4">{{ $observaciones->links() }}</div>@endif
        </section>
    @else
        <p class="rounded-xl border border-borde bg-superficie p-5 text-sm text-texto-secundario">Registra un período académico para preparar su programación.</p>
    @endif
</div>
@endsection

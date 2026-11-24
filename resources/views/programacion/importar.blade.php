@extends('layouts.app')

@section('content')
<div class="panel-editor">
    <x-encabezado-pagina titulo="Cargar programación anterior">
        Analiza el archivo y revisa sus cambios antes de incorporarlo.
    </x-encabezado-pagina>

    @if (session('success'))
        <x-aviso tipo="exito">{{ session('success') }}</x-aviso>
    @endif
    @if (session('error'))
        <x-aviso tipo="error">{{ session('error') }}</x-aviso>
    @endif

    <form action="{{ route('programacion.importar.analizar') }}" method="POST" enctype="multipart/form-data" class="panel-seccion">
        @csrf
        <div class="grid min-w-0 gap-4 @min-[38rem]/seccion:grid-cols-[minmax(0,1fr)_auto] @min-[38rem]/seccion:items-end">
            <div class="min-w-0">
                <label for="archivo" class="mb-1.5 block text-sm font-semibold">Archivo de programación</label>
                <x-campo-archivo id="archivo" name="archivo" ayuda="formatos-programacion" accept=".xlsx,.xls,.csv" required />
                <p id="formatos-programacion" class="mt-1.5 text-xs text-texto-secundario">XLSX, XLS o CSV de hasta 20 MB.</p>
            </div>
            <button type="submit" class="boton boton-primario @min-[38rem]/seccion:mb-6">Analizar archivo</button>
        </div>
        @error('archivo')
            <p role="alert" class="mt-3 text-sm text-red-700">{{ $message }}</p>
        @enderror
    </form>

    @isset($resultado)
        @php
            $valido = $resultado['valido'] ?? false;
            $comparacion = $resultado['comparacion'] ?? [];
            $conciliacion = $comparacion['conciliacion'] ?? [];
            $esNuevaProgramacion = $comparacion['es_nueva_programacion'] ?? false;
            $hayCambios = $comparacion['hay_cambios'] ?? false;
            $detalles = collect($comparacion['detalle_cambios'] ?? []);
            $gruposCambios = $detalles->groupBy(fn ($cambio) => json_encode([$cambio['entidad'], $cambio['nombre']]));
            $erroresComparacion = collect($comparacion['errores'] ?? []);
            $erroresConciliacion = collect($conciliacion['errores'] ?? []);
            $errores = $erroresComparacion->merge($erroresConciliacion)
                ->unique(fn ($error) => ($error['fila'] ?? '').'|'.($error['mensaje'] ?? ''))->values();
            $avisos = collect($conciliacion['avisos'] ?? [])->unique()->values();
            $puedeConfirmar = $valido && ($esNuevaProgramacion || $hayCambios)
                && $errores->isEmpty() && empty($conciliacion['total_errores'])
                && filled($resultado['ruta_temporal'] ?? null);
        @endphp

        <section class="min-w-0 overflow-hidden rounded-xl border border-borde bg-superficie shadow-sm" aria-labelledby="titulo-analisis">
            <header class="flex flex-wrap items-start justify-between gap-3 border-b border-borde p-4 sm:px-5">
                <div class="min-w-0">
                    <h2 id="titulo-analisis" class="font-semibold">Resultado del análisis</h2>
                    <p class="mt-1 break-words text-sm text-texto-secundario">{{ $resultado['nombre_archivo'] ?? 'Archivo' }}</p>
                    @if ($valido && filled($comparacion['periodo_actual'] ?? null))
                        <p class="mt-1 text-xs text-texto-secundario">
                            Período {{ $comparacion['periodo_actual'] }}
                            @if (isset($comparacion['version_anterior']['version']))
                                · Comparado con V{{ $comparacion['version_anterior']['version'] }}
                            @endif
                        </p>
                    @endif
                </div>
                <span @class([
                    'shrink-0 rounded-md px-2.5 py-1 text-xs font-semibold',
                    'bg-green-50 text-green-800' => $valido,
                    'bg-red-50 text-red-800' => ! $valido,
                ])>{{ $valido ? 'Estructura válida' : 'Estructura incompleta' }}</span>
            </header>

            @if (! $valido)
                <div class="p-4 sm:p-5">
                    <x-aviso tipo="error" titulo="Columnas requeridas no encontradas">
                        @if (! empty($resultado['faltantes']))
                            <ul class="mt-1 list-disc space-y-1 pl-5">
                                @foreach ($resultado['faltantes'] as $faltante)
                                    <li>{{ $faltante }}</li>
                                @endforeach
                            </ul>
                        @else
                            El archivo no pudo ser validado correctamente.
                        @endif
                    </x-aviso>
                </div>
            @elseif ($comparacion)
                <div class="space-y-5 p-4 sm:p-5">
                    @if ($detalles->isNotEmpty())
                        <div class="flex flex-wrap items-end justify-between gap-2">
                            <h3 class="font-semibold">Cambios del archivo</h3>
                            <p class="text-xs text-texto-secundario">
                                {{ number_format($gruposCambios->count(), 0, ',', '.') }}
                                {{ $gruposCambios->count() === 1 ? 'registro con cambios' : 'registros con cambios' }}
                            </p>
                        </div>
                        @include('programacion.detalle-cambios-importacion', ['gruposCambios' => $gruposCambios])
                    @elseif (! $hayCambios && ! $esNuevaProgramacion)
                        <div class="rounded-lg bg-fondo px-4 py-3 text-sm">
                            <p class="font-semibold">No se detectaron cambios</p>
                            <p class="mt-1 text-texto-secundario">El archivo coincide con la última programación anterior del período.</p>
                        </div>
                    @elseif ($esNuevaProgramacion)
                        <p class="rounded-lg bg-fondo px-4 py-3 text-sm text-texto-secundario">Se creará la primera versión de programación anterior del período.</p>
                    @endif

                    @if ($errores->isNotEmpty() || ($conciliacion['total_errores'] ?? 0))
                        <x-aviso tipo="error" titulo="Revisa estas observaciones antes de importar">
                            <ul class="mt-1 list-disc space-y-1 pl-5">
                                @foreach ($errores->take(30) as $error)
                                    <li>
                                        @if (filled($error['fila'] ?? null))Fila {{ $error['fila'] }}. @endif
                                        {{ $error['mensaje'] ?? 'Revisa los datos del archivo.' }}
                                    </li>
                                @endforeach
                            </ul>
                            @if ($errores->count() > 30 || ($conciliacion['total_errores'] ?? 0) > $erroresConciliacion->count())
                                <p class="mt-2">Se muestran las primeras observaciones. Corrígelas y vuelve a analizar el archivo para continuar.</p>
                            @endif
                        </x-aviso>
                    @endif

                    @if ($conciliacion)
                        <details class="rounded-lg border border-borde bg-fondo px-4 py-3">
                            <summary class="cursor-pointer text-sm font-semibold">Qué ocurre al guardar</summary>
                            <div class="mt-3 space-y-2 text-sm text-texto-secundario">
                                @if ($puedeConfirmar)
                                    <p>Al confirmar el archivo, se creará una nueva versión de la programación anterior.</p>
                                @endif
                                @foreach ($avisos as $aviso)
                                    <p>{{ $aviso }}</p>
                                @endforeach
                                @if ($puedeConfirmar)
                                    <p class="text-xs leading-5">Tienes 30 minutos para confirmar este análisis.</p>
                                @endif
                            </div>
                        </details>
                    @endif

                    <div class="flex flex-col-reverse gap-3 border-t border-borde pt-4 sm:flex-row sm:justify-end">
                        @if (filled($resultado['ruta_temporal'] ?? null))
                            <form action="{{ route('programacion.importar.cancelar') }}" method="POST">
                                @csrf
                                <input type="hidden" name="ruta_temporal" value="{{ $resultado['ruta_temporal'] }}">
                                <button type="submit" class="boton boton-secundario w-full sm:w-auto">{{ $puedeConfirmar ? 'Cancelar' : 'Volver' }}</button>
                            </form>
                        @else
                            <a href="{{ route('programacion.importar') }}" class="boton boton-secundario">Volver a la carga</a>
                        @endif

                        @if ($puedeConfirmar)
                            <form
                                action="{{ route('programacion.importar.confirmar') }}"
                                method="POST"
                                data-confirmar="Los datos de {{ $resultado['nombre_archivo'] ?? 'este archivo' }} se incorporarán como una versión de programación anterior."
                                data-confirmar-titulo="Confirmar carga de programación"
                                data-confirmar-accion="Cargar programación"
                            >
                                @csrf
                                <input type="hidden" name="ruta_temporal" value="{{ $resultado['ruta_temporal'] }}">
                                <input type="hidden" name="nombre_archivo" value="{{ $resultado['nombre_archivo'] ?? '' }}">
                                <button type="submit" class="boton boton-primario w-full sm:w-auto">Confirmar carga</button>
                            </form>
                        @endif
                    </div>
                </div>
            @endif
        </section>
    @endisset
</div>
@endsection

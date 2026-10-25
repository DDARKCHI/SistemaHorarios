@extends('layouts.app')

@section('content')

<div class="space-y-6">

    @if(session('success'))
        <div class="rounded-xl border border-green-200 bg-green-50 p-4 shadow-sm">
            <p class="font-medium text-green-800">
                {{ session('success') }}
            </p>
        </div>
    @endif

    @if(session('error'))
        <div class="rounded-xl border border-red-200 bg-red-50 p-4 shadow-sm">
            <p class="font-medium text-red-800">
                {{ session('error') }}
            </p>
        </div>
    @endif

    <div>
        <h1 class="text-2xl font-semibold text-gray-800">
            Cargar programación anterior
        </h1>

        <p class="mt-1 text-sm text-gray-500">
            Carga una programación anterior para analizar sus datos antes de incorporarlos al sistema.
        </p>
    </div>

    <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">

        <form
            action="{{ route('programacion.importar.analizar') }}"
            method="POST"
            enctype="multipart/form-data"
        >
            @csrf

            <div>
                <label
                    for="archivo"
                    class="mb-2 block text-sm font-medium text-gray-700"
                >
                    Archivo de programación
                </label>

                <input
                    id="archivo"
                    name="archivo"
                    type="file"
                    accept=".xlsx,.xls,.csv"
                    required
                    class="block w-full rounded-lg border border-gray-300 bg-white text-sm text-gray-700 file:mr-4 file:border-0 file:bg-gray-100 file:px-4 file:py-2 file:text-sm file:font-medium"
                >

                <p class="mt-2 text-xs text-gray-500">
                    Formatos permitidos: XLSX, XLS o CSV. Tamaño máximo: 20 MB.
                </p>
            </div>

            @error('archivo')
                <div class="mt-4 rounded-lg bg-red-50 p-3 text-sm text-red-700">
                    {{ $message }}
                </div>
            @enderror

            <div class="mt-6 flex justify-end">
                <button
                    type="submit"
                    class="rounded-lg bg-red-700 px-5 py-2.5 text-sm font-medium text-white transition hover:bg-red-800"
                >
                    Analizar archivo
                </button>
            </div>

        </form>

    </div>

    @isset($resultado)

        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">

            <div class="flex min-w-0 flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">

                <div class="min-w-0">
                    <h2 class="text-lg font-semibold text-gray-800">
                        Resultado del análisis
                    </h2>

                    <p class="mt-1 break-words text-sm text-gray-500">
                        {{ $resultado['nombre_archivo'] ?? 'Archivo' }}
                    </p>
                </div>

                @if($resultado['valido'] ?? false)

                    <span class="shrink-0 self-start rounded-full bg-green-100 px-3 py-1 text-sm font-medium text-green-700">
                        Estructura válida
                    </span>

                @else

                    <span class="shrink-0 self-start rounded-full bg-red-100 px-3 py-1 text-sm font-medium text-red-700">
                        Estructura incompleta
                    </span>

                @endif

            </div>

            <div class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2">

                <div class="rounded-lg bg-gray-50 p-4">
                    <p class="text-sm text-gray-500">
                        Columnas
                    </p>

                    <p class="mt-1 text-2xl font-semibold text-gray-800">
                        {{ $resultado['total_columnas'] ?? 0 }}
                    </p>
                </div>

                <div class="rounded-lg bg-gray-50 p-4">
                    <p class="text-sm text-gray-500">
                        Filas vacías
                    </p>

                    <p class="mt-1 text-2xl font-semibold text-gray-800">
                        {{ $resultado['filas_vacias'] ?? 0 }}
                    </p>
                </div>

            </div>

            @if(!($resultado['valido'] ?? false))

                <div class="mt-6 rounded-lg bg-red-50 p-4">

                    <h3 class="font-medium text-red-800">
                        Columnas requeridas no encontradas
                    </h3>

                    @if(!empty($resultado['faltantes']))

                        <ul class="mt-2 list-disc pl-5 text-sm text-red-700">
                            @foreach($resultado['faltantes'] as $faltante)
                                <li>
                                    {{ $faltante }}
                                </li>
                            @endforeach
                        </ul>

                    @else

                        <p class="mt-2 text-sm text-red-700">
                            El archivo no pudo ser validado correctamente.
                        </p>

                    @endif

                </div>

            @else

                @if(isset($resultado['comparacion']))

                    @php
                        $comparacion = $resultado['comparacion'];
                        $diagnostico = $comparacion['diagnostico'] ?? [];
                        $totales = $comparacion['totales'] ?? [];

                        $esNuevaProgramacion = $comparacion['es_nueva_programacion'] ?? false;
                        $hayCambios = $comparacion['hay_cambios'] ?? false;
                        $resumenCambios = $comparacion['resumen_cambios'] ?? [];

                        $mostrarCambios =
                            !$esNuevaProgramacion
                            && $hayCambios
                            && count($resumenCambios) > 0;

                        $puedeConfirmar =
                            $esNuevaProgramacion
                            || $hayCambios;
                    @endphp

                    <div class="mt-8">

                        <h3 class="text-lg font-semibold text-gray-800">
                            Resumen de la programación
                        </h3>

                        <p class="mt-1 text-sm text-gray-500">
                            Datos generales detectados en el archivo.
                        </p>

                        <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">

                            <div class="rounded-xl border border-gray-200 bg-white p-5">
                                <p class="text-sm text-gray-500">
                                    Períodos académicos
                                </p>

                                <p class="mt-2 text-3xl font-semibold text-gray-800">
                                    {{ $totales['periodos'] ?? 0 }}
                                </p>
                            </div>

                            <div class="rounded-xl border border-gray-200 bg-white p-5">
                                <p class="text-sm text-gray-500">
                                    Carreras
                                </p>

                                <p class="mt-2 text-3xl font-semibold text-gray-800">
                                    {{ $totales['carreras'] ?? 0 }}
                                </p>
                            </div>

                            <div class="rounded-xl border border-gray-200 bg-white p-5">
                                <p class="text-sm text-gray-500">
                                    Asignaturas
                                </p>

                                <p class="mt-2 text-3xl font-semibold text-gray-800">
                                    {{ $totales['asignaturas'] ?? 0 }}
                                </p>
                            </div>

                            <div class="rounded-xl border border-gray-200 bg-white p-5">
                                <p class="text-sm text-gray-500">
                                    Secciones
                                </p>

                                <p class="mt-2 text-3xl font-semibold text-gray-800">
                                    {{ $totales['secciones'] ?? 0 }}
                                </p>
                            </div>

                            <div class="rounded-xl border border-gray-200 bg-white p-5">
                                <p class="text-sm text-gray-500">
                                    Profesores
                                </p>

                                <p class="mt-2 text-3xl font-semibold text-gray-800">
                                    {{ $totales['profesores'] ?? 0 }}
                                </p>
                            </div>

                            <div class="rounded-xl border border-gray-200 bg-white p-5">
                                <p class="text-sm text-gray-500">
                                    Grupos
                                </p>

                                <p class="mt-2 text-3xl font-semibold text-gray-800">
                                    {{ $totales['grupos'] ?? 0 }}
                                </p>
                            </div>

                            <div class="rounded-xl border border-gray-200 bg-white p-5">
                                <p class="text-sm text-gray-500">
                                    Relaciones sección-carrera
                                </p>

                                <p class="mt-2 text-3xl font-semibold text-gray-800">
                                    {{ $totales['relaciones_seccion_carrera'] ?? 0 }}
                                </p>
                            </div>

                            <div class="rounded-xl border border-gray-200 bg-white p-5">
                                <p class="text-sm text-gray-500">
                                    Registros procesados
                                </p>

                                <p class="mt-2 text-3xl font-semibold text-gray-800">
                                    {{ $totales['registros_excel'] ?? 0 }}
                                </p>
                            </div>

                        </div>

                    </div>

                    <div class="mt-10">

                        <h3 class="text-lg font-semibold text-gray-800">
                            Diagnóstico de la estructura académica
                        </h3>

                        <p class="mt-1 text-sm text-gray-500">
                            Información especial detectada en la programación.
                        </p>

                        <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-3">

                            <div class="rounded-lg border border-yellow-200 bg-yellow-50 p-4">

                                <p class="text-sm text-yellow-700">
                                    Cursos asociados a más de una carrera
                                </p>

                                <p class="mt-1 text-2xl font-semibold text-yellow-800">
                                    {{ $diagnostico['cursos_compartidos'] ?? 0 }}
                                </p>

                            </div>

                            <div class="rounded-lg border border-gray-200 bg-gray-50 p-4">

                                <p class="text-sm text-gray-500">
                                    Cursos compartidos con un profesor
                                </p>

                                <p class="mt-1 text-2xl font-semibold text-gray-800">
                                    {{ $diagnostico['cursos_compartidos_un_profesor'] ?? 0 }}
                                </p>

                            </div>

                            <div class="rounded-lg border border-gray-200 bg-gray-50 p-4">

                                <p class="text-sm text-gray-500">
                                    Cursos compartidos con varios profesores
                                </p>

                                <p class="mt-1 text-2xl font-semibold text-gray-800">
                                    {{ $diagnostico['cursos_compartidos_varios_profesores'] ?? 0 }}
                                </p>

                            </div>

                        </div>

                        @if(($diagnostico['cursos_compartidos'] ?? 0) > 0)

                            <div class="mt-4 rounded-lg border border-yellow-200 bg-yellow-50 p-4">

                                <p class="text-sm text-yellow-700">
                                    Los cursos compartidos se mantendrán como una misma sección y quedarán relacionados con las carreras correspondientes.
                                </p>

                            </div>

                        @endif

                    </div>

                    @if($esNuevaProgramacion)

                        <div class="mt-10 rounded-lg border border-blue-200 bg-blue-50 p-4">

                            <p class="font-medium text-blue-800">
                                Primera versión de programación
                            </p>

                            <p class="mt-1 text-sm text-blue-700">
                                Todos los datos detectados en el archivo serán incorporados como la versión inicial de la programación anterior.
                            </p>

                        </div>

                    @elseif($mostrarCambios)

                        <div class="mt-10">

                            <h3 class="text-lg font-semibold text-gray-800">
                                Cambios detectados
                            </h3>

                            <p class="mt-1 text-sm text-gray-500">
                                Cambios encontrados respecto de la versión actual de la programación.
                            </p>

                            <div class="mt-4 space-y-3">

                                @foreach($resumenCambios as $cambio)

                                    <div class="flex items-start justify-between gap-4 rounded-lg border border-blue-200 bg-blue-50 p-4">

                                        <div>

                                            <p class="font-medium text-blue-800">
                                                {{ ucfirst($cambio['descripcion'] ?? 'Cambio detectado') }}
                                            </p>

                                            <p class="mt-1 text-sm text-blue-700">

                                                @switch($cambio['tipo'] ?? '')

                                                    @case('periodos')
                                                        Se detectaron nuevos períodos académicos.
                                                        @break

                                                    @case('carreras')
                                                        Se detectaron nuevas carreras.
                                                        @break

                                                    @case('asignaturas')
                                                        Se detectaron nuevas asignaturas.
                                                        @break

                                                    @case('profesores')
                                                        Se detectaron nuevos profesores.
                                                        @break

                                                    @case('profesores_modificados')
                                                        Se modificaron datos de profesores existentes.
                                                        @break

                                                    @case('secciones_nuevas')
                                                        Se detectaron nuevas secciones.
                                                        @break

                                                    @case('secciones_modificadas')
                                                        Se detectaron modificaciones en secciones existentes.
                                                        @break

                                                    @case('relaciones_nuevas')
                                                        Se detectaron nuevas relaciones entre secciones y carreras.
                                                        @break

                                                    @case('relaciones_modificadas')
                                                        Se detectaron modificaciones en relaciones entre secciones y carreras.
                                                        @break

                                                    @case('grupos_nuevos')
                                                        Se detectaron nuevos grupos.
                                                        @break

                                                    @case('horarios_nuevos')
                                                        Se detectaron nuevos horarios.
                                                        @break

                                                    @case('horarios_modificados')
                                                        Se detectaron modificaciones de horarios.
                                                        @break

                                                    @default
                                                        Se detectaron cambios en la programación.

                                                @endswitch

                                            </p>

                                        </div>

                                        <span class="shrink-0 rounded-full bg-blue-100 px-3 py-1 text-sm font-semibold text-blue-800">
                                            {{ $cambio['cantidad'] ?? 0 }}
                                        </span>

                                    </div>

                                @endforeach

                            </div>

                        </div>

                    @elseif(!$hayCambios)

                        <div class="mt-10 rounded-lg border border-green-200 bg-green-50 p-4">

                            <p class="font-medium text-green-800">
                                No se detectaron cambios
                            </p>

                            <p class="mt-1 text-sm text-green-700">
                                El archivo contiene la misma información que la versión actual de la programación.
                            </p>

                        </div>

                    @endif

                    @if(($totales['errores'] ?? 0) > 0)

                        <div class="mt-6 rounded-lg border border-red-200 bg-red-50 p-4">

                            <p class="font-medium text-red-800">
                                Se encontraron
                                {{ $totales['errores'] }}
                                registros con errores.
                            </p>

                        </div>

                    @else

                        <div class="mt-6 rounded-lg border border-green-200 bg-green-50 p-4">

                            <p class="font-medium text-green-800">
                                No se encontraron errores en los registros analizados.
                            </p>

                        </div>

                    @endif

                    <div class="mt-8 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">

                        <form
                            action="{{ route('programacion.importar.cancelar') }}"
                            method="POST"
                        >
                            @csrf

                            <input
                                type="hidden"
                                name="ruta_temporal"
                                value="{{ $resultado['ruta_temporal'] ?? '' }}"
                            >

                            <button
                                type="submit"
                                class="w-full rounded-lg border border-gray-300 bg-white px-5 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50 sm:w-auto"
                            >
                                {{ $puedeConfirmar ? 'Cancelar' : 'Volver' }}
                            </button>
                        </form>

                        @if($puedeConfirmar)

                            <form
                                action="{{ route('programacion.importar.confirmar') }}"
                                method="POST"
                                onsubmit="const boton = this.querySelector('button[type=submit]'); boton.disabled = true; boton.textContent = 'Cargando programación...';"
                            >
                                @csrf

                                <input
                                    type="hidden"
                                    name="ruta_temporal"
                                    value="{{ $resultado['ruta_temporal'] ?? '' }}"
                                >

                                <input
                                    type="hidden"
                                    name="nombre_archivo"
                                    value="{{ $resultado['nombre_archivo'] ?? '' }}"
                                >

                                <button
                                    type="submit"
                                    class="w-full rounded-lg bg-red-700 px-5 py-2.5 text-sm font-medium text-white transition hover:bg-red-800 disabled:cursor-not-allowed disabled:opacity-60 sm:w-auto"
                                >
                                    Confirmar carga
                                </button>
                            </form>

                        @endif

                    </div>

                    @if($puedeConfirmar)

                        <p class="mt-2 text-right text-xs text-gray-400">
                            Al confirmar, los datos del archivo se incorporarán como una nueva versión de la programación anterior.
                        </p>

                    @endif

                @endif

            @endif

        </div>

    @endisset

</div>

@endsection
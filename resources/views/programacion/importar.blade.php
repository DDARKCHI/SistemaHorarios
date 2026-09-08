@extends('layouts.app')

@section('content')
<div class="space-y-6">

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

            <div class="flex items-center justify-between gap-4">

                <div>
                    <h2 class="text-lg font-semibold text-gray-800">
                        Resultado del análisis
                    </h2>

                    <p class="mt-1 text-sm text-gray-500">
                        {{ $resultado['nombre_archivo'] ?? 'Archivo' }}
                    </p>
                </div>

                @if($resultado['valido'] ?? false)

                    <span class="rounded-full bg-green-100 px-3 py-1 text-sm font-medium text-green-700">
                        Estructura válida
                    </span>

                @else

                    <span class="rounded-full bg-red-100 px-3 py-1 text-sm font-medium text-red-700">
                        Estructura incompleta
                    </span>

                @endif

            </div>

            <div class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-3">

                <div class="rounded-lg bg-gray-50 p-4">
                    <p class="text-sm text-gray-500">
                        Registros
                    </p>

                    <p class="mt-1 text-2xl font-semibold text-gray-800">
                        {{ $resultado['total_registros'] ?? 0 }}
                    </p>
                </div>

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
                        $diagnostico = $resultado['comparacion']['diagnostico'] ?? [];
                        $totales = $resultado['comparacion']['totales'] ?? [];
                    @endphp

                    <div class="mt-8">

                        <div>
                            <h3 class="text-lg font-semibold text-gray-800">
                                Resumen de la programación
                            </h3>

                            <p class="mt-1 text-sm text-gray-500">
                                Resumen general de los datos que contiene el archivo antes de incorporarlos al sistema.
                            </p>
                        </div>

                        <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">

                            <div class="rounded-xl border border-gray-200 bg-white p-5">

                                <p class="text-sm text-gray-500">
                                    Periodos académicos
                                </p>

                                <p class="mt-2 text-3xl font-semibold text-gray-800">
                                    {{ $totales['periodos'] ?? 0 }}
                                </p>

                                <p class="mt-1 text-xs text-gray-500">
                                    Periodos detectados
                                </p>

                            </div>

                            <div class="rounded-xl border border-gray-200 bg-white p-5">

                                <p class="text-sm text-gray-500">
                                    Carreras
                                </p>

                                <p class="mt-2 text-3xl font-semibold text-gray-800">
                                    {{ $totales['carreras'] ?? 0 }}
                                </p>

                                <p class="mt-1 text-xs text-gray-500">
                                    Carreras asociadas
                                </p>

                            </div>

                            <div class="rounded-xl border border-gray-200 bg-white p-5">

                                <p class="text-sm text-gray-500">
                                    Asignaturas
                                </p>

                                <p class="mt-2 text-3xl font-semibold text-gray-800">
                                    {{ $totales['asignaturas'] ?? 0 }}
                                </p>

                                <p class="mt-1 text-xs text-gray-500">
                                    Asignaturas detectadas
                                </p>

                            </div>

                            <div class="rounded-xl border border-gray-200 bg-white p-5">

                                <p class="text-sm text-gray-500">
                                    Secciones
                                </p>

                                <p class="mt-2 text-3xl font-semibold text-gray-800">
                                    {{ $totales['secciones'] ?? 0 }}
                                </p>

                                <p class="mt-1 text-xs text-gray-500">
                                    Secciones reales
                                </p>

                            </div>

                            <div class="rounded-xl border border-gray-200 bg-white p-5">

                                <p class="text-sm text-gray-500">
                                    Profesores
                                </p>

                                <p class="mt-2 text-3xl font-semibold text-gray-800">
                                    {{ $totales['profesores'] ?? 0 }}
                                </p>

                                <p class="mt-1 text-xs text-gray-500">
                                    Profesores detectados
                                </p>

                            </div>

                            <div class="rounded-xl border border-gray-200 bg-white p-5">

                                <p class="text-sm text-gray-500">
                                    Grupos
                                </p>

                                <p class="mt-2 text-3xl font-semibold text-gray-800">
                                    {{ $diagnostico['grupos_unicos'] ?? 0 }}
                                </p>

                                <p class="mt-1 text-xs text-gray-500">
                                    Grupos detectados
                                </p>

                            </div>

                            <div class="rounded-xl border border-gray-200 bg-white p-5">

                                <p class="text-sm text-gray-500">
                                    Relaciones sección-carrera
                                </p>

                                <p class="mt-2 text-3xl font-semibold text-gray-800">
                                    {{ $diagnostico['relaciones_seccion_carrera'] ?? 0 }}
                                </p>

                                <p class="mt-1 text-xs text-gray-500">
                                    Asociaciones detectadas
                                </p>

                            </div>

                            <div class="rounded-xl border border-gray-200 bg-white p-5">

                                <p class="text-sm text-gray-500">
                                    Registros procesados
                                </p>

                                <p class="mt-2 text-3xl font-semibold text-gray-800">
                                    {{ $resultado['total_registros'] ?? 0 }}
                                </p>

                                <p class="mt-1 text-xs text-gray-500">
                                    Filas del archivo
                                </p>

                            </div>

                        </div>

                        <div class="mt-4 rounded-lg border border-blue-200 bg-blue-50 p-4">

                            <p class="font-medium text-blue-800">
                                Programación detectada correctamente
                            </p>

                            <p class="mt-1 text-sm text-blue-700">
                                El archivo contiene
                                {{ $totales['secciones'] ?? 0 }}
                                secciones distribuidas entre
                                {{ $totales['carreras'] ?? 0 }}
                                carreras y
                                {{ $totales['profesores'] ?? 0 }}
                                profesores.
                            </p>

                        </div>

                    </div>

                    <div class="mt-10">

                        <h3 class="text-lg font-semibold text-gray-800">
                            Diagnóstico de la estructura académica
                        </h3>

                        <p class="mt-1 text-sm text-gray-500">
                            Información utilizada para comprobar cómo se interpretará la programación antes de almacenarla.
                        </p>

                        <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">

                            <div class="rounded-lg border border-gray-200 bg-gray-50 p-4">

                                <p class="text-sm text-gray-500">
                                    Filas originales
                                </p>

                                <p class="mt-1 text-2xl font-semibold text-gray-800">
                                    {{ $diagnostico['filas_originales'] ?? 0 }}
                                </p>

                            </div>

                            <div class="rounded-lg border border-gray-200 bg-gray-50 p-4">

                                <p class="text-sm text-gray-500">
                                    Cursos únicos
                                </p>

                                <p class="mt-1 text-2xl font-semibold text-gray-800">
                                    {{ $diagnostico['id_cursos_unicos'] ?? 0 }}
                                </p>

                                <p class="mt-1 text-xs text-gray-500">
                                    ID_CURSO
                                </p>

                            </div>

                            <div class="rounded-lg border border-gray-200 bg-gray-50 p-4">

                                <p class="text-sm text-gray-500">
                                    Asignaturas
                                </p>

                                <p class="mt-1 text-2xl font-semibold text-gray-800">
                                    {{ $diagnostico['cod_ramos_unicos'] ?? 0 }}
                                </p>

                                <p class="mt-1 text-xs text-gray-500">
                                    COD_RAMO
                                </p>

                            </div>

                            <div class="rounded-lg border border-gray-200 bg-gray-50 p-4">

                                <p class="text-sm text-gray-500">
                                    Secciones
                                </p>

                                <p class="mt-1 text-2xl font-semibold text-gray-800">
                                    {{ $diagnostico['id_curso_seccion_unicos'] ?? 0 }}
                                </p>

                                <p class="mt-1 text-xs text-gray-500">
                                    Secciones reales detectadas
                                </p>

                            </div>

                            <div class="rounded-lg border border-gray-200 bg-gray-50 p-4">

                                <p class="text-sm text-gray-500">
                                    Relaciones sección-carrera
                                </p>

                                <p class="mt-1 text-2xl font-semibold text-gray-800">
                                    {{ $diagnostico['relaciones_seccion_carrera'] ?? 0 }}
                                </p>

                                <p class="mt-1 text-xs text-gray-500">
                                    Asociaciones detectadas
                                </p>

                            </div>

                            <div class="rounded-lg border border-gray-200 bg-gray-50 p-4">

                                <p class="text-sm text-gray-500">
                                    Grupos
                                </p>

                                <p class="mt-1 text-2xl font-semibold text-gray-800">
                                    {{ $diagnostico['grupos_unicos'] ?? 0 }}
                                </p>

                                <p class="mt-1 text-xs text-gray-500">
                                    Grupos detectados en horarios
                                </p>

                            </div>

                        </div>

                    </div>

                    @if(($diagnostico['cursos_compartidos'] ?? 0) > 0)

                        <div class="mt-8">

                            <div class="rounded-lg border border-yellow-200 bg-yellow-50 p-4">

                                <p class="font-medium text-yellow-800">
                                    Cursos asociados a más de una carrera:
                                    {{ $diagnostico['cursos_compartidos'] }}
                                </p>

                                <p class="mt-1 text-sm text-yellow-700">
                                    Estos cursos se mantendrán como una misma sección con relaciones hacia las carreras correspondientes.
                                </p>

                            </div>

                            <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">

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

                            <div class="mt-4 overflow-x-auto rounded-lg border border-gray-200">

                                <table class="min-w-full text-left text-sm">

                                    <thead class="bg-gray-50">

                                        <tr>

                                            <th class="whitespace-nowrap border-b border-gray-200 px-4 py-3 font-medium text-gray-700">
                                                ID_CURSO
                                            </th>

                                            <th class="whitespace-nowrap border-b border-gray-200 px-4 py-3 font-medium text-gray-700">
                                                COD_RAMO
                                            </th>

                                            <th class="whitespace-nowrap border-b border-gray-200 px-4 py-3 font-medium text-gray-700">
                                                SECC
                                            </th>

                                            <th class="border-b border-gray-200 px-4 py-3 font-medium text-gray-700">
                                                Carreras
                                            </th>

                                            <th class="border-b border-gray-200 px-4 py-3 font-medium text-gray-700">
                                                Profesores
                                            </th>

                                            <th class="border-b border-gray-200 px-4 py-3 font-medium text-gray-700">
                                                Carrera / profesor
                                            </th>

                                        </tr>

                                    </thead>

                                    <tbody>

                                        @foreach($diagnostico['ejemplos_cursos_compartidos'] ?? [] as $curso)

                                            <tr class="border-b border-gray-100 align-top last:border-0">

                                                <td class="whitespace-nowrap px-4 py-3 font-medium text-gray-800">
                                                    {{ $curso['id_curso'] }}
                                                </td>

                                                <td class="whitespace-nowrap px-4 py-3 text-gray-600">
                                                    {{ $curso['cod_ramo'] }}
                                                </td>

                                                <td class="whitespace-nowrap px-4 py-3 text-gray-600">
                                                    {{ $curso['secc'] }}
                                                </td>

                                                <td class="px-4 py-3 text-gray-600">

                                                    <div class="max-w-xl space-y-1">

                                                        @foreach($curso['carreras'] ?? [] as $carrera)

                                                            <div>
                                                                {{ $carrera }}
                                                            </div>

                                                        @endforeach

                                                    </div>

                                                </td>

                                                <td class="px-4 py-3 text-gray-600">

                                                    <div class="max-w-md space-y-1">

                                                        @forelse($curso['profesores'] ?? [] as $profesor)

                                                            <div>

                                                                {{ $profesor['nombre'] ?: 'Profesor sin nombre' }}

                                                                @if(!empty($profesor['identificador']))

                                                                    <span class="text-xs text-gray-400">
                                                                        ({{ $profesor['identificador'] }})
                                                                    </span>

                                                                @endif

                                                            </div>

                                                        @empty

                                                            <span class="text-gray-400">
                                                                Sin profesor informado
                                                            </span>

                                                        @endforelse

                                                    </div>

                                                </td>

                                                <td class="px-4 py-3 text-gray-600">

                                                    <div class="max-w-2xl space-y-3">

                                                        @foreach($curso['carrera_profesores'] ?? [] as $relacion)

                                                            <div class="rounded-lg bg-gray-50 p-3">

                                                                <p class="font-medium text-gray-800">
                                                                    {{ $relacion['carrera'] }}
                                                                </p>

                                                                <div class="mt-1 space-y-1">

                                                                    @forelse($relacion['profesores'] ?? [] as $profesor)

                                                                        <p class="text-sm text-gray-600">

                                                                            {{ $profesor['nombre'] ?: 'Profesor sin nombre' }}

                                                                            @if(!empty($profesor['identificador']))

                                                                                <span class="text-xs text-gray-400">
                                                                                    ({{ $profesor['identificador'] }})
                                                                                </span>

                                                                            @endif

                                                                        </p>

                                                                    @empty

                                                                        <p class="text-sm text-gray-400">
                                                                            Sin profesor informado
                                                                        </p>

                                                                    @endforelse

                                                                </div>

                                                            </div>

                                                        @endforeach

                                                    </div>

                                                </td>

                                            </tr>

                                        @endforeach

                                    </tbody>

                                </table>

                            </div>

                        </div>

                    @else

                        <div class="mt-8 rounded-lg border border-green-200 bg-green-50 p-4">

                            <p class="font-medium text-green-800">
                                No se detectaron cursos asociados a múltiples carreras.
                            </p>

                        </div>

                    @endif

                    <div class="mt-10">

                        <h3 class="text-lg font-semibold text-gray-800">
                            Estado de los datos
                        </h3>

                        <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">

                            <div class="rounded-lg border border-gray-200 bg-gray-50 p-4">

                                <p class="text-sm text-gray-500">
                                    Periodos nuevos
                                </p>

                                <p class="mt-1 text-2xl font-semibold text-gray-800">
                                    {{ $resultado['comparacion']['periodos']['nuevos'] ?? 0 }}
                                </p>

                            </div>

                            <div class="rounded-lg border border-gray-200 bg-gray-50 p-4">

                                <p class="text-sm text-gray-500">
                                    Carreras nuevas
                                </p>

                                <p class="mt-1 text-2xl font-semibold text-gray-800">
                                    {{ $resultado['comparacion']['carreras']['nuevas'] ?? 0 }}
                                </p>

                            </div>

                            <div class="rounded-lg border border-gray-200 bg-gray-50 p-4">

                                <p class="text-sm text-gray-500">
                                    Asignaturas nuevas
                                </p>

                                <p class="mt-1 text-2xl font-semibold text-gray-800">
                                    {{ $resultado['comparacion']['asignaturas']['nuevas'] ?? 0 }}
                                </p>

                            </div>

                            <div class="rounded-lg border border-gray-200 bg-gray-50 p-4">

                                <p class="text-sm text-gray-500">
                                    Profesores nuevos
                                </p>

                                <p class="mt-1 text-2xl font-semibold text-gray-800">
                                    {{ $resultado['comparacion']['profesores']['nuevos'] ?? 0 }}
                                </p>

                            </div>

                        </div>

                    </div>

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

                    <div class="mt-8 flex justify-end">

                        <button
                            type="button"
                            disabled
                            class="cursor-not-allowed rounded-lg bg-gray-300 px-5 py-2.5 text-sm font-medium text-gray-600"
                        >
                            Confirmar carga
                        </button>

                    </div>

                    <p class="mt-2 text-right text-xs text-gray-400">
                        La confirmación de carga se habilitará cuando finalicemos la persistencia de los datos.
                    </p>

                @endif

                <div class="mt-10">

                    <h3 class="text-sm font-semibold text-gray-800">
                        Primeras filas normalizadas
                    </h3>

                    <div class="mt-3 overflow-x-auto rounded-lg border border-gray-200">

                        <table class="min-w-full text-left text-sm">

                            <thead class="bg-gray-50">

                                <tr>

                                    @foreach($resultado['encabezados'] as $encabezado)

                                        <th class="whitespace-nowrap border-b border-gray-200 px-4 py-3 font-medium text-gray-700">
                                            {{ $encabezado }}
                                        </th>

                                    @endforeach

                                </tr>

                            </thead>

                            <tbody>

                                @foreach($resultado['primeras_filas'] as $fila)

                                    <tr class="border-b border-gray-100 last:border-0">

                                        @foreach($resultado['encabezados'] as $encabezado)

                                            <td class="whitespace-nowrap px-4 py-3 text-gray-600">
                                                {{ $fila[$encabezado] ?? '' }}
                                            </td>

                                        @endforeach

                                    </tr>

                                @endforeach

                            </tbody>

                        </table>

                    </div>

                </div>

            @endif

        </div>

    @endisset

</div>
@endsection
@extends('layouts.app')

@section('content')

<x-encabezado-pagina titulo="Administración">
    Administra los accesos y los catálogos institucionales y académicos.
    <x-slot:acciones>
        <a
            href="{{ route('usuarios.create') }}"
            class="boton boton-primario"
        >
            Nuevo usuario
        </a>
    </x-slot:acciones>
</x-encabezado-pagina>

<nav aria-label="Catálogos institucionales y académicos" class="mb-6 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
    <a class="min-w-0 rounded-xl border border-borde bg-superficie p-4 shadow-sm transition hover:border-institucional focus-visible:outline focus-visible:outline-2 focus-visible:outline-institucional" href="{{ route('administracion.perfiles.index') }}"><span class="block text-sm font-semibold">Perfiles y permisos</span><span class="mt-1 block text-xs text-texto-secundario">Funciones permitidas y cuentas vinculadas a cada perfil</span></a>
    <a class="min-w-0 rounded-xl border border-borde bg-superficie p-4 shadow-sm transition hover:border-institucional focus-visible:outline focus-visible:outline-2 focus-visible:outline-institucional" href="{{ route('administracion.docentes.index') }}"><span class="block text-sm font-semibold">Docentes</span><span class="mt-1 block text-xs text-texto-secundario">Registro y datos académicos de los docentes</span></a>
    <a class="min-w-0 rounded-xl border border-borde bg-superficie p-4 shadow-sm transition hover:border-institucional focus-visible:outline focus-visible:outline-2 focus-visible:outline-institucional" href="{{ route('administracion.secciones.index') }}"><span class="block text-sm font-semibold">Oferta por período</span><span class="mt-1 block text-xs text-texto-secundario">Secciones, actividades y grupos para programar</span></a>
    @foreach ([['Sedes', 'Espacios y sedes de la institución', 'administracion.estructura.index', 'sedes'], ['Carreras', 'Carreras y las sedes donde se imparten', 'administracion.estructura.index', 'carreras'], ['Facultades e institutos', 'Unidades académicas y sus carreras', 'administracion.academicos.index', 'facultades'], ['Asignaturas y ramos', 'Identificación y módulos de referencia', 'administracion.oferta.index', 'asignaturas'], ['Períodos académicos', 'Semestres de trabajo y archivo', 'administracion.oferta.index', 'periodos']] as [$tituloCatalogo, $detalleCatalogo, $rutaCatalogo, $tipoCatalogo])
    <a class="min-w-0 rounded-xl border border-borde bg-superficie p-4 shadow-sm transition hover:border-institucional focus-visible:outline focus-visible:outline-2 focus-visible:outline-institucional" href="{{ route($rutaCatalogo, $tipoCatalogo) }}"><span class="block text-sm font-semibold">{{ $tituloCatalogo }}</span><span class="mt-1 block text-xs text-texto-secundario">{{ $detalleCatalogo }}</span></a>
    @endforeach
</nav>

<div class="min-w-0 w-full">

    @if (session('success'))
        <div class="mb-3 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
            {{ session('success') }}
        </div>
    @endif

    @if (session('error'))
        <div class="mb-3 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            {{ session('error') }}
        </div>
    @endif

    <div class="overflow-hidden rounded-xl border border-borde bg-white shadow-sm">

        <div class="listado-adaptable overflow-x-auto overscroll-x-contain" role="region" aria-label="Listado de usuarios">
            <table role="table" class="min-w-full divide-y divide-gray-200">

                <thead role="rowgroup" class="bg-gray-50">
                    <tr role="row">
                        <th role="columnheader" scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                            Nombre
                        </th>

                        <th role="columnheader" scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                            RUT
                        </th>

                        <th role="columnheader" scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                            Correo
                        </th>

                        <th role="columnheader" scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                            Rol
                        </th>

                        <th role="columnheader" scope="col" class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">
                            Acciones
                        </th>
                    </tr>
                </thead>

                <tbody role="rowgroup" class="divide-y divide-gray-100 bg-white">

                    @forelse ($usuarios as $usuario)

                        <tr role="row" class="hover:bg-gray-50">

                            <td role="cell" data-principal-listado class="min-w-[10rem] max-w-[16rem] whitespace-normal px-4 py-3 text-sm font-medium text-gray-800 [overflow-wrap:anywhere]">
                                {{ $usuario->name }}
                            </td>

                            <td role="cell" class="whitespace-nowrap px-4 py-3 text-sm text-gray-600"><span class="etiqueta-listado" aria-hidden="true">RUT</span><div class="dato-listado">
                                {{ $usuario->rut }}
                            </div></td>

                            <td role="cell" class="min-w-[12rem] max-w-[16rem] whitespace-normal px-4 py-3 text-sm text-gray-600 [overflow-wrap:anywhere]"><span class="etiqueta-listado" aria-hidden="true">Correo</span><div class="dato-listado">
                                {{ $usuario->email }}
                            </div></td>

                            <td role="cell" class="whitespace-normal px-4 py-3"><span class="etiqueta-listado" aria-hidden="true">Rol</span><div class="dato-listado">
                                <span class="inline-block max-w-[10rem] rounded-full bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-700 [overflow-wrap:anywhere]">
                                    {{ $usuario->getRoleNames()->first() ?? 'Sin rol' }}
                                </span>
                            </div></td>

                            <td role="cell" data-acciones-listado class="whitespace-nowrap px-4 py-3 text-right">

                                <div class="flex justify-end gap-2">

                                    <a
                                        href="{{ route('usuarios.edit', $usuario) }}"
                                        class="boton boton-secundario"
                                    >
                                        Editar
                                    </a>

                                    @if (auth()->id() !== $usuario->id)
                                        <form
                                            action="{{ route('usuarios.destroy', $usuario) }}"
                                            method="POST"
                                            data-confirmar="Se eliminará la cuenta de {{ $usuario->name }}."
                                            data-confirmar-titulo="Eliminar usuario"
                                            data-confirmar-accion="Eliminar cuenta"
                                        >
                                            @csrf
                                            @method('DELETE')

                                            <button
                                                type="submit"
                                                class="boton boton-peligro"
                                            >
                                                Eliminar
                                            </button>
                                        </form>
                                    @endif

                                </div>

                            </td>

                        </tr>

                    @empty

                        <tr role="row">
                            <td role="cell"
                                colspan="5"
                                class="px-4 py-10 text-center text-sm text-gray-400"
                            >
                                No existen usuarios registrados.
                            </td>
                        </tr>

                    @endforelse

                </tbody>

            </table>
        </div>

    </div>

</div>

@endsection

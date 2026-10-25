@extends('layouts.app')

@section('content')

<div class="min-w-0 w-full px-2 py-2">

    <div class="mb-3 flex flex-col gap-3 rounded-lg border border-gray-200 bg-white px-4 py-3 shadow-sm sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-base font-bold text-gray-800">
                Administración
            </h1>

            <p class="mt-0.5 text-xs text-gray-500">
                Administra los usuarios que tendrán acceso al sistema y sus roles.
            </p>
        </div>

        <a
            href="{{ route('usuarios.create') }}"
            class="inline-flex min-h-11 items-center justify-center rounded-lg bg-institucional px-4 py-2 text-sm font-semibold text-white transition hover:opacity-90"
        >
            Nuevo usuario
        </a>
    </div>

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

    <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">

        <div class="overflow-x-auto overscroll-x-contain" role="region" aria-label="Listado de usuarios" tabindex="0">
            <table class="min-w-full divide-y divide-gray-200">

                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                            Nombre
                        </th>

                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                            RUT
                        </th>

                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                            Correo
                        </th>

                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                            Rol
                        </th>

                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">
                            Acciones
                        </th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-gray-100 bg-white">

                    @forelse ($usuarios as $usuario)

                        <tr class="hover:bg-gray-50">

                            <td class="min-w-[10rem] max-w-[16rem] whitespace-normal px-4 py-3 text-sm font-medium text-gray-800 [overflow-wrap:anywhere]">
                                {{ $usuario->name }}
                            </td>

                            <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-600">
                                {{ $usuario->rut }}
                            </td>

                            <td class="min-w-[12rem] max-w-[16rem] whitespace-normal px-4 py-3 text-sm text-gray-600 [overflow-wrap:anywhere]">
                                {{ $usuario->email }}
                            </td>

                            <td class="whitespace-normal px-4 py-3">
                                <span class="inline-block max-w-[10rem] rounded-full bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-700 [overflow-wrap:anywhere]">
                                    {{ $usuario->getRoleNames()->first() ?? 'Sin rol' }}
                                </span>
                            </td>

                            <td class="whitespace-nowrap px-4 py-3 text-right">

                                <div class="flex justify-end gap-2">

                                    <a
                                        href="{{ route('usuarios.edit', $usuario) }}"
                                        class="inline-flex min-h-10 items-center justify-center rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 transition hover:bg-gray-50"
                                    >
                                        Editar
                                    </a>

                                    @if (auth()->id() !== $usuario->id)
                                        <form
                                            action="{{ route('usuarios.destroy', $usuario) }}"
                                            method="POST"
                                            onsubmit="return confirm('¿Deseas eliminar este usuario?');"
                                        >
                                            @csrf
                                            @method('DELETE')

                                            <button
                                                type="submit"
                                                class="inline-flex min-h-10 items-center justify-center rounded-lg border border-red-200 px-3 py-1.5 text-xs font-medium text-red-600 transition hover:bg-red-50"
                                            >
                                                Eliminar
                                            </button>
                                        </form>
                                    @endif

                                </div>

                            </td>

                        </tr>

                    @empty

                        <tr>
                            <td
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

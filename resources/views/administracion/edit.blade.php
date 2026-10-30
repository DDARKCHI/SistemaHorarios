@extends('layouts.app')

@section('content')

<x-encabezado-pagina titulo="Editar usuario">
    Modifica los datos del usuario y el rol asignado.
</x-encabezado-pagina>

<div class="min-w-0 w-full">

    <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">

        <form
            action="{{ route('usuarios.update', $usuario) }}"
            method="POST"
            class="space-y-4"
        >
            @csrf
            @method('PUT')

            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">

                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">
                        Nombre
                    </label>

                    <input
                        type="text"
                        name="name"
                        value="{{ old('name', $usuario->name) }}"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-institucional focus:outline-none"
                        required
                    >

                    @error('name')
                        <p class="mt-1 text-xs text-red-600">
                            {{ $message }}
                        </p>
                    @enderror
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">
                        RUT
                    </label>

                    <input
                        type="text"
                        name="rut"
                        value="{{ old('rut', $usuario->rut) }}"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-institucional focus:outline-none"
                        required
                    >

                    @error('rut')
                        <p class="mt-1 text-xs text-red-600">
                            {{ $message }}
                        </p>
                    @enderror
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">
                        Correo electrónico
                    </label>

                    <input
                        type="email"
                        name="email"
                        value="{{ old('email', $usuario->email) }}"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-institucional focus:outline-none"
                        required
                    >

                    @error('email')
                        <p class="mt-1 text-xs text-red-600">
                            {{ $message }}
                        </p>
                    @enderror
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">
                        Rol
                    </label>

                    <select
                        name="role"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-institucional focus:outline-none"
                        required
                    >
                        @foreach ($roles as $rol)
                            <option
                                value="{{ $rol->name }}"
                                @selected(
                                    old(
                                        'role',
                                        $usuario->getRoleNames()->first()
                                    ) === $rol->name
                                )
                            >
                                {{ $rol->name }}
                            </option>
                        @endforeach
                    </select>

                    @error('role')
                        <p class="mt-1 text-xs text-red-600">
                            {{ $message }}
                        </p>
                    @enderror
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">
                        Nueva contraseña
                    </label>

                    <input
                        type="password"
                        name="password"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-institucional focus:outline-none"
                    >

                    <p class="mt-1 text-xs text-gray-400">
                        Déjala vacía si no deseas cambiarla.
                    </p>

                    @error('password')
                        <p class="mt-1 text-xs text-red-600">
                            {{ $message }}
                        </p>
                    @enderror
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">
                        Confirmar nueva contraseña
                    </label>

                    <input
                        type="password"
                        name="password_confirmation"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-institucional focus:outline-none"
                    >
                </div>

            </div>

            <div class="flex flex-col-reverse gap-2 border-t border-gray-100 pt-4 sm:flex-row sm:justify-end">

                <a
                    href="{{ route('administracion.index') }}"
                    class="inline-flex min-h-11 w-full items-center justify-center rounded-lg border border-gray-300 px-4 py-2 text-center text-sm font-medium text-gray-700 hover:bg-gray-50 sm:w-auto"
                >
                    Cancelar
                </a>

                <button
                    type="submit"
                    class="inline-flex min-h-11 w-full items-center justify-center rounded-lg bg-institucional px-4 py-2 text-center text-sm font-semibold text-white hover:opacity-90 sm:w-auto"
                >
                    Guardar cambios
                </button>

            </div>

        </form>

    </div>

</div>

@endsection

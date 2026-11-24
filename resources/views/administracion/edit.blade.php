@extends('layouts.app')

@section('content')

<div class="panel-editor">
<x-encabezado-pagina titulo="Editar usuario">
    Modifica los datos del usuario y el rol asignado.
</x-encabezado-pagina>

        <form
            action="{{ route('usuarios.update', $usuario) }}"
            method="POST"
            class="panel-formulario"
            data-confirmar="Se guardarán los datos y el perfil seleccionados para {{ $usuario->name }}."
            data-confirmar-titulo="Actualizar usuario"
            data-confirmar-accion="Guardar cambios"
        >
            @csrf
            @method('PUT')

            <x-seccion-formulario titulo="Datos del usuario" id="datos-usuario">
            <div class="panel-campos">

                <div class="panel-campo--largo">
                    <label for="name" class="mb-1.5 block text-sm font-semibold">
                        Nombre *
                    </label>

                    <input placeholder="Ej. María González"
                        type="text"
                        id="name"
                        name="name"
                        value="{{ old('name', $usuario->name) }}"
                        class="h-11 w-full rounded-lg border border-borde bg-superficie px-3 focus:border-institucional focus:outline-none focus:ring-1 focus:ring-institucional"
                        required
                    >

                    @error('name')
                        <p class="mt-1 text-xs text-red-600">
                            {{ $message }}
                        </p>
                    @enderror
                </div>

                <div class="panel-campo--corto">
                    <label for="rut" class="mb-1.5 block text-sm font-semibold">
                        RUT *
                    </label>

                    <input placeholder="Ej. 12345678-5"
                        type="text"
                        id="rut"
                        name="rut"
                        value="{{ old('rut', $usuario->rut) }}"
                        class="h-11 w-full rounded-lg border border-borde bg-superficie px-3 focus:border-institucional focus:outline-none focus:ring-1 focus:ring-institucional"
                        required
                    >

                    @error('rut')
                        <p class="mt-1 text-xs text-red-600">
                            {{ $message }}
                        </p>
                    @enderror
                </div>

                <div class="panel-campo--medio">
                    <label for="email" class="mb-1.5 block text-sm font-semibold">
                        Correo electrónico *
                    </label>

                    <input placeholder="Ej. nombre@institucion.cl"
                        type="email"
                        id="email"
                        name="email"
                        value="{{ old('email', $usuario->email) }}"
                        class="h-11 w-full rounded-lg border border-borde bg-superficie px-3 focus:border-institucional focus:outline-none focus:ring-1 focus:ring-institucional"
                        required
                    >

                    @error('email')
                        <p class="mt-1 text-xs text-red-600">
                            {{ $message }}
                        </p>
                    @enderror
                </div>

                <div class="panel-campo--medio">
                    <label for="role" class="mb-1.5 block text-sm font-semibold">
                        Rol *
                    </label>

                    <select
                        id="role"
                        name="role"
                        class="h-11 w-full rounded-lg border border-borde bg-superficie px-3 focus:border-institucional focus:outline-none focus:ring-1 focus:ring-institucional"
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

                    <p class="mt-2 text-xs text-gray-500">Los permisos de cada perfil tienen alcance institucional. <a href="{{ route('administracion.perfiles.index') }}" class="font-semibold text-institucional underline underline-offset-2">Revisar perfiles y permisos</a></p>
                    @error('role')
                        <p class="mt-1 text-xs text-red-600">
                            {{ $message }}
                        </p>
                    @enderror
                </div>

                <div class="panel-campo--medio">
                    <label for="password" class="mb-1.5 block text-sm font-semibold">
                        Nueva contraseña
                    </label>

                    <input
                        type="password"
                        id="password"
                        name="password"
                        class="h-11 w-full rounded-lg border border-borde bg-superficie px-3 focus:border-institucional focus:outline-none focus:ring-1 focus:ring-institucional"
                    >

                    <p class="mt-1 text-xs text-texto-secundario">
                        Déjala vacía si no deseas cambiarla.
                    </p>

                    @error('password')
                        <p class="mt-1 text-xs text-red-600">
                            {{ $message }}
                        </p>
                    @enderror
                </div>

                <div class="panel-campo--medio">
                    <label for="password_confirmation" class="mb-1.5 block text-sm font-semibold">
                        Confirmar nueva contraseña
                    </label>

                    <input
                        type="password"
                        id="password_confirmation"
                        name="password_confirmation"
                        class="h-11 w-full rounded-lg border border-borde bg-superficie px-3 focus:border-institucional focus:outline-none focus:ring-1 focus:ring-institucional"
                    >
                </div>

            </div>

            </x-seccion-formulario>

            <x-acciones-formulario :cancelar="route('administracion.index')">
                <button type="submit" class="boton boton-primario">Guardar cambios</button>
            </x-acciones-formulario>

        </form>
</div>

@endsection

@props(['profesores', 'version', 'carreraId' => null, 'semestre' => null])
@php
    $restaurar = is_scalar(old('horario_id')) && old('accion_docente') && $errors->any() ? (string) old('horario_id') : '';
    $profesorAnterior = is_scalar(old('profesor_id')) ? (string) old('profesor_id') : '';
    $accionAnterior = is_scalar(old('accion_docente')) ? (string) old('accion_docente') : 'reemplazar';
@endphp
<dialog id="asignar-docente-horario" aria-labelledby="titulo-asignar-docente" class="fixed inset-0 m-auto w-[calc(100%_-_2rem)] max-w-xl rounded-xl border border-gray-200 bg-white p-5 text-gray-800 shadow-xl backdrop:bg-gray-900/40 sm:p-6"
    data-restaurar-horario="{{ $restaurar }}" data-profesor-anterior="{{ $profesorAnterior }}" data-accion-anterior="{{ $accionAnterior }}">
    <div class="flex items-start justify-between gap-4">
        <div><h2 id="titulo-asignar-docente" class="text-lg font-semibold">Asignar docente al horario</h2><p data-resumen-horario class="mt-1 text-sm text-gray-500"></p></div>
        <button type="button" data-cerrar-docente class="boton boton-secundario size-11 shrink-0 px-0" aria-label="Cerrar">✕</button>
    </div>
    <p class="mt-4 text-sm leading-6 text-gray-600">Puedes asignar un docente aunque no haya informado disponibilidad. El cambio afecta solo este módulo.</p>
    @if ($restaurar)<ul role="alert" class="mt-3 list-inside list-disc rounded-lg bg-red-50 p-3 text-sm text-red-800">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>@endif
    <form method="POST" class="mt-4 space-y-4" data-form-docente data-confirmar="La asignación de docentes se actualizará para el módulo seleccionado." data-confirmar-titulo="Guardar asignación del horario" data-confirmar-accion="Guardar asignación">
        @csrf
        @method('PUT')
        <input type="hidden" name="version_id" value="{{ $version->id }}">
        <input type="hidden" name="horario_id" value="">
        <input type="hidden" name="huella_profesores" value="">
        <input type="hidden" name="carrera_id" value="{{ $carreraId }}">
        <input type="hidden" name="semestre" value="{{ $semestre }}">
        <div>
            <label for="profesor-del-horario" class="mb-1.5 block text-sm font-semibold">Docente *</label>
            <select id="profesor-del-horario" name="profesor_id" data-select-buscable aria-required="true" class="h-11 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm focus:border-institucional focus:outline-none focus:ring-1 focus:ring-institucional">
                <option value="">Buscar por nombre o RUT</option>
                @foreach ($profesores as $profesor)<option value="{{ $profesor->id }}">{{ trim($profesor->nombre.' '.$profesor->apellido) }}{{ $profesor->rut ? ' · '.$profesor->rut : '' }}</option>@endforeach
            </select>
        </div>
        <div>
            <label for="accion-del-docente" class="mb-1.5 block text-sm font-semibold">Asignación *</label>
            <select id="accion-del-docente" name="accion_docente" required class="h-11 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm">
                <option value="reemplazar">Reemplazar los docentes de este módulo</option>
                <option value="agregar">Añadir a los docentes actuales</option>
            </select>
        </div>
        <div class="flex flex-wrap justify-end gap-2 border-t border-gray-100 pt-4">
            <button type="button" data-cerrar-docente class="boton boton-secundario">Cancelar</button>
            <button type="submit" class="boton boton-primario">Guardar docente</button>
        </div>
    </form>
</dialog>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const dialogo = document.getElementById('asignar-docente-horario');
    const formulario = dialogo.querySelector('[data-form-docente]');
    const profesor = document.getElementById('profesor-del-horario');
    const accion = document.getElementById('accion-del-docente');
    const abrir = (boton, restaurar = false) => {
        formulario.action = boton.dataset.url;
        formulario.elements.horario_id.value = boton.dataset.horario;
        formulario.elements.huella_profesores.value = boton.dataset.huella;
        dialogo.querySelector('[data-resumen-horario]').textContent = boton.dataset.resumen;
        profesor.value = restaurar ? dialogo.dataset.profesorAnterior : '';
        profesor.dispatchEvent(new Event('change', {bubbles:true}));
        accion.value = restaurar ? dialogo.dataset.accionAnterior : 'reemplazar';
        dialogo.showModal();
        (document.getElementById('profesor-del-horario-busqueda') || profesor).focus();
    };
    document.querySelectorAll('[data-asignar-docente]').forEach(boton => boton.addEventListener('click', () => abrir(boton)));
    dialogo.querySelectorAll('[data-cerrar-docente]').forEach(boton => boton.addEventListener('click', () => dialogo.close()));
    if (dialogo.dataset.restaurarHorario) {
        const boton = Array.from(document.querySelectorAll('[data-asignar-docente]')).find(b => b.dataset.horario === dialogo.dataset.restaurarHorario);
        if (boton) abrir(boton, true);
    }
});
</script>

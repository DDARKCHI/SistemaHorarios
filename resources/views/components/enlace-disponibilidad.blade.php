@can('asignar profesores')
<a href="{{ route('disponibilidad.index') }}" data-sidebar-row aria-label="Disponibilidad docente" title="Disponibilidad docente" class="mb-2 flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium {{ request()->routeIs('disponibilidad.*') ? 'bg-institucional text-white' : 'text-texto hover:bg-fondo' }}">
    <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path d="M8 2v4m8-4v4M3 10h18M5 4h14a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Zm3 11 3 3 5-5" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" /></svg><span data-sidebar-label>Disponibilidad docente</span>
</a>
@endcan

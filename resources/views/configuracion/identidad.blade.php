@extends('layouts.app')
@section('title', 'Identidad visual | '.$identidad['nombre'])
@section('content')
@php
    $valor = static function ($campo, $base = null) { $v = old($campo, $base); return is_scalar($v) || $v === null ? $v : $base; };
    $colores = ['color_principal' => ['Color principal', 'Botones, navegación y acentos.', '#D12421'], 'color_secundario' => ['Color de texto', 'Nombre de la plataforma y textos principales.', '#1F2937'], 'color_fondo' => ['Fondo del sitio', 'Un tono claro para formularios y tablas legibles.', '#F5F6F8']];
    $imagenes = ['logo' => ['Logo de la plataforma', 'Menú, acceso, correos y reportes. Recomendado: horizontal.'], 'logo_compacto' => ['Logo compacto', 'Menú contraído. Recomendado: cuadrado.'], 'logo_institucion' => ['Logo institucional', 'Identifica a la institución en el acceso y la navegación.'], 'favicon' => ['Favicon', 'Icono de las pestañas. Debe ser cuadrado, de 32 a 1024 px.']];
    $archivos = [];
    foreach ($imagenes as $campo => $texto) { $ruta = app(\App\Services\IdentidadVisual::class)->archivoValido($configuracion->$campo); $archivos[$campo] = $ruta ? route('identidad.archivo', basename($ruta)) : null; }
    $subtitulo = $configuracion->identidad_activa || $configuracion->color_fondo !== null ? ($configuracion->lema_sistema ?? '') : \App\Services\IdentidadVisual::PREDETERMINADA['lema'];
@endphp
<x-encabezado-pagina titulo="Identidad visual">
    Personaliza la plataforma para tu institución: nombre, colores, logos e iconos.
    <x-slot:acciones><a class="boton boton-secundario" href="{{ route('configuracion.index') }}">Volver a configuración</a></x-slot:acciones>
</x-encabezado-pagina>
@include('disponibilidad.avisos')
<div class="mb-5 flex flex-col gap-2 rounded-xl border border-borde bg-white p-4 text-sm sm:flex-row sm:items-center sm:justify-between"><div><span class="font-semibold">Identidad actual:</span> {{ $actual['nombre'] }} · {{ $actual['sigla'] }}</div><span class="text-xs text-texto-secundario">{{ $actual['personalizada'] ? 'Personalizada para esta instalación' : 'Diseño inicial de PROSEC' }}</span></div>
@if($universidades->count() > 1)
<form method="get" class="mb-5 flex flex-col gap-3 rounded-xl border border-borde bg-white p-5 sm:flex-row sm:items-end"><div class="min-w-0 flex-1"><label class="mb-2 block font-semibold" for="institucion-identidad">Institución que deseas personalizar</label><select id="institucion-identidad" name="universidad_id" class="h-11 w-full rounded-lg border border-borde px-3">@foreach($universidades as $u)<option value="{{ $u->id }}" @selected($universidad?->id === $u->id)>{{ $u->sigla }} · {{ $u->nombre }}</option>@endforeach</select></div><button class="boton boton-secundario">Consultar identidad</button></form>
@endif
@if($universidad)
<form
    method="post"
    enctype="multipart/form-data"
    action="{{ route('configuracion.identidad.update', $universidad) }}"
    data-identidad-editor
    class="space-y-5"
    @can('administrar usuarios')
        data-confirmar="El nombre, los colores y los logotipos elegidos se aplicarán a toda la plataforma."
        data-confirmar-titulo="Aplicar identidad visual"
        data-confirmar-accion="Aplicar identidad"
    @endcan
>
@csrf <input type="hidden" name="firma" value="{{ $valor('firma', $firma) }}">
<div class="grid min-w-0 items-start gap-5 xl:grid-cols-[minmax(0,1.65fr)_minmax(300px,1fr)]">
    <div class="min-w-0 space-y-5">
        <section class="rounded-xl border border-borde bg-white p-5 shadow-sm sm:p-6">
            <h2 class="mb-5 font-semibold">Nombre y presentación</h2>
            <fieldset @disabled(!auth()->user()->can('administrar usuarios')) class="grid gap-4 md:grid-cols-2">
                <div><label class="mb-2 block font-semibold" for="nombre_sistema">Nombre de la plataforma *</label><input placeholder="Ej. PROSEC" id="nombre_sistema" name="nombre_sistema" required maxlength="80" value="{{ $valor('nombre_sistema', $configuracion->nombre_sistema) }}" data-identidad-nombre class="h-11 w-full rounded-lg border border-borde px-3 text-sm focus:border-institucional focus:outline-none focus:ring-1 focus:ring-institucional"></div>
                <div><label class="mb-2 block font-semibold" for="lema_sistema">Subtítulo</label><input placeholder="Ej. Programación Semestral de Cursos" id="lema_sistema" name="lema_sistema" maxlength="150" value="{{ $valor('lema_sistema', $subtitulo) }}" data-identidad-lema class="h-11 w-full rounded-lg border border-borde px-3 text-sm focus:border-institucional focus:outline-none focus:ring-1 focus:ring-institucional"></div>
            </fieldset>
            <p class="mt-3 text-xs leading-5 text-texto-secundario">{{ $universidad->nombre }}. Sus datos se administran en Configuración institucional.</p>
        </section>
        <section class="rounded-xl border border-borde bg-white p-5 shadow-sm sm:p-6">
            <h2 class="mb-5 font-semibold">Colores del sitio</h2>
            <fieldset @disabled(!auth()->user()->can('administrar usuarios')) class="grid gap-5 sm:grid-cols-3">
            @foreach($colores as $campo => [$etiqueta, $ayuda, $defecto])
                @php($color = $valor($campo, $configuracion->$campo ?: $defecto))
                <div class="min-w-0"><label class="mb-2 block font-semibold" for="{{ $campo }}">{{ $etiqueta }} *</label><div class="flex items-center gap-2"><input type="color" value="{{ preg_match('/\A#[a-f0-9]{6}\z/i', (string) $color) ? $color : $defecto }}" data-color-selector="{{ $campo }}" aria-label="Elegir {{ mb_strtolower($etiqueta) }}" class="h-11 w-12 shrink-0 cursor-pointer rounded-lg border border-borde bg-white p-1"><input placeholder="{{ $defecto }}" id="{{ $campo }}" name="{{ $campo }}" value="{{ $color }}" data-identidad-color="{{ $campo }}" maxlength="7" pattern="#[a-fA-F0-9]{6}" required class="h-11 min-w-0 w-full rounded-lg border border-borde px-2 text-sm uppercase focus:border-institucional focus:outline-none focus:ring-1 focus:ring-institucional"></div><p class="mt-2 text-xs leading-5 text-texto-secundario">{{ $ayuda }}</p></div>
            @endforeach
            </fieldset>
            <p class="mt-4 text-xs leading-5 text-texto-secundario">El texto de los botones se adapta al color principal. Se conserva el significado de errores, advertencias y disponibilidad.</p>
            <p data-identidad-contraste hidden role="status" class="mt-3 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900"></p>
        </section>
        <section class="rounded-xl border border-borde bg-white p-5 shadow-sm sm:p-6">
            <h2 class="font-semibold">Logos y favicon</h2><p class="mt-1 text-sm leading-6 text-texto-secundario">PNG, JPG o WebP, hasta 2 MB por imagen. Los logos admiten de 32 a 3000 px por lado. Se conserva la transparencia.</p>
            <fieldset @disabled(!auth()->user()->can('administrar usuarios')) class="mt-5 grid gap-5 md:grid-cols-2">
            @foreach($imagenes as $campo => [$etiqueta, $ayuda])
                <div class="min-w-0 rounded-lg border border-borde p-4"><label for="{{ $campo }}" class="block font-semibold">{{ $etiqueta }}</label><p class="mt-1 min-h-10 text-xs leading-5 text-texto-secundario">{{ $ayuda }}</p>
                    @if($archivos[$campo])<div class="my-3 flex h-16 items-center justify-center rounded-md bg-gray-50 p-2"><img src="{{ $archivos[$campo] }}" alt="{{ $etiqueta }} actual" class="max-h-full max-w-full object-contain"></div>@endif
                    <div class="mt-3"><x-campo-archivo :id="$campo" :name="$campo" accept="image/png,image/jpeg,image/webp" :data-identidad-imagen="$campo" /></div>
                    @if($archivos[$campo])<label class="mt-3 flex items-start gap-2 text-xs leading-5"><input type="checkbox" name="quitar_{{ $campo }}" value="1" data-quitar-imagen="{{ $campo }}" @checked($valor('quitar_'.$campo, false)) class="mt-1 shrink-0 accent-institucional"><span>Retirar imagen y usar una versión automática</span></label>@endif
                </div>
            @endforeach
            </fieldset>
        </section>
    </div>
    <aside class="min-w-0 space-y-5 xl:sticky xl:top-5">
        <section class="overflow-hidden rounded-xl border border-borde bg-white shadow-sm">
            <div class="border-b border-borde p-5"><h2 class="font-semibold">Vista previa</h2><p class="mt-1 text-xs text-texto-secundario">Los cambios se aplican cuando guardas.</p></div>
            <div data-vista-fondo class="p-5" style="background-color: #F5F6F8">
                <div class="mb-4 flex min-w-0 items-center gap-2 rounded-lg border border-gray-200 bg-white px-3 py-2"><img data-vista-imagen="favicon" src="{{ $archivos['favicon'] ?? $actual['favicon_url'] }}" alt="" class="h-5 w-5 object-contain" @if(!$archivos['favicon']) hidden @endif><span data-vista-iniciales class="flex h-6 w-6 shrink-0 items-center justify-center rounded-md border border-gray-200 text-xs font-bold" @if($archivos['favicon']) hidden @endif>PS</span><span data-vista-nombre class="min-w-0 truncate text-xs">{{ $configuracion->nombre_sistema }}</span><span class="ml-auto text-gray-400" aria-hidden="true">×</span></div>
                <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                    <div class="mb-5 flex items-center gap-3"><img data-vista-imagen="logo_compacto" src="{{ $archivos['logo_compacto'] ?? $actual['compacto_url'] }}" alt="Logo compacto en vista previa" class="h-10 w-10 object-contain" @if(!$archivos['logo_compacto']) hidden @endif><span data-vista-compacto class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg border border-gray-200 text-sm font-bold" @if($archivos['logo_compacto']) hidden @endif>PS</span><div class="min-w-0"><img data-vista-imagen="logo" src="{{ $archivos['logo'] ?? $actual['principal_url'] }}" alt="Logo en vista previa" class="max-h-16 max-w-full object-contain" @if(!$archivos['logo']) hidden @endif><strong data-vista-nombre data-vista-logo-texto class="block break-words text-lg" @if($archivos['logo']) hidden @endif>{{ $configuracion->nombre_sistema }}</strong><p data-vista-lema class="mt-1 break-words text-xs text-gray-500">{{ $subtitulo }}</p></div></div>
                    <div class="border-t border-gray-100 pt-4"><p data-vista-texto class="text-base font-semibold">Programación académica</p><p class="mt-2 text-xs leading-5 text-gray-500">Consulta horarios, docentes y espacios desde una plataforma con tu identidad.</p><span data-vista-boton class="mt-5 flex min-h-11 items-center justify-center rounded-lg px-4 py-2 text-sm font-semibold">Consultar programación</span></div>
                    <div class="mt-5 border-t border-gray-100 pt-4 text-center"><img data-vista-imagen="logo_institucion" src="{{ $archivos['logo_institucion'] ?? $actual['institucion_url'] }}" alt="Logo institucional en vista previa" class="mx-auto max-h-12 max-w-full object-contain" @if(!$archivos['logo_institucion']) hidden @endif><span data-vista-institucion-texto class="break-words text-sm font-semibold" @if($archivos['logo_institucion']) hidden @endif>{{ $universidad->sigla }}</span></div>
                </div>
            </div>
        </section>
        <div class="rounded-xl border border-borde bg-white p-5 text-sm leading-6 text-texto-secundario"><h2 class="mb-2 font-semibold text-texto">Una identidad para toda la instalación</h2><p>Se utiliza en el menú, acceso, pestañas, recuperación de contraseña y nuevos PDF. También la verán otras cuentas y quienes abran enlaces docentes.</p></div>
    </aside>
</div>
@can('administrar usuarios')
<div class="rounded-xl border border-borde bg-white p-5 shadow-sm sm:p-6"><label class="flex items-start gap-3 text-sm leading-6"><input type="checkbox" name="confirmar" data-consentimiento-confirmacion value="1" required @checked($valor('confirmar', false)) class="mt-1.5 h-4 w-4 shrink-0 accent-institucional"><span>He revisado la identidad de <strong>{{ $universidad->sigla }}</strong> y deseo aplicarla a toda la plataforma.</span></label><div class="mt-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"><p class="text-xs text-texto-secundario">Los campos con * son obligatorios.</p><div class="flex flex-col-reverse gap-2 sm:flex-row"><a class="boton boton-secundario" href="{{ route('configuracion.index') }}">Cancelar</a><button class="boton boton-primario">Guardar y aplicar identidad</button></div></div></div>
@else<p class="rounded-xl border border-borde bg-white p-5 text-sm text-texto-secundario">Tu perfil permite consultar la identidad visual. La edición requiere permisos de administración.</p>@endcan
</form>
@else<p class="rounded-xl border border-borde bg-white p-6 text-sm text-texto-secundario">Registra una institución activa para personalizar la plataforma.</p>@endif
@if($actual['personalizada'])
@can('administrar usuarios')
<details class="mt-6 rounded-xl border border-borde bg-white p-5"><summary class="cursor-pointer text-sm font-semibold">Restaurar identidad inicial</summary><form method="post" action="{{ route('configuracion.identidad.restaurar') }}" class="mt-4 space-y-4" data-confirmar="La plataforma volverá a mostrar el diseño inicial." data-confirmar-titulo="Restaurar identidad visual" data-confirmar-accion="Restaurar identidad">@csrf<input type="hidden" name="firma" value="{{ $firma }}"><p class="text-sm leading-6 text-texto-secundario">Vuelve al nombre, colores, logos y favicon iniciales de PROSEC. Se conservan las imágenes y configuraciones guardadas para que puedas aplicarlas nuevamente.</p><label class="flex items-start gap-2 text-sm"><input type="checkbox" name="confirmar_restauracion" data-consentimiento-confirmacion value="1" required class="mt-1 accent-institucional"><span>Deseo restaurar la identidad inicial de PROSEC.</span></label><button class="boton boton-secundario">Restaurar identidad PROSEC</button></form></details>
@endcan
@endif
@endsection

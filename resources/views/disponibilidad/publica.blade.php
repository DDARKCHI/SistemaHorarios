<!DOCTYPE html>
<html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="referrer" content="no-referrer"><title>Informar disponibilidad | PROSEC</title>@vite(['resources/css/app.css', 'resources/js/app.js'])<x-favicon /></head>
<body class="min-h-screen bg-fondo text-texto">
    <header class="border-b border-borde bg-superficie"><div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-4 px-4 py-5 sm:px-6"><img src="{{ asset('images/prosec-logo.svg') }}" alt="PROSEC" class="w-40"><span class="text-sm text-texto-secundario">Disponibilidad docente</span></div></header>
    <main class="mx-auto max-w-6xl space-y-6 px-4 py-6 sm:px-6">
        <header><h1 class="text-2xl font-semibold">Tu disponibilidad para {{ $solicitud->periodoAcademico->nombre }}</h1><p class="mt-2 text-sm text-texto-secundario">{{ $solicitud->profesor->nombre }} {{ $solicitud->profesor->apellido }}</p></header>
        @include('disponibilidad.avisos')
        @if ($abierta)
        <form method="post" action="{{ request()->fullUrl() }}" class="space-y-5 rounded-xl border border-borde bg-superficie p-4 shadow-sm sm:p-6">
            @csrf
            <div><h2 class="font-semibold">Marca los módulos en los que puedes realizar clases</h2><p class="mt-1 text-sm text-texto-secundario">Los módulos sin marcar se considerarán no disponibles. Tu respuesta será revisada antes de entrar en vigencia.</p><p class="mt-2 text-xs text-texto-secundario">Puedes responder hasta el {{ $solicitud->fecha_limite->format('d/m/Y') }}. Se permite una respuesta por solicitud.</p></div>
            @if ($solicitud->observaciones)<p class="whitespace-pre-line rounded-lg bg-fondo p-4 text-sm">{{ $solicitud->observaciones }}</p>@endif
            @include('disponibilidad.calendario', ['editable' => true])
            <label class="flex items-start gap-3 text-sm"><input type="checkbox" name="sin_disponibilidad" value="1" @checked(old('sin_disponibilidad')) class="mt-0.5 h-4 w-4 accent-institucional">No tengo disponibilidad para realizar clases en este período.</label>
            <div><label for="observaciones" class="mb-2 block text-sm font-semibold">Comentarios para la revisión</label><textarea id="observaciones" name="observaciones" rows="3" maxlength="2000" placeholder="Opcional" class="w-full rounded-lg border border-borde p-3">{{ old('observaciones') }}</textarea></div>
            <div class="flex flex-wrap items-center justify-between gap-4 border-t border-borde pt-5"><p class="text-xs text-texto-secundario">Revisa los módulos antes de enviar tu respuesta.</p><button class="h-11 rounded-lg bg-institucional px-6 text-sm font-semibold text-white">Enviar disponibilidad</button></div>
        </form>
        @else
        <section role="status" class="space-y-3 rounded-xl border border-borde bg-superficie p-6 shadow-sm"><h2 class="text-lg font-semibold">{{ in_array($solicitud->estado, ['En revisión', 'Aprobada', 'Rechazada'], true) ? 'Tu respuesta ya fue recibida' : 'Esta solicitud ya no está disponible' }}</h2><p class="text-sm text-texto-secundario">{{ $solicitud->estado === 'En revisión' ? 'Está pendiente de revisión. Puedes cerrar esta página.' : 'Si necesitas informar cambios, solicita un nuevo enlace a la coordinación académica.' }}</p></section>
        @endif
    </main>
</body></html>

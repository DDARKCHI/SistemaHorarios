@if($identidad['personalizada'])
<style id="tema-institucional">
:root {
    --color-institucional: {{ $identidad['principal'] }};
    --color-institucional-oscuro: {{ $identidad['oscuro'] }};
    --color-fondo: {{ $identidad['fondo'] }};
    --color-texto: {{ $identidad['secundario'] }};
    --color-texto-secundario: {{ $identidad['muted'] }};
    --color-enlace-institucional: {{ $identidad['enlace'] }};
    --color-sobre-institucional: {{ $identidad['sobre_principal'] }};
    --color-sobre-institucional-oscuro: {{ $identidad['sobre_oscuro'] }};
}
.text-institucional, .hover\:text-institucional:hover { color: var(--color-enlace-institucional); }
.text-gray-800, .text-gray-900 { color: var(--color-texto); }
.text-gray-500, .text-gray-600 { color: var(--color-texto-secundario); }
.bg-institucional, .bg-institucional.text-white, .bg-institucional .text-white { color: var(--color-sobre-institucional); }
.hover\:bg-institucional:hover { color: var(--color-sobre-institucional); }
.hover\:bg-institucional-oscuro:hover { color: var(--color-sobre-institucional-oscuro); }
</style>
@endif

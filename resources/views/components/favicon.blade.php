@if(!$identidad['favicon_base'])
<link rel="icon" type="{{ $identidad['favicon_archivo'] ? 'image/png' : 'image/svg+xml' }}" href="{{ $identidad['favicon_url'] }}" sizes="any">
<link rel="apple-touch-icon" sizes="180x180" href="{{ $identidad['apple_url'] }}">
@else
<link rel="icon" href="{{ asset('favicon.ico') }}?v=2" sizes="any">
<link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}?v=2">
<link rel="icon" type="image/png" sizes="64x64" href="{{ asset('favicon.png') }}?v=2">
<link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}?v=2">
@endif

@props(['tipo', 'width' => null])

@php
    $url = app(\App\Services\IdentidadVisual::class)->logoCorreoUrl($tipo);
    $esInstitucion = $tipo === 'institucion';
    $nombre = $esInstitucion ? $identidad['sigla'] : $identidad['nombre'];
    $ancho = $width ?? ($esInstitucion ? 144 : 250);
    $margen = $esInstitucion ? '0 auto 12px' : '0 auto';
@endphp

@if ($url)
    <img src="{{ $url }}" alt="{{ $esInstitucion ? $identidad['institucion'] : $nombre }}" width="{{ $ancho }}" style="display:block;width:{{ $ancho }}px;max-width:100%;height:auto;margin:{{ $margen }};border:0;">
@elseif ($esInstitucion)
    <strong style="display:block;margin:{{ $margen }};color:{{ $identidad['principal'] }};font-size:20px;line-height:1.2;">{{ $nombre }}</strong>
@else
    <strong style="display:block;color:{{ $identidad['secundario'] }};font-size:34px;line-height:1.1;font-weight:900;letter-spacing:-1px;">
        @if ($nombre === 'PROSEC')
            PRO<span style="color:{{ $identidad['principal'] }};">SEC</span>
        @else
            {{ $nombre }}
        @endif
    </strong>
    @if ($identidad['lema'] !== '')
        <span style="display:block;margin-top:4px;color:{{ $identidad['secundario'] }};font-size:10px;line-height:1.3;letter-spacing:1px;text-transform:uppercase;">{{ $identidad['lema'] }}</span>
    @endif
@endif

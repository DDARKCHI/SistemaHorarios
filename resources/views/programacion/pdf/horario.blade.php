<!DOCTYPE html>
<html lang="es"><head><meta charset="utf-8"><title>Horario publicado {{ $identidad['nombre'] }}</title><style>
@page { margin: 32px 32px 40px; }
body { font-family: DejaVu Sans, sans-serif; color: {{ $identidad['secundario'] }}; font-size: 9px; line-height: 1.35; }
.marca { width: 150px; margin-bottom: 10px; } .cabecera { border-bottom: 3px solid {{ $identidad['principal'] }}; padding-bottom: 10px; margin-bottom: 14px; }
h1 { font-size: 19px; margin: 0 0 5px; } h2 { font-size: 12px; margin: 0 0 8px; } p { margin: 4px 0; }
.meta { color: #68737e; font-size: 9px; } .seleccion { margin: 8px 0 13px; color: #515c68; }
table { border-collapse: collapse; width: 100%; table-layout: fixed; } th { background: #f1f3f5; text-align: left; font-size: 8px; color: #515c68; }
td, th { padding: 7px; border: 1px solid #dce0e5; vertical-align: top; overflow-wrap: break-word; word-wrap: break-word; }
thead { display: table-header-group; } tr { page-break-inside: avoid; } .curso { font-weight: bold; font-size: 9px; } .detalle { font-size: 8px; color: #68737e; margin-top: 4px; }
.codigo { color: {{ $identidad['enlace'] }}; font-size: 8px; margin-top: 5px; } .pagina { page-break-before: always; } .semana td { height: 66px; } .semana th { text-align: center; }
.celda { padding: 4px; background: #f7f8fa; border-left: 2px solid {{ $identidad['principal'] }}; margin-bottom: 5px; } .hora { font-size: 8px; font-weight: bold; color: #515c68; }
.pie { margin-top: 12px; font-size: 8px; color: #68737e; }
</style></head><body>
@if ($formato === 'detalle')
@foreach ($filas->chunk((int) ceil($filas->count() / ceil($filas->count() / 6))) as $parte)
<div class="{{ $loop->first ? '' : 'pagina' }}">
<div class="cabecera"><img class="marca" src="{{ $logo }}"><h1>Horario académico publicado</h1><p>{{ $cierre->datos['periodo'] }} · Versión {{ $cierre->datos['version'] }}</p><p class="meta">Publicado el {{ $cierre->created_at->format('d/m/Y H:i') }} · {{ $filas->count() }} módulos en esta selección</p></div>
@if ($filtros)<p class="seleccion">Selección: {{ implode(' · ', array_values($filtros)) }}</p>@endif
<table><thead><tr><th style="width: 32%">RAMO Y ACTIVIDAD</th><th style="width: 15%">DÍA Y HORA</th><th style="width: 25%">DOCENTES</th><th style="width: 28%">ESPACIOS Y CÓDIGO</th></tr></thead><tbody>
@foreach ($parte as $h)<tr><td><div class="curso">{{ $h['curso'] }}</div><div class="detalle">Sección {{ $h['seccion'] }} · {{ $h['actividad'] }} · {{ $h['grupo'] }}</div>@if (!empty($h['carreras']))<div class="detalle">{{ $h['carreras'] }} · Semestre {{ $h['nivel'] ?? '—' }}</div>@endif</td><td>{{ $h['dia'] }}<br>{{ $h['hora'] }}<div class="detalle">Módulo {{ $h['modulo'] }}</div></td><td>{{ $h['docentes'] }}</td><td>{{ $h['espacios'] }}<div class="codigo">{{ $h['codigo_horario'] }}</div></td></tr>@endforeach
</tbody></table><p class="pie">Este documento conserva los datos del momento de publicación. Los filtros solo limitan esta copia de consulta.</p>
</div>
@endforeach
@else
@foreach ($semanas as $semana)
<div class="{{ $loop->first ? '' : 'pagina' }}">
<div class="cabecera"><img class="marca" src="{{ $logo }}"><h1>Horario semanal</h1><h2>{{ $semana['titulo'] }}</h2><p class="meta">{{ $cierre->datos['periodo'] }} · Versión {{ $cierre->datos['version'] }} · Publicado el {{ $cierre->created_at->format('d/m/Y') }}@if ($semana['partes'] > 1) · Parte {{ $semana['parte'] }} de {{ $semana['partes'] }}@endif</p></div>
<table class="semana"><thead><tr><th style="width: 11%">HORA</th>@foreach ($semana['dias'] as $dia)<th>{{ $dia }}</th>@endforeach</tr></thead><tbody>
@foreach ($semana['horas'] as $hora)<tr><td class="hora">{{ $hora }}</td>@foreach ($semana['dias'] as $dia)<td>@foreach ($semana['clases']->where('hora', $hora)->where('dia', $dia) as $h)<div class="celda"><strong>{{ $h['actividad'] }} · {{ $h['grupo'] }}</strong><div class="detalle">{{ $h['docentes'] }}</div><div class="detalle">{{ $h['espacios'] }}</div><div class="codigo">{{ $h['codigo_horario'] }}</div></div>@endforeach</td>@endforeach</tr>@endforeach
</tbody></table><p class="pie">Se muestran los días y módulos con clases en la selección. @if ($filtros)Selección: {{ implode(' · ', array_values($filtros)) }}.@endif</p>
</div>
@endforeach
@endif
</body></html>

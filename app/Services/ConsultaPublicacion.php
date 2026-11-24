<?php

namespace App\Services;

use App\Models\CierreProgramacion;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ConsultaPublicacion
{
    public function preparar(Request $request, CierreProgramacion $cierre): array
    {
        $campos = ['curso' => 'Ramo', 'seccion' => 'Sección', 'docentes' => 'Docente', 'espacios' => 'Espacio', 'carreras' => 'Carrera', 'nivel' => 'Nivel', 'sedes' => 'Sede'];
        $todas = collect($cierre->datos['filas']);
        $opciones = [];
        $reglas = ['q' => ['nullable', 'string', 'max:100'], 'page' => ['nullable', 'integer', 'min:1'], 'formato' => ['nullable', Rule::in(['detalle', 'semanal'])]];
        foreach ($campos as $campo => $titulo) {
            $opciones[$campo] = $todas->flatMap(fn ($fila) => $this->valores($fila, $campo))->filter(fn ($valor) => filled($valor))->unique()->sort(SORT_NATURAL | SORT_FLAG_CASE)->values()->all();
            $reglas[$campo] = ['nullable', 'string', 'max:1000', Rule::in($opciones[$campo])];
        }
        $datos = $request->validate($reglas);
        $q = trim($datos['q'] ?? '');
        $filtros = array_filter(array_intersect_key($datos, $campos), fn ($v) => filled($v));
        $filas = $todas->filter(function ($fila) use ($filtros, $q) {
            foreach ($filtros as $campo => $valor) if (! in_array((string) $valor, $this->valores($fila, $campo), true)) return false;
            return $q === '' || mb_stripos(implode(' ', $fila), $q) !== false;
        })->values();
        $contexto = $filtros + ($q !== '' ? ['q' => $q] : []);
        return compact('campos', 'opciones', 'filas', 'filtros', 'contexto', 'q') + ['pagina' => $datos['page'] ?? 1, 'formato' => $datos['formato'] ?? 'detalle'];
    }

    private function valores(array $fila, string $campo): array
    {
        $valor = (string) ($fila[$campo] ?? '');
        return in_array($campo, ['docentes', 'espacios', 'carreras', 'sedes'], true) ? explode(' / ', $valor) : [$valor];
    }

    public static function codigo(array $fila): string
    {
        $dias = ['lunes' => 'LU', 'martes' => 'MA', 'miércoles' => 'MI', 'miercoles' => 'MI', 'jueves' => 'JU', 'viernes' => 'VI', 'sábado' => 'SA', 'sabado' => 'SA', 'domingo' => 'DO'];
        $tipo = mb_strtolower($fila['actividad'] ?? '');
        $prefijo = str_contains($tipo, 'cátedra') || str_contains($tipo, 'catedra') ? 'ca' : (str_contains($tipo, 'laboratorio') ? 'la' : mb_substr($tipo, 0, 2));
        $dia = $dias[mb_strtolower($fila['dia'] ?? '')] ?? mb_strtoupper(mb_substr($fila['dia'] ?? '', 0, 2));
        $grupo = preg_replace('/^G\s*:\s*/i', '', $fila['grupo'] ?? '');
        return $prefijo.': '.$dia.'.'.($fila['modulo'] ?? '').'/G:'.$grupo.' ('.($fila['codigo_espacios'] ?? $fila['espacios'] ?? '').')';
    }
}

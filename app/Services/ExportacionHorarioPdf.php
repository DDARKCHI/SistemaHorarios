<?php

namespace App\Services;

use App\Models\CierreProgramacion;
use Dompdf\{Dompdf, Options};
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class ExportacionHorarioPdf
{
    public function generar(CierreProgramacion $cierre, Collection $filas, string $formato, array $filtros = []): string
    {
        if ($filas->isEmpty()) throw ValidationException::withMessages(['pdf' => 'No hay clases en esta selección para exportar.']);
        if ($filas->count() > 300) throw ValidationException::withMessages(['pdf' => 'Selecciona un ramo, sección, carrera o docente para exportar hasta 300 módulos por archivo.']);
        $filas = $filas->map(fn ($fila) => $fila + ['codigo_horario' => ConsultaPublicacion::codigo($fila)])->values();
        $semanas = [];
        if ($formato === 'semanal') {
            foreach ($filas->groupBy(fn ($f) => $f['curso'].' · Sección '.$f['seccion']) as $titulo => $clases) {
                $dias = $clases->pluck('dia')->unique()->sortBy(fn ($d) => $this->ordenDia($d))->values();
                $intervalos = $clases->pluck('hora')->unique()->sort()->values();
                foreach ($intervalos->chunk(3) as $indice => $horas) $semanas[] = ['titulo' => $titulo, 'parte' => $indice + 1, 'partes' => (int) ceil($intervalos->count() / 3),
                    'dias' => $dias, 'horas' => $horas, 'clases' => $clases];
            }
            if (count($semanas) > 60) throw ValidationException::withMessages(['pdf' => 'La vista semanal supera 60 páginas. Reduce la selección antes de exportar.']);
        }
        $temporal = storage_path('framework/cache/pdf');
        if (! is_dir($temporal)) mkdir($temporal, 0775, true);
        $opciones = new Options;
        $opciones->setIsRemoteEnabled(false);
        $opciones->setIsPhpEnabled(false);
        $opciones->setIsJavascriptEnabled(false);
        $opciones->setChroot([public_path('images')]);
        $opciones->setTempDir($temporal);
        $opciones->setFontCache($temporal);
        $opciones->setDefaultFont('DejaVu Sans');
        $pdf = new Dompdf($opciones);
        $pdf->setPaper('A4', 'landscape');
        $marca = app(IdentidadVisual::class);
        $logo = 'data:image/png;base64,'.base64_encode($marca->png('principal'));
        $html = view('programacion.pdf.horario', compact('cierre', 'filas', 'formato', 'filtros', 'semanas', 'logo'))->render();
        $pdf->loadHtml($html, 'UTF-8');
        $pdf->render();
        $pdf->getCanvas()->page_text(32, 571, $marca->datos()['nombre'].' · Horario publicado', null, 8, [0.4, 0.43, 0.47]);
        $pdf->getCanvas()->page_text(715, 571, 'Página {PAGE_NUM} de {PAGE_COUNT}', null, 8, [0.4, 0.43, 0.47]);
        return $pdf->output();
    }

    private function ordenDia(string $dia): int
    {
        return match (mb_strtolower($dia)) { 'lunes' => 1, 'martes' => 2, 'miércoles', 'miercoles' => 3, 'jueves' => 4, 'viernes' => 5, 'sábado', 'sabado' => 6, 'domingo' => 7, default => 8 };
    }
}

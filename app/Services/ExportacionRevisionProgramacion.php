<?php

namespace App\Services;

use App\Models\VersionPlanificacion;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportacionRevisionProgramacion
{
    public function descargar(VersionPlanificacion $version, string $tipo, array $reporte, Collection $observaciones): StreamedResponse
    {
        // Los identificadores y la clave validada evitan usar nombres libres en la descarga.
        $nombre = 'revision-periodo-'.$version->periodo_academico_id.'-version-'.$version->id.'-'.$tipo.'.xlsx';

        return response()->streamDownload(function () use ($version, $tipo, $reporte, $observaciones) {
            $libro = $this->crearLibro($version, $tipo, $reporte, $observaciones);
            try {
                (new Xlsx($libro))->save('php://output');
            } finally {
                $libro->disconnectWorksheets();
            }
        }, $nombre, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'private, no-store, max-age=0', 'X-Content-Type-Options' => 'nosniff']);
    }

    private function crearLibro(VersionPlanificacion $version, string $tipo, array $reporte, Collection $observaciones): Spreadsheet
    {
        $libro = new Spreadsheet;
        $libro->getProperties()->setCreator('PROSEC')->setTitle('Revisión de conflictos de programación');
        $libro->getDefaultStyle()->getFont()->setName('Calibri')->setSize(11);

        $resumen = $libro->getActiveSheet()->setTitle('Resumen');
        $this->escribirFila($resumen, 1, ['Revisión de conflictos de programación']);
        $resumen->mergeCells('A1:B1');
        $datos = [
            ['Período académico', $version->periodoAcademico->nombre],
            ['ID de versión', $version->id],
            ['Versión semestral', 'v'.$version->version.' · '.$version->nombre],
            ['Estado de la versión', $version->estado.($version->es_actual ? ' · Actual' : '')],
            ['Filtro aplicado', RevisionConflictosProgramacion::TIPOS[$tipo]],
            ['Observaciones exportadas', $observaciones->count()],
            ['Fecha de exportación (UTC)', now()->utc()->format('Y-m-d H:i:s')],
            ['Alcance', 'Incluye todas las páginas del filtro aplicado y refleja los datos al momento de descargar.'],
            ['Revisión adicional', 'Este informe no constituye una aprobación de la programación. La aprobación formal de disponibilidades y la aplicación automática de prioridades siguen pendientes.'],
        ];
        foreach ($datos as $indice => $fila) $this->escribirFila($resumen, $indice + 3, $fila);
        $this->escribirFila($resumen, 13, ['Totales de la versión completa', 'Cantidad']);
        $totales = ['modulos' => 'Módulos revisados', 'conflictos' => 'Cruces detectados', 'asignaciones' => 'Asignaciones incompletas',
            'capacidad' => 'Capacidades por revisar', 'disponibilidad' => 'Disponibilidad por revisar', 'recursos' => 'Recursos por revisar',
            'carga_observaciones' => 'Carga por revisar', 'datos' => 'Datos por revisar'];
        foreach (array_keys($totales) as $indice => $clave) $this->escribirFila($resumen, $indice + 14, [$totales[$clave], $reporte[$clave]]);
        $this->escribirFila($resumen, 23, ['Cumplimiento de módulos', 'Cantidad']);
        $carga = ['requeridos' => 'Módulos requeridos', 'asignados' => 'Asignados a grupos válidos', 'pendientes' => 'Módulos pendientes', 'excedentes' => 'Módulos en exceso'];
        foreach (array_keys($carga) as $indice => $clave) $this->escribirFila($resumen, $indice + 24, [$carga[$clave], $reporte['carga'][$clave]]);
        $resumen->getColumnDimension('A')->setWidth(36);
        $resumen->getColumnDimension('B')->setWidth(100);
        $resumen->getStyle('A1:B27')->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);
        $resumen->getStyle('A3:A11')->getFont()->setBold(true);
        $resumen->getRowDimension(10)->setRowHeight(34);
        $resumen->getRowDimension(11)->setRowHeight(48);
        $this->encabezado($resumen, 'A1:B1');
        $this->encabezado($resumen, 'A13:B13');
        $this->encabezado($resumen, 'A23:B23');

        $detalle = $libro->createSheet()->setTitle('Observaciones');
        $this->escribirFila($detalle, 1, ['N.º', 'Tipo de revisión', 'Día', 'Intervalo revisado', 'Observación', 'Detalle',
            'Recursos involucrados', 'ID horarios', 'Asignaturas', 'Secciones, actividades y grupos', 'Módulos y horas']);
        foreach ($observaciones->values() as $indice => $observacion) {
            $clases = collect($observacion['horarios'] ?: [$observacion['clase']]);
            $recursos = collect($observacion['recursos'])->map(fn ($recurso) => RevisionConflictosProgramacion::TIPOS[$recurso['tipo']].': '.$recurso['nombre']);
            $this->escribirFila($detalle, $indice + 2, [
                $indice + 1, RevisionConflictosProgramacion::ETIQUETAS[$observacion['tipo']],
                $observacion['dia'] ?? '', $observacion['intervalo'] ?? '',
                $observacion['titulo'] ?? RevisionConflictosProgramacion::ETIQUETAS[$observacion['tipo']],
                $observacion['mensaje'], $recursos->implode("\n"), $clases->pluck('id')->filter()->implode("\n"),
                $clases->pluck('asignatura')->implode("\n"),
                $clases->map(fn ($clase) => 'Sección '.$clase['seccion'].' · '.$clase['actividad'].(filled($clase['grupo'] ?? null) ? ' · '.$clase['grupo'] : ''))->implode("\n"),
                $clases->map(fn ($clase) => isset($clase['id']) ? 'Módulo '.($clase['bloque'] ?? 'sin número').' · '.$clase['hora'] : 'Sin módulo asociado')->implode("\n"),
            ]);
        }
        $ultimaFila = max(1, $observaciones->count() + 1);
        $detalle->getStyle('A1:K'.$ultimaFila)->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);
        foreach (['A' => 8, 'B' => 29, 'C' => 16, 'D' => 23, 'E' => 33, 'F' => 85, 'G' => 50, 'H' => 18, 'I' => 65, 'J' => 50, 'K' => 35] as $columna => $ancho) {
            $detalle->getColumnDimension($columna)->setWidth($ancho);
        }
        $this->encabezado($detalle, 'A1:K1');
        $detalle->getRowDimension(1)->setRowHeight(34);
        $detalle->freezePane('A2');
        $detalle->setAutoFilter('A1:K'.$ultimaFila);
        $libro->setActiveSheetIndex(0);

        return $libro;
    }

    private function escribirFila(Worksheet $hoja, int $fila, array $valores): void
    {
        foreach (array_values($valores) as $indice => $valor) {
            // Los textos del catálogo no se interpretan como fórmulas; los contadores siguen siendo números.
            $esNumero = is_int($valor) || is_float($valor);
            $hoja->setCellValueExplicit([$indice + 1, $fila], $esNumero ? $valor : (string) ($valor ?? ''),
                $esNumero ? DataType::TYPE_NUMERIC : DataType::TYPE_STRING);
        }
    }

    private function encabezado(Worksheet $hoja, string $rango): void
    {
        $hoja->getStyle($rango)->applyFromArray(['font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => 'D12421']]]);
    }
}

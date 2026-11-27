<?php

namespace App\Services;

use App\Models\Carrera;
use App\Models\VersionPlanificacion;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportacionPanelCarrera
{
    public function descargar(VersionPlanificacion $version, Carrera $carrera, array $panel): StreamedResponse
    {
        return response()->streamDownload(function () use ($version, $carrera, $panel) {
            $libro = $this->crearLibro($version, $carrera, $panel);
            try {
                $escritor = new Xlsx($libro);
                $escritor->setPreCalculateFormulas(false);
                $escritor->save('php://output');
            } finally {
                $libro->disconnectWorksheets();
            }
        }, 'programacion-carrera-'.$carrera->id.'-version-'.$version->id.'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function crearLibro(VersionPlanificacion $version, Carrera $carrera, array $panel): Spreadsheet
    {
        $libro = new Spreadsheet;
        $libro->getProperties()->setCreator('PROSEC')->setTitle('Programación de carrera');
        $resumen = [
            ['Carrera', $carrera->nombre], ['Período', $version->periodoAcademico->nombre],
            ['Versión', 'V'.$version->version.' · '.$version->estado],
            ['Secciones', count($panel['secciones'])],
            ['Secciones con responsable confirmado', $panel['indicadores']['confirmadas']],
            ['Secciones confirmadas con carga completa', $panel['indicadores']['completas']],
            ['Módulos programados', count($panel['horarios'])],
            ['Observaciones', count($panel['observaciones'])],
        ];
        $this->hoja($libro->getActiveSheet()->setTitle('Resumen'), ['Indicador', 'Valor'], $resumen, [40, 60]);

        $dias = $panel['dias']->keyBy('id');
        $bloques = $panel['bloques']->keyBy('id');
        $horarios = collect($panel['horarios'])->map(function ($clase) use ($dias, $bloques) {
            $bloque = $bloques->get($clase['bloque_id']);

            return [$clase['ramo_codigo'], $clase['ramo'], $clase['semestre'], $clase['seccion'], $clase['actividad'], $clase['grupo'],
                $dias->get($clase['dia_id'])?->nombre, $bloque?->numero, substr($bloque?->hora_inicio ?? '', 0, 5),
                substr($bloque?->hora_fin ?? '', 0, 5), implode(', ', array_column($clase['docentes'], 'nombre')),
                implode(', ', array_column($clase['espacios'], 'nombre')), implode(' ', $clase['motivos'])];
        });
        $this->hoja($libro->createSheet()->setTitle('Horario'), ['Código', 'Ramo', 'Semestre', 'Sección', 'Actividad', 'Grupo',
            'Día', 'Módulo', 'Inicio', 'Término', 'Docentes', 'Espacios', 'Observaciones'], $horarios, [14, 44, 12, 12, 18, 12, 16, 12, 12, 12, 40, 40, 75]);

        $ramos = collect($panel['secciones'])->flatMap(fn ($s) => array_map(fn ($g) => [
            $s['ramo_codigo'], $s['ramo'], $s['semestre'], $s['codigo'], $g['actividad'], $g['codigo'],
            $g['requeridos'], $g['asignados'], $g['pendientes'], $g['excedentes'], $g['confirmado'] ? 'Sí' : 'Pendiente',
        ], $s['grupos']));
        $this->hoja($libro->createSheet()->setTitle('Ramos y grupos'), ['Código', 'Ramo', 'Semestre', 'Sección', 'Actividad', 'Grupo',
            'Requeridos', 'Asignados', 'Pendientes', 'En exceso', 'Responsable confirmado'], $ramos, [14, 44, 12, 12, 18, 12, 13, 13, 13, 13, 24]);
        $hojaCarga = $libro->getSheetByName('Ramos y grupos');
        // Los cambios en la hoja Horario recalculan la carga por grupo dentro del libro.
        for ($fila = 2; $fila <= $ramos->count() + 1; $fila++) {
            $criterios = collect(['A', 'C', 'D', 'E', 'F'])->map(
                fn ($columna) => 'Horario!$'.$columna.'$2:$'.$columna.'$15000,'.$columna.$fila
            )->implode(',');
            $hojaCarga->setCellValue('H'.$fila, '=COUNTIFS('.$criterios.')');
            $hojaCarga->setCellValue('I'.$fila, '=MAX(0,G'.$fila.'-H'.$fila.')');
            $hojaCarga->setCellValue('J'.$fila, '=MAX(0,H'.$fila.'-G'.$fila.')');
        }

        $docentes = array_map(fn ($d) => [$d['nombre'], $d['dias'], $d['modulos'], $d['huecos'], $d['dias_cortos'], $d['fuera'],
            ['aprobada' => 'Aprobada', 'registrada' => 'Registrada', 'sin_declarar' => 'Sin declarar'][$d['disponibilidad']]], $panel['docentes']);
        $this->hoja($libro->createSheet()->setTitle('Carga docente'), ['Docente', 'Días con clases', 'Módulos', 'Huecos',
            'Días con 1 o 2 módulos', 'Módulos fuera de disponibilidad', 'Disponibilidad'], $docentes, [40, 18, 12, 12, 25, 34, 20]);

        $laboratorios = array_map(fn ($l) => [$l['nombre'], $l['sede'], $l['tipo'] === 'movil' ? 'Móvil' : 'Fijo',
            $l['capacidad'], $l['ocupados'], $l['libres'], $l['total'], $l['disponible'] ? 'Habilitado' : 'No habilitado'], $panel['laboratorios']);
        $this->hoja($libro->createSheet()->setTitle('Laboratorios'), ['Laboratorio', 'Sede', 'Tipo', 'Capacidad',
            'Módulos ocupados', 'Módulos libres', 'Módulos del calendario', 'Estado'], $laboratorios, [40, 28, 12, 12, 20, 19, 26, 18]);

        $control = array_map(fn ($o) => [$o['titulo'], $o['dia'], $o['intervalo'], $o['mensaje']], $panel['observaciones']);
        $this->hoja($libro->createSheet()->setTitle('Control'), ['Observación', 'Día', 'Intervalo', 'Detalle'], $control, [38, 16, 20, 80]);
        $libro->setActiveSheetIndex(0);

        return $libro;
    }

    private function hoja(Worksheet $hoja, array $titulos, iterable $filas, array $anchos): void
    {
        $this->fila($hoja, 1, $titulos);
        $numero = 1;
        foreach ($filas as $fila) $this->fila($hoja, ++$numero, $fila);
        $ultima = Coordinate::stringFromColumnIndex(count($titulos));
        $hoja->getStyle('A1:'.$ultima.'1')->applyFromArray(['font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => 'D12421']]]);
        foreach ($anchos as $indice => $ancho) $hoja->getColumnDimensionByColumn($indice + 1)->setWidth($ancho);
        $hoja->freezePane('A2');
        $hoja->setAutoFilter('A1:'.$ultima.$numero);
    }

    private function fila(Worksheet $hoja, int $numero, array $valores): void
    {
        foreach (array_values($valores) as $indice => $valor) {
            $numerico = is_int($valor) || is_float($valor);
            $hoja->setCellValueExplicit([$indice + 1, $numero], $numerico ? $valor : (string) ($valor ?? ''),
                $numerico ? DataType::TYPE_NUMERIC : DataType::TYPE_STRING);
        }
    }
}

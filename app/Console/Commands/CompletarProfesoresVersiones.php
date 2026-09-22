<?php

namespace App\Console\Commands;

use App\Imports\ProgramacionImport;
use App\Models\Profesor;
use App\Models\ProfesorVersion;
use App\Models\VersionPlanificacion;
use App\Services\ProgramacionExcelService;
use Illuminate\Console\Command;
use Maatwebsite\Excel\Facades\Excel;

class CompletarProfesoresVersiones extends Command
{
    protected $signature = 'programacion:completar-profesores-versiones';

    protected $description = 'Completa los datos históricos de profesores para versiones anteriores';

    public function handle(): int
    {
        $archivos = [
            20 => storage_path('app/prueba.xlsx'),
            21 => storage_path('app/prueba_cambio.xlsx'),
        ];

        foreach ($archivos as $versionId => $rutaArchivo) {
            $version = VersionPlanificacion::query()
                ->where('id', $versionId)
                ->where('tipo', 'anterior')
                ->first();

            if (!$version) {
                $this->error("No existe la versión ID {$versionId}.");
                return self::FAILURE;
            }

            if (!file_exists($rutaArchivo)) {
                $this->error("No existe el archivo: {$rutaArchivo}");
                return self::FAILURE;
            }

            $hojas = Excel::toArray(
                new ProgramacionImport,
                $rutaArchivo
            );

            $resultado = (new ProgramacionExcelService())->analizar(
                $hojas
            );

            if (!$resultado['valido']) {
                $this->error(
                    "El archivo para la versión {$version->version} no es válido."
                );

                return self::FAILURE;
            }

            $profesoresGuardados = [];

            foreach ($resultado['registros'] as $registro) {
                $registro = array_change_key_case(
                    $registro,
                    CASE_UPPER
                );

                $rut = $this->texto(
                    $registro['PROF_RESP'] ?? null
                );

                $correo = $this->texto(
                    $registro['CORREO_INST'] ?? null
                );

                if ($rut === '' && $correo === '') {
                    continue;
                }

                $profesor = Profesor::query()
                    ->where('universidad_id', 1)
                    ->when(
                        $rut !== '',
                        fn ($query) => $query->where('rut', $rut),
                        fn ($query) => $query->where(
                            'correo_institucional',
                            $correo
                        )
                    )
                    ->first();

                if (!$profesor) {
                    continue;
                }

                if (isset($profesoresGuardados[$profesor->id])) {
                    continue;
                }

                $nombreCompleto = $this->texto(
                    $registro['NOM_PROF_RESP'] ?? null
                );

                [$nombre, $apellido] = $this->separarNombre(
                    $nombreCompleto
                );

                ProfesorVersion::updateOrCreate(
                    [
                        'version_planificacion_id' => $version->id,
                        'profesor_id' => $profesor->id,
                    ],
                    [
                        'rut' => $rut ?: null,
                        'nombre' => $nombre ?: null,
                        'apellido' => $apellido ?: null,
                        'correo_institucional' => $correo ?: null,
                        'correo_personal' => $this->texto(
                            $registro['CORREO_PERS'] ?? null
                        ) ?: null,
                        'tipo_contrato' => $this->texto(
                            $registro['TIPO_CONTRATO'] ?? null
                        ) ?: null,
                        'tipo_profesor' => $this->texto(
                            $registro['TIPO_PROFESOR'] ?? null
                        ) ?: null,
                        'unidad_academica' => $this->texto(
                            $registro['UA'] ?? null
                        ) ?: null,
                    ]
                );

                $profesoresGuardados[$profesor->id] = true;
            }

            $this->info(
                'V' . $version->version
                . ': '
                . count($profesoresGuardados)
                . ' profesores guardados.'
            );
        }

        $this->info('Proceso terminado correctamente.');

        return self::SUCCESS;
    }

    private function separarNombre(string $nombreCompleto): array
    {
        $partes = preg_split(
            '/\s+/',
            trim($nombreCompleto)
        );

        if (!$partes) {
            return ['', ''];
        }

        if (count($partes) === 1) {
            return [$partes[0], ''];
        }

        $nombre = array_shift($partes);
        $apellido = implode(' ', $partes);

        return [$nombre, $apellido];
    }

    private function texto($valor): string
    {
        if ($valor === null) {
            return '';
        }

        return trim((string) $valor);
    }
}
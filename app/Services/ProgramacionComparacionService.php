<?php

namespace App\Services;

use App\Models\Asignatura;
use App\Models\Carrera;
use App\Models\PeriodoAcademico;
use App\Models\Profesor;
use App\Models\Seccion;

class ProgramacionComparacionService
{
    public function comparar(array $registros): array
    {
        $registros = $this->normalizarRegistros($registros);

        $resultado = [
            'periodos' => [
                'nuevos' => 0,
                'existentes' => 0,
            ],
            'carreras' => [
                'nuevas' => 0,
                'existentes' => 0,
            ],
            'asignaturas' => [
                'nuevas' => 0,
                'existentes' => 0,
            ],
            'profesores' => [
                'nuevos' => 0,
                'existentes' => 0,
            ],
            'secciones' => [
                'nuevas' => 0,
                'existentes' => 0,
            ],
            'errores' => [],
        ];

        $periodos = [];
        $carreras = [];
        $asignaturas = [];
        $profesores = [];
        $secciones = [];

        foreach ($registros as $indice => $registro) {
            try {
                $this->validarRegistroMinimo($registro);

                $periodos[$this->clavePeriodo($registro)] = $registro;
                $carreras[$this->claveCarrera($registro)] = $registro;
                $asignaturas[$this->claveAsignatura($registro)] = $registro;

                $claveProfesor = $this->claveProfesor($registro);

                if ($claveProfesor !== null) {
                    $profesores[$claveProfesor] = $registro;
                }

                $claveSeccion = $this->claveSeccion($registro);

                if ($claveSeccion !== null) {
                    $secciones[$claveSeccion] = $registro;
                }
            } catch (\Throwable $e) {
                $resultado['errores'][] = [
                    'fila' => $indice + 2,
                    'mensaje' => $e->getMessage(),
                ];
            }
        }

        $resultado['diagnostico'] = $this->diagnosticarEstructura($registros);

        $resultado['periodos'] = $this->compararPeriodos(
            array_values($periodos)
        );

        $resultado['carreras'] = $this->compararCarreras(
            array_values($carreras)
        );

        $resultado['asignaturas'] = $this->compararAsignaturas(
            array_values($asignaturas)
        );

        $resultado['profesores'] = $this->compararProfesores(
            array_values($profesores)
        );

        $resultado['secciones'] = $this->compararSecciones(
            array_values($secciones)
        );

        $resultado['totales'] = [
            'registros_excel' => count($registros),
            'periodos' => count($periodos),
            'carreras' => count($carreras),
            'asignaturas' => count($asignaturas),
            'profesores' => count($profesores),
            'secciones' => count($secciones),
            'relaciones_seccion_carrera' =>
                $resultado['diagnostico']['relaciones_seccion_carrera'] ?? 0,
            'grupos' =>
                $resultado['diagnostico']['grupos_unicos'] ?? 0,
            'errores' => count($resultado['errores']),
        ];

        return $resultado;
    }

    private function normalizarRegistros(array $registros): array
    {
        return array_map(function ($registro) {
            $normalizado = [];

            foreach ($registro as $clave => $valor) {
                $claveNormalizada = mb_strtoupper(
                    trim((string) $clave)
                );

                $claveNormalizada = str_replace(
                    ['Ã', 'Â'],
                    ['Ñ', 'N'],
                    $claveNormalizada
                );

                $normalizado[$claveNormalizada] = $valor;
            }

            return $normalizado;
        }, $registros);
    }

    private function diagnosticarEstructura(array $registros): array
    {
        $idCursos = [];
        $codRamos = [];
        $idCursoSeccion = [];
        $idCursoCarrera = [];
        $ramoSeccionCarrera = [];
        $relacionesSeccionCarrera = [];
        $grupos = [];
        $datosCursos = [];

        foreach ($registros as $registro) {
            $idCurso = trim((string) ($registro['ID_CURSO'] ?? ''));
            $codRamo = trim((string) ($registro['COD_RAMO'] ?? ''));
            $secc = trim((string) ($registro['SECC'] ?? ''));
            $carrera = trim((string) ($registro['NOM_CARR'] ?? ''));
            $semestre = trim((string) ($registro['SEMESTRE'] ?? ''));
            $profesorRut = trim((string) ($registro['PROF_RESP'] ?? ''));
            $profesorNombre = trim((string) ($registro['NOM_PROF_RESP'] ?? ''));
            $sede = trim((string) ($registro['SEDE'] ?? ''));

            if ($idCurso === '') {
                continue;
            }

            $idCursos[$idCurso] = true;

            if (!isset($datosCursos[$idCurso])) {
                $datosCursos[$idCurso] = [
                    'id_curso' => $idCurso,
                    'cod_ramo' => $codRamo,
                    'secc' => $secc,
                    'semestre' => $semestre,
                    'sede' => $sede,
                    'carreras' => [],
                    'profesores' => [],
                    'carrera_profesores' => [],
                ];
            }

            if ($codRamo !== '') {
                $codRamos[$codRamo] = true;
            }

            if ($carrera !== '') {
                $claveCarrera = mb_strtoupper($carrera);

                $datosCursos[$idCurso]['carreras'][$claveCarrera] = $carrera;

                if (!isset($datosCursos[$idCurso]['carrera_profesores'][$claveCarrera])) {
                    $datosCursos[$idCurso]['carrera_profesores'][$claveCarrera] = [
                        'carrera' => $carrera,
                        'profesores' => [],
                    ];
                }

                $profesor = $this->datosProfesor($profesorRut, $profesorNombre);

                if ($profesor !== null) {
                    $claveProfesor = $this->claveProfesorDesdeDatos(
                        $profesorRut,
                        $profesorNombre
                    );

                    $datosCursos[$idCurso]['profesores'][$claveProfesor] = $profesor;

                    $datosCursos[$idCurso]['carrera_profesores'][$claveCarrera]['profesores'][$claveProfesor] = $profesor;
                }
            }

            $profesor = $this->datosProfesor($profesorRut, $profesorNombre);

            if ($profesor !== null) {
                $claveProfesor = $this->claveProfesorDesdeDatos(
                    $profesorRut,
                    $profesorNombre
                );

                $datosCursos[$idCurso]['profesores'][$claveProfesor] = $profesor;
            }

            if ($idCurso !== '' && $secc !== '') {
                $clave = implode('|', [
                    $idCurso,
                    $secc,
                    $semestre,
                ]);

                $idCursoSeccion[$clave] = true;
            }

            if ($idCurso !== '' && $carrera !== '') {
                $clave = implode('|', [
                    $idCurso,
                    mb_strtoupper($carrera),
                    $semestre,
                ]);

                $idCursoCarrera[$clave] = true;
            }

            if ($idCurso !== '' && $secc !== '' && $carrera !== '') {
                $claveRelacion = implode('|', [
                    $idCurso,
                    $secc,
                    mb_strtoupper($carrera),
                    $semestre,
                ]);

                $relacionesSeccionCarrera[$claveRelacion] = true;
            }

            if ($codRamo !== '' && $secc !== '' && $carrera !== '') {
                $clave = implode('|', [
                    $codRamo,
                    $secc,
                    mb_strtoupper($carrera),
                    $semestre,
                ]);

                $ramoSeccionCarrera[$clave] = true;
            }

            $horario = trim((string) ($registro['HORARIO'] ?? ''));

            foreach ($this->extraerGrupos($horario) as $grupo) {
                $claveGrupo = implode('|', [
                    $idCurso,
                    $secc,
                    $grupo,
                ]);

                $grupos[$claveGrupo] = true;
            }
        }

        $cursosCompartidos = [];

        foreach ($datosCursos as $curso) {
            $carreras = array_values($curso['carreras']);
            $profesores = array_values($curso['profesores']);

            if (count($carreras) <= 1) {
                continue;
            }

            $carreraProfesores = [];

            foreach ($curso['carrera_profesores'] as $relacion) {
                $carreraProfesores[] = [
                    'carrera' => $relacion['carrera'],
                    'profesores' => array_values(
                        $relacion['profesores']
                    ),
                ];
            }

            $cursosCompartidos[] = [
                'id_curso' => $curso['id_curso'],
                'cod_ramo' => $curso['cod_ramo'],
                'secc' => $curso['secc'],
                'semestre' => $curso['semestre'],
                'sede' => $curso['sede'],
                'cantidad_carreras' => count($carreras),
                'cantidad_profesores' => count($profesores),
                'carreras' => $carreras,
                'profesores' => $profesores,
                'carrera_profesores' => $carreraProfesores,
            ];
        }

        usort(
            $cursosCompartidos,
            function ($a, $b) {
                return $b['cantidad_carreras']
                    <=> $a['cantidad_carreras'];
            }
        );

        $distribucionProfesores = [
            'un_profesor' => 0,
            'varios_profesores' => 0,
        ];

        foreach ($cursosCompartidos as $curso) {
            if ($curso['cantidad_profesores'] <= 1) {
                $distribucionProfesores['un_profesor']++;
            } else {
                $distribucionProfesores['varios_profesores']++;
            }
        }

        return [
            'filas_originales' => count($registros),
            'id_cursos_unicos' => count($idCursos),
            'cod_ramos_unicos' => count($codRamos),
            'id_curso_seccion_unicos' => count($idCursoSeccion),
            'id_curso_carrera_unicos' => count($idCursoCarrera),
            'ramo_seccion_carrera_unicos' => count($ramoSeccionCarrera),
            'relaciones_seccion_carrera' => count($relacionesSeccionCarrera),
            'grupos_unicos' => count($grupos),
            'cursos_compartidos' => count($cursosCompartidos),
            'cursos_compartidos_un_profesor' => $distribucionProfesores['un_profesor'],
            'cursos_compartidos_varios_profesores' => $distribucionProfesores['varios_profesores'],
            'ejemplos_cursos_compartidos' => array_slice(
                $cursosCompartidos,
                0,
                10
            ),
        ];
    }

    private function extraerGrupos(string $horario): array
    {
        if ($horario === '') {
            return [];
        }

        preg_match_all(
            '/\bG\s*:\s*(\d+)/i',
            $horario,
            $coincidencias
        );

        if (empty($coincidencias[1])) {
            return [];
        }

        return array_values(
            array_unique(
                array_map(
                    fn ($grupo) => 'G:' . $grupo,
                    $coincidencias[1]
                )
            )
        );
    }

    private function validarRegistroMinimo(array $registro): void
    {
        if (empty($registro['COD_RAMO'])) {
            throw new \Exception('El registro no contiene COD_RAMO.');
        }

        if (empty($registro['RAMO_NOMBRE'])) {
            throw new \Exception('El registro no contiene RAMO_NOMBRE.');
        }

        if (empty($registro['SEMESTRE'])) {
            throw new \Exception('El registro no contiene SEMESTRE.');
        }

        if (empty($registro['NOM_CARR'])) {
            throw new \Exception('El registro no contiene NOM_CARR.');
        }
    }

    private function clavePeriodo(array $registro): string
    {
        return trim((string) $registro['SEMESTRE']);
    }

    private function claveCarrera(array $registro): string
    {
        return 'NOMBRE:' . mb_strtoupper(
            trim((string) $registro['NOM_CARR'])
        );
    }

    private function claveAsignatura(array $registro): string
    {
        return trim((string) $registro['COD_RAMO']);
    }

    private function claveProfesor(array $registro): ?string
    {
        if (!empty($registro['PROF_RESP'])) {
            return 'RUT:' . trim((string) $registro['PROF_RESP']);
        }

        if (!empty($registro['CORREO_INST'])) {
            return 'CORREO:' . mb_strtolower(
                trim((string) $registro['CORREO_INST'])
            );
        }

        if (!empty($registro['NOM_PROF_RESP'])) {
            return 'NOMBRE:' . mb_strtoupper(
                trim((string) $registro['NOM_PROF_RESP'])
            );
        }

        return null;
    }

    private function claveSeccion(array $registro): ?string
    {
        if (
            empty($registro['ID_CURSO']) ||
            empty($registro['SECC']) ||
            empty($registro['SEMESTRE'])
        ) {
            return null;
        }

        return implode('|', [
            trim((string) $registro['ID_CURSO']),
            trim((string) $registro['SECC']),
            trim((string) $registro['SEMESTRE']),
        ]);
    }

    private function compararPeriodos(array $registros): array
    {
        $nuevos = 0;
        $existentes = 0;

        foreach ($registros as $registro) {
            $existe = PeriodoAcademico::where(
                'nombre',
                trim((string) $registro['SEMESTRE'])
            )->exists();

            if ($existe) {
                $existentes++;
            } else {
                $nuevos++;
            }
        }

        return compact('nuevos', 'existentes');
    }

    private function compararCarreras(array $registros): array
    {
        $nuevas = 0;
        $existentes = 0;

        foreach ($registros as $registro) {
            $nombre = trim((string) $registro['NOM_CARR']);

            $existe = Carrera::where(
                'nombre',
                $nombre
            )->exists();

            if ($existe) {
                $existentes++;
            } else {
                $nuevas++;
            }
        }

        return compact('nuevas', 'existentes');
    }

    private function compararAsignaturas(array $registros): array
    {
        $nuevas = 0;
        $existentes = 0;

        foreach ($registros as $registro) {
            $existe = Asignatura::where(
                'codigo',
                trim((string) $registro['COD_RAMO'])
            )->exists();

            if ($existe) {
                $existentes++;
            } else {
                $nuevas++;
            }
        }

        return compact('nuevas', 'existentes');
    }

    private function compararProfesores(array $registros): array
    {
        $nuevos = 0;
        $existentes = 0;

        foreach ($registros as $registro) {
            $clave = $this->claveProfesor($registro);

            if ($clave === null) {
                continue;
            }

            $valor = substr(
                $clave,
                strpos($clave, ':') + 1
            );

            $consulta = Profesor::query();

            if (str_starts_with($clave, 'RUT:')) {
                $consulta->where('rut', $valor);
            } elseif (str_starts_with($clave, 'CORREO:')) {
                $consulta->where(
                    'correo_institucional',
                    $valor
                );
            } else {
                $consulta->where('nombre', $valor);
            }

            if ($consulta->exists()) {
                $existentes++;
            } else {
                $nuevos++;
            }
        }

        return compact('nuevos', 'existentes');
    }

    private function compararSecciones(array $registros): array
    {
        $nuevas = 0;
        $existentes = 0;

        foreach ($registros as $registro) {
            $periodo = PeriodoAcademico::where(
                'nombre',
                trim((string) $registro['SEMESTRE'])
            )->first();

            $asignatura = Asignatura::where(
                'codigo',
                trim((string) $registro['COD_RAMO'])
            )->first();

            if (!$periodo || !$asignatura) {
                $nuevas++;
                continue;
            }

            $existe = Seccion::where(
                    'identificador_externo',
                    trim((string) $registro['ID_CURSO'])
                )
                ->where(
                    'asignatura_id',
                    $asignatura->id
                )
                ->where(
                    'periodo_academico_id',
                    $periodo->id
                )
                ->where(
                    'codigo',
                    trim((string) $registro['SECC'])
                )
                ->exists();

            if ($existe) {
                $existentes++;
            } else {
                $nuevas++;
            }
        }

        return compact('nuevas', 'existentes');
    }

    private function datosProfesor(
        string $rut,
        string $nombre
    ): ?array {
        if ($rut !== '') {
            return [
                'identificador' => $rut,
                'nombre' => $nombre,
            ];
        }

        if ($nombre !== '') {
            return [
                'identificador' => null,
                'nombre' => $nombre,
            ];
        }

        return null;
    }

    private function claveProfesorDesdeDatos(
        string $rut,
        string $nombre
    ): string {
        if ($rut !== '') {
            return 'RUT:' . $rut;
        }

        return 'NOMBRE:' . mb_strtoupper($nombre);
    }
}

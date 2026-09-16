<?php

namespace App\Services;

use App\Models\Asignatura;

use App\Models\Carrera;

use App\Models\PeriodoAcademico;

use App\Models\Profesor;

use App\Models\Seccion;

use App\Models\VersionPlanificacion;

class ProgramacionComparacionService

{

    public function comparar(array $registros): array

    {

        $registros = $this->normalizarRegistros($registros);

        $resultado = [

            'es_nueva_programacion' => false,

            'hay_cambios' => false,

            'periodo_actual' => null,

            'version_anterior' => null,

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

                'modificadas' => 0,

                'existentes' => 0,

            ],

            'relaciones' => [

                'nuevas' => 0,

                'eliminadas' => 0,

                'modificadas' => 0,

            ],

            'grupos' => [

                'nuevos' => 0,

                'eliminados' => 0,

            ],

            'horarios' => [

                'nuevos' => 0,

                'eliminados' => 0,

                'modificados' => 0,

            ],

            'errores' => [],

        ];

        $periodoNombre = $this->obtenerPeriodoNombre(

            $registros

        );

        if ($periodoNombre !== null) {

            $resultado['periodo_actual'] = $periodoNombre;

        }

        $periodo = $periodoNombre !== null

            ? PeriodoAcademico::where(

                'nombre',

                $periodoNombre

            )->first()

            : null;

        $versionAnterior = null;

        if ($periodo) {

            $versionAnterior = VersionPlanificacion::where(

                'periodo_academico_id',

                $periodo->id

            )

                ->orderByDesc('id')

                ->first();

        }

        if (!$versionAnterior) {

            $resultado['es_nueva_programacion'] = true;

        } else {

            $resultado['version_anterior'] = [

                'id' => $versionAnterior->id,

                'version' => $versionAnterior->version,

                'nombre' => $versionAnterior->nombre,

            ];

        }

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

                $asignaturas[

                    $this->claveAsignatura($registro)

                ] = $registro;

                $claveProfesor =

                    $this->claveProfesor($registro);

                if ($claveProfesor !== null) {

                    $profesores[$claveProfesor] = $registro;

                }

                $claveSeccion =

                    $this->claveSeccion($registro);

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

        /*

         * DiagnÃ³stico estructural.

         *

         * Esta informaciÃ³n describe cÃ³mo estÃ¡ construido

         * el Excel y no se considera automÃ¡ticamente un cambio.

         */

        $resultado['diagnostico'] =

            $this->diagnosticarEstructura($registros);

        /*

         * ComparaciÃ³n de entidades generales.

         */

        $resultado['periodos'] =

            $this->compararPeriodos(

                array_values($periodos)

            );

        $resultado['carreras'] =

            $this->compararCarreras(

                array_values($carreras)

            );

        $resultado['asignaturas'] =

            $this->compararAsignaturas(

                array_values($asignaturas)

            );

        $resultado['profesores'] =
    $this->compararProfesores(
        $registros
    );

        /*

         * Si ya existe una versiÃ³n anterior, hacemos

         * la comparaciÃ³n especÃ­fica de la programaciÃ³n.

         */

        if ($versionAnterior) {

            $resultado['secciones'] =

                $this->compararSeccionesConVersion(

                    array_values($secciones),

                    $versionAnterior

                );

            $resultado['relaciones'] =

                $this->compararRelacionesConVersion(

                    $registros,

                    $versionAnterior

                );

            $resultado['grupos'] =

                $this->compararGruposConVersion(

                    $registros,

                    $versionAnterior

                );

            $resultado['horarios'] =

                $this->compararHorariosConVersion(

                    $registros,

                    $versionAnterior

                );

        } else {

            /*

             * Primera programaciÃ³n:

             * todos los elementos estructurales del archivo

             * se consideran nuevos para efectos del resumen.

             */

            $resultado['secciones']['nuevas'] =

                count($secciones);

            $resultado['relaciones']['nuevas'] =

                $resultado['diagnostico'][

                    'relaciones_seccion_carrera'

                ] ?? 0;

            $resultado['grupos']['nuevos'] =

                $resultado['diagnostico'][

                    'grupos_unicos'

                ] ?? 0;

            $resultado['horarios']['nuevos'] =

                $this->contarHorariosExcel(

                    $registros

                );

        }

        $resultado['totales'] = [

            'registros_excel' => count($registros),

            'periodos' => count($periodos),

            'carreras' => count($carreras),

            'asignaturas' => count($asignaturas),

            'profesores' => count($profesores),

            'secciones' => count($secciones),

            'relaciones_seccion_carrera' =>

                $resultado['diagnostico'][

                    'relaciones_seccion_carrera'

                ] ?? 0,

            'grupos' =>

                $resultado['diagnostico'][

                    'grupos_unicos'

                ] ?? 0,

            'errores' =>

                count($resultado['errores']),

        ];

        $resultado['resumen_cambios'] =

            $this->construirResumenCambios(

                $resultado

            );

        $resultado['hay_cambios'] =

            $this->detectarCambios($resultado);

        return $resultado;

    }

    /*

    |--------------------------------------------------------------------------

    | Resumen de cambios

    |--------------------------------------------------------------------------

    */

    private function construirResumenCambios(

        array $resultado

    ): array {

        $cambios = [];

        $this->agregarCambio(

            $cambios,

            $resultado['periodos']['nuevos'] ?? 0,

            'periodos',

            'perÃ­odo nuevo'

        );

        $this->agregarCambio(

            $cambios,

            $resultado['carreras']['nuevas'] ?? 0,

            'carreras',

            'carrera nueva'

        );

        $this->agregarCambio(

            $cambios,

            $resultado['asignaturas']['nuevas'] ?? 0,

            'asignaturas',

            'asignatura nueva'

        );

        $this->agregarCambio(

            $cambios,

            $resultado['profesores']['nuevos'] ?? 0,

            'profesores',

            'profesor nuevo'

        );

        $this->agregarCambio(
    $cambios,
    $resultado['profesores']['modificados'] ?? 0,
    'profesores_modificados',
    'profesor modificado'
);

        $this->agregarCambio(

            $cambios,

            $resultado['secciones']['nuevas'] ?? 0,

            'secciones_nuevas',

            'secciÃ³n nueva'

        );

        $this->agregarCambio(

            $cambios,

            $resultado['secciones']['modificadas'] ?? 0,

            'secciones_modificadas',

            'secciÃ³n modificada'

        );

        $this->agregarCambio(

            $cambios,

            $resultado['relaciones']['nuevas'] ?? 0,

            'relaciones_nuevas',

            'relaciÃ³n secciÃ³n-carrera nueva'

        );

        $this->agregarCambio(

            $cambios,

            $resultado['relaciones']['modificadas'] ?? 0,

            'relaciones_modificadas',

            'relaciÃ³n secciÃ³n-carrera modificada'

        );

        $this->agregarCambio(

            $cambios,

            $resultado['grupos']['nuevos'] ?? 0,

            'grupos_nuevos',

            'grupo nuevo'

        );

        $this->agregarCambio(

            $cambios,

            $resultado['horarios']['nuevos'] ?? 0,

            'horarios_nuevos',

            'horario nuevo'

        );

        $this->agregarCambio(

            $cambios,

            $resultado['horarios']['modificados'] ?? 0,

            'horarios_modificados',

            'horario modificado'

        );

        return $cambios;

    }

    private function agregarCambio(

        array &$cambios,

        int $cantidad,

        string $tipo,

        string $descripcion

    ): void {

        if ($cantidad <= 0) {

            return;

        }

        $cambios[] = [

            'tipo' => $tipo,

            'cantidad' => $cantidad,

            'descripcion' => $descripcion,

        ];

    }

    private function detectarCambios(

        array $resultado

    ): bool {

        if ($resultado['es_nueva_programacion']) {

            return true;

        }

        $campos = [

            $resultado['periodos']['nuevos'] ?? 0,

            $resultado['carreras']['nuevas'] ?? 0,

            $resultado['asignaturas']['nuevas'] ?? 0,

            $resultado['profesores']['nuevos'] ?? 0,

            $resultado['profesores']['modificados'] ?? 0,

            $resultado['secciones']['nuevas'] ?? 0,

            $resultado['secciones']['modificadas'] ?? 0,

            $resultado['relaciones']['nuevas'] ?? 0,

            $resultado['relaciones']['eliminadas'] ?? 0,

            $resultado['relaciones']['modificadas'] ?? 0,

            $resultado['grupos']['nuevos'] ?? 0,

            $resultado['grupos']['eliminados'] ?? 0,

            $resultado['horarios']['nuevos'] ?? 0,

            $resultado['horarios']['eliminados'] ?? 0,

            $resultado['horarios']['modificados'] ?? 0,

        ];

        foreach ($campos as $cantidad) {

            if ((int) $cantidad > 0) {

                return true;

            }

        }

        return false;

    }

    /*

    |--------------------------------------------------------------------------

    | ComparaciÃ³n de secciones

    |--------------------------------------------------------------------------

    */

    private function compararSeccionesConVersion(

        array $registros,

        VersionPlanificacion $version

    ): array {

        $nuevas = 0;

        $modificadas = 0;

        $existentes = 0;

        $anteriores = $this->obtenerSeccionesVersion(

            $version

        );

        $procesadas = [];

        foreach ($registros as $registro) {

            $clave = $this->claveSeccion(

                $registro

            );

            if ($clave === null) {

                continue;

            }

            if (isset($procesadas[$clave])) {

                continue;

            }

            $procesadas[$clave] = true;

            if (!isset($anteriores[$clave])) {

                $nuevas++;

                continue;

            }

            $anterior = $anteriores[$clave];

            if (

                $this->seccionTieneCambios(

                    $registro,

                    $anterior

                )

            ) {

                $modificadas++;

            } else {

                $existentes++;

            }

        }

        return [

            'nuevas' => $nuevas,

            'modificadas' => $modificadas,

            'existentes' => $existentes,

        ];

    }

    private function obtenerSeccionesVersion(

        VersionPlanificacion $version

    ): array {

        $resultado = [];

        $secciones = Seccion::query()
            ->where(
                'periodo_academico_id',
                $version->periodo_academico_id
            )
            ->with([
                'carreras',
                'profesores.profesor',
                'actividades.grupos',
                'actividades.horarios' => function ($query) use ($version) {
                    $query->where(
                        'version_planificacion_id',
                        $version->id
                    )->with([
                        'dia',
                        'bloqueHorario',
                        'grupoSeccion',
                    ]);
                },
            ])
            ->get();

        foreach ($secciones as $seccion) {

            $clave = implode('|', [

                (string) $seccion->identificador_externo,

                (string) $seccion->codigo,

                (string) optional(

                    $seccion->periodoAcademico

                )->nombre,

            ]);

            $resultado[$clave] = $this->normalizarSeccionAnterior(

                $seccion

            );

        }

        return $resultado;

    }

    private function normalizarSeccionAnterior(

        Seccion $seccion

    ): array {

        $carreras = $seccion->carreras

            ->pluck('nombre')

            ->map(

                fn ($valor) =>

                    mb_strtoupper(

                        trim((string) $valor)

                    )

            )

            ->sort()

            ->values()

            ->all();

        $profesores = $seccion->profesores

            ->map(

                fn ($relacion) =>

                    $this->claveProfesorModelo(

                        $relacion->profesor

                    )

            )

            ->filter()

            ->sort()

            ->values()

            ->all();

        $horarios = [];

        foreach ($seccion->actividades as $actividad) {

            foreach ($actividad->horarios as $horario) {

                $horarios[] = implode('|', [

                    $actividad->tipo,

                    optional($horario->dia)->abreviatura,

                    optional($horario->bloqueHorario)->numero,

                    optional($horario->grupoSeccion)->codigo,

                ]);

            }

        }

        sort($horarios);

        return [

            'nombre' => trim(

                (string) $seccion->nombre

            ),

            'cupos' => (int) $seccion->cupos,

            'inscritos' => (int) $seccion->inscritos,

            'modalidad' => trim(

                (string) $seccion->modalidad

            ),

            'categoria' => trim(

                (string) $seccion->categoria

            ),

            'tipo_dictacion' => trim(

                (string) $seccion->tipo_dictacion

            ),

            'carreras' => $carreras,

            'profesores' => $profesores,

            'horarios' => $horarios,

        ];

    }

    private function seccionTieneCambios(

        array $registro,

        array $anterior

    ): bool {

        $datosActuales = [

            'nombre' => $this->texto(

                $registro['RAMO_NOMBRE'] ?? null

            ),

            'cupos' => $this->entero(

                $registro['CUPO'] ?? null

            ) ?? 0,

            'inscritos' => $this->entero(

                $registro['INSCRITOS'] ?? null

            ) ?? 0,

            'modalidad' => $this->texto(

                $registro['MODALIDAD'] ?? null

            ),

            'categoria' => $this->texto(

                $registro['CATEGORIA'] ?? null

            ),

            'tipo_dictacion' => $this->texto(

                $registro['TIPO_DICTACION'] ?? null

            ),

        ];

        foreach ($datosActuales as $campo => $valor) {

            if (

                (string) $valor !==

                (string) ($anterior[$campo] ?? '')

            ) {

                return true;

            }

        }

        return false;

    }

    /*

    |--------------------------------------------------------------------------

    | Relaciones secciÃ³n-carrera

    |--------------------------------------------------------------------------

    */

    private function compararRelacionesConVersion(

        array $registros,

        VersionPlanificacion $version

    ): array {

        $actuales = [];

        foreach ($registros as $registro) {

            $idCurso = $this->texto(

                $registro['ID_CURSO'] ?? null

            );

            $seccion = $this->texto(

                $registro['SECC'] ?? null

            );

            $carrera = $this->texto(

                $registro['NOM_CARR'] ?? null

            );

            $semestre = $this->texto(

                $registro['SEMESTRE'] ?? null

            );

            if (

                $idCurso === ''

                || $seccion === ''

                || $carrera === ''

                || $semestre === ''

            ) {

                continue;

            }

            $clave = implode('|', [

                $idCurso,

                $seccion,

                mb_strtoupper($carrera),

                $semestre,

            ]);

            $actuales[$clave] = true;

        }

        $anteriores = [];

        $secciones = Seccion::query()

            ->where(

                'periodo_academico_id',

                $version->periodo_academico_id

            )

            ->with('carreras')

            ->get();

        foreach ($secciones as $seccion) {

            foreach ($seccion->carreras as $carrera) {

                $clave = implode('|', [

                    $seccion->identificador_externo,

                    $seccion->codigo,

                    mb_strtoupper(

                        trim((string) $carrera->nombre)

                    ),

                    optional(

                        $seccion->periodoAcademico

                    )->nombre,

                ]);

                $anteriores[$clave] = true;

            }

        }

        $nuevas = count(

            array_diff_key(

                $actuales,

                $anteriores

            )

        );

        $eliminadas = count(

            array_diff_key(

                $anteriores,

                $actuales

            )

        );

        return [

            'nuevas' => $nuevas,

            'eliminadas' => $eliminadas,

            'modificadas' => 0,

        ];

    }

    /*

    |--------------------------------------------------------------------------

    | Grupos

    |--------------------------------------------------------------------------

    */

    private function compararGruposConVersion(

        array $registros,

        VersionPlanificacion $version

    ): array {

        $actuales = [];

        foreach ($registros as $registro) {

            $idCurso = $this->texto(

                $registro['ID_CURSO'] ?? null

            );

            $seccion = $this->texto(

                $registro['SECC'] ?? null

            );

            $horario = $this->texto(

                $registro['HORARIO'] ?? null

            );

            foreach (

                $this->extraerGrupos($horario)

                as $grupo

            ) {

                $actuales[

                    $idCurso . '|' .

                    $seccion . '|' .

                    $grupo

                ] = true;

            }

        }

        $anteriores = [];

        $secciones = Seccion::query()

            ->where(

                'periodo_academico_id',

                $version->periodo_academico_id

            )

            ->with([

                'actividades.grupos',

            ])

            ->get();

        foreach ($secciones as $seccion) {

            foreach ($seccion->actividades as $actividad) {

                foreach ($actividad->grupos as $grupo) {

                    $anteriores[

                        $seccion->identificador_externo . '|' .

                        $seccion->codigo . '|' .

                        $grupo->codigo

                    ] = true;

                }

            }

        }

        return [

            'nuevos' => count(

                array_diff_key(

                    $actuales,

                    $anteriores

                )

            ),

            'eliminados' => count(

                array_diff_key(

                    $anteriores,

                    $actuales

                )

            ),

        ];

    }

    /*

    |--------------------------------------------------------------------------

    | Horarios

    |--------------------------------------------------------------------------

    */

    private function compararHorariosConVersion(

        array $registros,

        VersionPlanificacion $version

    ): array {

        $actuales = [];

        foreach ($registros as $registro) {

            $idCurso = $this->texto(

                $registro['ID_CURSO'] ?? null

            );

            $seccion = $this->texto(

                $registro['SECC'] ?? null

            );

            $horario = $this->texto(

                $registro['HORARIO'] ?? null

            );

            foreach (

                $this->parsearHorarios($horario)

                as $dato

            ) {

                $actuales[

                    implode('|', [

                        $idCurso,

                        $seccion,

                        $dato['tipo'],

                        $dato['dia'],

                        $dato['bloque'],

                        $dato['grupo'],

                    ])

                ] = true;

            }

        }

        $anteriores = [];

        $secciones = Seccion::query()

            ->where(

                'periodo_academico_id',

                $version->periodo_academico_id

            )

            ->with([

                'actividades.horarios' => function ($query) use (

                    $version

                ) {

                    $query->where(

                        'version_planificacion_id',

                        $version->id

                    )

                        ->with([

                            'dia',

                            'bloqueHorario',

                            'grupoSeccion',

                        ]);

                },

            ])

            ->get();

        foreach ($secciones as $seccion) {

            foreach ($seccion->actividades as $actividad) {

                foreach ($actividad->horarios as $horario) {

                    $anteriores[

                        implode('|', [

                            $seccion->identificador_externo,

                            $seccion->codigo,

                            $actividad->tipo,

                            optional($horario->dia)->abreviatura,

                            optional($horario->bloqueHorario)->numero,

                            optional($horario->grupoSeccion)->codigo,

                        ])

                    ] = true;

                }

            }

        }

        $nuevos = count(

            array_diff_key(

                $actuales,

                $anteriores

            )

        );

        $eliminados = count(

            array_diff_key(

                $anteriores,

                $actuales

            )

        );

        return [

            'nuevos' => $nuevos,

            'eliminados' => $eliminados,

            'modificados' => 0,

        ];

    }

    private function contarHorariosExcel(

        array $registros

    ): int {

        $horarios = [];

        foreach ($registros as $registro) {

            $idCurso = $this->texto(

                $registro['ID_CURSO'] ?? null

            );

            $seccion = $this->texto(

                $registro['SECC'] ?? null

            );

            $horario = $this->texto(

                $registro['HORARIO'] ?? null

            );

            foreach (

                $this->parsearHorarios($horario)

                as $dato

            ) {

                $horarios[

                    implode('|', [

                        $idCurso,

                        $seccion,

                        $dato['tipo'],

                        $dato['dia'],

                        $dato['bloque'],

                        $dato['grupo'],

                    ])

                ] = true;

            }

        }

        return count($horarios);

    }

    /*

    |--------------------------------------------------------------------------

    | DiagnÃ³stico estructural

    |--------------------------------------------------------------------------

    */

    private function diagnosticarEstructura(

        array $registros

    ): array {

        $idCursos = [];

        $codRamos = [];

        $idCursoSeccion = [];

        $idCursoCarrera = [];

        $ramoSeccionCarrera = [];

        $relacionesSeccionCarrera = [];

        $grupos = [];

        $datosCursos = [];

        foreach ($registros as $registro) {

            $idCurso = trim(

                (string) ($registro['ID_CURSO'] ?? '')

            );

            $codRamo = trim(

                (string) ($registro['COD_RAMO'] ?? '')

            );

            $secc = trim(

                (string) ($registro['SECC'] ?? '')

            );

            $carrera = trim(

                (string) ($registro['NOM_CARR'] ?? '')

            );

            $semestre = trim(

                (string) ($registro['SEMESTRE'] ?? '')

            );

            $profesorRut = trim(

                (string) ($registro['PROF_RESP'] ?? '')

            );

            $profesorNombre = trim(

                (string) ($registro['NOM_PROF_RESP'] ?? '')

            );

            $sede = trim(

                (string) ($registro['SEDE'] ?? '')

            );

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

                $claveCarrera =

                    mb_strtoupper($carrera);

                $datosCursos[$idCurso]['carreras'][

                    $claveCarrera

                ] = $carrera;

                if (

                    !isset(

                        $datosCursos[$idCurso][

                            'carrera_profesores'

                        ][$claveCarrera]

                    )

                ) {

                    $datosCursos[$idCurso][

                        'carrera_profesores'

                    ][$claveCarrera] = [

                        'carrera' => $carrera,

                        'profesores' => [],

                    ];

                }

                $profesor = $this->datosProfesor(

                    $profesorRut,

                    $profesorNombre

                );

                if ($profesor !== null) {

                    $claveProfesor =

                        $this->claveProfesorDesdeDatos(

                            $profesorRut,

                            $profesorNombre

                        );

                    $datosCursos[$idCurso][

                        'profesores'

                    ][$claveProfesor] = $profesor;

                    $datosCursos[$idCurso][

                        'carrera_profesores'

                    ][$claveCarrera]['profesores'][

                        $claveProfesor

                    ] = $profesor;

                }

            }

            $profesor = $this->datosProfesor(

                $profesorRut,

                $profesorNombre

            );

            if ($profesor !== null) {

                $claveProfesor =

                    $this->claveProfesorDesdeDatos(

                        $profesorRut,

                        $profesorNombre

                    );

                $datosCursos[$idCurso][

                    'profesores'

                ][$claveProfesor] = $profesor;

            }

            if ($secc !== '') {

                $clave = implode('|', [

                    $idCurso,

                    $secc,

                    $semestre,

                ]);

                $idCursoSeccion[$clave] = true;

            }

            if ($carrera !== '') {

                $clave = implode('|', [

                    $idCurso,

                    mb_strtoupper($carrera),

                    $semestre,

                ]);

                $idCursoCarrera[$clave] = true;

            }

            if (

                $secc !== ''

                && $carrera !== ''

            ) {

                $claveRelacion = implode('|', [

                    $idCurso,

                    $secc,

                    mb_strtoupper($carrera),

                    $semestre,

                ]);

                $relacionesSeccionCarrera[

                    $claveRelacion

                ] = true;

            }

            if (

                $codRamo !== ''

                && $secc !== ''

                && $carrera !== ''

            ) {

                $clave = implode('|', [

                    $codRamo,

                    $secc,

                    mb_strtoupper($carrera),

                    $semestre,

                ]);

                $ramoSeccionCarrera[$clave] = true;

            }

            $horario = trim(

                (string) ($registro['HORARIO'] ?? '')

            );

            foreach (

                $this->extraerGrupos($horario)

                as $grupo

            ) {

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

            $carreras =

                array_values(

                    $curso['carreras']

                );

            $profesores =

                array_values(

                    $curso['profesores']

                );

            if (count($carreras) <= 1) {

                continue;

            }

            $carreraProfesores = [];

            foreach (

                $curso['carrera_profesores']

                as $relacion

            ) {

                $carreraProfesores[] = [

                    'carrera' =>

                        $relacion['carrera'],

                    'profesores' =>

                        array_values(

                            $relacion['profesores']

                        ),

                ];

            }

            $cursosCompartidos[] = [

                'id_curso' =>

                    $curso['id_curso'],

                'cod_ramo' =>

                    $curso['cod_ramo'],

                'secc' =>

                    $curso['secc'],

                'semestre' =>

                    $curso['semestre'],

                'sede' =>

                    $curso['sede'],

                'cantidad_carreras' =>

                    count($carreras),

                'cantidad_profesores' =>

                    count($profesores),

                'carreras' =>

                    $carreras,

                'profesores' =>

                    $profesores,

                'carrera_profesores' =>

                    $carreraProfesores,

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

            if (

                $curso['cantidad_profesores'] <= 1

            ) {

                $distribucionProfesores[

                    'un_profesor'

                ]++;

            } else {

                $distribucionProfesores[

                    'varios_profesores'

                ]++;

            }

        }

        return [

            'filas_originales' =>

                count($registros),

            'id_cursos_unicos' =>

                count($idCursos),

            'cod_ramos_unicos' =>

                count($codRamos),

            'id_curso_seccion_unicos' =>

                count($idCursoSeccion),

            'id_curso_carrera_unicos' =>

                count($idCursoCarrera),

            'ramo_seccion_carrera_unicos' =>

                count($ramoSeccionCarrera),

            'relaciones_seccion_carrera' =>

                count($relacionesSeccionCarrera),

            'grupos_unicos' =>

                count($grupos),

            'cursos_compartidos' =>

                count($cursosCompartidos),

            'cursos_compartidos_un_profesor' =>

                $distribucionProfesores[

                    'un_profesor'

                ],

            'cursos_compartidos_varios_profesores' =>

                $distribucionProfesores[

                    'varios_profesores'

                ],

            'ejemplos_cursos_compartidos' =>

                array_slice(

                    $cursosCompartidos,

                    0,

                    10

                ),

        ];

    }

    /*

    |--------------------------------------------------------------------------

    | Comparaciones generales

    |--------------------------------------------------------------------------

    */

    private function compararPeriodos(

        array $registros

    ): array {

        $nuevos = 0;

        $existentes = 0;

        foreach ($registros as $registro) {

            $existe =

                PeriodoAcademico::where(

                    'nombre',

                    trim(

                        (string)

                        $registro['SEMESTRE']

                    )

                )->exists();

            if ($existe) {

                $existentes++;

            } else {

                $nuevos++;

            }

        }

        return compact(

            'nuevos',

            'existentes'

        );

    }

    private function compararCarreras(

        array $registros

    ): array {

        $nuevas = 0;

        $existentes = 0;

        foreach ($registros as $registro) {

            $nombre = trim(

                (string)

                $registro['NOM_CARR']

            );

            $existe =

                Carrera::where(

                    'nombre',

                    $nombre

                )->exists();

            if ($existe) {

                $existentes++;

            } else {

                $nuevas++;

            }

        }

        return compact(

            'nuevas',

            'existentes'

        );

    }

    private function compararAsignaturas(

        array $registros

    ): array {

        $nuevas = 0;

        $existentes = 0;

        foreach ($registros as $registro) {

            $existe =

                Asignatura::where(

                    'codigo',

                    trim(

                        (string)

                        $registro['COD_RAMO']

                    )

                )->exists();

            if ($existe) {

                $existentes++;

            } else {

                $nuevas++;

            }

        }

        return compact(

            'nuevas',

            'existentes'

        );

    }

    private function compararProfesores(array $registros): array
{
    $nuevos = 0;
    $modificados = 0;
    $existentes = 0;

    $profesoresExcel = [];

    foreach ($registros as $registro) {
        $clave = $this->claveProfesor($registro);

        if ($clave === null) {
            continue;
        }

        
    if (!isset($profesoresExcel[$clave])) {
    $profesoresExcel[$clave] = [];
}

$profesoresExcel[$clave][] = [
    'rut' => $registro['PROF_RESP'] ?? null,
    'nombre_completo' => trim(
        (string) ($registro['NOM_PROF_RESP'] ?? '')
    ),
    'correo_institucional' => trim(
        (string) ($registro['CORREO_INST'] ?? '')
    ),
];

    }

    foreach ($profesoresExcel as $variantesProfesor) {
    $profesorAnterior = null;

    $primeraVariante = $variantesProfesor[0] ?? null;

    if (!$primeraVariante) {
        continue;
    }

    if (!empty($primeraVariante['rut'])) {
        $profesorAnterior = Profesor::where(
            'rut',
            $primeraVariante['rut']
        )->first();
    }

    if (
        !$profesorAnterior
        && !empty($primeraVariante['correo_institucional'])
    ) {
        $profesorAnterior = Profesor::whereRaw(
            'LOWER(correo_institucional) = ?',
            [
                mb_strtolower(
                    $primeraVariante['correo_institucional']
                ),
            ]
        )->first();
    }

    if (!$profesorAnterior) {
        $nuevos++;
        continue;
    }

    $nombreAnterior = trim(
        $profesorAnterior->nombre
        . ' '
        . $profesorAnterior->apellido
    );

    $correoAnterior = trim(
        (string) $profesorAnterior->correo_institucional
    );

    $estaModificado = false;

    foreach ($variantesProfesor as $profesorExcel) {
        $nombreActual = trim(
            (string) $profesorExcel['nombre_completo']
        );

        $correoActual = trim(
            (string) $profesorExcel['correo_institucional']
        );

        if (
            $this->normalizarTexto($nombreAnterior)
                !== $this->normalizarTexto($nombreActual)
            || $this->normalizarTexto($correoAnterior)
                !== $this->normalizarTexto($correoActual)
        ) {
            $estaModificado = true;
            break;
        }
    }

    if ($estaModificado) {
        $modificados++;
    } else {
        $existentes++;
    }
}

    return [
        'nuevos' => $nuevos,
        'modificados' => $modificados,
        'existentes' => $existentes,
    ];
}

private function normalizarTexto($valor): string
{
    return mb_strtolower(
        trim((string) ($valor ?? ''))
    );
}


    /*

    |--------------------------------------------------------------------------

    | Utilidades

    |--------------------------------------------------------------------------

    */

    private function obtenerPeriodoNombre(

        array $registros

    ): ?string {

        foreach ($registros as $registro) {

            $periodo = $this->texto(

                $registro['SEMESTRE'] ?? null

            );

            if ($periodo !== '') {

                return $periodo;

            }

        }

        return null;

    }

    private function normalizarRegistros(

        array $registros

    ): array {

        return array_map(

            function ($registro) {

                $normalizado = [];

                foreach (

                    $registro as $clave => $valor

                ) {

                    $claveNormalizada =

                        mb_strtoupper(

                            trim(

                                (string) $clave

                            )

                        );

                    $claveNormalizada =

                        str_replace(

                            ['Ãƒ', 'Ã‚'],

                            ['Ã‘', 'N'],

                            $claveNormalizada

                        );

                    $normalizado[

                        $claveNormalizada

                    ] = $valor;

                }

                return $normalizado;

            },

            $registros

        );

    }

    private function validarRegistroMinimo(

        array $registro

    ): void {

        if (

            empty($registro['COD_RAMO'])

        ) {

            throw new \Exception(

                'El registro no contiene COD_RAMO.'

            );

        }

        if (

            empty($registro['RAMO_NOMBRE'])

        ) {

            throw new \Exception(

                'El registro no contiene RAMO_NOMBRE.'

            );

        }

        if (

            empty($registro['SEMESTRE'])

        ) {

            throw new \Exception(

                'El registro no contiene SEMESTRE.'

            );

        }

        if (

            empty($registro['NOM_CARR'])

        ) {

            throw new \Exception(

                'El registro no contiene NOM_CARR.'

            );

        }

    }

    private function clavePeriodo(

        array $registro

    ): string {

        return trim(

            (string)

            $registro['SEMESTRE']

        );

    }

    private function claveCarrera(

        array $registro

    ): string {

        return 'NOMBRE:' .

            mb_strtoupper(

                trim(

                    (string)

                    $registro['NOM_CARR']

                )

            );

    }

    private function claveAsignatura(

        array $registro

    ): string {

        return trim(

            (string)

            $registro['COD_RAMO']

        );

    }

    private function claveProfesor(

        array $registro

    ): ?string {

        if (

            !empty($registro['PROF_RESP'])

        ) {

            return 'RUT:' .

                trim(

                    (string)

                    $registro['PROF_RESP']

                );

        }

        if (

            !empty($registro['CORREO_INST'])

        ) {

            return 'CORREO:' .

                mb_strtolower(

                    trim(

                        (string)

                        $registro['CORREO_INST']

                    )

                );

        }

        if (

            !empty($registro['NOM_PROF_RESP'])

        ) {

            return 'NOMBRE:' .

                mb_strtoupper(

                    trim(

                        (string)

                        $registro['NOM_PROF_RESP']

                    )

                );

        }

        return null;

    }

    private function claveSeccion(

        array $registro

    ): ?string {

        if (

            empty($registro['ID_CURSO'])

            || empty($registro['SECC'])

            || empty($registro['SEMESTRE'])

        ) {

            return null;

        }

        return implode('|', [

            trim(

                (string)

                $registro['ID_CURSO']

            ),

            trim(

                (string)

                $registro['SECC']

            ),

            trim(

                (string)

                $registro['SEMESTRE']

            ),

        ]);

    }

    private function claveProfesorModelo(

        $profesor

    ): ?string {

        if (!$profesor) {

            return null;

        }

        if (!empty($profesor->rut)) {

            return 'RUT:' .

                trim(

                    (string)

                    $profesor->rut

                );

        }

        if (

            !empty(

                $profesor->correo_institucional

            )

        ) {

            return 'CORREO:' .

                mb_strtolower(

                    trim(

                        (string)

                        $profesor

                            ->correo_institucional

                    )

                );

        }

        $nombre = trim(

            implode(

                ' ',

                array_filter([

                    $profesor->nombre,

                    $profesor->apellido,

                ])

            )

        );

        if ($nombre !== '') {

            return 'NOMBRE:' .

                mb_strtoupper($nombre);

        }

        return null;

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

        return 'NOMBRE:' .

            mb_strtoupper($nombre);

    }

    private function parsearHorarios(string $horario): array
{
    $resultado = [];

    if (trim($horario) === '') {
        return [];
    }

    preg_match_all(
        '/(?:^|\s)(ca|la|ta|ce)\s*:\s*(.*?)(?=\s+(?:ca|la|ta|ce)\s*:|$)/is',
        trim($horario),
        $actividades,
        PREG_SET_ORDER
    );

    foreach ($actividades as $actividadEncontrada) {
        $codigoTipo = mb_strtolower(
            trim($actividadEncontrada[1])
        );

        $contenido = trim(
            $actividadEncontrada[2]
        );

        $tipo = match ($codigoTipo) {
            'ca' => 'Cátedra',
            'la' => 'Laboratorio',
            'ta' => 'Taller',
            'ce' => 'Centro de práctica',
            default => 'Otro',
        };

        preg_match_all(
            '/\b(LU|MA|MI|JU|VI|SA)\s*\.\s*(\d+)(?:\s*\/\s*G\s*:\s*(\d+))?(?:\s*\(([^)]+)\))?/i',
            $contenido,
            $coincidencias,
            PREG_SET_ORDER
        );

        foreach ($coincidencias as $coincidencia) {
            $resultado[] = [
                'dia' => mb_strtoupper(
                    $coincidencia[1]
                ),
                'bloque' => (int) $coincidencia[2],
                'grupo' =>
                    isset($coincidencia[3])
                    && $coincidencia[3] !== ''
                        ? 'G:' . $coincidencia[3]
                        : null,
                'sala' =>
                    isset($coincidencia[4])
                    && $coincidencia[4] !== ''
                        ? trim($coincidencia[4])
                        : null,
                'tipo' => $tipo,
            ];
        }
    }

    return $resultado;
}

    private function texto(

        $valor

    ): string {

        if ($valor === null) {

            return '';

        }

        return trim(

            (string) $valor

        );

    }

    private function entero(

        $valor

    ): ?int {

        if (

            $valor === null

            || $valor === ''

        ) {

            return null;

        }

        return (int) $valor;

    }

}



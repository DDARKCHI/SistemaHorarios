<?php

namespace App\Services;

use App\Models\ActividadSeccion;
use App\Models\Asignatura;
use App\Models\BloqueHorario;
use App\Models\Carrera;
use App\Models\Dia;
use App\Models\FacultadInstituto;
use App\Models\GrupoSeccion;
use App\Models\Horario;
use App\Models\PeriodoAcademico;
use App\Models\Profesor;
use App\Models\Sede;
use App\Models\Seccion;
use App\Models\SeccionProfesor;
use App\Models\Universidad;
use App\Models\VersionPlanificacion;
use Illuminate\Support\Facades\DB;

class ProgramacionCargaService
{
    public function cargar(
        array $registros,
        int $usuarioId,
        string $nombreArchivo
    ): VersionPlanificacion {
        return DB::transaction(function () use (
            $registros,
            $usuarioId,
            $nombreArchivo
        ) {
            $universidad = Universidad::first();

            if (!$universidad) {
                throw new \Exception(
                    'No existe una universidad configurada.'
                );
            }

            $periodo = $this->obtenerPeriodo(
                $registros,
                $universidad->id
            );

            $version = VersionPlanificacion::create([
                'periodo_academico_id' => $periodo->id,
                'nombre' => 'Programación importada - ' . $nombreArchivo,
                'version' => $this->siguienteVersion($periodo->id),
                'descripcion' => 'Carga de programación desde archivo Excel.',
                'estado' => 'Borrador',
                'usuario_id' => $usuarioId,
                'es_actual' => true,
            ]);

            VersionPlanificacion::where(
                'periodo_academico_id',
                $periodo->id
            )
                ->where('id', '!=', $version->id)
                ->update([
                    'es_actual' => false,
                ]);

            $cache = [
                'carreras' => [],
                'asignaturas' => [],
                'profesores' => [],
                'secciones' => [],
                'actividades' => [],
                'grupos' => [],
                'dias' => [],
                'bloques' => [],
                'sedes' => [],
                'facultades' => [],
            ];

            foreach ($registros as $registro) {
                $this->procesarRegistro(
                    $registro,
                    $universidad,
                    $periodo,
                    $version,
                    $cache
                );
            }

            return $version;
        });
    }

    private function procesarRegistro(
        array $registro,
        Universidad $universidad,
        PeriodoAcademico $periodo,
        VersionPlanificacion $version,
        array &$cache
    ): void {
        $registro = array_change_key_case(
            $registro,
            CASE_UPPER
        );

        $codRamo = $this->texto(
            $registro['COD_RAMO'] ?? null
        );

        $nombreRamo = $this->texto(
            $registro['RAMO_NOMBRE'] ?? null
        );

        if ($codRamo === '' || $nombreRamo === '') {
            return;
        }

        $asignatura = $this->obtenerAsignatura(
            $registro,
            $universidad->id,
            $cache
        );

        $carrera = $this->obtenerCarrera(
            $registro,
            $universidad->id,
            $cache
        );

        $profesor = $this->obtenerProfesor(
            $registro,
            $universidad->id,
            $cache
        );

        $seccion = $this->obtenerSeccion(
            $registro,
            $asignatura,
            $periodo,
            $cache
        );

        if ($carrera) {
            $seccion->carreras()->syncWithoutDetaching([
                $carrera->id,
            ]);
        }

        if ($profesor) {
            SeccionProfesor::firstOrCreate(
                [
                    'seccion_id' => $seccion->id,
                    'profesor_id' => $profesor->id,
                ],
                [
                    'tipo_participacion' => $this->texto(
                        $registro['TIPO_PROFESOR'] ?? null
                    ) ?: 'Profesor responsable',
                    'es_principal' => true,
                    'estado' => true,
                ]
            );
        }

        $this->procesarHorarios(
            $registro,
            $seccion,
            $version,
            $cache
        );
    }

    private function obtenerPeriodo(
        array $registros,
        int $universidadId
    ): PeriodoAcademico {
        $semestre = '';

        foreach ($registros as $registro) {
            $registro = array_change_key_case(
                $registro,
                CASE_UPPER
            );

            $semestre = $this->texto(
                $registro['SEMESTRE'] ?? null
            );

            if ($semestre !== '') {
                break;
            }
        }

        if ($semestre === '') {
            throw new \Exception(
                'No se pudo determinar el período académico.'
            );
        }

        $anio = null;
        $numeroSemestre = null;

        if (preg_match(
            '/^(\d{4})[-\/](\d+)$/',
            $semestre,
            $coincidencia
        )) {
            $anio = (int) $coincidencia[1];
            $numeroSemestre = (int) $coincidencia[2];
        }

        return PeriodoAcademico::firstOrCreate(
            [
                'universidad_id' => $universidadId,
                'nombre' => $semestre,
            ],
            [
                'anio' => $anio,
                'numero_semestre' => $numeroSemestre,
                'estado' => true,
            ]
        );
    }

    private function obtenerCarrera(
        array $registro,
        int $universidadId,
        array &$cache
    ): ?Carrera {
        $nombre = $this->texto(
            $registro['NOM_CARR'] ?? null
        );

        if ($nombre === '') {
            return null;
        }

        $clave = mb_strtoupper($nombre);

        if (isset($cache['carreras'][$clave])) {
            return $cache['carreras'][$clave];
        }

        $codigo = $this->texto(
            $registro['COD_CARR'] ?? null
        );

        $codSede = $this->texto(
            $registro['COD_SEDE'] ?? null
        );

        $sedeNombre = $this->texto(
            $registro['SEDE'] ?? null
        );

        $codFacultad = $this->texto(
            $registro['COD_FACULTAD'] ?? null
        );

        $facultadNombre = $this->texto(
            $registro['FACULTAD'] ?? null
        );

        $sede = $this->obtenerSede(
            $universidadId,
            $codSede,
            $sedeNombre,
            $cache
        );

        $facultad = $this->obtenerFacultad(
            $universidadId,
            $codFacultad,
            $facultadNombre,
            $cache
        );

        $carrera = Carrera::firstOrCreate(
            [
                'universidad_id' => $universidadId,
                'nombre' => $nombre,
            ],
            [
                'facultad_instituto_id' => $facultad?->id,
                'sede_id' => $sede?->id,
                'codigo' => $codigo ?: null,
                'regimen' => $this->texto(
                    $registro['REGIMEN'] ?? null
                ) ?: null,
                'tipo_estudio' => $this->texto(
                    $registro['TIPO_ESTUDIO'] ?? null
                ) ?: null,
                'estado' => true,
            ]
        );

        $cache['carreras'][$clave] = $carrera;

        return $carrera;
    }

    private function obtenerSede(
        int $universidadId,
        string $codigo,
        string $nombre,
        array &$cache
    ): ?Sede {
        if ($codigo === '' && $nombre === '') {
            return null;
        }

        $clave = $codigo !== ''
            ? 'COD:' . $codigo
            : 'NOMBRE:' . mb_strtoupper($nombre);

        if (isset($cache['sedes'][$clave])) {
            return $cache['sedes'][$clave];
        }

        $sede = Sede::query()
            ->where('universidad_id', $universidadId)
            ->when(
                $codigo !== '',
                fn ($query) => $query->where('codigo', $codigo),
                fn ($query) => $query->where('nombre', $nombre)
            )
            ->first();

        if (!$sede) {
            $sede = Sede::create([
                'universidad_id' => $universidadId,
                'codigo' => $codigo ?: null,
                'nombre' => $nombre ?: $codigo,
                'estado' => true,
            ]);
        }

        $cache['sedes'][$clave] = $sede;

        return $sede;
    }

    private function obtenerFacultad(
        int $universidadId,
        string $codigo,
        string $nombre,
        array &$cache
    ): ?FacultadInstituto {
        if ($codigo === '' && $nombre === '') {
            return null;
        }

        $clave = $codigo !== ''
            ? 'COD:' . $codigo
            : 'NOMBRE:' . mb_strtoupper($nombre);

        if (isset($cache['facultades'][$clave])) {
            return $cache['facultades'][$clave];
        }

        $facultad = FacultadInstituto::query()
            ->where('universidad_id', $universidadId)
            ->when(
                $codigo !== '',
                fn ($query) => $query->where('codigo', $codigo),
                fn ($query) => $query->where('nombre', $nombre)
            )
            ->first();

        if (!$facultad) {
            $facultad = FacultadInstituto::create([
                'universidad_id' => $universidadId,
                'codigo' => $codigo ?: null,
                'nombre' => $nombre ?: $codigo,
                'tipo' => 'Instituto',
                'estado' => true,
            ]);
        }

        $cache['facultades'][$clave] = $facultad;

        return $facultad;
    }

    private function obtenerAsignatura(
        array $registro,
        int $universidadId,
        array &$cache
    ): Asignatura {
        $codigo = $this->texto(
            $registro['COD_RAMO'] ?? null
        );

        if (isset($cache['asignaturas'][$codigo])) {
            return $cache['asignaturas'][$codigo];
        }

        $asignatura = Asignatura::firstOrCreate(
            [
                'universidad_id' => $universidadId,
                'codigo' => $codigo,
            ],
            [
                'nombre' => $this->texto(
                    $registro['RAMO_NOMBRE'] ?? null
                ),
                'horas_catedra' => $this->numero(
                    $registro['MOD_CATEDRA'] ?? null
                ),
                'horas_laboratorio' => $this->numero(
                    $registro['MOD_LABORATORIO'] ?? null
                ),
                'horas_ayudantia' => $this->numero(
                    $registro['MOD_AYUDANTIA'] ?? null
                ),
                'horas_taller' => $this->numero(
                    $registro['MOD_TALLER'] ?? null
                ),
                'horas_clinica' => $this->numero(
                    $registro['MOD_CLINICA'] ?? null
                ),
                'horas_practica' => $this->numero(
                    $registro['MOD_CENTRO_PRAC'] ?? null
                ),
                'horas_otros' => $this->numero(
                    $registro['MOD_OTROS'] ?? null
                ),
                'modalidad' => $this->texto(
                    $registro['MODALIDAD'] ?? null
                ) ?: null,
                'categoria' => $this->texto(
                    $registro['CATEGORIA'] ?? null
                ) ?: null,
                'tipo_dictacion' => $this->texto(
                    $registro['TIPO_DICTACION'] ?? null
                ) ?: null,
                'es_libre_eleccion' => $this->booleano(
                    $registro['ES_LIBRE_ELECCION'] ?? null
                ),
                'ramo_terminal' => $this->booleano(
                    $registro['RAMO_TERMINAL'] ?? null
                ),
                'estado' => true,
            ]
        );

        $cache['asignaturas'][$codigo] = $asignatura;

        return $asignatura;
    }

    private function obtenerProfesor(
        array $registro,
        int $universidadId,
        array &$cache
    ): ?Profesor {
        $rut = $this->texto(
            $registro['PROF_RESP'] ?? null
        );

        $correo = $this->texto(
            $registro['CORREO_INST'] ?? null
        );

        if ($rut === '' && $correo === '') {
            return null;
        }

        $clave = $rut !== ''
            ? 'RUT:' . $rut
            : 'CORREO:' . mb_strtolower($correo);

        if (isset($cache['profesores'][$clave])) {
            return $cache['profesores'][$clave];
        }

        $nombreCompleto = $this->texto(
            $registro['NOM_PROF_RESP'] ?? null
        );

        [$nombre, $apellido] = $this->separarNombre(
            $nombreCompleto
        );

        $consulta = Profesor::query()
            ->where('universidad_id', $universidadId);

        if ($rut !== '') {
            $consulta->where('rut', $rut);
        } else {
            $consulta->where(
                'correo_institucional',
                $correo
            );
        }

        $profesor = $consulta->first();

        if (!$profesor) {
            $profesor = Profesor::create([
                'universidad_id' => $universidadId,
                'rut' => $rut ?: null,
                'nombre' => $nombre,
                'apellido' => $apellido,
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
                'estado' => true,
            ]);
        }

        $cache['profesores'][$clave] = $profesor;

        return $profesor;
    }

    private function obtenerSeccion(
        array $registro,
        Asignatura $asignatura,
        PeriodoAcademico $periodo,
        array &$cache
    ): Seccion {
        $idCurso = $this->texto(
            $registro['ID_CURSO'] ?? null
        );

        $codigo = $this->texto(
            $registro['SECC'] ?? null
        );

        $clave = implode('|', [
            $idCurso,
            $codigo,
            $periodo->id,
        ]);

        if (isset($cache['secciones'][$clave])) {
            return $cache['secciones'][$clave];
        }

        $seccion = Seccion::firstOrCreate(
            [
                'identificador_externo' => $idCurso,
                'asignatura_id' => $asignatura->id,
                'periodo_academico_id' => $periodo->id,
                'codigo' => $codigo,
            ],
            [
                'nombre' => $this->texto(
                    $registro['RAMO_NOMBRE'] ?? null
                ) ?: null,
                'cupos' => $this->entero(
                    $registro['CUPO'] ?? null
                ),
                'inscritos' => $this->entero(
                    $registro['INSCRITOS'] ?? null
                ) ?? 0,
                'modalidad' => $this->texto(
                    $registro['MODALIDAD'] ?? null
                ) ?: null,
                'categoria' => $this->texto(
                    $registro['CATEGORIA'] ?? null
                ) ?: null,
                'tipo_dictacion' => $this->texto(
                    $registro['TIPO_DICTACION'] ?? null
                ) ?: null,
                'estado' => $this->texto(
                    $registro['ESTADO_CURSO'] ?? null
                ) ?: 'Pendiente',
                'estado_activo' => true,
            ]
        );

        $cache['secciones'][$clave] = $seccion;

        return $seccion;
    }

    private function procesarHorarios(
        array $registro,
        Seccion $seccion,
        VersionPlanificacion $version,
        array &$cache
    ): void {
        $horario = $this->texto(
            $registro['HORARIO'] ?? null
        );

        if ($horario === '') {
            return;
        }

        foreach ($this->parsearHorarios($horario) as $dato) {
            $actividad = $this->obtenerActividad(
                $seccion,
                $dato['tipo'],
                $registro,
                $cache
            );

            $grupo = null;

            if ($dato['grupo'] !== null) {
                $grupo = $this->obtenerGrupo(
                    $actividad,
                    $dato['grupo'],
                    $registro,
                    $cache
                );
            }

            $dia = $this->obtenerDia(
                $dato['dia'],
                $cache
            );

            $bloque = $this->obtenerBloque(
                $dato['bloque'],
                $cache
            );

            if (!$dia || !$bloque) {
                continue;
            }

            Horario::firstOrCreate(
                [
                    'actividad_seccion_id' => $actividad->id,
                    'grupo_seccion_id' => $grupo?->id,
                    'version_planificacion_id' => $version->id,
                    'dia_id' => $dia->id,
                    'bloque_horario_id' => $bloque->id,
                ],
                [
                    'observaciones' => $dato['sala'] !== null
                        ? 'Recurso indicado en archivo: ' . $dato['sala']
                        : null,
                    'estado' => true,
                ]
            );
        }
    }

    private function parsearHorarios(
        string $horario
    ): array {
        $resultado = [];

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
                '/\b(LU|MA|MI|JU|VI|SA)\.(\d+)(?:\s*\/\s*G\s*:\s*(\d+))?\s*(?:\(([^)]+)\))?/i',
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
                    'grupo' => isset($coincidencia[3])
                        && $coincidencia[3] !== ''
                        ? 'G:' . $coincidencia[3]
                        : null,
                    'sala' => isset($coincidencia[4])
                        && $coincidencia[4] !== ''
                        ? trim($coincidencia[4])
                        : null,
                    'tipo' => $tipo,
                ];
            }
        }

        return $resultado;
    }

    private function obtenerActividad(
        Seccion $seccion,
        string $tipo,
        array $registro,
        array &$cache
    ): ActividadSeccion {
        $clave = $seccion->id . '|' . $tipo;

        if (isset($cache['actividades'][$clave])) {
            return $cache['actividades'][$clave];
        }

        $actividad = ActividadSeccion::firstOrCreate(
            [
                'seccion_id' => $seccion->id,
                'tipo' => $tipo,
            ],
            [
                'cantidad_horas' => $this->horasActividad(
                    $registro,
                    $tipo
                ),
                'cantidad_estudiantes' => $this->entero(
                    $registro['INSCRITOS'] ?? null
                ),
                'modalidad' => $this->texto(
                    $registro['MODALIDAD'] ?? null
                ) ?: null,
                'estado' => true,
            ]
        );

        $cache['actividades'][$clave] = $actividad;

        return $actividad;
    }

    private function obtenerGrupo(
        ActividadSeccion $actividad,
        string $codigo,
        array $registro,
        array &$cache
    ): GrupoSeccion {
        $clave = $actividad->id . '|' . $codigo;

        if (isset($cache['grupos'][$clave])) {
            return $cache['grupos'][$clave];
        }

        $numero = null;

        if (preg_match(
            '/(\d+)/',
            $codigo,
            $coincidencia
        )) {
            $numero = (int) $coincidencia[1];
        }

        $grupo = GrupoSeccion::firstOrCreate(
            [
                'actividad_seccion_id' => $actividad->id,
                'codigo' => $codigo,
            ],
            [
                'numero' => $numero,
                'cupos' => $this->entero(
                    $registro['CUPO'] ?? null
                ),
                'inscritos' => $this->entero(
                    $registro['INSCRITOS'] ?? null
                ) ?? 0,
                'estado' => 'Activo',
                'estado_activo' => true,
            ]
        );

        $cache['grupos'][$clave] = $grupo;

        return $grupo;
    }

    private function obtenerDia(
        string $abreviatura,
        array &$cache
    ): ?Dia {
        $abreviatura = mb_strtoupper(
            trim($abreviatura)
        );

        if ($abreviatura === '') {
            return null;
        }

        if (isset($cache['dias'][$abreviatura])) {
            return $cache['dias'][$abreviatura];
        }

        $dia = Dia::where(
            'abreviatura',
            $abreviatura
        )->first();

        if (!$dia) {
            $mapa = [
                'LU' => 'Lunes',
                'MA' => 'Martes',
                'MI' => 'Miércoles',
                'JU' => 'Jueves',
                'VI' => 'Viernes',
                'SA' => 'Sábado',
            ];

            if (isset($mapa[$abreviatura])) {
                $dia = Dia::where(
                    'nombre',
                    $mapa[$abreviatura]
                )->first();
            }
        }

        $cache['dias'][$abreviatura] = $dia;

        return $dia;
    }

    private function obtenerBloque(
        int $numero,
        array &$cache
    ): ?BloqueHorario {
        if ($numero <= 0) {
            return null;
        }

        if (isset($cache['bloques'][$numero])) {
            return $cache['bloques'][$numero];
        }

        $bloque = BloqueHorario::where(
            'numero',
            $numero
        )->first();

        $cache['bloques'][$numero] = $bloque;

        return $bloque;
    }

    private function horasActividad(
        array $registro,
        string $tipo
    ): float {
        return match ($tipo) {
            'Laboratorio' => $this->numero(
                $registro['MOD_LABORATORIO'] ?? null
            ),
            'Taller' => $this->numero(
                $registro['MOD_TALLER'] ?? null
            ),
            'Centro de práctica' => $this->numero(
                $registro['MOD_CENTRO_PRAC'] ?? null
            ),
            'Cátedra' => $this->numero(
                $registro['MOD_CATEDRA'] ?? null
            ),
            default => $this->numero(
                $registro['MOD_OTROS'] ?? null
            ),
        };
    }

    private function siguienteVersion(
        int $periodoId
    ): float {
        $ultima = VersionPlanificacion::where(
            'periodo_academico_id',
            $periodoId
        )
            ->orderByDesc('version')
            ->value('version');

        return $ultima
            ? ((float) $ultima + 1)
            : 1.0;
    }

    private function separarNombre(
        string $nombreCompleto
    ): array {
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

    private function numero($valor): float
    {
        if ($valor === null || $valor === '') {
            return 0;
        }

        return (float) str_replace(
            ',',
            '.',
            trim((string) $valor)
        );
    }

    private function entero($valor): ?int
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        return (int) $valor;
    }

    private function booleano($valor): bool
    {
        $valor = mb_strtolower(
            $this->texto($valor)
        );

        return in_array(
            $valor,
            [
                '1',
                'si',
                'sí',
                'true',
                'verdadero',
                'yes',
            ],
            true
        );
    }
}
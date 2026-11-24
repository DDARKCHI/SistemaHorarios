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
use App\Models\HorarioProfesor;
use App\Models\PeriodoAcademico;
use App\Models\Profesor;
use App\Models\ProfesorVersion;
use App\Models\Sede;
use App\Models\Seccion;
use App\Models\SeccionProfesor;
use App\Models\Universidad;
use App\Models\VersionPlanificacion;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProgramacionCargaService
{
    public function cargar(
        array $registros,
        int $usuarioId,
        string $nombreArchivo,
        ?string $firma = null
    ): VersionPlanificacion {
        return DB::transaction(function () use (
            $registros,
            $usuarioId,
            $nombreArchivo,
            $firma
        ) {
            $universidad = Universidad::orderBy('id')->lockForUpdate()->first();

            if (!$universidad) {
                throw new \Exception(
                    'No existe una universidad configurada.'
                );
            }

            $periodos = PeriodoAcademico::where('universidad_id', $universidad->id)->orderBy('id')->lockForUpdate()->get();
            VersionPlanificacion::whereIn('periodo_academico_id', $periodos->modelKeys())->orderBy('id')->lockForUpdate()->get();
            foreach ([Profesor::class, Sede::class, FacultadInstituto::class, Carrera::class, Asignatura::class] as $modelo) $modelo::where('universidad_id', $universidad->id)->orderBy('id')->lockForUpdate()->get();
            $secciones = Seccion::whereIn('periodo_academico_id', $periodos->modelKeys())->orderBy('id')->lockForUpdate()->get();
            $actividades = ActividadSeccion::whereIn('seccion_id', $secciones->modelKeys())->orderBy('id')->lockForUpdate()->get();
            GrupoSeccion::whereIn('actividad_seccion_id', $actividades->modelKeys())->orderBy('id')->lockForUpdate()->get();
            $plan = (new ConciliacionOfertaImportada)->analizar($registros);
            if ($plan['errores']) throw ValidationException::withMessages(['conciliacion' => array_column($plan['errores'], 'mensaje')]);
            if ($plan['sin_cambios']) throw ValidationException::withMessages(['conciliacion' => 'Este contenido ya corresponde a la última importación del período. No se creará otra versión idéntica.']);
            if ($firma !== null && ! hash_equals($firma, $plan['firma'])) throw ValidationException::withMessages(['conciliacion' => 'Los datos cambiaron desde el análisis. Vuelve a analizar el archivo antes de confirmar.']);

            $periodo = $this->obtenerPeriodo(
                $registros,
                $universidad->id,
                $plan
            );

            $version = VersionPlanificacion::create([
                'periodo_academico_id' => $periodo->id,
                'nombre' => 'Programación importada - ' . $nombreArchivo,
                'version' => $this->siguienteVersion($periodo->id),
                'tipo' => 'anterior',
                'descripcion' => 'Carga de programación desde archivo Excel.',
                'estado' => 'Borrador',
                'usuario_id' => $usuarioId,
                'es_actual' => true,
            ]);

            VersionPlanificacion::where(
                'periodo_academico_id',
                $periodo->id
            )
                ->where('tipo', 'anterior')
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
                'salas' => [],
                'tipos_sala' => [],
                'conciliacion' => $plan,
                'claves_seccion' => [],
                'claves_actividad' => [],
                'docencia_importada' => [],
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

            // Fijar el respaldo docente del archivo en esta versión; no usar plantillas manuales compartidas.
            foreach ($version->horarios()->whereDoesntHave('profesores')->with('actividadSeccion')->get() as $h) {
                foreach ($cache['docencia_importada'][$h->actividadSeccion->seccion_id] ?? [] as $docente) $h->profesores()->create($docente);
            }
            $cuentas = app(CuentasDocentes::class);
            foreach (collect($cache['profesores'])->unique('id') as $profesor) {
                $cuentas->asegurarCuenta($profesor);
            }
            DB::table('historial_acciones')->insert(['usuario_id' => $usuarioId, 'accion' => 'Importar programación anterior', 'modulo' => 'Programación anterior',
                'tabla_afectada' => 'versiones_planificacion', 'registro_id' => $version->id, 'descripcion' => 'Carga con conciliación de la oferta existente y conservación de plantillas.',
                'datos' => json_encode(['archivo' => $nombreArchivo, 'contenido_hash' => $plan['contenido_hash'], 'registros' => count($registros), 'resumen' => $plan['resumen'], 'secciones_importadas' => collect($cache['secciones'])->pluck('id')->unique()->values()->all(), 'secciones_reutilizadas' => array_values(array_filter($plan['mapas']['secciones'])),
                    'datos_importados' => app(DetalleComparacionImportacion::class)->snapshot($registros, $universidad->id)], JSON_THROW_ON_ERROR),
                'created_at' => now(), 'updated_at' => now()]);
            return $version;
        }, 3);
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
        if (ConciliacionOfertaImportada::rut($registro['PROF_RESP'] ?? '') === '') {
            $correo = mb_strtolower(ConciliacionOfertaImportada::texto($registro['CORREO_INST'] ?? ''));
            $registro['PROF_RESP'] = $cache['conciliacion']['alias_docentes'][$correo] ?? '';
        }

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

        if ($profesor) {
            $this->guardarProfesorVersion(
                $registro,
                $profesor,
                $version
            );
        }

        $seccion = $this->obtenerSeccion(
            $registro,
            $asignatura,
            $periodo,
            $cache
        );

        $existente = in_array($seccion->id, $cache['conciliacion']['mapas']['secciones'], true);
        if ($profesor) {
            $tipo = $this->texto($registro['TIPO_PROFESOR'] ?? null) ?: 'Profesor responsable';
            $cache['docencia_importada'][$seccion->id][$profesor->id] = ['profesor_id' => $profesor->id, 'tipo_participacion' => $tipo, 'es_principal' => $this->esProfesorPrincipal($tipo), 'estado' => true];
        }
        if ($carrera && ! $existente) {
            $seccion->carreras()->syncWithoutDetaching([
                $carrera->id,
            ]);
        }

        if ($profesor && ! $existente) {
            $tipoParticipacion = $this->texto(
                $registro['TIPO_PROFESOR'] ?? null
            ) ?: 'Profesor responsable';

            SeccionProfesor::updateOrCreate(
                [
                    'seccion_id' => $seccion->id,
                    'profesor_id' => $profesor->id,
                ],
                [
                    'tipo_participacion' => $tipoParticipacion,
                    'es_principal' => $this->esProfesorPrincipal(
                        $tipoParticipacion
                    ),
                    'estado' => true,
                ]
            );
        }

        $this->procesarHorarios(
            $registro,
            $seccion,
            $version,
            $profesor,
            $universidad,
            $cache
        );
    }

    private function obtenerPeriodo(array $registros, int $universidadId, array $plan): PeriodoAcademico
    {
        if ($plan['periodo_id']) return PeriodoAcademico::findOrFail($plan['periodo_id']);
        return PeriodoAcademico::create(['universidad_id' => $universidadId] + $plan['periodo'] + ['estado' => true]);
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

        $clave = ConciliacionOfertaImportada::claveCarrera($registro);
        if (isset($cache['carreras'][$clave])) return $cache['carreras'][$clave];
        $existente = $cache['conciliacion']['mapas']['carreras'][$clave] ?? null;
        if ($existente) return $cache['carreras'][$clave] = Carrera::findOrFail($existente);

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

        if (isset($cache['carreras'][$clave])) {
            $carrera = $cache['carreras'][$clave];
        } else {
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
        }

        if ($sede) {
            $carrera->sedes()->syncWithoutDetaching([
                $sede->id,
            ]);
        }

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
            ? 'COD:' . ConciliacionOfertaImportada::clave($codigo)
            : 'NOMBRE:' . ConciliacionOfertaImportada::clave($nombre);

        if (isset($cache['sedes'][$clave])) {
            return $cache['sedes'][$clave];
        }

        $existente = $cache['conciliacion']['mapas']['sedes'][$clave] ?? null;
        if ($existente) return $cache['sedes'][$clave] = Sede::findOrFail($existente);

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
            ? 'COD:' . ConciliacionOfertaImportada::clave($codigo)
            : 'NOMBRE:' . ConciliacionOfertaImportada::clave($nombre);

        if (isset($cache['facultades'][$clave])) {
            return $cache['facultades'][$clave];
        }

        $existente = $cache['conciliacion']['mapas']['facultades'][$clave] ?? null;
        if ($existente) return $cache['facultades'][$clave] = FacultadInstituto::findOrFail($existente);

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
        $claveCodigo = ConciliacionOfertaImportada::clave($codigo);
        $existente = $cache['conciliacion']['mapas']['asignaturas'][ConciliacionOfertaImportada::clave($codigo)] ?? null;
        if ($existente) return Asignatura::findOrFail($existente);

        if (isset($cache['asignaturas'][$claveCodigo])) {
            return $cache['asignaturas'][$claveCodigo];
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

        $cache['asignaturas'][$claveCodigo] = $asignatura;

        return $asignatura;
    }

    private function obtenerProfesor(
        array $registro,
        int $universidadId,
        array &$cache
    ): ?Profesor {
        $rut = ConciliacionOfertaImportada::rut(
            $registro['PROF_RESP'] ?? null
        );

        $correo = $this->texto(
            $registro['CORREO_INST'] ?? null
        );

        if ($rut === '' && $correo === '') {
            return null;
        }

        $clave = ConciliacionOfertaImportada::claveProfesor($registro);

        if (isset($cache['profesores'][$clave])) {
            return $cache['profesores'][$clave];
        }
        $existente = $cache['conciliacion']['mapas']['profesores'][$clave] ?? null;
        if ($existente) return $cache['profesores'][$clave] = Profesor::findOrFail($existente);

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

    private function guardarProfesorVersion(
        array $registro,
        Profesor $profesor,
        VersionPlanificacion $version
    ): void {
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
                'rut' => $this->texto(
                    $registro['PROF_RESP'] ?? null
                ) ?: null,
                'nombre' => $nombre ?: null,
                'apellido' => $apellido ?: null,
                'correo_institucional' => $this->texto(
                    $registro['CORREO_INST'] ?? null
                ) ?: null,
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
    }

    private function obtenerSeccion(array $registro, Asignatura $asignatura, PeriodoAcademico $periodo, array &$cache): Seccion
    {
        $clave = ConciliacionOfertaImportada::claveSeccion($registro);
        if (isset($cache['secciones'][$clave])) return $cache['secciones'][$clave];
        $id = $cache['conciliacion']['mapas']['secciones'][$clave] ?? null;
        $seccion = $id ? Seccion::findOrFail($id) : Seccion::create([
            'identificador_externo' => $this->texto($registro['ID_CURSO'] ?? null) ?: null,
            'asignatura_id' => $asignatura->id, 'periodo_academico_id' => $periodo->id,
            'codigo' => $this->texto($registro['SECC'] ?? null), 'nombre' => $this->texto($registro['RAMO_NOMBRE'] ?? null) ?: null,
            'semestre' => $this->entero($registro['NIVEL_UA'] ?? null), 'cupos' => $this->entero($registro['CUPO'] ?? null),
            'inscritos' => $this->entero($registro['INSCRITOS'] ?? null) ?? 0,
            'modalidad' => $this->texto($registro['MODALIDAD'] ?? null) ?: null, 'categoria' => $this->texto($registro['CATEGORIA'] ?? null) ?: null,
            'tipo_dictacion' => $this->texto($registro['TIPO_DICTACION'] ?? null) ?: null,
            'estado' => $this->texto($registro['ESTADO_CURSO'] ?? null) ?: 'Pendiente', 'estado_activo' => true,
        ]);
        $cache['claves_seccion'][$seccion->id] = $clave;
        return $cache['secciones'][$clave] = $seccion;
    }

    private function procesarHorarios(
        array $registro,
        Seccion $seccion,
        VersionPlanificacion $version,
        ?Profesor $profesor,
        Universidad $universidad,
        array &$cache
    ): void {
        $horario = $this->texto(
            $registro['HORARIO'] ?? null
        );

        if ($horario === '') {
            return;
        }

        $horariosProfesor = $profesor
            ? $this->parsearHorarios(
                $this->texto(
                    $registro['HORARIO_PROFESOR'] ?? null
                )
            )
            : [];

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

            $horarioModelo = Horario::firstOrCreate(
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

            if ($dato['sala'] !== null) {
                $this->asignarRecurso(
                    $dato['sala'],
                    $registro,
                    $horarioModelo->id,
                    $universidad->id,
                    $cache
                );
            }

            if (!$profesor) {
                continue;
            }

            if (!$this->horarioPerteneceAProfesor(
                $dato,
                $horariosProfesor
            )) {
                continue;
            }

            $tipoParticipacion = $this->texto(
                $registro['TIPO_PROFESOR'] ?? null
            ) ?: 'Profesor responsable';

            HorarioProfesor::updateOrCreate(
                [
                    'horario_id' => $horarioModelo->id,
                    'profesor_id' => $profesor->id,
                ],
                [
                    'tipo_participacion' => $tipoParticipacion,
                    'es_principal' => $this->esProfesorPrincipal(
                        $tipoParticipacion
                    ),
                    'estado' => true,
                ]
            );
        }
    }

    private function asignarRecurso(
        string $codigoRecurso,
        array $registro,
        int $horarioId,
        int $universidadId,
        array &$cache
    ): void {
        $codigoRecurso = trim($codigoRecurso);

        if ($codigoRecurso === '') {
            return;
        }

        $codigoSede = $this->texto(
            $registro['COD_SEDE'] ?? null
        );

        $nombreSede = $this->texto(
            $registro['SEDE'] ?? null
        );

        $sede = $this->obtenerSede(
            $universidadId,
            $codigoSede,
            $nombreSede,
            $cache
        );

        if (!$sede) {
            return;
        }

        $clave = $sede->id . '|' . mb_strtoupper($codigoRecurso);

        if (!isset($cache['salas'][$clave])) {
            $sala = DB::table('salas')
                ->where('universidad_id', $universidadId)
                ->where('sede_id', $sede->id)
                ->where('codigo', $codigoRecurso)
                ->first();

            if (!$sala) {
                $tipoSalaId = $this->obtenerTipoSalaRecurso($cache);
                $salaId = DB::table('salas')->insertGetId([
                    'universidad_id' => $universidadId,
                    'sede_id' => $sede->id,
                    'tipo_sala_id' => $tipoSalaId,
                    'codigo' => $codigoRecurso,
                    'nombre' => $codigoRecurso,
                    'capacidad' => $this->entero(
                        $registro['CUPO'] ?? null
                    ) ?? 0,
                    'edificio' => null,
                    'piso' => null,
                    'descripcion' => 'Recurso importado desde programación Excel.',
                    'disponible' => true,
                    'estado' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                $salaId = $sala->id;
            }

            $cache['salas'][$clave] = $salaId;
        }

        $salaId = $cache['salas'][$clave];

        $existe = DB::table('asignaciones_salas')
            ->where('horario_id', $horarioId)
            ->where('sala_id', $salaId)
            ->exists();

        if (!$existe) {
            DB::table('asignaciones_salas')->insert([
                'horario_id' => $horarioId,
                'sala_id' => $salaId,
                'capacidad_asignada' => $this->entero(
                    $registro['CUPO'] ?? null
                ),
                'observaciones' => 'Recurso indicado en archivo Excel.',
                'es_principal' => false,
                'estado' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function obtenerTipoSalaRecurso(
        array &$cache
    ): int {
        $clave = 'RECURSO_IMPORTADO';

        if (isset($cache['tipos_sala'][$clave])) {
            return $cache['tipos_sala'][$clave];
        }

        $tipo = DB::table('tipos_sala')
            ->where('nombre', 'Recurso importado')
            ->first();

        if (!$tipo) {
            $tipoId = DB::table('tipos_sala')->insertGetId([
                'nombre' => 'Recurso importado',
                'descripcion' => 'Tipo genérico para recursos físicos obtenidos desde la programación Excel.',
                'requiere_equipamiento' => false,
                'estado' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            $tipoId = $tipo->id;
        }

        $cache['tipos_sala'][$clave] = $tipoId;

        return $tipoId;
    }

    private function horarioPerteneceAProfesor(
        array $horario,
        array $horariosProfesor
    ): bool {
        foreach ($horariosProfesor as $horarioProfesor) {
            if (
                $horarioProfesor['tipo'] === $horario['tipo']
                && $horarioProfesor['grupo'] === $horario['grupo']
                && $horarioProfesor['dia'] === $horario['dia']
                && $horarioProfesor['bloque'] === $horario['bloque']
            ) {
                return true;
            }
        }

        return false;
    }

    private function parsearHorarios(string $horario): array
    {
        return ConciliacionOfertaImportada::horarios($horario);
    }

    private function obtenerActividad(Seccion $seccion, string $tipo, array $registro, array &$cache): ActividadSeccion
    {
        $clave = $cache['claves_seccion'][$seccion->id].'|'.ConciliacionOfertaImportada::tipo($tipo);
        if (isset($cache['actividades'][$clave])) return $cache['actividades'][$clave];
        $id = $cache['conciliacion']['mapas']['actividades'][$clave] ?? null;
        $actividad = $id ? ActividadSeccion::findOrFail($id) : ActividadSeccion::create([
            'seccion_id' => $seccion->id, 'tipo' => $tipo, 'cantidad_horas' => $this->horasActividad($registro, $tipo),
            'cantidad_estudiantes' => $this->entero($registro['INSCRITOS'] ?? null),
            'modalidad' => $this->texto($registro['MODALIDAD'] ?? null) ?: null, 'estado' => true,
        ]);
        $cache['claves_actividad'][$actividad->id] = $clave;
        return $cache['actividades'][$clave] = $actividad;
    }

    private function obtenerGrupo(ActividadSeccion $actividad, string $codigo, array $registro, array &$cache): GrupoSeccion
    {
        $clave = $cache['claves_actividad'][$actividad->id].'|'.$codigo;
        if (isset($cache['grupos'][$clave])) return $cache['grupos'][$clave];
        $id = $cache['conciliacion']['mapas']['grupos'][$clave] ?? null;
        $grupo = $id ? GrupoSeccion::findOrFail($id) : GrupoSeccion::create([
            'actividad_seccion_id' => $actividad->id, 'codigo' => $codigo,
            'numero' => (int) substr($codigo, 2), 'cupos' => $this->entero($registro['CUPO'] ?? null),
            'inscritos' => $this->entero($registro['INSCRITOS'] ?? null) ?? 0, 'estado' => 'Activo', 'estado_activo' => true,
        ]);
        return $cache['grupos'][$clave] = $grupo;
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
            ->where('tipo', 'anterior')
            ->orderByDesc('version')
            ->value('version');

        if ($ultima === null) {
            return 0.1;
        }

        return (round((float) $ultima * 10) + 1) / 10;
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

    private function esProfesorPrincipal(
        string $tipoParticipacion
    ): bool {
        return mb_strtoupper(
            trim($tipoParticipacion)
        ) === 'PROFESOR RESPONSABLE';
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

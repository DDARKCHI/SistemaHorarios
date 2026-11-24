<?php

namespace App\Services;

use App\Models;
use Illuminate\Support\Facades\DB;

class DetalleComparacionImportacion
{
    private const CAMPOS_DOCENTE = [
        'Nombre' => 'NOM_PROF_RESP',
        'RUT' => 'PROF_RESP',
        'Correo institucional' => 'CORREO_INST',
        'Correo personal' => 'CORREO_PERS',
        'Tipo de contrato' => 'TIPO_CONTRATO',
        'Tipo de profesor' => 'TIPO_PROFESOR',
        'Unidad académica' => 'UA',
    ];

    private const CAMPOS_RAMO = [
        'Nombre' => 'RAMO_NOMBRE',
        'Módulos de cátedra' => 'MOD_CATEDRA',
        'Módulos de laboratorio' => 'MOD_LABORATORIO',
        'Módulos de ayudantía' => 'MOD_AYUDANTIA',
        'Módulos de taller' => 'MOD_TALLER',
        'Módulos de clínica' => 'MOD_CLINICA',
        'Módulos de centro de práctica' => 'MOD_CENTRO_PRAC',
        'Otros módulos' => 'MOD_OTROS',
        'Libre elección' => 'ES_LIBRE_ELECCION',
        'Ramo terminal' => 'RAMO_TERMINAL',
    ];

    private const CAMPOS_SECCION = [
        'Identificador externo' => 'ID_CURSO',
        'Nivel de la carrera' => 'NIVEL_UA',
        'Cupos' => 'CUPO',
        'Inscritos' => 'INSCRITOS',
        'Modalidad' => 'MODALIDAD',
        'Categoría' => 'CATEGORIA',
        'Tipo de dictación' => 'TIPO_DICTACION',
        'Estado del curso' => 'ESTADO_CURSO',
    ];

    public function comparar(array $registros, ?Models\VersionPlanificacion $version, ?int $universidadId = null): array
    {
        $universidadId ??= $version?->periodoAcademico->universidad_id ?? Models\Universidad::orderBy('id')->value('id');
        $nuevo = $this->snapshot($registros, $universidadId);
        if ($version) {
            $auditado = DB::table('historial_acciones')->where('accion', 'Importar programación anterior')
                ->where('registro_id', $version->id)->orderByDesc('id')->value('datos');
            $guardado = $auditado ? (json_decode($auditado, true)['datos_importados'] ?? null) : null;
            $anterior = is_array($guardado) && ($guardado['version'] ?? null) === 1
                ? $guardado : $this->desdeVersion($version);
        } else {
            $anterior = $this->catalogoInicial($nuevo, $universidadId);
        }

        $cambios = [];
        foreach (['Sede', 'Facultad o instituto', 'Carrera', 'Ramo', 'Docente', 'Sección'] as $entidad) {
            $antes = $anterior['entidades'][$entidad] ?? [];
            $despues = $nuevo['entidades'][$entidad] ?? [];
            // Las versiones antiguas no conservaban los campos del archivo para estos catálogos.
            if ($version && ! isset($anterior['entidades'][$entidad])) continue;
            foreach ($despues as $clave => $dato) {
                if (! isset($antes[$clave])) {
                    $this->registro($cambios, 'agregado', $entidad, $dato);
                } elseif (! ($antes[$clave]['solo_identidad'] ?? false)) {
                    $camposAnteriores = $antes[$clave]['datos'];
                    $camposNuevos = $dato['datos'];
                    if (isset($antes[$clave]['carreras'], $dato['carreras'])
                        && array_keys($antes[$clave]['carreras']) !== array_keys($dato['carreras'])) {
                        $camposAnteriores['Carreras'] = implode(' / ', $antes[$clave]['carreras']);
                        $camposNuevos['Carreras'] = implode(' / ', $dato['carreras']);
                    }
                    $this->campos($cambios, $entidad, $dato['nombre'], $camposAnteriores, $camposNuevos);
                }
            }
            foreach (array_diff_key($antes, $despues) as $dato) {
                $this->registro($cambios, 'eliminado', $entidad, $dato);
            }
        }
        $this->horarios($cambios, $anterior['horarios'] ?? [], $nuevo['horarios']);

        return $cambios;
    }

    /** Datos del archivo deduplicados. Permiten comparar sin reinterpretar catálogos manuales. */
    public function snapshot(array $registros, ?int $universidadId = null): array
    {
        $universidadId ??= Models\Universidad::orderBy('id')->value('id');
        $filas = array_map(static fn ($r) => array_change_key_case(is_array($r) ? $r : [], CASE_UPPER), $registros);
        $docentes = Models\Profesor::where('universidad_id', $universidadId)->get();
        $porRut = $docentes->keyBy(fn ($p) => ConciliacionOfertaImportada::rut($p->rut));
        $porCorreo = $docentes->keyBy(fn ($p) => mb_strtolower(ConciliacionOfertaImportada::texto($p->correo_institucional)));
        $sedes = Models\Sede::where('universidad_id', $universidadId)->get();
        $alias = [];
        foreach ($filas as $fila) {
            $rut = ConciliacionOfertaImportada::rut($fila['PROF_RESP'] ?? '');
            $correo = mb_strtolower(ConciliacionOfertaImportada::texto($fila['CORREO_INST'] ?? ''));
            if ($rut !== '' && $correo !== '') $alias[$correo] = $rut;
        }

        $entidades = array_fill_keys(['Sede', 'Facultad o instituto', 'Carrera', 'Ramo', 'Docente', 'Sección'], []);
        $horarios = $responsables = [];
        foreach ($filas as $fila) {
            $ramo = ConciliacionOfertaImportada::texto($fila['COD_RAMO'] ?? '');
            $seccion = ConciliacionOfertaImportada::texto($fila['SECC'] ?? '');
            if ($ramo === '' || $seccion === '') continue;
            $claveSeccion = ConciliacionOfertaImportada::claveSeccion($fila);
            $nombreRamo = $this->etiqueta($ramo, $fila['RAMO_NOMBRE'] ?? '');
            $nombreSeccion = $nombreRamo.' · Sección '.$seccion;
            $this->entidad($entidades, 'Sede', $fila['COD_SEDE'] ?? '', $fila['SEDE'] ?? '', [
                'Código' => $fila['COD_SEDE'] ?? '', 'Nombre' => $fila['SEDE'] ?? '',
            ]);
            $this->entidad($entidades, 'Facultad o instituto', $fila['COD_FACULTAD'] ?? '', $fila['FACULTAD'] ?? '', [
                'Código' => $fila['COD_FACULTAD'] ?? '', 'Nombre' => $fila['FACULTAD'] ?? '',
            ]);
            $this->entidad($entidades, 'Carrera', $fila['COD_CARR'] ?? '', $fila['NOM_CARR'] ?? '', [
                'Código' => $fila['COD_CARR'] ?? '', 'Nombre' => $fila['NOM_CARR'] ?? '',
                'Régimen' => $fila['REGIMEN'] ?? '', 'Tipo de estudio' => $fila['TIPO_ESTUDIO'] ?? '',
            ]);
            $entidades['Ramo'][ConciliacionOfertaImportada::clave($ramo)] = [
                'nombre' => $nombreRamo, 'datos' => $this->extraer($fila, self::CAMPOS_RAMO),
            ];
            $carrerasSeccion = $entidades['Sección'][$claveSeccion]['carreras'] ?? [];
            $claveCarrera = ConciliacionOfertaImportada::claveCarrera($fila);
            $carrerasSeccion[$claveCarrera] = $this->etiqueta($fila['COD_CARR'] ?? '', $fila['NOM_CARR'] ?? '');
            ksort($carrerasSeccion);
            $entidades['Sección'][$claveSeccion] = [
                'nombre' => $nombreSeccion, 'datos' => $this->extraer($fila, self::CAMPOS_SECCION),
                'carreras' => $carrerasSeccion,
            ];

            $rut = ConciliacionOfertaImportada::rut($fila['PROF_RESP'] ?? '');
            $correo = mb_strtolower(ConciliacionOfertaImportada::texto($fila['CORREO_INST'] ?? ''));
            $rut = $rut ?: ($alias[$correo] ?? '');
            $profesor = ($rut !== '' ? $porRut->get($rut) : null) ?? ($correo !== '' ? $porCorreo->get($correo) : null);
            $claveProfesor = $profesor ? 'ID:'.$profesor->id : ($rut !== '' ? 'RUT:'.$rut : ($correo !== '' ? 'CORREO:'.$correo : null));
            if ($claveProfesor) {
                $datos = $this->extraer($fila, self::CAMPOS_DOCENTE);
                $datos['RUT'] = $rut;
                $nombre = ConciliacionOfertaImportada::texto($fila['NOM_PROF_RESP'] ?? '') ?: $correo ?: $rut;
                $nombre .= $rut !== '' ? ' · '.$rut : '';
                $entidades['Docente'][$claveProfesor] = ['nombre' => $nombre, 'datos' => $datos];
                $responsables[$claveSeccion][$claveProfesor] = $nombre;
            }

            $propios = ConciliacionOfertaImportada::horarios(ConciliacionOfertaImportada::texto($fila['HORARIO_PROFESOR'] ?? ''));
            $clavesPropias = array_fill_keys(array_map(fn ($h) => $this->claveHorario($claveSeccion, $h), $propios), true);
            foreach (ConciliacionOfertaImportada::horarios(ConciliacionOfertaImportada::texto($fila['HORARIO'] ?? '')) as $horario) {
                $clave = $this->claveHorario($claveSeccion, $horario);
                $horarios[$clave] ??= [
                    'familia' => $claveSeccion.'|'.($horario['grupo'] ?? ''),
                    'seccion' => $claveSeccion,
                    'nombre' => $nombreSeccion.($horario['grupo'] ? ' · '.$horario['grupo'] : ''),
                    'datos' => ['Actividad' => $horario['tipo'], 'Día' => $horario['dia'], 'Módulo' => (string) $horario['bloque']],
                    'salas' => [], 'docentes' => [],
                ];
                if ($horario['sala'] !== null) {
                    $sede = ConciliacionOfertaImportada::texto($fila['SEDE'] ?? '');
                    $codigoSede = ConciliacionOfertaImportada::clave($fila['COD_SEDE'] ?? '');
                    $sedeActual = $sedes->first(fn ($s) => ($codigoSede !== '' && ConciliacionOfertaImportada::clave($s->codigo) === $codigoSede)
                        || ConciliacionOfertaImportada::clave($s->nombre) === ConciliacionOfertaImportada::clave($sede));
                    $claveSede = $sedeActual ? 'ID:'.$sedeActual->id : ($codigoSede ?: ConciliacionOfertaImportada::clave($sede));
                    $identidadSala = $claveSede.'|'.ConciliacionOfertaImportada::clave($horario['sala']);
                    $horarios[$clave]['salas'][$identidadSala] = $this->etiqueta($horario['sala'], $sede);
                }
                if ($claveProfesor && isset($clavesPropias[$clave])) {
                    $horarios[$clave]['docentes'][$claveProfesor] = $entidades['Docente'][$claveProfesor]['nombre'];
                }
            }
        }
        foreach ($horarios as &$horario) {
            if (! $horario['docentes']) $horario['docentes'] = $responsables[$horario['seccion']] ?? [];
            $this->ordenarHorario($horario);
        }
        unset($horario);

        return ['version' => 1, 'entidades' => $entidades, 'horarios' => $horarios];
    }

    private function desdeVersion(Models\VersionPlanificacion $version): array
    {
        $entidades = ['Docente' => [], 'Sección' => []];
        $auditoria = DB::table('historial_acciones')->where('accion', 'Importar programación anterior')
            ->where('registro_id', $version->id)->orderByDesc('id')->value('datos');
        $idsSecciones = $auditoria ? (json_decode($auditoria, true)['secciones_importadas'] ?? []) : [];
        foreach (Models\Seccion::where('periodo_academico_id', $version->periodo_academico_id)
            ->whereIn('id', $idsSecciones)->with('asignatura')->get() as $seccion) {
            $clave = ConciliacionOfertaImportada::clave($seccion->asignatura?->codigo)
                .'|'.ConciliacionOfertaImportada::clave($seccion->codigo);
            $entidades['Sección'][$clave] = [
                'nombre' => $this->etiqueta($seccion->asignatura?->codigo, $seccion->asignatura?->nombre).' · Sección '.$seccion->codigo,
                'datos' => [],
            ];
        }
        $historicos = Models\ProfesorVersion::where('version_planificacion_id', $version->id)->with('profesor')->get();
        foreach ($historicos as $docente) {
            $nombre = trim($docente->nombre.' '.$docente->apellido);
            $rut = ConciliacionOfertaImportada::rut($docente->rut);
            $entidades['Docente']['ID:'.$docente->profesor_id] = [
                'nombre' => $nombre.($rut !== '' ? ' · '.$rut : ''),
                'datos' => [
                    'Nombre' => $nombre, 'RUT' => $rut,
                    'Correo institucional' => $docente->correo_institucional ?? '',
                    'Correo personal' => $docente->correo_personal ?? '',
                    'Tipo de contrato' => $docente->tipo_contrato ?? '',
                    'Tipo de profesor' => $docente->tipo_profesor ?? '',
                    'Unidad académica' => $docente->unidad_academica ?? '',
                ],
            ];
        }
        $horarios = [];
        $modulos = $version->horarios()->where('estado', true)->with([
            'actividadSeccion.seccion.asignatura', 'grupoSeccion', 'dia', 'bloqueHorario',
            'profesores' => fn ($q) => $q->where('estado', true), 'profesores.profesor',
            'asignacionesSalas' => fn ($q) => $q->where('estado', true), 'asignacionesSalas.sala.sede',
            'asignacionesLaboratorios' => fn ($q) => $q->where('estado', true), 'asignacionesLaboratorios.laboratorio.sala.sede',
        ])->get();
        foreach ($modulos as $modulo) {
            $seccion = $modulo->actividadSeccion?->seccion;
            if (! $seccion) continue;
            $claveSeccion = ConciliacionOfertaImportada::clave($seccion->asignatura?->codigo)
                .'|'.ConciliacionOfertaImportada::clave($seccion->codigo);
            $nombreSeccion = $this->etiqueta($seccion->asignatura?->codigo, $seccion->asignatura?->nombre).' · Sección '.$seccion->codigo;
            $entidades['Sección'][$claveSeccion] = ['nombre' => $nombreSeccion, 'datos' => []];
            $grupo = $modulo->grupoSeccion;
            $grupoTexto = $grupo ? ($grupo->numero ? 'G:'.$grupo->numero : (string) $grupo->codigo) : null;
            $dato = ['tipo' => $modulo->actividadSeccion->tipo, 'dia' => $modulo->dia?->abreviatura,
                'bloque' => (int) $modulo->bloqueHorario?->numero, 'grupo' => $grupoTexto];
            $horario = [
                'familia' => $claveSeccion.'|'.($grupoTexto ?? ''),
                'seccion' => $claveSeccion,
                'nombre' => $nombreSeccion.($grupoTexto ? ' · '.$grupoTexto : ''),
                'datos' => ['Actividad' => $dato['tipo'], 'Día' => $dato['dia'], 'Módulo' => (string) $dato['bloque']],
                'salas' => [], 'docentes' => [],
            ];
            $salas = $modulo->asignacionesSalas->pluck('sala')
                ->concat($modulo->asignacionesLaboratorios->pluck('laboratorio.sala'))->filter()->unique('id');
            foreach ($salas as $sala) {
                $claveSala = 'ID:'.$sala->sede_id.'|'.ConciliacionOfertaImportada::clave($sala->codigo);
                $horario['salas'][$claveSala] = $this->etiqueta($sala->codigo, $sala->sede?->nombre);
            }
            foreach ($modulo->profesores as $asignacion) {
                $clave = 'ID:'.$asignacion->profesor_id;
                $nombre = $entidades['Docente'][$clave]['nombre'] ?? trim($asignacion->profesor?->nombre.' '.$asignacion->profesor?->apellido);
                $horario['docentes'][$clave] = $nombre;
            }
            $this->ordenarHorario($horario);
            $horarios[$this->claveHorario($claveSeccion, $dato)] = $horario;
        }
        // Sin snapshot sólo se conocen las identidades de las secciones, no sus antiguos datos del Excel.
        foreach ($entidades['Sección'] as &$seccion) $seccion['solo_identidad'] = true;
        unset($seccion);

        return ['version' => 1, 'entidades' => $entidades, 'horarios' => $horarios];
    }

    private function catalogoInicial(array $nuevo, ?int $universidad): array
    {
        $modelos = [
            'Sede' => Models\Sede::class, 'Facultad o instituto' => Models\FacultadInstituto::class,
            'Carrera' => Models\Carrera::class, 'Ramo' => Models\Asignatura::class,
        ];
        $anteriores = [];
        foreach ($modelos as $entidad => $modelo) {
            $existentes = $modelo::where('universidad_id', $universidad)->get();
            foreach ($nuevo['entidades'][$entidad] as $clave => $dato) {
                $codigo = explode('|', $clave, 2)[0];
                $coincide = $existentes->contains(fn ($registro) =>
                    ($registro->codigo && ConciliacionOfertaImportada::clave($registro->codigo) === $codigo)
                    || ConciliacionOfertaImportada::clave($registro->nombre) === ConciliacionOfertaImportada::clave($dato['datos']['Nombre'] ?? ''));
                if ($coincide) $anteriores[$entidad][$clave] = $dato;
            }
        }

        return ['entidades' => $anteriores, 'horarios' => []];
    }

    private function entidad(array &$lista, string $entidad, mixed $codigo, mixed $nombre, array $datos): void
    {
        $codigo = ConciliacionOfertaImportada::texto($codigo);
        $nombre = ConciliacionOfertaImportada::texto($nombre);
        if ($codigo === '' && $nombre === '') return;
        $clave = ConciliacionOfertaImportada::clave($codigo ?: $nombre);
        $lista[$entidad][$clave] = ['nombre' => $this->etiqueta($codigo, $nombre), 'datos' => array_map(ConciliacionOfertaImportada::texto(...), $datos)];
    }

    private function extraer(array $fila, array $campos): array
    {
        $valores = [];
        foreach ($campos as $etiqueta => $columna) $valores[$etiqueta] = ConciliacionOfertaImportada::texto($fila[$columna] ?? '');

        return $valores;
    }

    private function etiqueta(mixed $codigo, mixed $nombre): string
    {
        return implode(' · ', array_unique(array_filter([
            ConciliacionOfertaImportada::texto($codigo), ConciliacionOfertaImportada::texto($nombre),
        ], fn ($valor) => $valor !== '')));
    }

    private function claveHorario(string $seccion, array $horario): string
    {
        return implode('|', [$seccion, $horario['tipo'], $horario['grupo'] ?? '', $horario['dia'], $horario['bloque']]);
    }

    private function ordenarHorario(array &$horario): void
    {
        ksort($horario['salas']);
        ksort($horario['docentes']);
    }

    private function registro(array &$cambios, string $tipo, string $entidad, array $dato): void
    {
        $descripcion = [];
        foreach ($dato['datos'] as $campo => $valor) {
            if ($valor !== '' && $valor !== null) $descripcion[] = $this->etiquetaCampo($campo).': '.$valor;
        }
        $valor = $descripcion ? implode(' · ', $descripcion) : $dato['nombre'];
        $cambios[] = ['tipo' => $tipo, 'entidad' => $entidad, 'nombre' => $dato['nombre'],
            'campo' => 'Registro', 'anterior' => $tipo === 'eliminado' ? $valor : null,
            'nuevo' => $tipo === 'agregado' ? $valor : null];
    }

    private function campos(array &$cambios, string $entidad, string $nombre, array $antes, array $despues): void
    {
        foreach (array_unique([...array_keys($antes), ...array_keys($despues)]) as $campo) {
            $anterior = $antes[$campo] ?? '';
            $nuevo = $despues[$campo] ?? '';
            if ($this->normalizar($campo, $anterior) === $this->normalizar($campo, $nuevo)) continue;
            $cambios[] = [
                'tipo' => $nuevo === '' ? 'eliminado' : ($anterior === '' ? 'agregado' : 'modificado'),
                'entidad' => $entidad, 'nombre' => $nombre, 'campo' => $this->etiquetaCampo($campo),
                'anterior' => $anterior === '' ? null : $anterior, 'nuevo' => $nuevo === '' ? null : $nuevo,
            ];
        }
    }

    private function etiquetaCampo(string $campo): string
    {
        // Los nombres guardados en importaciones anteriores conservan su significado.
        return match ($campo) {
            'Nivel de la carrera' => 'Semestre de la carrera',
            'Tipo de profesor' => 'Tipo de docente',
            default => $campo,
        };
    }

    private function normalizar(string $campo, mixed $valor): string
    {
        $valor = ConciliacionOfertaImportada::texto($valor);
        if ($campo === 'RUT') return ConciliacionOfertaImportada::rut($valor);
        if (in_array($campo, ['Módulo', 'Cupos', 'Inscritos', 'Nivel de la carrera'], true) || str_starts_with($campo, 'Módulos ') || $campo === 'Otros módulos') {
            return is_numeric($valor) ? (string) (int) $valor : $valor;
        }

        return mb_strtolower($valor);
    }

    private function horarios(array &$cambios, array $antes, array $despues): void
    {
        foreach (array_intersect_key($despues, $antes) as $clave => $horario) {
            $this->compararHorario($cambios, $antes[$clave], $horario);
            unset($antes[$clave], $despues[$clave]);
        }
        uasort($despues, fn ($a, $b) =>
            [$a['familia'], $a['datos']['Actividad'], $a['datos']['Día'], (int) $a['datos']['Módulo']]
            <=> [$b['familia'], $b['datos']['Actividad'], $b['datos']['Día'], (int) $b['datos']['Módulo']]);
        // Un módulo desplazado conserva su sección y grupo. Se muestran sólo sus campos distintos.
        foreach ($despues as $clave => $horario) {
            $coincidentes = array_filter($antes, fn ($previo) => $previo['familia'] === $horario['familia']);
            if ($coincidentes) {
                uasort($coincidentes, fn ($a, $b) =>
                    [$a['datos']['Actividad'] !== $horario['datos']['Actividad'], $a['datos']['Día'] !== $horario['datos']['Día'], (int) $a['datos']['Módulo']]
                    <=> [$b['datos']['Actividad'] !== $horario['datos']['Actividad'], $b['datos']['Día'] !== $horario['datos']['Día'], (int) $b['datos']['Módulo']]);
                $anterior = array_key_first($coincidentes);
                $this->compararHorario($cambios, $antes[$anterior], $horario);
                unset($antes[$anterior], $despues[$clave]);
            }
        }
        foreach ($despues as $horario) $this->registroHorario($cambios, 'agregado', $horario);
        foreach ($antes as $horario) $this->registroHorario($cambios, 'eliminado', $horario);
    }

    private function compararHorario(array &$cambios, array $antes, array $despues): void
    {
        $anteriores = $antes['datos'];
        $nuevos = $despues['datos'];
        foreach (['salas' => 'Sala', 'docentes' => 'Docentes'] as $lista => $campo) {
            // Un cambio de nombre o correo se presenta en la ficha docente, sin repetir sus módulos.
            if (array_keys($antes[$lista]) !== array_keys($despues[$lista])) {
                $anteriores[$campo] = implode(' / ', $antes[$lista]);
                $nuevos[$campo] = implode(' / ', $despues[$lista]);
            }
        }
        $this->campos($cambios, 'Horario', $despues['nombre'].' · '.$despues['datos']['Día'].' · Módulo '.$despues['datos']['Módulo'], $anteriores, $nuevos);
    }

    private function registroHorario(array &$cambios, string $tipo, array $horario): void
    {
        $horario['datos']['Sala'] = implode(' / ', $horario['salas']);
        $horario['datos']['Docentes'] = implode(' / ', $horario['docentes']);
        $this->registro($cambios, $tipo, 'Horario', $horario);
    }
}

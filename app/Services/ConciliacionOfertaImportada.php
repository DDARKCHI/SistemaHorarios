<?php

namespace App\Services;

use App\Models;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ConciliacionOfertaImportada
{
    private array $resueltos = [];
    private array $docentesPorRut = [];
    private array $docentesPorCorreo = [];
    public static function texto(mixed $valor): string { return is_scalar($valor) ? Str::squish((string) $valor) : ''; }
    public static function clave(mixed $valor): string { return mb_strtoupper(self::texto($valor)); }
    public static function claveSeccion(array $r): string { return self::clave($r['COD_RAMO'] ?? '').'|'.self::clave($r['SECC'] ?? ''); }
    public static function claveCarrera(array $r): string { return self::clave($r['COD_CARR'] ?? '') !== '' ? 'COD:'.self::clave($r['COD_CARR']) : 'NOMBRE:'.self::clave($r['NOM_CARR'] ?? ''); }
    public static function claveSede(string $codigo, string $nombre): string { return $codigo !== '' ? 'COD:'.self::clave($codigo) : 'NOMBRE:'.self::clave($nombre); }
    public static function tipo(string $valor): string { return Str::upper(Str::ascii(self::texto($valor))); }
    public static function rut(mixed $valor): string { return preg_replace('/[.\-\s]+/u', '', self::clave($valor)); }
    public static function claveProfesor(array $r): string { return self::rut($r['PROF_RESP'] ?? '') !== '' ? 'RUT:'.self::rut($r['PROF_RESP']) : 'CORREO:'.mb_strtolower(self::texto($r['CORREO_INST'] ?? '')); }

    /** Analiza sin escribir. Los mapas internos se usan también al confirmar la carga. */
    public function analizar(array $registros): array
    {
        $this->resueltos = [];
        $uni = Models\Universidad::orderBy('id')->first();
        $mapas = array_fill_keys(['sedes', 'facultades', 'carreras', 'asignaturas', 'profesores', 'secciones', 'actividades', 'grupos'], []);
        $errores = []; $avisos = []; $periodo = null; $periodoDato = null; $identificadores = [];
        $error = static function ($mensaje, $fila = null) use (&$errores) { $errores[$mensaje] ??= ['fila' => $fila, 'mensaje' => $mensaje]; };
        $aliasDocentes = []; $correosPorRut = [];
        foreach ($registros as $i => $registro) {
            $r = is_array($registro) ? array_change_key_case($registro, CASE_UPPER) : [];
            $rut = self::rut($r['PROF_RESP'] ?? ''); $correo = mb_strtolower(self::texto($r['CORREO_INST'] ?? ''));
            if ($rut !== '' && $correo !== '') {
                if (isset($aliasDocentes[$correo]) && $aliasDocentes[$correo] !== $rut) $error('El archivo asocia un correo docente a RUT diferentes. Revisa las identidades antes de importar.', $i + 2);
                if (isset($correosPorRut[$rut]) && $correosPorRut[$rut] !== $correo) $error('El archivo asocia un RUT docente a correos institucionales diferentes. Unifica el contacto antes de importar.', $i + 2);
                $aliasDocentes[$correo] = $rut; $correosPorRut[$rut] = $correo;
            }
        }
        if (! $uni || ! $uni->estado) $error('La institución de importación debe existir y estar activa.');
        $periodos = Models\PeriodoAcademico::where('universidad_id', $uni?->id)->orderBy('id')->get();
        $sedes = Models\Sede::where('universidad_id', $uni?->id)->orderBy('id')->get();
        $facultades = Models\FacultadInstituto::where('universidad_id', $uni?->id)->orderBy('id')->get();
        $carreras = Models\Carrera::where('universidad_id', $uni?->id)->with(['sedes' => fn ($q) => $q->orderBy('sedes.id')])->orderBy('id')->get();
        $ramos = Models\Asignatura::where('universidad_id', $uni?->id)->orderBy('id')->get();
        $docentes = Models\Profesor::where('universidad_id', $uni?->id)->orderBy('id')->get();
        $this->docentesPorRut = $docentes->groupBy(fn ($p) => self::rut($p->rut))->all();
        $this->docentesPorCorreo = $docentes->groupBy(fn ($p) => mb_strtolower(self::texto($p->correo_institucional)))->all();
        $secciones = Models\Seccion::whereIn('periodo_academico_id', $periodos->modelKeys())->with(['carreras' => fn ($q) => $q->orderBy('carreras.id'), 'profesores' => fn ($q) => $q->orderBy('id')])->orderBy('id')->get();
        $actividades = Models\ActividadSeccion::whereIn('seccion_id', $secciones->modelKeys())->orderBy('id')->get();
        $grupos = Models\GrupoSeccion::whereIn('actividad_seccion_id', $actividades->modelKeys())->with(['profesores' => fn ($q) => $q->orderBy('id')])->orderBy('id')->get();
        $seccionesPorId = $secciones->keyBy('id');
        $seccionesPorIdentidad = $secciones->groupBy(fn ($s) => $s->periodo_academico_id.'|'.$s->asignatura_id.'|'.self::clave($s->codigo));
        $actividadesPorId = $actividades->keyBy('id'); $actividadesPorSeccion = $actividades->groupBy('seccion_id');
        $gruposPorId = $grupos->keyBy('id'); $gruposPorActividad = $grupos->groupBy('actividad_seccion_id');
        $versiones = Models\VersionPlanificacion::whereIn('periodo_academico_id', $periodos->modelKeys())->orderBy('id')->get();
        $filas = []; $conHorario = []; $conDocente = [];
        foreach ($registros as $i => $registro) {
            $r = is_array($registro) ? array_change_key_case($registro, CASE_UPPER) : [];
            $filas[] = $r;
            try {
                foreach (['SEMESTRE', 'COD_RAMO', 'RAMO_NOMBRE', 'SECC', 'NOM_CARR'] as $campo) if (self::texto($r[$campo] ?? '') === '') $this->rechazar('El registro requiere '.$campo.'.');
                if (! preg_match('/\A([0-9]{4})[-\/]([12])\z/', self::texto($r['SEMESTRE']), $p)) $this->rechazar('El período debe indicar año y semestre 1 o 2, por ejemplo 2026-2.');
                $dato = ['anio' => (int) $p[1], 'numero_semestre' => (int) $p[2], 'nombre' => $p[1].'-'.$p[2]];
                if ($periodoDato && $periodoDato !== $dato) $this->rechazar('Importa un solo período académico por archivo.');
                $periodoDato = $dato;
                $porNumero = $this->unico($periodos->filter(fn ($v) => (int) $v->anio === $dato['anio'] && (int) $v->numero_semestre === $dato['numero_semestre']), 'período académico');
                $porNombre = $this->por($periodos, 'nombre', self::texto($r['SEMESTRE']), 'período académico');
                if ($porNumero && $porNombre && $porNumero->id !== $porNombre->id) $this->rechazar('El nombre y el año/semestre apuntan a períodos diferentes. Revisa el catálogo.');
                $periodo = $porNumero ?? $porNombre;
                if ($periodo && ((int) $periodo->anio !== $dato['anio'] || (int) $periodo->numero_semestre !== $dato['numero_semestre'])) $this->rechazar('El período existente no coincide con el año y semestre del archivo.');
                if ($periodo && ! $periodo->estado) $this->rechazar('El período está archivado. Reactívalo antes de importar.');
                $cerrado = $periodo && $versiones->contains(fn ($v) => (int) $v->periodo_academico_id === (int) $periodo->id && $v->tipo === 'semestral' && in_array($v->estado, ['Aprobado', 'Publicado'], true));

                $sede = $this->porCodigoNombre($sedes, self::texto($r['COD_SEDE'] ?? ''), self::texto($r['SEDE'] ?? ''), 'sede');
                if (self::texto($r['COD_SEDE'] ?? '') !== '' || self::texto($r['SEDE'] ?? '') !== '') $mapas['sedes'][self::claveSede(self::texto($r['COD_SEDE'] ?? ''), self::texto($r['SEDE'] ?? ''))] = $sede?->id;
                $facultad = $this->porCodigoNombre($facultades, self::texto($r['COD_FACULTAD'] ?? ''), self::texto($r['FACULTAD'] ?? ''), 'facultad o instituto');
                if (self::texto($r['COD_FACULTAD'] ?? '') !== '' || self::texto($r['FACULTAD'] ?? '') !== '') $mapas['facultades'][self::claveSede(self::texto($r['COD_FACULTAD'] ?? ''), self::texto($r['FACULTAD'] ?? ''))] = $facultad?->id;
                $carrera = $this->porCodigoNombre($carreras, self::texto($r['COD_CARR'] ?? ''), self::texto($r['NOM_CARR']), 'carrera');
                $mapas['carreras'][self::claveCarrera($r)] = $carrera?->id;
                if ($carrera && $sede && (int) $carrera->sede_id !== (int) $sede->id && ! $carrera->sedes->contains('id', $sede->id)) $this->rechazar('La sede del archivo no está vinculada a la carrera '.$carrera->nombre.'. Revisa sus sedes antes de importar.');
                $ramo = $this->por($ramos, 'codigo', self::texto($r['COD_RAMO']), 'ramo');
                $mapas['asignaturas'][self::clave($r['COD_RAMO'])] = $ramo?->id;
                $identidad = $r;
                if (self::rut($identidad['PROF_RESP'] ?? '') === '') $identidad['PROF_RESP'] = $aliasDocentes[mb_strtolower(self::texto($r['CORREO_INST'] ?? ''))] ?? '';
                $docente = $this->profesor($docentes, $identidad);
                if (self::rut($identidad['PROF_RESP'] ?? '') !== '' || self::texto($identidad['CORREO_INST'] ?? '') !== '') $mapas['profesores'][self::claveProfesor($identidad)] = $docente?->id;
                $clave = self::claveSeccion($r);
                if (self::rut($r['PROF_RESP'] ?? '') !== '' || self::texto($r['CORREO_INST'] ?? '') !== '') $conDocente[$clave] = true;
                $seccion = array_key_exists($clave, $mapas['secciones']) ? $seccionesPorId->get($mapas['secciones'][$clave]) : ($ramo && $periodo ? $this->unico($seccionesPorIdentidad->get($periodo->id.'|'.$ramo->id.'|'.self::clave($r['SECC']), collect()), 'sección '.$r['SECC']) : null);
                $mapas['secciones'][$clave] = $seccion?->id;
                $externo = self::texto($r['ID_CURSO'] ?? '');
                if (isset($identificadores[$clave]) && $identificadores[$clave] !== $externo) $this->rechazar('Una misma sección tiene identificadores externos diferentes en el archivo.');
                $identificadores[$clave] = $externo;
                if ($seccion && self::texto($seccion->identificador_externo) !== '' && $externo !== '' && self::texto($seccion->identificador_externo) !== $externo) $this->rechazar('La sección '.$seccion->codigo.' ya tiene otro identificador externo. No se fusionarán registros diferentes.');
                if ($seccion && (! $carrera || ! $seccion->carreras->contains('id', $carrera->id))) $this->rechazar('La carrera del archivo no está vinculada a la sección '.$seccion->codigo.'. Revisa la oferta antes de importar.');
                if (! $seccion && $cerrado) $this->rechazar('El período tiene programación aprobada o publicada. No se agregarán secciones a su oferta compartida.');
                if ($seccion) $avisos['preservacion'] = 'Las secciones existentes usarán los cupos, estudiantes y carreras registrados en Oferta por período. Los docentes del archivo quedarán asignados a sus clases.';
                if (($seccion && ! $seccion->estado_activo) || ($ramo && ! $ramo->estado) || ($carrera && ! $carrera->estado) || ($docente && ! $docente->estado)) $avisos['inactivos'] = 'El archivo incluye registros inactivos. Revisa su estado antes de generar los horarios del semestre.';
                $horarios = self::horarios(self::texto($r['HORARIO'] ?? ''));
                if ($horarios) $conHorario[$clave] = true;
                if ($seccion && ((self::texto($r['CUPO'] ?? '') !== '' && (int) $r['CUPO'] !== (int) $seccion->cupos)
                    || (self::texto($r['NIVEL_UA'] ?? '') !== '' && (int) $r['NIVEL_UA'] !== (int) $seccion->semestre))) $avisos['diferencias'] = 'Hay diferencias en los cupos o semestres del archivo. Revísalas en Oferta por período antes de usar esta versión para generar horarios.';
                foreach ($horarios as $h) {
                    $claveA = $clave.'|'.self::tipo($h['tipo']);
                    $actividad = array_key_exists($claveA, $mapas['actividades']) ? $actividadesPorId->get($mapas['actividades'][$claveA]) : ($seccion ? $this->unico($actividadesPorSeccion->get($seccion->id, collect())->filter(fn ($a) => self::tipo($a->tipo) === self::tipo($h['tipo'])), 'actividad de la sección '.$seccion->codigo) : null);
                    $mapas['actividades'][$claveA] = $actividad?->id;
                    if (! $actividad && $cerrado) $this->rechazar('La carga requiere una actividad nueva en un período con programación cerrada. Conserva su oferta.');
                    if ($h['grupo'] !== null) {
                        $numero = (int) substr($h['grupo'], 2);
                        if ($numero < 1) $this->rechazar('Los grupos del archivo deben tener un número mayor que cero.');
                        $claveG = $claveA.'|'.$h['grupo'];
                        $grupo = array_key_exists($claveG, $mapas['grupos']) ? $gruposPorId->get($mapas['grupos'][$claveG]) : ($actividad ? $this->unico($gruposPorActividad->get($actividad->id, collect())->filter(fn ($g) => self::clave($g->codigo) === self::clave($h['grupo']) || (int) $g->numero === $numero || preg_match('/\A(?:G\s*:\s*)?0*'.preg_quote((string) $numero, '/').'\z/i', self::texto($g->codigo))), 'grupo '.$numero.' de la sección '.$seccion->codigo) : null);
                        if ($grupo && preg_match('/\A(?:G\s*:\s*)?([0-9]+)\z/i', self::texto($grupo->codigo), $n) && ((int) $n[1] !== $numero || ((int) $grupo->numero > 0 && (int) $grupo->numero !== $numero))) $this->rechazar('El código y número del grupo existente no coinciden. Revisa la oferta antes de importar.');
                        $mapas['grupos'][$claveA.'|'.$h['grupo']] = $grupo?->id;
                        if (! $grupo && $cerrado) $this->rechazar('La carga requiere un grupo nuevo en un período con programación cerrada. Conserva su oferta.');
                    }
                }
            } catch (ValidationException $e) { foreach (collect($e->errors())->flatten() as $mensaje) $error($mensaje, $i + 2); }
        }
        if (! $registros) $error('El archivo no contiene registros que puedan incorporarse.');
        foreach ($conHorario as $clave => $_) if (($mapas['secciones'][$clave] ?? null) && empty($conDocente[$clave])) {
            $s = $seccionesPorId->get($mapas['secciones'][$clave]);
            $actividadesSeccion = $actividadesPorSeccion->get($s->id, collect())->pluck('id');
            $plantilla = $s->profesores->where('estado', true)->isNotEmpty() || $grupos->contains(fn ($g) => $actividadesSeccion->contains($g->actividad_seccion_id) && $g->profesores->where('estado', true)->isNotEmpty());
            if ($plantilla) $error('La sección existente '.$clave.' tiene horarios sin docentes identificables en el archivo. Informa sus responsables para no inferirlos desde una plantilla manual ajena a esa programación.');
            else $avisos['sin_docentes'] = 'Algunas clases quedarán sin docente. Completa su asignación antes de generar la programación del semestre.';
        }
        $resumen = [];
        foreach ($mapas as $tipo => $valores) $resumen[$tipo] = ['nuevos' => count(array_filter($valores, fn ($v) => $v === null)), 'reutilizados' => count(array_unique(array_filter($valores, fn ($v) => $v !== null)))];
        $contenidoHash = self::contenidoHash($registros);
        $anterior = $periodo ? $versiones->where('periodo_academico_id', $periodo->id)->where('tipo', 'anterior')->sortByDesc('id')->sortByDesc('es_actual')->first() : null;
        $auditoria = $anterior ? \Illuminate\Support\Facades\DB::table('historial_acciones')->where('accion', 'Importar programación anterior')->where('registro_id', $anterior->id)->orderByDesc('id')->value('datos') : null;
        $hashAnterior = $auditoria ? (json_decode($auditoria, true)['contenido_hash'] ?? null) : null;
        $sinCambios = is_string($hashAnterior) && hash_equals($hashAnterior, $contenidoHash);
        $firma = hash('sha256', json_encode([$filas, $uni?->getAttributes(), $periodos->toArray(), $sedes->toArray(), $facultades->toArray(), $carreras->toArray(), $ramos->toArray(), $docentes->toArray(), $secciones->toArray(), $actividades->toArray(), $grupos->toArray(), $versiones->toArray(), $mapas], JSON_THROW_ON_ERROR));
        return ['universidad_id' => $uni?->id, 'periodo_id' => $periodo?->id, 'periodo' => $periodoDato, 'mapas' => $mapas, 'resumen' => $resumen,
            'avisos' => array_values($avisos), 'errores' => array_values($errores), 'firma' => $firma, 'contenido_hash' => $contenidoHash, 'sin_cambios' => $sinCambios, 'alias_docentes' => $aliasDocentes];
    }

    public static function contenidoHash(array $registros): string
    {
        $filas = [];
        foreach ($registros as $r) {
            $r = array_map(fn ($v) => self::texto($v), array_change_key_case(is_array($r) ? $r : [], CASE_UPPER)); ksort($r);
            $filas[] = json_encode($r, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        }
        sort($filas, SORT_STRING);
        return hash('sha256', json_encode($filas, JSON_THROW_ON_ERROR));
    }

    private function por(Collection $lista, string $campo, string $valor, string $etiqueta)
    {
        if ($valor === '') return null;
        $clave = spl_object_id($lista).'|'.$campo.'|'.self::clave($valor);
        if (! array_key_exists($clave, $this->resueltos)) $this->resueltos[$clave] = $this->unico($lista->filter(fn ($m) => self::clave($m->$campo) === self::clave($valor)), $etiqueta);
        return $this->resueltos[$clave];
    }
    private function porCodigoNombre(Collection $lista, string $codigo, string $nombre, string $etiqueta)
    {
        $porCodigo = $this->por($lista, 'codigo', $codigo, $etiqueta);
        if ($porCodigo) return $porCodigo;
        $porNombre = $this->por($lista, 'nombre', $nombre, $etiqueta);
        if ($porNombre && $codigo !== '' && self::texto($porNombre->codigo) !== '' && self::clave($porNombre->codigo) !== self::clave($codigo)) $this->rechazar('El nombre de '.$etiqueta.' coincide con un registro de código diferente. Revisa el catálogo.');
        return $porNombre;
    }
    private function profesor(Collection $lista, array $r)
    {
        $rut = self::rut($r['PROF_RESP'] ?? ''); $correo = mb_strtolower(self::texto($r['CORREO_INST'] ?? ''));
        $clave = 'docente|'.$rut.'|'.$correo;
        if (array_key_exists($clave, $this->resueltos)) return $this->resueltos[$clave];
        $porRut = $rut === '' ? null : $this->unico($this->docentesPorRut[$rut] ?? collect(), 'docente por RUT');
        $porCorreo = $correo === '' ? null : $this->unico($this->docentesPorCorreo[$correo] ?? collect(), 'docente por correo');
        if ($porRut && $porCorreo && $porRut->id !== $porCorreo->id) $this->rechazar('El RUT y el correo del docente apuntan a personas distintas. Revisa el catálogo.');
        if (! $porRut && $porCorreo && $rut !== '' && self::rut($porCorreo->rut) !== '' && self::rut($porCorreo->rut) !== $rut) $this->rechazar('El correo del docente coincide con un registro de RUT diferente. Revisa su identidad.');
        return $this->resueltos[$clave] = $porRut ?? $porCorreo;
    }
    private function unico(Collection $coincidencias, string $etiqueta)
    {
        if ($coincidencias->count() > 1) $this->rechazar('Hay más de una coincidencia para '.$etiqueta.'. No se elegirá una automáticamente.');
        return $coincidencias->first();
    }
    private function rechazar(string $mensaje): never { throw ValidationException::withMessages(['conciliacion' => $mensaje]); }

    public static function horarios(string $horario): array
    {
        $resultado = [];
        preg_match_all('/(?:^|\s)(ca|la|ta|ce)\s*:\s*(.*?)(?=\s+(?:ca|la|ta|ce)\s*:|$)/is', trim($horario), $actividades, PREG_SET_ORDER);
        foreach ($actividades as $actividad) {
            $tipo = match (mb_strtolower(trim($actividad[1]))) { 'ca' => 'Cátedra', 'la' => 'Laboratorio', 'ta' => 'Taller', 'ce' => 'Centro de práctica' };
            preg_match_all('/\b(LU|MA|MI|JU|VI|SA)\.(\d+)(?:\s*\/\s*G\s*:\s*(\d+))?\s*(?:\(([^)]+)\))?/i', trim($actividad[2]), $coincidencias, PREG_SET_ORDER);
            foreach ($coincidencias as $c) $resultado[] = ['dia' => mb_strtoupper($c[1]), 'bloque' => (int) $c[2], 'grupo' => isset($c[3]) && $c[3] !== '' ? 'G:'.(int) $c[3] : null, 'sala' => isset($c[4]) && $c[4] !== '' ? trim($c[4]) : null, 'tipo' => $tipo];
        }
        return $resultado;
    }
}

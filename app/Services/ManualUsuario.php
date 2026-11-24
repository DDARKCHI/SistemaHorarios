<?php

namespace App\Services;

use App\Models\User;

class ManualUsuario
{
    public function paraUsuario(User $usuario): array
    {
        $manual = json_decode(file_get_contents(resource_path('manual/usuario.json')), true, flags: JSON_THROW_ON_ERROR);
        $manual['capitulos'] = array_values(array_filter(array_map(
            fn (array $capitulo) => $this->adaptar($capitulo, $usuario), $manual['capitulos']
        )));

        return $manual;
    }

    private function adaptar(array $c, User $u): ?array
    {
        $puede = fn (string $p) => $u->can($p);
        $docente = $u->profesor?->estado || $puede('enviar propuesta horario');
        $gestionaDisponibilidad = $puede('asignar profesores');
        $consulta = $puede('ver programacion semestral');
        $espacios = $puede('gestionar salas y recursos');
        $administra = $puede('administrar usuarios');
        $genera = $puede('generar programacion semestral');

        switch ($c['id']) {
            case 'cuenta':
            case 'glosario':
                return $c;
            case 'navegacion':
                $c['titulo'] = 'Orientarte en la plataforma';
                $c['objetivo'] = 'Encontrar las funciones disponibles para tu cuenta.';
                $c['bloques'][0]['pasos'][0] = 'Utiliza el menú lateral para abrir las funciones de tu cuenta.';
                if (! $puede('ver panel inicio')) {
                    $c['ruta'] = 'Menú lateral y barra superior';
                    $c['bloques'] = $this->bloques($c, ['navegar', 'controles-comunes']);
                } elseif (! $genera) {
                    $c['bloques'][1]['texto'] = 'Inicio muestra el período activo, las cantidades de carreras, docentes, salas y secciones y el avance de la programación.';
                }
                return $c;
            case 'flujo-trabajo':
                return $administra && $genera && $gestionaDisponibilidad && $puede('asignar horarios')
                    && $puede('gestionar conflictos') && $puede('gestionar salas y recursos') ? $c : null;
            case 'catalogos':
            case 'docentes':
            case 'oferta':
            case 'perfiles':
                return $administra ? $c : null;
            case 'programacion-anterior':
                $importa = $puede('importar programacion anterior');
                $consultaAnterior = $puede('ver programacion anterior');
                if (! $importa && ! $consultaAnterior) return null;
                $c['bloques'] = $this->bloques($c, array_filter([
                    $importa ? 'importar-un-archivo' : null, $consultaAnterior ? 'consultar' : null,
                ]));
                if (! $importa) {
                    $c['titulo'] = 'Consultar programación anterior';
                    $c['objetivo'] = 'Revisar cursos, docentes y horarios de una versión anterior.';
                    $c['bloques'][0]['texto'] = 'En Consultar programación anterior elige período, versión y los filtros del horario. Pulsa Aplicar y revisa la tabla. Utiliza Limpiar para comenzar otra consulta.';
                    $c['resultado'] = 'Una consulta del horario anterior sin modificar sus datos.';
                    unset($c['nota']);
                }
                return $c;
            case 'espacios':
            case 'equipamiento':
                return $puede('gestionar salas y recursos') ? $c : null;
            case 'prioridades':
                $prioridades = $puede('gestionar salas y recursos');
                $requerimientos = $puede('gestionar conflictos');
                if (! $prioridades && ! $requerimientos) return null;
                $c['bloques'] = $this->bloques($c, array_filter([
                    $prioridades ? 'prioridad-por-ramo' : null,
                    $requerimientos ? 'requerimientos-de-una-actividad' : null, 'ejemplo',
                ]));
                if ($requerimientos && ! $puede('asignar horarios')) {
                    foreach ($c['bloques'] as &$bloque) {
                        if ($bloque['id'] === 'requerimientos-de-una-actividad') {
                            $bloque['pasos'] = ['Abre Requerimientos de actividades y selecciona el período.',
                                'Filtra por ramo o actividad para consultar los recursos y las condiciones que necesita cada clase.'];
                        }
                    }
                    unset($bloque);
                }
                $c['ruta'] = implode(' / ', array_filter([
                    $prioridades ? 'Salas y recursos → Prioridades' : null,
                    $requerimientos ? 'Programación semestral → Requerimientos' : null,
                ]));
                return $c;
            case 'disponibilidad':
                if (! $docente && ! $gestionaDisponibilidad) return null;
                $c['bloques'] = $this->bloques($c, array_filter([
                    $gestionaDisponibilidad ? 'coordinar-y-revisar' : null, $docente ? 'responder-como-docente' : null,
                ]));
                if (! $gestionaDisponibilidad) {
                    $c['titulo'] = 'Registrar mi disponibilidad';
                    $c['ruta'] = 'Mi disponibilidad';
                    $c['objetivo'] = 'Informar cuándo puedes realizar clases y consultar el estado de tu respuesta.';
                    $c['nota'] = 'Completa tu disponibilidad antes de la fecha límite del correo. Si no aparece una solicitud o no puedes abrir Mi disponibilidad, contacta con coordinación para revisar tu ficha docente y el período.';
                    $c['resultado'] = 'Tu disponibilidad enviada a coordinación para revisión.';
                }
                return $c;
            case 'consulta':
                if (! $consulta && ! $espacios) return null;
                $c['bloques'] = $this->bloques($c, array_filter([
                    $consulta ? 'programacion-semestral' : null, $espacios ? 'horario-de-espacios' : null,
                ]));
                if (! $consulta) {
                    $c['titulo'] = 'Consultar horario de espacios';
                    $c['ruta'] = 'Salas y recursos → Horario de espacios';
                } elseif (! $espacios) {
                    $c['titulo'] = 'Consultar programación semestral';
                    $c['ruta'] = 'Programación semestral';
                    $c['objetivo'] = 'Revisar las clases de una versión de la programación.';
                }
                return $c;
            case 'asignar-docentes':
                return $gestionaDisponibilidad ? $c : null;
            case 'asignar-horarios':
                return $puede('asignar horarios') ? $c : null;
            case 'generar':
                return $genera ? $c : null;
            case 'conflictos':
                return $puede('gestionar conflictos') ? $c : null;
            case 'publicacion':
                if (! $consulta && ! $genera) return null;
                if (! $genera) {
                    $c['titulo'] = 'Consultar publicaciones y descargar PDF';
                    $c['ruta'] = 'Programación semestral → Consultar copia publicada';
                    $c['objetivo'] = 'Consultar los horarios publicados y descargar una copia.';
                    $c['bloques'] = [['titulo' => 'Consultar y descargar', 'pasos' => [
                        'Abre Programación semestral y selecciona el período y la versión publicada.',
                        'Pulsa Consultar copia publicada cuando esté disponible.',
                        'Aplica los filtros de la carrera, sección, ramo o docente que necesitas revisar.',
                        'Descarga PDF detallado o PDF semanal. Si se solicita, reduce los filtros para acotar el documento.',
                    ]]];
                    $c['nota'] = 'La publicación conserva los horarios de su fecha de cierre. Consultarla o descargar un PDF no modifica la programación.';
                    $c['resultado'] = 'Un horario publicado disponible para consulta y descarga.';
                }
                return $c;
            case 'cambios':
                if ($puede('asignar horarios')) return $c;
                if (! $docente) return null;
                return ['id' => $c['id'], 'titulo' => 'Responder una solicitud de cambio', 'ruta' => 'Enlace recibido de coordinación',
                    'objetivo' => 'Indicar qué alternativa de horario puedes aceptar.',
                    'bloques' => [['titulo' => 'Enviar tu respuesta', 'pasos' => [
                        'Abre el enlace personal de la solicitud que te entregó coordinación.',
                        'Revisa la clase, las alternativas y la fecha límite.',
                        'Selecciona una alternativa que puedas aceptar. Si ninguna es adecuada, elige rechazar la solicitud.',
                        'Pulsa Aceptar la seleccionada o Rechazar alternativas. Si rechazas, explica el motivo en Comentarios.',
                    ]]],
                    'nota' => 'Tu respuesta no cambia el horario automáticamente. Coordinación revisa el acuerdo y confirma la modificación.',
                    'resultado' => 'Tu respuesta queda disponible para coordinación.'];
            case 'intercambio':
                if (! $puede('ver intercambio docente')) return null;
                if (! $gestionaDisponibilidad) {
                    $c['bloques'][0]['titulo'] = 'Evaluar candidatos';
                    $c['bloques'][0]['pasos'] = array_slice($c['bloques'][0]['pasos'], 0, 3);
                    $c['resultado'] = 'Información de disponibilidad y carga para evaluar un posible reemplazo.';
                }
                return $c;
            case 'configuracion':
                if (! $puede('ver configuracion')) return null;
                if (! $administra) {
                    $c['titulo'] = 'Consultar la configuración';
                    $c['objetivo'] = 'Revisar los datos institucionales y el calendario académico.';
                    $c['bloques'] = [['titulo' => 'Consultar', 'pasos' => [
                        'Abre Configuración general para consultar la institución.',
                        'En Calendario académico revisa los días y módulos horarios.',
                        'En Identidad visual consulta el nombre, los colores y los logotipos de la plataforma.',
                    ]]];
                    $c['nota'] = 'Los cambios de configuración los realiza una cuenta con permiso de administración.';
                    $c['resultado'] = 'Los datos institucionales y el calendario consultados.';
                }
                return $c;
            case 'dudas':
                $reglas = [
                    'No aparece un docente o espacio' => $consulta || $puede('gestionar salas y recursos'),
                    'No puedo generar' => $genera,
                    'La vista previa venció o cambió' => $genera || $puede('importar programacion anterior'),
                    'No puedo editar una clase' => $puede('asignar horarios'),
                    'Hay equipos pero faltan recursos' => $puede('gestionar salas y recursos'),
                    'El PDF solicita filtros' => $consulta,
                ];
                $c['tabla'] = array_values(array_filter($c['tabla'], fn ($fila) => $reglas[$fila[0]] ?? true));
                return $c;
        }

        return null;
    }

    private function bloques(array $capitulo, array $ids): array
    {
        return array_values(array_filter($capitulo['bloques'], fn ($b) => in_array($b['id'], $ids, true)));
    }
}

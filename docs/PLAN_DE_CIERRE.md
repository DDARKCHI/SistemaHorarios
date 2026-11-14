# Cierre de PROSEC

Actualizado el 14 de noviembre de 2026. Este documento registra avances comprobados y trabajo pendiente; no declara terminado el sistema.

Fuente de alcance: `C:/Users/sebai/OneDrive/Escritorio/PRACTICA/v2/Planificacion_Maestra_Sistema_Horarios_Salas.docx`, contrastada con las decisiones posteriores del usuario. La aplicación activa está en `C:/xampp/htdocs/xampp/SistemaHorarios`.

## Criterios que deben conservarse

- Identidad PROSEC: fondo gris claro, superficies blancas, rojo institucional, tipografía y componentes existentes, formularios adaptables y navegación coherente.
- Conservar datos, versiones, trazabilidad y asignaciones existentes; ninguna actualización debe reemplazar la base de datos.
- Permitir asignación manual excepcional cuando el docente todavía no tiene disponibilidad declarada, con advertencia y registro. Esto es distinto del generador automático, que deberá exigir disponibilidad aprobada.
- Prioridad por asignatura o ramo completo y tipo de recurso; el nivel 1 tiene preferencia sobre el 2. Las prioridades actuales informan la revisión de conflictos; falta aplicarlas al generador.
- Una sala puede registrarse como laboratorio. El laboratorio puede obtener automáticamente el nombre de la sala.
- Ningún cambio se considera completo solo por verse bien: requiere verificar permisos, validación, conservación de datos y funcionamiento.

## Estado y orden de implementación

| Bloque | Estado verificado | Falta para cerrar |
| --- | --- | --- |
| Acceso y usuarios | Implementado y con pruebas | Revisión final de permisos, alcance por carrera y recuperación de acceso en entorno de entrega |
| Importación Excel | Implementada con comparación y confirmación | Validación final con archivos institucionales representativos |
| Salas, laboratorios y equipamiento | Implementados, incluyendo distribución entre sedes | Integración con asignación automática y revisión final de uso |
| Prioridades y requerimientos | Implementados por ramo y recurso | Aplicación en el optimizador global |
| Asignación manual y reemplazos | Implementados y con pruebas | Integrar flujo formal de solicitudes de cambio |
| Revisión de conflictos y carga | Implementada, con exportación Excel | Conectar a aprobación y publicación global |
| Disponibilidad docente | Flujo implementado en este bloque: solicitud, enlace firmado con vencimiento, calendario, respuesta, aprobación, rechazo y cancelación | Entrega de enlaces por correo institucional cuando se configure el transporte; restricciones institucionales previas del calendario; alcance por carrera para jefaturas |
| Generación global | Actualmente copia una programación anterior | Motor que asigne y optimice globalmente con restricciones, prioridades, almuerzo y explicación de casos sin solución |
| Versiones | Existen versiones y borradores | Estados de validación, aprobación, publicación, recuperación y generación progresiva preservando cambios validados |
| Solicitudes de cambio | Hay estructura preliminar de datos | Alternativas, enlace para docente, respuesta y aprobación con nueva validación al aplicar |
| Mantenedores institucionales y académicos | Datos importados y modelos existentes | Interfaz para institución, facultades, sedes, carreras, planes, niveles, asignaturas, períodos y parámetros |
| Configuración institucional | Pantalla provisional | Configuración persistente de identidad y reglas sin perder la apariencia PROSEC |
| Reportes PDF | Pendiente | Horarios visuales y códigos institucionales con filtros |
| Entrega | Pendiente | Documentación de instalación y respaldo, manuales por perfil, revisión visual de escritorio/móvil y prueba integral de procesos |

## Flujo de disponibilidad incorporado

La coordinación utiliza el permiso existente `asignar profesores`, crea una solicitud por docente y período y comparte un enlace privado. No se envían correos automáticamente. El profesor puede responder sin cuenta, mediante un enlace firmado y con vencimiento. Una solicitud permite una sola respuesta y se puede cancelar antes de recibirla. Las respuestas rechazadas o las solicitudes vencidas permiten crear una nueva solicitud.

La respuesta pendiente no altera la disponibilidad vigente. Aprobar reemplaza únicamente la disponibilidad del docente y período correspondientes; conserva los horarios existentes y registra la revisión. Rechazar exige motivo y conserva la disponibilidad vigente. Los módulos no elegidos se registran como no disponibles cuando se aprueba. Una declaración sin disponibilidad es explícita para evitar interpretarla como ausencia de datos.

Los cambios del calendario posteriores a la respuesta impiden aprobarla con un significado distinto. El enlace no se almacena en el historial. Las operaciones se realizan en transacciones y bloquean el docente, igual que las asignaciones manuales.

## Verificación

Las pruebas usan SQLite en memoria. La base real no se utiliza para ejecutar pruebas. En este equipo las pruebas Excel requieren habilitar ZIP para el proceso: `php -d extension=zip vendor/phpunit/phpunit/phpunit --do-not-cache-result`.

Este bloque pasó 723 pruebas con 5.211 comprobaciones; incluye 13 pruebas nuevas del flujo de disponibilidad. Permanecen dos avisos de obsolescencia previos. La compilación de estilos finalizó correctamente y la migración aditiva de disponibilidad se aplicó a MySQL de XAMPP.

La aprobación de disponibilidad no corrige automáticamente las clases existentes: se debe ejecutar la revisión de conflictos. Las pruebas automatizadas no sustituyen una revisión visual del sistema completo.

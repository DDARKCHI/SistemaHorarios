# Perfiles y permisos de PROSEC

Actualizado el 27 de noviembre de 2026.

## Administrar un perfil

En **Administración → Perfiles y permisos** se muestran los perfiles del sistema, sus funciones y la cantidad de cuentas vinculadas. El acceso requiere el permiso **Cuentas, perfiles y catálogos** (`administrar usuarios`). Este permiso incluye también la administración de docentes y de la oferta institucional completa: no debe asignarse como una función limitada a cuentas.

Para crear un perfil, indica un nombre y selecciona las funciones. El formulario comienza con consulta de inicio y programación actual y anterior. Puedes cambiar la selección. Crear el perfil no crea cuentas ni lo asigna a las existentes. Después se selecciona en **Nuevo usuario** o **Editar usuario**.

Para editar, revisa las funciones y las cuentas afectadas. Si ya tiene cuentas, marca la confirmación antes de guardar. La modificación se aplica a todas las cuentas que usan el perfil. Si cambian las funciones, el nombre o las cuentas desde que abriste el formulario, debes recargarlo y revisar de nuevo. Los permisos adicionales de extensiones se muestran y se conservan.

El perfil **Administrador** permite consultar sus funciones y está protegido contra edición desde esta pantalla. No se elimina ningún perfil desde la interfaz. Se conserva además la protección del último administrador y no se permite retirar el propio permiso de administración desde el perfil o la cuenta que se está utilizando.

## Funciones y combinaciones

| Función | Acceso que otorga |
| --- | --- |
| Inicio y resumen | Indicadores generales |
| Programación semestral | Consulta de horarios y publicaciones y descarga de reportes publicados |
| Programación anterior | Consulta de versiones anteriores; no permite importar |
| Evaluación de reemplazos | Candidatos, carga e historial; aplicar requiere además asignación de profesores |
| Importación de Excel | Analizar y confirmar importaciones; requiere también consulta de programación anterior |
| Versiones, generación y publicación | Crear borradores, generar, activar, aprobar y publicar; requiere consulta semestral |
| Asignación y disponibilidad docente | Asignar profesores y gestionar solicitudes y aprobación de disponibilidad; requiere consulta semestral |
| Asignación de horarios y cambios | Modificar clases y gestionar solicitudes de cambio; requiere consulta semestral |
| Propuestas de horario | Preparar y guardar propuestas; requiere consulta semestral |
| Conflictos y requerimientos | Consultar observaciones, exportarlas y consultar requerimientos; requiere consulta semestral. Editar requerimientos exige también asignación de horarios |
| Salas, laboratorios y equipamiento | Administrar espacios, inventario, distribución y prioridades |
| Configuración y calendario | Consultar institución, días y módulos. Modificar exige además cuentas, perfiles y catálogos |
| Cuentas, perfiles y catálogos | Accesos, permisos, docentes y oferta institucional completa |

PROSEC comprueba los permisos también al recibir cada operación, incluso si se intenta abrir una dirección directamente. Al iniciar sesión dirige a una pantalla permitida. Si la cuenta no tiene ninguna función reconocida, informa del problema y cierra esa sesión.

## Panel de carrera

**Administrador** y **Jefe de carrera** pueden abrir el Panel de carrera con el permiso **Programación semestral**. Jefatura abre el panel al ingresar, después del cambio de contraseña si corresponde. Las acciones se habilitan según los permisos del perfil.

| Función | Acciones en el panel |
| --- | --- |
| Programación semestral | Consultar horarios, ramos, laboratorios, carga docente y versiones de una carrera. Exportar la consulta a Excel. |
| Asignación y disponibilidad docente | Asignar y confirmar al responsable del grupo. |
| Asignación de horarios y cambios | Agregar, mover o quitar módulos y asignar sus espacios. |
| Versiones, generación y publicación | Copiar una versión a un borrador y revisar su activación. |

Las ediciones de horarios, docentes y espacios se guardan en el borrador actual de un período activo y se reflejan en las demás vistas de esa programación.

## Ejemplos para preparar perfiles

Estos ejemplos son propuestas para revisión institucional; no se crean ni asignan automáticamente.

| Perfil propuesto | Funciones |
| --- | --- |
| Consulta académica | Inicio, programación semestral y programación anterior |
| Coordinación de horarios | Inicio, consulta semestral, asignación de profesores, asignación de horarios y conflictos |
| Gestión de recursos | Inicio y salas, laboratorios y equipamiento |
| Responsable de importación | Consulta de programación anterior e importación de Excel |

## Alcance y conservación

**El alcance de los perfiles es institucional.** Jefatura elige la carrera que va a gestionar en el panel. La ficha académica se vincula a la cuenta del docente desde su registro.

La actualización separa importar de consultar mediante una migración aditiva. Los perfiles y permisos directos que ya podían consultar programación anterior conservan la importación que antes incluía ese permiso. Los perfiles nuevos de consulta no la reciben automáticamente. La migración no cambia contraseñas, cuentas, perfiles asignados ni horarios. Una reversión del archivo de migración conserva las decisiones administrativas sobre permisos.

Los cambios de perfiles y la creación, modificación y eliminación de cuentas quedan registrados en el historial dentro de la misma operación. Un fallo al registrar la operación impide guardar cambios parciales. El registro no conserva contraseñas ni sus huellas de autenticación.

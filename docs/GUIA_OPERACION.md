# Guía de operación de PROSEC

Actualizada el 15 de noviembre de 2026. Describe las funciones implementadas. El estado de cierre y las limitaciones se registran en `PLAN_DE_CIERRE.md`.

## Administración y configuración

El responsable técnico dispone de `php artisan prosec:verificar` para comprobar la instalación y de `--entrega` para revisar la configuración de producción. No cambia registros ni corrige automáticamente errores; crea y elimina archivos vacíos para comprobar escritura. Las instrucciones están en `INSTALACION.md` y `RESPALDO_Y_RECUPERACION.md`. La actualización de seguridad y Excel se aplicó con autorización del usuario; el diagnóstico local no detecta errores ni avisos. La comprobación de entrega sigue siendo necesaria en el servidor institucional.

**Administración** reúne usuarios, **Perfiles y permisos**, docentes, sedes, carreras, facultades e institutos, planes, niveles, asignaturas, períodos y **Oferta por período**. Crear y editar estos catálogos requiere el permiso de administración de usuarios. Los registros se conservan: no hay eliminación de los catálogos académicos desde estas pantallas. Las modificaciones se registran en el historial.

En **Perfiles y permisos**, crea un perfil, selecciona sus funciones y luego asígnalo al crear o editar una cuenta. Para modificar un perfil utilizado, revisa las cuentas vinculadas y confirma el efecto del cambio. Administrador está protegido. Los perfiles tienen alcance institucional sobre todas las carreras; su nombre no establece una restricción por carrera. La consulta de programación anterior se puede otorgar sin permitir importar Excel. Las combinaciones y ejemplos están en [Perfiles y permisos](PERFILES_Y_PERMISOS.md).

En **Configuración**, selecciona la institución y consulta su nombre y sigla. Modificar estos datos o registrar una institución requiere tanto consulta de configuración como administración de usuarios. El perfil con permiso únicamente de consulta puede revisar los datos y el calendario, sin modificarlos.

**Identidad visual** permite personalizar el nombre de la plataforma, subtítulo, colores, logos y favicon para otra institución. Revisa la vista previa y confirma antes de guardar: la identidad se aplica a toda la instalación, incluidos acceso, correos y nuevos PDF. PROSEC es el diseño inicial y puede restaurarse conservando lo guardado. Aplicar o restaurar exige los dos permisos anteriores. Las instrucciones y formatos de imágenes están en [Identidad visual](IDENTIDAD_VISUAL.md).

**Calendario académico** reúne los días y módulos horarios compartidos por todas las instituciones y sedes. El número de cada módulo es único y los intervalos activos no pueden superponerse. Los intervalos contiguos sí se admiten. Un módulo de almuerzo se excluye de la generación; también se excluyen los intervalos que cruzan entre las 13:00 y las 14:00.

Los días y módulos ya vinculados a horarios, disponibilidad u opciones de cambio conservan su definición. Su desactivación exige que no estén siendo utilizados por horarios actuales o disponibilidad de períodos activos. Debe quedar al menos un día y un módulo activo. Antes de cambiar el calendario deben resolverse las solicitudes de disponibilidad pendientes o en revisión. Después de cambiarlo, la disponibilidad debe solicitarse y aprobarse con el nuevo calendario.

## Estructura académica

En **Administración → Docentes**, registra el profesor con su institución, nombres, apellidos y RUT o correo institucional. Los contactos y la vinculación académica son opcionales. El RUT se compara normalizando puntos, guion, espacios y mayúsculas, y el correo institucional se compara sin distinguir mayúsculas; los duplicados incluyen registros inactivos. Esto identifica el registro académico y no valida la identidad ante un organismo externo. Los accesos al sistema se gestionan por separado en Usuarios.

El docente activo puede utilizarse inmediatamente en los flujos de disponibilidad y asignación. Al corregir su RUT desde la ficha, se actualizan la cuenta vinculada y el RUT de sus versiones. Las asignaciones, solicitudes y respuestas siguen vinculadas al mismo docente. La auditoría registra el identificador anterior y el corregido. Si una cuenta personal con ese RUT ya corresponde al mismo correo, puede sustituir una cuenta provisoria que todavía no se haya utilizado. Los cambios de nombre o contacto no reescriben las copias docentes de versiones anteriores. Antes de desactivar, resuelve sus clases en programaciones semestrales actuales de períodos activos y sus solicitudes vigentes.

1. Registra la sede y, cuando corresponda, la facultad o instituto.
2. Registra la carrera, su sede principal y las demás sedes donde se imparte. Todas deben pertenecer a la misma institución.
3. Registra los planes de estudio y sus versiones. El catálogo identifica los planes; no construye automáticamente una malla curricular.
4. Registra los niveles de la carrera. Su número corresponde al semestre informado en las secciones. Un nivel con secciones históricas conserva ese número.
5. Registra las asignaturas o ramos con su código institucional. Los módulos de cátedra, laboratorio y demás actividades son una referencia del ramo. Modificarlos no recalcula actividades, grupos ni horarios de secciones existentes.

Un ramo ya utilizado conserva su código para mantener los vínculos y la correspondencia de importaciones posteriores. Se permiten ramos con el mismo nombre y códigos diferentes. No se permiten códigos duplicados dentro de la institución, incluso entre los inactivos.

Las carreras, ramos y niveles con actividad en períodos vigentes tienen protecciones para su desactivación. Las sedes y unidades académicas deben revisar primero sus registros activos dependientes. Los vínculos antiguos inactivos se conservan para consulta.

## Períodos académicos

En **Administración → Períodos académicos**, crea el año y semestre de trabajo y sus fechas opcionales. No se repite el año y semestre dentro de una institución. Las fechas deben mantener el inicio antes o en el mismo día que el término. Un período con información vinculada conserva su año y semestre; su nombre y fechas pueden corregirse.

Activar un período no desactiva otros. Inicio utiliza el período activo más reciente. Los filtros de programación permiten consultar períodos anteriores.

Para archivar, selecciona **Archivado**, marca la confirmación y guarda. Antes deben resolverse la versión semestral actual que todavía no esté publicada y las solicitudes de disponibilidad o cambio pendientes. Archivar conserva secciones, versiones, respuestas y publicaciones. No equivale a aprobar ni publicar. Puede reactivarse posteriormente sin borrar su contenido.

## Importar programación anterior y conservar la oferta manual

En **Programación anterior → Cargar programación anterior**, sube el XLSX, XLS o CSV y pulsa **Analizar archivo**. El archivo debe corresponder a un solo período. La vista **Conciliación con la oferta existente** indica carreras, ramos, docentes, secciones, actividades y grupos que se conservarán y los que se crearán. Analizar no carga registros académicos.

El período se reconoce por año y semestre aunque tenga un nombre manual diferente. Se reconocen los códigos de ramo y sección sin distinguir mayúsculas; un grupo numerado como 1 puede reconocerse aunque se llame «Práctico A» en la oferta y «G:1» en el archivo. El número debe ser inequívoco. No se elige automáticamente entre varios registros candidatos ni se mezclan identificadores externos existentes diferentes.

Al reutilizar una sección se conservan nombre, nivel, matrícula, cupos, carreras, carga de sus actividades y docentes de plantilla. Si el archivo informa una carrera o sede que no está vinculada, revisa primero esos vínculos en Administración. Las diferencias de cupos y niveles se avisan; importar no las corrige. Los registros inactivos no se reactivan. Un período archivado debe reactivarse antes de importar.

Los docentes se reconocen por RUT normalizado y correo institucional. Si esas referencias apuntan a personas distintas, corrige el archivo o el catálogo antes de continuar. Un RUT informado en otra fila del mismo correo permite reconocer al mismo docente. Las identidades contradictorias dentro del archivo se rechazan. No se reescriben nombres ni contactos del catálogo desde la importación.

Al confirmar la importación, PROSEC crea o vincula una cuenta por docente identificable. Las cuentas nuevas reciben una contraseña temporal. Para enviar los accesos, usa **Disponibilidad docente**.

Los participantes indicados por **HORARIO_PROFESOR** se fijan en los módulos correspondientes. Donde no hay detalle docente, se fijan los responsables generales de esa sección informados en el archivo; no se modifican las plantillas manuales. Un horario sin docentes puede incorporarse pendiente si tampoco tiene una plantilla previa que pueda confundirse con el archivo. Si existe esa plantilla y el archivo no informa responsables, se pide corregir la información para no atribuirle clases históricas por error.

Las observaciones de identidad o de oferta incompatible impiden confirmar. Un período con programación semestral aprobada o publicada permite reutilizar secciones, actividades y grupos ya existentes sin cambiar su definición, pero no ampliar esa oferta compartida desde la importación. El versionado independiente de las definiciones académicas sigue pendiente; los módulos sin participantes identificados deben revisarse antes de cualquier uso semestral.

Revisa el resumen y confirma. Se crea una versión de programación anterior; las versiones y horarios anteriores se conservan, y la semestral actual no se reemplaza. El análisis vence a los 30 minutos y verifica tanto el archivo como la oferta al confirmar: si cambian, vuelve a analizar. El contenido ya confirmado como última importación del período no crea otra versión idéntica, aunque cambies el nombre del archivo o el orden de sus filas. Los archivos anteriores al registro de huellas pueden requerir una primera carga revisada para disponer de esa comprobación.

El importador utiliza la primera institución configurada en el proyecto, como el flujo institucional existente; no decide automáticamente otra institución desde el archivo. La conciliación no fusiona arbitrariamente catálogos duplicados, no resuelve topes y no equivale a aprobar o publicar. Antes de usar una versión anterior como base, revisa docentes, carga, capacidades, recursos y disponibilidad.

## Preparar y trabajar una programación

La oferta de secciones y actividades puede prepararse manualmente en **Administración → Oferta por período**, además de la importación institucional existente. Los catálogos de ramos y carreras por sí solos no crean secciones ni grupos. Al importar programación anterior, revisa la comparación antes de confirmar la carga.

Para preparar la oferta manual:

1. Selecciona un período activo y elige **Crear sección**.
2. Selecciona el ramo, indica el código de sección y el nivel, y marca sus carreras. Deben pertenecer a la misma institución. Si las carreras tienen niveles registrados, el nivel seleccionado debe estar activo en ellas.
3. Informa cupos e inscritos. Puedes dejar los cupos sin informar; los grupos deben revisarse antes de comprobar capacidades.
4. Si marcas **Crear actividades desde los módulos de referencia**, se crea una actividad por cada referencia mayor que cero del ramo y un grupo inicial por actividad. Este paso solo se realiza al crear la sección.
5. En el detalle, revisa las actividades y los grupos. Puedes agregarlos manualmente, editar sus módulos por grupo y dividir la matrícula. Por ejemplo, dos grupos de laboratorio con tres módulos cada uno requieren seis módulos de laboratorio.
6. Si no hay programación semestral actual, vuelve al listado, confirma e inicia el borrador del período. Se crea vacío y actual, sin copiar horarios de la programación anterior. Requiere además permiso de generación. Si ya existe una versión actual, continúa con ella o con la copia para ajustes.
7. Utiliza los accesos **Asignar docentes** y, con el borrador iniciado, **Asignar horarios**. Los requerimientos de cada actividad se gestionan desde su acceso correspondiente cuando hay versión actual y permisos para conflictos.

La oferta se modifica en un período activo con versión actual en Borrador o todavía sin versión. No se mueve una sección a otro ramo o período, ni un grupo a otra actividad. Los códigos y niveles usados por horarios o docencia se conservan. No se retiran carreras previas de una sección con uso histórico, aunque pueden añadirse si no hay cierre que lo impida. Las definiciones ya utilizadas por versiones aprobadas o publicadas se conservan, incluso si hay otro borrador actual; la oferta todavía es compartida entre versiones.

Antes de desactivar una sección, actividad o grupo, retira sus horarios activos de la versión actual. La cantidad de módulos de una actividad no puede quedar por debajo de lo asignado a cualquiera de sus grupos. No se eliminan automáticamente los horarios anteriores. Si otro usuario cambia los datos mientras tienes un formulario abierto, actualiza y revisa antes de guardar; el sistema rechaza la edición anterior.

1. Revisa salas, laboratorios, equipamiento y sus ubicaciones por sede.
2. Define prioridades por ramo y tipo de recurso: el nivel 1 tiene preferencia sobre el 2.
3. Abre **Disponibilidad docente**. Filtra por período, elige docentes o usa **Seleccionar todos los resultados**, indica la fecha límite y confirma el envío. En el panel puedes revisar el envío, el primer ingreso, los recordatorios y la respuesta. Abre la disponibilidad para consultar sus bloques o revisar la respuesta.
4. Asigna docentes y módulos, y revisa carga, topes, capacidades y recursos. La asignación manual admite un docente sin disponibilidad declarada con la advertencia correspondiente.
5. Abre **Programación semestral**, selecciona el período y elige una programación anterior o semestral en **Versión base**.
6. Pulsa **Generar programación** y confirma. Se crea un **Borrador** independiente que completa la carga de toda la oferta y los espacios faltantes de los módulos existentes.
7. Revisa el resultado y abre **Más acciones → Revisar para activar**. Confirma la activación cuando la revisión sea correcta.

La generación conserva los días, horas, docentes y espacios ya asignados en la base. Los avisos de disponibilidad de esos módulos quedan en la revisión del borrador. Los módulos nuevos se distribuyen según la disponibilidad aprobada común a los docentes, la capacidad para cada grupo, los requerimientos y las sedes de sus carreras. Los laboratorios fijos reservan también su sala física.

Si falta una actividad o su carga, la generación utiliza los módulos de referencia del ramo. Conserva la carga que ya fue definida. Entre las opciones compatibles, busca horas tempranas y concentra las clases de cada docente para reducir días de asistencia y ventanas.

Antes de generar, completa matrícula o cupos, capacidades de los espacios, docentes asignados y disponibilidad aprobada con el calendario vigente. Corrige cualquier tope o incompatibilidad de los módulos que se conservarán. Las prioridades por ramo y tipo de recurso dan preferencia al nivel 1 sobre el 2 al distribuir los recursos restantes.

Si las restricciones impiden completar la oferta o la búsqueda alcanza su límite, el sistema muestra el motivo y revierte toda la operación. Puedes corregir los datos o preparar una selección desde **Completar programación**.

**Preparar generación** permite consultar el diagnóstico y, con una base completa compatible, reorganizar sus horas conservando docentes y espacios. Revisa su vista previa antes de guardarla como otro borrador.

## Envío de accesos docentes

La ficha debe tener RUT y correo antes de enviar. La invitación indica el RUT registrado sin puntos ni guion, la contraseña provisoria para cuentas nuevas, el período y la fecha límite. En el primer ingreso con una clave provisoria, el docente crea su contraseña personal. Después entra a **Mi disponibilidad**, marca los módulos y envía su respuesta.

Los recordatorios salen tres días y un día antes de la fecha límite si la invitación se envió y la solicitud sigue pendiente de respuesta. El sistema comprueba los recordatorios cada quince minutos desde las 09:00 en la zona horaria de la aplicación.

En esta instalación de XAMPP, la tarea de Windows **PROSEC - Correos y recordatorios** inicia los procesos al entrar a la sesión y comprueba su ejecución cada quince minutos. El equipo y MySQL deben estar encendidos para procesar los avisos. También puedes iniciarlos con **Iniciar correos PROSEC.cmd**. En un servidor institucional, configura la ejecución de `php artisan schedule:run` cada minuto, un trabajador para la cola **accesos-docentes** y una dirección de PROSEC que los docentes puedan abrir desde sus dispositivos.

Los correos usan la identidad institucional en el nombre del remitente. Los logos ligeros se cargan desde la dirección pública de PROSEC configurada en `APP_URL`. Si los archivos se publican por otro dominio, indica en `MAIL_ASSET_URL` la dirección base pública que sirve `/identidad/correo/`. Las instalaciones locales muestran el nombre del sistema y la sigla de la institución en texto.

## Completar automáticamente la carga pendiente

En **Programación semestral → Completar programación**, elige período y versión base en Borrador. Puede estar vacía, incompleta o ser un borrador no actual que quieras continuar. La institución y el período deben estar activos.

1. Filtra por carrera o busca un ramo, sección o actividad. Se muestran los grupos activos con carga pendiente.
2. Revisa sus docentes. Se utiliza la plantilla del grupo o, si no tiene una activa, la de la sección. Asigna primero los docentes que falten; quienes solo participan en un módulo anterior no se copian automáticamente a los nuevos.
3. Selecciona hasta 40 grupos visibles y una sede para cada uno. La sede debe ser común a todas las carreras vinculadas a la sección. Si no aparece ninguna, revisa las carreras y sus sedes. Los grupos ya parcialmente programados conservan su sede.
4. Elige la duración de cada módulo nuevo. Debe coincidir con la de los módulos previos del grupo. Si hay duraciones diferentes, trabaja en etapas separadas.
5. Pulsa **Preparar propuesta**. Se buscan horarios con disponibilidad aprobada común a todos los docentes, fuera del almuerzo y sin topes con las clases previas. Se eligen salas y laboratorios compatibles con capacidad, requerimientos e inventario ubicado en el espacio.
6. Revisa los nuevos módulos, sus docentes, horarios y espacios. El resumen muestra también los módulos conservados y lo que seguirá pendiente en el período. La propuesta vence a los treinta minutos y no ha creado horarios todavía.
7. Marca la confirmación y pulsa **Guardar como borrador nuevo**. Se crea una versión independiente sin reemplazar la actual. Desde ese borrador puedes continuar otra etapa; solo activa cuando su revisión global esté completa.

La base conserva sus módulos activos con los mismos días, horas, docentes efectivos y recursos. Los registros inactivos permanecen en la fuente. Antes de conservar una base hay que corregir sus topes, asignaciones inválidas y observaciones de capacidad, recursos o carga excedente. Los avisos de disponibilidad de clases manuales previas se conservan y aparecen en la propuesta; los módulos nuevos siempre requieren aprobación vigente. Antes de activar y cerrar deben resolverse también esos avisos y los pendientes de los grupos no seleccionados.

El laboratorio fijo reserva su sala y los móviles se reservan para evitar compartirlos simultáneamente. Esta modalidad elige una sala física por módulo, con un laboratorio fijo y un móvil cuando corresponda. Para dividir una clase entre varias salas o utilizar varios laboratorios del mismo tipo, realiza la asignación manual. La matrícula y las capacidades deben estar informadas para validar la propuesta. El inventario no se modifica al generar.

La búsqueda aplica la prioridad del ramo y tipo de recurso, con el nivel 1 antes del 2 para los recursos que quedan por asignar. Las clases conservadas mantienen sus asignaciones. Un cambio de datos desde la vista previa exige generar otra propuesta.

Cada etapa admite hasta 300 módulos nuevos y 4.000 activos en el resultado. La preparación y búsqueda tienen límites de recursos y tiempo: alcanzar un límite no demuestra que no exista solución. Reduce la selección o revisa alternativas de sede, espacios, requerimientos y disponibilidad. Nunca se guarda una propuesta parcial. Guardar, activar, aprobar y publicar son pasos separados.

## Revisar, aprobar y publicar

**Revisar para activar** evalúa un borrador no actual y permite activarlo cuando supera las comprobaciones globales. Activarlo conserva las otras versiones y no lo publica.

En **Aprobación y publicación**, aprueba la versión actual y luego publícala. Ambos pasos requieren revisión global sin bloqueos. La publicación conserva una copia de consulta; los cambios posteriores de los catálogos no reescriben esa copia. Los usuarios autorizados pueden consultar y filtrar el horario publicado y descargar PDF detallado o semanal.

Para corregir una versión cerrada, utiliza **Crear borrador para ajustes**. La fuente y su publicación se conservan. El borrador debe superar nuevamente la revisión y el cierre.

## Solicitar un cambio de horario

Desde un módulo editable, abre **Solicitar cambio**, conserva su espacio o elige una sala o laboratorio compatible y busca alternativas. Selecciona entre una y cinco, informa el motivo y la fecha límite. Comparte los enlaces individuales con los docentes participantes.

Las respuestas no cambian el horario. Todos los docentes deben aceptar la misma alternativa. La coordinación revisa y aplica; el sistema comprueba nuevamente disponibilidad, ocupación, recursos y estado de la versión. Si los datos cambiaron o el destino dejó de estar disponible, se conserva el horario original.

Las solicitudes pueden rechazarse o cancelarse con motivo. Un módulo con solicitudes conserva su historial y no puede eliminarse desde la asignación manual. La aprobación de un cambio no sustituye la aprobación y publicación de la programación completa.

## Manual de consulta por tareas

Abre **Manual de usuario** desde el menú de tu cuenta. La guía web muestra los temas y pasos disponibles según el perfil. El PDF completo incluye un índice navegable y los accesos por perfil en la hoja carta institucional de la UCSC. También puedes consultar `MANUAL_USUARIO.md` y `docs/manual/Manual_de_usuario_PROSEC.pdf`.

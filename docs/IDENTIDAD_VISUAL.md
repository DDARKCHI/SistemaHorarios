# Identidad visual de la instalación

Desde **Configuración → Identidad visual** puedes adaptar la plataforma para otra institución sin editar el código. PROSEC se conserva como diseño inicial.

## Personalizar

1. Si existen varias instituciones activas, selecciona la que identificará esta instalación. Consultar otra institución no aplica su identidad.
2. Indica el nombre de la plataforma y un subtítulo opcional.
3. Elige el color principal, el color de texto y un fondo claro. La vista previa cambia al editar. El texto de los botones se adapta automáticamente a colores claros u oscuros. Los enlaces se ajustan para mantener la lectura. El fondo y el color de texto deben tener contraste suficiente.
4. Carga las imágenes que necesites: logo principal, logo compacto para el menú contraído, logo institucional y favicon. Puedes conservar las actuales, sustituirlas o retirarlas para utilizar versiones automáticas con el nombre y las iniciales.
5. Revisa la vista previa, marca la confirmación del alcance y pulsa **Guardar y aplicar identidad**.

La consulta requiere el permiso de configuración. Aplicar o restaurar requiere además administración de usuarios. El cambio afecta a todas las cuentas y a quienes abran enlaces docentes de esta instalación. No cambia cuentas, permisos, horarios ni datos académicos.

Si otra persona cambia la configuración o la institución mientras editas, el sistema solicita recargar y revisar. No se guardan cambios parciales si falla la operación o su auditoría.

## Imágenes admitidas

Se admiten PNG, JPG y WebP, con un máximo de 2 MB por archivo. Los logos deben medir entre 32 y 3000 píxeles por lado. El favicon debe ser cuadrado, entre 32 y 1024 píxeles; la plataforma prepara su versión PNG de 180 px para los dispositivos compatibles. No es necesario preparar un archivo ICO.

Las imágenes se comprueban y vuelven a generar como PNG, conservando transparencia y eliminando contenido adicional. El logo principal se adapta para que un archivo muy alto no deforme correos o PDF. Si no cargas un logo o favicon, se genera una versión con el nombre o las iniciales de la plataforma. Para instituciones distintas de UCSC se usa su identificación y no se reutiliza el logo UCSC como imagen automática.

## Dónde se aplica

- Nombre y títulos de las páginas y pestañas.
- Colores de marca, botones, navegación, textos y fondo.
- Logos del menú completo, menú contraído, acceso y navegación móvil.
- Favicon y acceso directo en dispositivos compatibles.
- Acceso, recuperación y restablecimiento de contraseña y enlaces docentes.
- Asunto, nombre visible del remitente, logos y cuerpo de los correos de recuperación y contraseña actualizada.
- Logo, colores y nombre en los PDF que se generen posteriormente.

Se conserva el significado de los colores de errores, advertencias, coincidencias y disponibilidad. Los PDF ya descargados y los correos ya enviados permanecen como fueron generados. Reexportar una publicación utiliza la identidad actual y mantiene sus datos académicos conservados.

Este panel no configura direcciones, credenciales ni transporte de correo. Estos datos continúan en la configuración protegida del servidor. Aplicar una identidad no envía mensajes.

## Restaurar o volver a aplicar

En **Restaurar identidad inicial**, confirma la operación para volver a PROSEC. Las configuraciones e imágenes anteriores se conservan y puedes volver a aplicarlas desde el mismo panel. Retirar una imagen del diseño no borra el archivo anterior.

## Alcance y respaldo

Hay una identidad activa para toda la instalación. Seleccionar otra institución cambia la presentación global; no crea una web independiente ni separa los permisos o los datos de sus cuentas. Para vender instalaciones independientes, cada cliente debe disponer de su propia instalación y base con el alcance correspondiente.

Los archivos se conservan en `storage/app/private/identidad`, dentro de `storage/app`. Incluye esa carpeta y la base al respaldar o trasladar una instalación. Los logos son recursos públicos: se entregan mediante identificadores aleatorios y una ruta limitada a imágenes PNG. No se publica el resto del almacenamiento privado ni hace falta crear un enlace de almacenamiento público para esta función.

# Respaldo y recuperación de PROSEC

El respaldo debe permitir recuperar una instalación completa, no solo sus horarios. La base local de práctica es `sistema_horarios`; las otras aplicaciones de XAMPP usan bases distintas. No restaures sobre ellas ni sobre la base activa para hacer una prueba.

## Contenido y conservación

Conserva conjuntamente la base completa, `storage/app` con las importaciones privadas, el código o identificador de entrega, `composer.lock`, `package-lock.json` y el `.env` protegido con su `APP_KEY` original. El código y los recursos pueden reconstruirse; la clave y los datos privados no se recrean desde el repositorio. Incluye cualquier archivo institucional utilizado por procedimientos auxiliares de operación.

La personalización conserva logos y favicon en `storage/app/private/identidad`. Incluye esa carpeta, también sus archivos anteriores, al trasladar o restaurar una instalación; las rutas de la configuración dependen de esas imágenes. La identidad activa y sus colores se conservan en la base.

Guarda los respaldos fuera de `public`, fuera de una ruta servida por Apache y con acceso restringido. La base incluye cuentas, contactos docentes y trazabilidad; el `.env` incluye secretos. Aplica el cifrado y la política de custodia de la institución. No los adjuntes a incidencias ni los subas al repositorio. Define responsable, periodicidad, retención y una copia separada del equipo de trabajo. Verifica el respaldo antes de actualizar y después de cambios institucionales importantes.

## Copia consistente de MySQL/MariaDB

1. Comprueba el nombre y servidor de la base y la ubicación de los binarios. En este XAMPP están en `C:/xampp/mysql/bin`.
2. Reserva una ventana de mantenimiento con `php artisan down --retry=60`. Coordina también cualquier proceso que escriba directamente en la base. Evita cambios de estructura durante la copia; la transacción de respaldo no protege frente a toda operación de esquema.
3. Crea una carpeta de destino nueva y exclusiva fuera de la web. El siguiente ejemplo solicita la contraseña interactivamente y escribe el SQL directamente desde el cliente, evitando que PowerShell cambie su codificación:

```powershell
$carpetaRespaldo = 'C:\RespaldosPROSEC\respaldo-AAAA-MM-DD-HHMMSS'
New-Item -ItemType Directory -Path $carpetaRespaldo -ErrorAction Stop
$archivoRespaldo = Join-Path $carpetaRespaldo 'sistema_horarios.sql'
if (Test-Path -LiteralPath $archivoRespaldo) { throw 'El destino ya existe' }
& 'C:\xampp\mysql\bin\mysqldump.exe' --host=127.0.0.1 --user=USUARIO_RESPALDO --password --single-transaction --quick --routines --triggers --events --default-character-set=utf8mb4 "--result-file=$archivoRespaldo" sistema_horarios
if ($LASTEXITCODE -ne 0) { throw 'El volcado no terminó correctamente; no se considera un respaldo válido' }
if ((Get-Item -LiteralPath $archivoRespaldo).Length -eq 0) { throw 'El volcado está vacío' }
Get-FileHash -LiteralPath $archivoRespaldo -Algorithm SHA256
```

Sustituye el usuario por el autorizado para respaldos; puede necesitar permisos de lectura y de consulta de rutinas/eventos. No escribas la contraseña en el comando. `--single-transaction` ofrece consistencia para tablas transaccionales: comprueba que las tablas sean InnoDB. Si existen tablas de otro motor, coordina un método consistente con el administrador. Un archivo no vacío y su hash no prueban por sí solos que sea restaurable.

4. Sin reabrir escrituras, copia `storage/app`, el código de entrega y su configuración protegida a ese mismo conjunto de respaldo. No distribuyas el `.env` con el código público. Registra fecha, base, versión de código, dependencias y resultado, sin copiar secretos al registro.
5. Guarda el hash y comprueba una restauración en un servidor separado. Después de verificar la operación, ejecuta `php artisan up` para reanudar.

El programa de volcado puede crear o sobrescribir el destino incluso si falla. Por eso el ejemplo usa una carpeta nueva y detiene el proceso ante error. No reutilices un archivo de un respaldo anterior.

## Ensayo de restauración en una instalación separada

1. Utiliza un servidor de pruebas sin correo saliente y una base nueva, por ejemplo `prosec_restauracion_prueba`. No uses `sistema_horarios`. Comprueba antes que el SQL no tenga instrucciones para seleccionar o crear la base original: el volcado del ejemplo no utiliza `--databases` precisamente para no fijar ese destino.
2. Instala el código de esa misma entrega y sus dependencias, y prepara un `.env` privado que apunte exclusivamente a la base nueva y conserve la clave original de la copia. Usa correo `array` o `log` y no entregues enlaces docentes reales desde el ensayo.
3. Entra al cliente MySQL con el nombre de la base de prueba, comprobado previamente, y carga el archivo desde el propio cliente:

```text
mysql --host=127.0.0.1 --user=USUARIO_RESTAURACION --password prosec_restauracion_prueba
SOURCE C:/RespaldosPROSEC/respaldo-AAAA-MM-DD-HHMMSS/sistema_horarios.sql;
```

No ejecutes esas líneas contra la base activa. No hacen falta `DROP DATABASE`, `migrate:fresh` ni una limpieza previa cuando el destino es una base nueva.

4. Restaura los archivos privados en el almacenamiento de la copia y conserva sus permisos. Ejecuta `php artisan prosec:verificar`, comprueba el estado de migraciones y compara los recuentos con el registro del respaldo. Si la entrega exige migraciones posteriores, ensáyalas después de verificar primero el estado original restaurado.
5. Verifica acceso, programación anterior y semestral, docentes históricos, recursos, disponibilidad, cierres y PDF. Comprueba que la auditoría y los archivos necesarios estén presentes. Los enlaces firmados pueden depender de la URL original: no los envíes como parte del ensayo.
6. Documenta el resultado y el tiempo de recuperación. Solo después de una aceptación explícita del responsable se planifica una recuperación de producción.

## Reversión de una actualización

Ante un fallo de código o dependencias sin cambios de esquema, vuelve al código y a los dos archivos de dependencias de la entrega anterior, reinstala sus versiones y recupera sus recursos compilados. Mantén `.env`, clave y almacenamiento. Reinicia PHP y reconstruye las cachés que correspondan. Revisa el acceso antes de retirar mantenimiento.

Si hubo cambios de esquema o escrituras incompatibles, no uses automáticamente `migrate:rollback`: una reversión puede borrar o reinterpretar datos. Prepara el conjunto completo de respaldo en una instalación separada, valida la recuperación y decide el cambio de destino con el responsable. Toda restauración pierde las escrituras posteriores a su fecha; deben identificarse y acordarse antes de sustituir una base activa.

No se automatiza una restauración destructiva ni se crea una tarea programada desde esta guía. La política definitiva y el ensayo con la copia institucional siguen siendo parte de la aceptación de entrega.

Referencia: [opciones y límites de mariadb-dump](https://mariadb.com/docs/server/clients-and-utilities/backup-restore-and-import-clients/mariadb-dump).

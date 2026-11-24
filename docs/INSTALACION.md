# Instalación y actualización de PROSEC

Esta guía prepara una instalación técnica. No certifica la aceptación institucional ni completa las integraciones pendientes descritas en `PLAN_DE_CIERRE.md`. La instalación local existente está en `C:/xampp/htdocs/xampp/SistemaHorarios` y utiliza `sistema_horarios`; no debe reemplazarse con una base vacía.

Los requisitos siguientes corresponden a la actualización de seguridad y Excel aplicada el 15 de noviembre después de la autorización expresa del usuario. Las versiones instaladas pasaron 987 pruebas con PHP 8.5.12; GD y ZIP están habilitados en el PHP local. La configuración del servidor institucional sigue requiriendo su propia validación.

## Requisitos y archivos

Utiliza PHP 8.3 o posterior, Composer 2 y MySQL/MariaDB. Instala Node.js compatible con la versión de Vite fijada en el proyecto para compilar; no hace falta ejecutar Node como servicio para servir los recursos compilados. Se verificó PHP 8.5.12 con Node.js 26.13.0. La resolución de Composer conserva un mínimo de PHP 8.3 para evitar seleccionar componentes que exijan una versión mayor; `check-platform-reqs` comprueba el PHP que realmente ejecutas.

Activa las extensiones indicadas por Composer, especialmente PDO MySQL, GD y ZIP, además de XML, DOM, Fileinfo, MBString, OpenSSL e Iconv. En XAMPP revisa `php --ini` para identificar el archivo que utiliza la consola y `php -m` para comprobarlas. Apache/PHP-FPM puede utilizar otra configuración: reinícialo después de un cambio y comprueba también el PHP del servidor. No basta con que funcione la consola.

Conserva `composer.lock` y `package-lock.json`. La entrega instala esas versiones con `composer install` y `npm ci`; no utiliza `update` ni ignora requisitos de plataforma. Las auditorías se ejecutan nuevamente al preparar cada entrega, porque sus resultados cambian con el tiempo.

## Preparación de un servidor separado

1. Reserva una carpeta de aplicación fuera del directorio público y una base vacía con nombre diferente de las instalaciones existentes. El usuario de la aplicación debe tener permisos sobre su propia base, sin privilegios globales de administración.
2. Copia el código y los archivos de dependencias fijadas. En una instalación nueva copia `.env.example` a `.env` y completa las credenciales locales; el archivo de ejemplo no contiene una cuenta válida ni una contraseña de base.
3. Instala las dependencias y compila desde la carpeta del proyecto:

```powershell
composer install --no-dev --prefer-dist --optimize-autoloader
npm.cmd ci
npm.cmd run build
composer check-platform-reqs --no-dev
```

4. Solo para una instalación completamente nueva, genera su clave con `php artisan key:generate`. Si transfieres una instalación con datos, recupera su `.env` protegido y su clave original. Nunca ejecutes ese comando para actualizar una instalación existente.
5. Revisa la conexión y aplica únicamente las migraciones normales con `php artisan migrate --force` después de un respaldo cuando haya datos. Las migraciones iniciales registran la institución y el calendario básico. No uses `migrate:fresh`, `migrate:refresh` ni `db:wipe` en una instalación existente. El seeder genérico ya no crea cuentas de ejemplo; no necesitas ejecutarlo para preparar el acceso.
6. Solo en una instalación nueva que todavía no tiene usuarios, el responsable autorizado ejecuta `php artisan prosec:inicializar` en una consola interactiva. Indica nombre, RUT de acceso sin puntos ni dígito verificador, correo y contraseña con al menos 12 caracteres, letras y números. La contraseña y su confirmación se solicitan ocultas; si el terminal no puede ocultarlas, no se continúa. La confirmación final crea la cuenta Administrador con los permisos del sistema y registra la operación sin guardar la contraseña en la auditoría. No hay credenciales predeterminadas ni opción de contraseña en la línea de comandos. El alta y sus permisos se revierten juntos si falla la operación.
7. Concede al proceso PHP escritura en `storage` y `bootstrap/cache`, sin hacer públicos esos directorios. Conserva `storage/app/private` fuera del acceso web. No crees enlaces públicos a los archivos de importación.
8. Configura Apache, Nginx o PHP-FPM para que el directorio servido sea exactamente la carpeta `public`. No sirvas la raíz del repositorio. En XAMPP utiliza un host local cuyo `DocumentRoot` apunte a `SistemaHorarios/public`; la carpeta física bajo `htdocs` por sí sola no garantiza esa separación.

El inicializador rechaza cualquier base con usuarios, aunque ninguno conserve el rol Administrador. No permite reinicializar, ascender una cuenta existente ni recuperar contraseñas; utiliza los procedimientos de Administración y recuperación para eso. Tampoco admite ejecución no interactiva. Al transferir una instalación existente, conserva sus cuentas y permisos y omite este paso. En la base local del proyecto solo se comprobó el rechazo: no se creó una cuenta real nueva.

## Configuración de entrega

```dotenv
APP_NAME=PROSEC
APP_ENV=production
APP_DEBUG=false
APP_URL=https://direccion-institucional.example
DB_CONNECTION=mysql
DB_DATABASE=nombre_exclusivo_de_esta_instalacion
DB_USERNAME=usuario_exclusivo
DB_PASSWORD=completar_localmente
SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=lax
```

Configura HTTPS real y la URL definitiva antes de preparar enlaces firmados: cambiar de dominio puede invalidar enlaces ya emitidos. Los proxies y certificados deben prepararse conforme a la infraestructura institucional. No se habilitan proxies arbitrarios en este proyecto como parte de la instalación.

Para pruebas sin entrega externa utiliza `MAIL_MAILER=array`. La opción `log` tampoco entrega mensajes, pero guarda su contenido en los registros: limita su acceso, ya que los correos de acceso pueden contener contraseñas temporales. En producción configura el transporte SMTP institucional y verifica la entrega con una cuenta autorizada. No incluyas credenciales de correo en el repositorio ni en capturas.

Los accesos docentes se seleccionan desde **Disponibilidad docente**, con filtros por período y sede. **Revisar envío** muestra la cantidad, la sede y el plazo antes de confirmar. El seguimiento registra los envíos, el ingreso y la respuesta. Los recordatorios se preparan tres días y un día antes del vencimiento para solicitudes pendientes; las canceladas o respondidas quedan excluidas. Configura los procesos siguientes en el servidor para que funcionen el correo y los recordatorios.

### Cola y recordatorios en el servidor

Mantén un trabajador de cola supervisado, con reinicio automático si termina:

```bash
php artisan queue:work database --queue=accesos-docentes --sleep=2 --tries=1 --timeout=60
```

En Linux configura el planificador de Laravel cada minuto mediante cron, sustituyendo la ruta por la instalación real:

```cron
* * * * * cd /ruta/a/SistemaHorarios && php artisan schedule:run >> /dev/null 2>&1
```

El planificador del proyecto revisa los recordatorios cada quince minutos entre las 09:00 y las 23:45, según la zona horaria configurada en `config/app.php`. En Windows puedes utilizar `scripts/iniciar-correos.ps1` para iniciar el trabajador y `schedule:work`; configura su arranque persistente según el servidor. Evita ejecutar a la vez cron y `schedule:work` como dos planificadores de la misma instalación. Al desplegar código nuevo ejecuta `php artisan queue:restart` y comprueba que el supervisor vuelva a iniciar el trabajador.

Comprueba que no exista `public/hot` en la entrega: ese archivo hace que Laravel busque el servidor de desarrollo aunque ya hayas compilado. En desarrollo puede existir mientras Vite esté activo; no es un recurso que deba distribuirse.

Ejecuta:

```powershell
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan prosec:verificar --entrega
composer audit --no-dev
npm.cmd audit
```

El diagnóstico no guarda cambios de configuración ni aplica migraciones. Devuelve código 1 cuando detecta errores y 0 cuando no hay errores; revisa también sus avisos. `--json` devuelve un objeto sin contraseñas, claves ni detalles de excepciones. La comprobación de escritura crea y elimina un archivo vacío exclusivo en cada carpeta de trabajo. No consulta proveedores de auditoría ni prueba correo o HTTPS desde fuera del servidor.

## Actualizar una instalación existente

1. Realiza y verifica el respaldo de base, archivos privados, `.env` y clave conforme a `RESPALDO_Y_RECUPERACION.md`. Identifica la versión anterior de código y sus dos archivos de dependencias.
2. Prueba el cambio en un entorno separado sin conexiones al correo o servicios reales. Ejecuta las pruebas con SQLite en memoria y comprueba importación, exportación, disponibilidad, generación y publicación.
3. Programa una ventana breve de mantenimiento. `php artisan down --retry=60` impide nuevas operaciones HTTP; detén también procesos externos de escritura si existen. No dejes una entrega incompleta accesible.
4. Instala el código y las versiones fijadas, manteniendo `.env`, clave y almacenamiento. Aplica solo migraciones pendientes previamente revisadas. En el avance de dependencias del 15 de noviembre no se agregaron migraciones.
5. Compila o despliega los recursos compilados, retira un `public/hot` obsoleto y regenera las cachés de configuración, rutas y vistas. Reinicia los procesos PHP y de cola que mantengan código anterior.
6. Comprueba el diagnóstico y el acceso, ejecuta las pruebas de aceptación y finaliza con `php artisan up`. Si algo falla, mantén mantenimiento y sigue la reversión documentada; no reintentes cargas académicas sobre datos dudosos.

Para desarrollo local se puede usar `php artisan serve --host=127.0.0.1 --port=8000`. Ese servidor no sustituye el servidor HTTPS institucional. No cambies automáticamente el `.env` local a producción para lograr un diagnóstico verde: la revisión de entrega está destinada al servidor de destino.

## Verificación de aceptación

- Acceso de cada perfil autorizado y denegación de pantallas ajenas; recuperación de contraseña con transporte real validado.
- Oferta manual y archivo institucional representativo, revisando las coincidencias, las definiciones conservadas y cualquier identidad ambigua antes de confirmar.
- Disponibilidad aprobada, generación por etapas, clase manual excepcional, capacidades, prioridades y recursos ubicados.
- Borrador nuevo, activación, aprobación, publicación, PDF y conservación de una publicación anterior.
- Solicitud de cambio con varios docentes, vencimiento, acuerdo y rechazo de una alternativa ocupada.
- Copia y restauración en una base separada, con recuentos e integridad comprobados.

Fuentes técnicas consultadas: [despliegue de Laravel 12](https://laravel.com/docs/12.x/deployment), [migración de Laravel Excel 4](https://docs.laravel-excel.com/4.x/getting-started/upgrade.html) y [versiones mantenidas de Vite](https://vite.dev/releases). Los límites funcionales de PROSEC dependen del código y las pruebas locales, no de esas guías.

## Personalizar para otra institución

Después de preparar el acceso administrativo, abre **Configuración → Identidad visual**. Puedes aplicar nombre, subtítulo, colores, logos y favicon sin editar archivos. PROSEC es el diseño inicial y existe una opción de restauración. Consulta `IDENTIDAD_VISUAL.md` para formatos, permisos y alcance.

Cada instalación tiene una identidad activa. Esto permite adaptar la presentación de una instalación independiente para un cliente; no crea aislamiento entre instituciones que compartan la misma base. Conserva `storage/app/private/identidad` junto con la base al trasladar o respaldar el sistema. Las imágenes no necesitan un enlace público de almacenamiento. La dirección y el transporte del correo siguen configurándose en el entorno protegido del servidor; su nombre visible utiliza la marca elegida.

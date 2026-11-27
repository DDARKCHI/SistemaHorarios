# PROSEC · Programación Semestral de Cursos

Aplicación de práctica para gestionar oferta académica, docentes, disponibilidad, salas y recursos, horarios, reemplazos, revisión de conflictos y publicaciones. Mantiene la identidad institucional PROSEC.

Incluye generación y ajuste de horarios, asignación de salas y laboratorios, prioridades de recursos, acceso y seguimiento de disponibilidad docente con filtro por sede, confirmación de envíos, configuración visual y manual de usuario. Consulta las guías siguientes para instalarlo y operarlo.

- [Guía de operación](docs/GUIA_OPERACION.md): flujo de trabajo y límites de cada función.
- [Instalación y actualización](docs/INSTALACION.md): requisitos y preparación del servidor.
- [Respaldo y recuperación](docs/RESPALDO_Y_RECUPERACION.md): conservación de datos, archivos y clave.
- [Estado de cierre](docs/PLAN_DE_CIERRE.md): avances comprobados y pendientes.

## Requisitos

La aplicación requiere PHP 8.3 o posterior con las extensiones requeridas por Composer, incluidas GD, ZIP y PDO MySQL; Composer 2, MySQL/MariaDB y Node.js compatible con Vite 6. Los archivos `composer.lock` y `package-lock.json` fijan las versiones de cada entrega. El entorno local comprobado utiliza PHP 8.5.12 y Node.js 26.13.0; no se declara probada cada combinación de versiones permitida. GD y ZIP están habilitados en el PHP local y los requisitos de plataforma se verifican sin excepciones.

## Descargar e instalar

El repositorio público está en [GitHub](https://github.com/DDARKCHI/SistemaHorarios). Puedes descargar el código desde **Code → Download ZIP** o clonarlo:

```bash
git clone https://github.com/DDARKCHI/SistemaHorarios.git
cd SistemaHorarios
```

Sigue la [guía de instalación](docs/INSTALACION.md) para configurar el servidor, instalar dependencias, compilar los recursos y preparar la base. El servidor web debe apuntar a `public`.

Para probar PROSEC con horarios de ejemplo, importa la [base de demostración](database/distribucion/sistema_horarios_demo.sql) en una base vacía. Incluye el esquema y una programación ficticia con sala y laboratorio. Para trasladar una instalación activa y sus datos, sigue la [guía de respaldo y recuperación](docs/RESPALDO_Y_RECUPERACION.md).

El [manual completo en PDF](docs/manual/Manual_de_usuario_PROSEC.pdf) está incluido. Dentro del sistema, la ayuda adapta sus instrucciones a los permisos de cada usuario.

## Comprobaciones

```powershell
php artisan prosec:verificar
php artisan prosec:verificar --entrega
composer check-platform-reqs
composer audit
npm.cmd audit
php vendor/phpunit/phpunit/phpunit --do-not-cache-result
npm.cmd run build
```

El diagnóstico comprueba requisitos y configuración sin modificar registros. Para verificar permisos crea y retira archivos vacíos temporales. `--entrega` añade las comprobaciones de producción y `--json` permite obtener el resultado estructurado sin secretos. Un código de salida distinto de cero indica errores; revisa también los avisos.

Las pruebas de datos usan SQLite en memoria. No ejecutes cargas de ejemplo, restauraciones ni migraciones de reinicio sobre una instalación existente. No regeneres su `APP_KEY`. El servidor web debe servir exclusivamente `public`.

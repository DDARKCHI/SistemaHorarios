<?php

namespace App\Services;

use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\DB;

class RevisionInstalacion
{
    /** Diagnóstico local de consulta. Nunca devuelve secretos ni excepciones de conexión. */
    public function revisar(bool $entrega = false): array
    {
        $filas = [];
        $agregar = static function (string $id, bool $cumple, string $correcto, string $pendiente, bool $bloqueante = true) use (&$filas) {
            $filas[] = ['id' => $id, 'estado' => $cumple ? 'correcto' : ($bloqueante ? 'error' : 'aviso'), 'detalle' => $cumple ? $correcto : $pendiente];
        };
        $agregar('php', PHP_VERSION_ID >= 80300, 'PHP compatible con el mínimo 8.3.', 'Se requiere PHP 8.3 o posterior.');
        $extensiones = ['bcmath', 'ctype', 'curl', 'dom', 'fileinfo', 'filter', 'gd', 'iconv', 'libxml', 'mbstring', 'openssl', 'pdo', 'pdo_mysql', 'simplexml', 'tokenizer', 'xml', 'xmlreader', 'xmlwriter', 'zip', 'zlib'];
        $faltan = array_values(array_filter($extensiones, fn ($e) => ! extension_loaded($e)));
        $agregar('extensiones', ! $faltan, 'Extensiones PHP disponibles, incluidas GD y ZIP.', 'Habilita las extensiones: '.implode(', ', $faltan).'.');
        $clave = config('app.key');
        $clave = is_string($clave) ? $clave : '';
        if (str_starts_with($clave, 'base64:')) $clave = base64_decode(substr($clave, 7), true) ?: '';
        $agregar('clave', Encrypter::supported($clave, config('app.cipher', 'AES-256-CBC')), 'Clave de aplicación válida.', 'Falta una clave válida. En una instalación existente recupera su clave original; no la regeneres.');
        $agregar('almacenamiento', collect([storage_path('framework/cache/data'), storage_path('framework/sessions'), storage_path('framework/views'), storage_path('logs'), base_path('bootstrap/cache')])->every(fn ($p) => $this->permiteEscribir($p)),
            'Carpetas de trabajo disponibles para escritura.', 'Revisa la existencia y permisos de storage y bootstrap/cache.');
        $agregar('recursos', $this->manifiestoValido(public_path('build')), 'CSS y JavaScript compilados disponibles.', 'Ejecuta npm ci y npm run build; revisa el manifiesto y sus archivos.');
        $agregar('servidor_desarrollo', ! file_exists(public_path('hot')), 'Se utilizan los recursos compilados.', 'Existe public/hot: la web intentará usar el servidor de desarrollo. Retíralo al entregar.', $entrega);
        $agregar('secretos_publicos', ! file_exists(public_path('.env')) && ! file_exists(public_path('composer.json')) && ! is_dir(public_path('.git')),
            'No se encontraron los archivos privados comprobados en public.', 'Hay archivos privados dentro de public. Retíralos del directorio servido.');

        $motor = null; $conectada = false;
        try {
            DB::connection()->getPdo();
            $conectada = true;
            $motor = DB::connection()->getDriverName();
            $agregar('conexion', true, 'Conexión con la base configurada disponible.', '');
            $migrador = app('migrator');
            $repositorio = $migrador->getRepository();
            $pendientes = $repositorio->repositoryExists()
                ? array_diff(array_keys($migrador->getMigrationFiles(database_path('migrations'))), $repositorio->getRan()) : ['sin_instalar'];
            $agregar('migraciones', ! $pendientes, 'Migraciones del proyecto aplicadas.', 'Hay migraciones pendientes o la base todavía no está instalada. Respáldala antes de actualizar.');
        } catch (\Throwable) {
            $agregar($conectada ? 'migraciones' : 'conexion', false, '', $conectada
                ? 'No fue posible consultar el estado de migraciones. Revisa el repositorio y los permisos de lectura.'
                : 'No fue posible comprobar la base. Revisa el servicio, las credenciales y los permisos de consulta.');
        }

        if ($entrega) {
            $agregar('entorno', app()->environment('production'), 'Entorno de producción seleccionado.', 'Configura APP_ENV=production en el servidor de entrega.');
            $agregar('depuracion', config('app.debug') === false, 'Depuración desactivada.', 'Configura APP_DEBUG=false antes de exponer el sistema.');
            $url = filter_var(config('app.url'), FILTER_VALIDATE_URL);
            $host = $url ? strtolower((string) parse_url($url, PHP_URL_HOST)) : '';
            $https = $url && parse_url($url, PHP_URL_SCHEME) === 'https' && $host && ! in_array($host, ['localhost', '127.0.0.1', '::1', '[::1]'], true);
            $agregar('url', (bool) $https, 'URL HTTPS de entrega configurada.', 'Configura APP_URL con la dirección HTTPS del servidor institucional.');
            $agregar('sesiones', config('session.secure') === true && config('session.http_only') === true && in_array(config('session.same_site'), ['lax', 'strict'], true),
                'Cookies de sesión con protección HTTPS y HTTP-only.', 'Revisa SESSION_SECURE_COOKIE=true, HTTP-only y SameSite=lax o strict.');
            $agregar('sesiones_cifradas', config('session.encrypt') === true, 'Almacenamiento de sesión cifrado.', 'Se recomienda SESSION_ENCRYPT=true en la entrega.', false);
            $agregar('motor_datos', $motor === 'mysql', 'Motor MySQL de la instalación institucional.', 'Valida MySQL para la entrega; SQLite se utiliza en las pruebas.', false);
            $agregar('correo', ! in_array(config('mail.default'), ['log', 'array'], true), 'Transporte de correo configurado; requiere prueba de entrega aparte.', 'El correo está en modo de prueba. La recuperación por correo requiere configurar y validar el transporte.', false);
        }
        return ['modo' => $entrega ? 'entrega' : 'local', 'errores' => count(array_filter($filas, fn ($f) => $f['estado'] === 'error')),
            'avisos' => count(array_filter($filas, fn ($f) => $f['estado'] === 'aviso')), 'comprobaciones' => $filas];
    }

    private function permiteEscribir(string $directorio): bool
    {
        if (! is_dir($directorio)) return false;
        // En Windows/OneDrive, is_writable puede informar falso aunque permita escribir.
        // Probar un archivo vacío exclusivo y retirarlo; no toca archivos existentes.
        $prueba = $directorio.'/.prosec-verificacion-'.bin2hex(random_bytes(12));
        $archivo = @fopen($prueba, 'x');
        if ($archivo === false) return false;
        fclose($archivo);
        return @unlink($prueba);
    }

    private function manifiestoValido(string $directorio): bool
    {
        $ruta = $directorio.'/manifest.json';
        if (! is_readable($ruta)) return false;
        try { $manifiesto = json_decode(file_get_contents($ruta), true, 32, JSON_THROW_ON_ERROR); }
        catch (\Throwable) { return false; }
        $raiz = realpath($directorio);
        foreach (['resources/css/app.css', 'resources/js/app.js'] as $entrada) {
            $nombre = $manifiesto[$entrada]['file'] ?? null;
            $extension = pathinfo($entrada, PATHINFO_EXTENSION);
            if (! is_string($nombre) || ! preg_match('~\Aassets/[a-zA-Z0-9_.-]+\.'.$extension.'\z~D', $nombre)) return false;
            $archivo = realpath($directorio.'/'.$nombre);
            if (! $archivo || ! is_file($archivo) || ! str_starts_with($archivo, $raiz.DIRECTORY_SEPARATOR)) return false;
        }
        return true;
    }
}

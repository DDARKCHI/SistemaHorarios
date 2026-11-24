<?php

namespace App\Console\Commands;

use App\Services\RevisionInstalacion;
use Illuminate\Console\Command;

class VerificarInstalacion extends Command
{
    protected $signature = 'prosec:verificar {--entrega : Comprueba también la configuración de producción} {--json : Devuelve el diagnóstico sin secretos en JSON}';
    protected $description = 'Comprueba requisitos, recursos, conexión y configuración sin modificar datos';

    public function handle(RevisionInstalacion $revision): int
    {
        $resultado = $revision->revisar((bool) $this->option('entrega'));
        if ($this->option('json')) $this->line(json_encode($resultado, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        else {
            $this->info('Revisión de instalación PROSEC · '.$resultado['modo']);
            $this->table(['Comprobación', 'Estado', 'Detalle'], array_map(fn ($f) => [$f['id'], $f['estado'], $f['detalle']], $resultado['comprobaciones']));
            $this->line($resultado['errores'].' errores · '.$resultado['avisos'].' avisos. No se modificaron datos.');
            $this->comment('Esta revisión no sustituye las pruebas funcionales, la auditoría de dependencias ni una prueba de restauración del respaldo.');
        }
        return $resultado['errores'] ? self::FAILURE : self::SUCCESS;
    }
}

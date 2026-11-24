<?php

namespace App\Console\Commands;

use App\Services\InstalacionInicial;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

class InicializarProsec extends Command
{
    protected $signature = 'prosec:inicializar';
    protected $description = 'Prepara la primera cuenta administrativa únicamente en una instalación sin usuarios';

    public function handle(InstalacionInicial $instalacion): int
    {
        try {
            $instalacion->comprobar();
            if (! $this->input->isInteractive()) {
                $this->error('El alta inicial requiere una consola interactiva. No se admiten contraseñas como argumentos.');
                return self::FAILURE;
            }
            $this->info('Alta inicial de PROSEC. Solo se admite cuando todavía no existen usuarios.');
            $datos = ['name' => $this->ask('Nombre del administrador'), 'rut' => $this->ask('RUT de acceso, sin puntos ni dígito verificador'),
                'email' => $this->ask('Correo institucional')];
            $this->comment('La contraseña debe tener al menos 12 caracteres, con letras y números. No se mostrará al escribir.');
            $datos['password'] = $this->secret('Contraseña', false);
            $datos['password_confirmation'] = $this->secret('Repetir contraseña', false);
            if (! $this->confirm('¿Crear la primera cuenta con rol Administrador en la base configurada?', false)) {
                $this->comment('No se creó ninguna cuenta.');
                return self::SUCCESS;
            }
            $instalacion->crear($datos);
            $this->info('Acceso inicial preparado. Ya puedes ingresar con el RUT y la contraseña elegidos.');
            return self::SUCCESS;
        } catch (ValidationException $e) {
            foreach ($e->validator?->errors()->all() ?? collect($e->errors())->flatten()->all() as $mensaje) $this->error($mensaje);
            return self::FAILURE;
        } catch (\Throwable) {
            $this->error('No se pudo completar el alta inicial. Los cambios de esta operación se revirtieron. Revisa la instalación.');
            return self::FAILURE;
        }
    }
}

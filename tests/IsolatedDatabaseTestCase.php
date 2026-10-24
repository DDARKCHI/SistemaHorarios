<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Illuminate\Foundation\Testing\RefreshDatabase;

abstract class IsolatedDatabaseTestCase extends TestCase
{
    use RefreshDatabase;

    public function createApplication()
    {
        $app = require dirname(__DIR__).'/bootstrap/app.php';
        $this->traitsUsedByTest = array_flip(class_uses_recursive(static::class));

        // Configurar antes de registrar proveedores: ninguna prueba usa la base de .env.
        $app->afterBootstrapping(LoadConfiguration::class, function ($app) {
            $app['config']->set([
                'app.env' => 'testing',
                'database.default' => 'sqlite',
                'database.connections.sqlite' => [
                    'driver' => 'sqlite',
                    'database' => ':memory:',
                    'prefix' => '',
                    'foreign_key_constraints' => true,
                ],
                'cache.default' => 'array',
                'session.driver' => 'array',
                'mail.default' => 'array',
                'queue.default' => 'sync',
                'logging.default' => 'stderr',
                'hashing.bcrypt.rounds' => 4,
            ]);
            $app->detectEnvironment(fn () => 'testing');
        });
        $app->make(Kernel::class)->bootstrap();

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }
}

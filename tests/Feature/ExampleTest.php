<?php

namespace Tests\Feature;

use Tests\IsolatedDatabaseTestCase;

class ExampleTest extends IsolatedDatabaseTestCase
{
    public function test_una_visita_sin_sesion_redirige_al_login(): void
    {
        $response = $this->get('/');

        $response->assertRedirect(route('login'));
    }
}

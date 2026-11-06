<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * Verifica que la raíz redirige al panel administrativo y la pantalla de login carga con la línea gráfica de Diesel Nacional.
     */
    public function test_the_application_redirects_to_admin_and_loads_login_page(): void
    {
        $response = $this->get('/');
        $response->assertRedirect('/admin');

        $loginResponse = $this->get('/admin/login');
        $loginResponse->assertStatus(200);
        $loginResponse->assertSee('logo-diesel-gold.svg');
        $loginResponse->assertDontSee('Entre a su cuenta');
    }
}

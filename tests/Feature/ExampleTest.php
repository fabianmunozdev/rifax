<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_root_route_shows_the_public_landing(): void
    {
        // La raíz ya no redirige al panel: muestra la landing pública de rifas.
        $this->get('/')->assertOk();
    }

    public function test_the_admin_login_page_is_available(): void
    {
        $response = $this->get('/admin/login');

        $response->assertOk();
    }
}

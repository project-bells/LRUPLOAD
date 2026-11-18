<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_root_redirects_guest_to_login_and_login_has_aikchol_branding_and_security_headers(): void
    {
        $this->get('/')->assertRedirect(route('login'));

        $response = $this->get(route('login'));
        $response->assertOk();
        $response->assertSee('Aikchol Hospital');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('Referrer-Policy', 'no-referrer');
    }
}

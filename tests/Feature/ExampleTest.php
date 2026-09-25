<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * This repo deploys as two Railway services with two different login
     * pages, switched by the LOGIN_MODE env var — see routes/web.php's own
     * comment above the `/` and `/login` routes for the full story. Asserts
     * `/platform/login` here because `.env.example` (what CI's "Prepare
     * .env" step copies) sets `LOGIN_MODE=platform`, matching CI's own
     * disposable database, which is always Platform-schema-only.
     */
    public function test_the_application_redirects_to_login(): void
    {
        $response = $this->get('/');

        $response->assertRedirect('/platform/login');
    }
}

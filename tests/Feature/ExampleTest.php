<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * This project's database only has the Platform schema (no legacy
     * `employees` table), so `/` and `/login` both redirect to
     * `/platform/login` — see routes/web.php's own comment on why.
     */
    public function test_the_application_redirects_to_login(): void
    {
        $response = $this->get('/');

        $response->assertRedirect('/platform/login');
    }
}

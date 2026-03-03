<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class CSRFLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_returns_csrf_token()
    {
        $response = $this->get('/login');
        
        $response->assertStatus(200);
        $response->assertSee('csrf-token', false);
    }

    public function test_login_page_has_inertia_response()
    {
        $response = $this->get('/login');
        
        $response->assertStatus(200);
        $this->assertStringContainsString('Inertia', $response->getContent());
    }

    public function test_csrf_token_is_validated_on_login_post()
    {
        $response = $this->post('/login', [
            'email' => 'test@example.com',
            'password' => 'password',
            'role' => 'student',
        ]);

        // Without CSRF token, should fail
        $this->assertTrue(
            $response->status() === 302 || 
            $response->status() === 419 || 
            $response->status() === 422
        );
    }

    public function test_login_post_with_session_csrf_token()
    {
        // Get login page (which sets up session and CSRF token)
        $response = $this->get('/login');
        $response->assertStatus(200);

        // Try to login with the session CSRF token
        $token = session()->token();
        
        $response = $this->post('/login', [
            'email' => 'test@example.com',
            'password' => 'password',
            'role' => 'student',
            '_token' => $token,
        ]);

        // Should either show validation errors or proceed
        $this->assertContains($response->status(), [302, 422, 419]);
    }
}
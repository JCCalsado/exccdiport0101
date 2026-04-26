<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered()
    {
        $response = $this->get(route('register'));

        $response->assertStatus(200);
    }

    public function test_new_users_can_register()
    {
        $response = $this->post(route('register.store'), [
            'first_name' => 'Test',
            'last_name' => 'User',
            'middle_initial' => 'A',
            'email' => 'test@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'birthday' => '2000-01-01',
            'year_level' => '1st Year',
            'course' => 'BS Computer Science',
            'address' => '123 Test Street',
            'phone' => '09123456789',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }
}
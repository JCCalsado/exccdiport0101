<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_visit_the_dashboard()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('dashboard'));
        // Student users are redirected to their role-specific dashboard
        $response->assertRedirect(route('student.dashboard'));
    }

    public function test_admin_users_are_redirected_to_admin_dashboard()
    {
        $user = User::factory()->create(['role' => 'admin']);
        $this->actingAs($user);

        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('admin.dashboard'));
    }

    public function test_accounting_users_are_redirected_to_accounting_dashboard()
    {
        $user = User::factory()->create(['role' => 'accounting']);
        $this->actingAs($user);

        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('accounting.dashboard'));
    }
}
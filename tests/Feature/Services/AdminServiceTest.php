<?php

namespace Tests\Feature\Services;

use Tests\TestCase;
use App\Models\User;
use App\Enums\UserRoleEnum;
use App\Services\AdminService;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AdminServiceTest extends TestCase
{
    use RefreshDatabase;

    protected AdminService $adminService;
    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->adminService = app(AdminService::class);
        $this->admin = User::factory()->create([
            'role' => UserRoleEnum::ADMIN,
            'is_active' => true,
        ]);
    }

    /** @test */
    public function create_admin_creates_new_admin_user(): void
    {
        $data = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'johndoe@example.com',
            'password' => 'SecurePassword1234567',
            'department' => 'Administrator',
        ];

        $admin = $this->adminService->createAdmin($data, $this->admin->id);

        $this->assertNotNull($admin->id);
        $this->assertEquals('johndoe@example.com', $admin->email);
        $this->assertTrue($admin->is_active);
    }

    /** @test */
    public function deactivate_admin_sets_is_active_to_false(): void
    {
        $admin = User::factory()->create(['role' => UserRoleEnum::ADMIN, 'is_active' => true]);
        $this->adminService->deactivateAdmin($admin);
        $admin->refresh();
        $this->assertFalse($admin->is_active);
    }

    /** @test */
    public function get_admin_stats_returns_correct_statistics(): void
    {
        $stats = $this->adminService->getAdminStats();
        $this->assertIsArray($stats);
        $this->assertArrayHasKey('total_admins', $stats);
    }

    /** @test */
    public function has_permission_returns_true_for_active_admin(): void
    {
        $this->assertTrue($this->adminService->hasPermission($this->admin, 'manage_fees'));
    }

    /** @test */
    public function has_permission_returns_false_for_inactive_admin(): void
    {
        $inactiveAdmin = User::factory()->create(['role' => UserRoleEnum::ADMIN, 'is_active' => false]);
        $this->assertFalse($this->adminService->hasPermission($inactiveAdmin, 'manage_fees'));
    }
}
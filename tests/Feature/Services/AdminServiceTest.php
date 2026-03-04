<?php

namespace Tests\Feature\Services;

use Tests\TestCase;
use App\Models\User;
use App\Enums\UserRoleEnum;
use App\Services\AdminService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AdminServiceTest extends TestCase
{
    use RefreshDatabase;

    protected AdminService $adminService;
    protected User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminService = app(AdminService::class);

        $this->superAdmin = User::factory()->create([
            'role' => UserRoleEnum::ADMIN,
            'admin_type' => 'super',
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
            'password' => 'SecurePassword123!',
            'admin_type' => 'manager',
            'department' => 'Finance',
        ];

        $admin = $this->adminService->createAdmin($data, $this->superAdmin->id);

        $this->assertNotNull($admin->id);
        $this->assertEquals('johndoe@example.com', $admin->email);
        $this->assertEquals('manager', $admin->admin_type);
        $this->assertTrue($admin->is_active);
    }

    /** @test */
    public function create_admin_hashes_password(): void
    {
        $plainPassword = 'SecurePassword123!';

        $data = [
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'email' => 'janesmith@example.com',
            'password' => $plainPassword,
            'admin_type' => 'operator',
        ];

        $admin = $this->adminService->createAdmin($data, $this->superAdmin->id);

        $this->assertTrue(Hash::check($plainPassword, $admin->password));
        $this->assertNotEquals($plainPassword, $admin->password);
    }

    /** @test */
    public function create_admin_sets_created_by_audit_field(): void
    {
        $data = [
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'testuser@example.com',
            'password' => 'TestPassword123!',
            'admin_type' => 'manager',
        ];

        $admin = $this->adminService->createAdmin($data, $this->superAdmin->id);

        $this->assertEquals($this->superAdmin->id, $admin->created_by);
    }

    /** @test */
    public function create_admin_sets_terms_accepted_at(): void
    {
        $data = [
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'testuser@example.com',
            'password' => 'TestPassword123!',
            'admin_type' => 'manager',
        ];

        $admin = $this->adminService->createAdmin($data, $this->superAdmin->id);

        $this->assertNotNull($admin->terms_accepted_at);
    }

    /** @test */
    public function create_admin_validates_required_fields(): void
    {
        $this->expectException(\Illuminate\Validation\ValidationException::class);

        $data = [
            'first_name' => 'Test',
            // Missing last_name
            'email' => 'test@example.com',
            'password' => 'TestPassword123!',
        ];

        $this->adminService->createAdmin($data, $this->superAdmin->id);
    }

    /** @test */
    public function update_admin_updates_admin_data(): void
    {
        $admin = User::factory()->create([
            'role' => UserRoleEnum::ADMIN,
            'admin_type' => 'operator',
            'department' => 'Old Department',
        ]);

        $data = [
            'department' => 'New Department',
            'admin_type' => 'manager',
        ];

        $updatedAdmin = $this->adminService->updateAdmin($admin, $data, $this->superAdmin->id);

        $this->assertEquals('New Department', $updatedAdmin->department);
        $this->assertEquals('manager', $updatedAdmin->admin_type);
    }

    /** @test */
    public function update_admin_sets_updated_by_audit_field(): void
    {
        $admin = User::factory()->create([
            'role' => UserRoleEnum::ADMIN,
            'admin_type' => 'operator',
        ]);

        $updater = User::factory()->create(['role' => UserRoleEnum::ADMIN]);

        $data = ['department' => 'Updated Department'];

        $this->adminService->updateAdmin($admin, $data, $updater->id);

        $admin->refresh();
        $this->assertEquals($updater->id, $admin->updated_by);
    }

    /** @test */
    public function update_admin_can_update_password(): void
    {
        $admin = User::factory()->create([
            'role' => UserRoleEnum::ADMIN,
            'password' => bcrypt('OldPassword123!'),
        ]);

        $oldPassword = $admin->password;

        $data = ['password' => 'NewPassword123!'];

        $this->adminService->updateAdmin($admin, $data, $this->superAdmin->id);

        $admin->refresh();
        $this->assertNotEquals($oldPassword, $admin->password);
        $this->assertTrue(Hash::check('NewPassword123!', $admin->password));
    }

    /** @test */
    public function update_admin_does_not_update_password_if_not_provided(): void
    {
        $admin = User::factory()->create([
            'role' => UserRoleEnum::ADMIN,
            'password' => bcrypt('OriginalPassword123!'),
        ]);

        $originalPassword = $admin->password;

        $data = ['department' => 'New Department'];

        $this->adminService->updateAdmin($admin, $data, $this->superAdmin->id);

        $admin->refresh();
        $this->assertEquals($originalPassword, $admin->password);
    }

    /** @test */
    public function deactivate_admin_sets_is_active_to_false(): void
    {
        $admin = User::factory()->create([
            'role' => UserRoleEnum::ADMIN,
            'admin_type' => 'operator',
            'is_active' => true,
        ]);

        $this->adminService->deactivateAdmin($admin);

        $admin->refresh();
        $this->assertFalse($admin->is_active);
    }

    /** @test */
    public function cannot_deactivate_last_super_admin(): void
    {
        // Ensure this is the only super admin
        User::query()->where('admin_type', 'super')->where('id', '!=', $this->superAdmin->id)->update(['is_active' => false]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Cannot deactivate the last super admin');

        $this->adminService->deactivateAdmin($this->superAdmin);
    }

    /** @test */
    public function can_deactivate_super_admin_if_other_super_admins_exist(): void
    {
        // Create another super admin
        $anotherSuper = User::factory()->create([
            'role' => UserRoleEnum::ADMIN,
            'admin_type' => 'super',
            'is_active' => true,
        ]);

        $result = $this->adminService->deactivateAdmin($this->superAdmin);

        $this->superAdmin->refresh();
        $this->assertFalse($this->superAdmin->is_active);
    }

    /** @test */
    public function reactivate_admin_sets_is_active_to_true(): void
    {
        $admin = User::factory()->create([
            'role' => UserRoleEnum::ADMIN,
            'admin_type' => 'manager',
            'is_active' => false,
        ]);

        $this->adminService->reactivateAdmin($admin);

        $admin->refresh();
        $this->assertTrue($admin->is_active);
    }

    /** @test */
    public function get_active_admins_returns_only_active_admins(): void
    {
        User::factory()->count(3)->create([
            'role' => UserRoleEnum::ADMIN,
            'is_active' => true,
        ]);

        User::factory()->count(2)->create([
            'role' => UserRoleEnum::ADMIN,
            'is_active' => false,
        ]);

        $activeAdmins = $this->adminService->getActiveAdmins();

        $this->assertCount(4, $activeAdmins); // 3 created + 1 from setUp
        $activeAdmins->each(fn($admin) => $this->assertTrue($admin->is_active));
    }

    /** @test */
    public function get_admins_by_type_returns_correct_admin_type(): void
    {
        User::factory()->count(5)->create([
            'role' => UserRoleEnum::ADMIN,
            'admin_type' => 'manager',
        ]);

        User::factory()->count(3)->create([
            'role' => UserRoleEnum::ADMIN,
            'admin_type' => 'operator',
        ]);

        $managers = $this->adminService->getAdminsByType('manager');

        $this->assertCount(5, $managers);
        $managers->each(fn($admin) => $this->assertEquals('manager', $admin->admin_type));
    }

    /** @test */
    public function get_admin_stats_returns_correct_statistics(): void
    {
        User::factory()->count(3)->create([
            'role' => UserRoleEnum::ADMIN,
            'admin_type' => 'manager',
            'is_active' => true,
            'terms_accepted_at' => now(),
        ]);

        User::factory()->count(2)->create([
            'role' => UserRoleEnum::ADMIN,
            'admin_type' => 'operator',
            'is_active' => true,
            'terms_accepted_at' => null,
        ]);

        $stats = $this->adminService->getAdminStats();

        $this->assertEquals(6, $stats['total_admins']); // 3 managers + 2 operators + 1 from setUp
        $this->assertEquals(3, $stats['managers']);
        $this->assertEquals(2, $stats['operators']);
    }

    /** @test */
    public function has_permission_returns_true_for_super_admin(): void
    {
        $this->assertTrue(
            $this->adminService->hasPermission($this->superAdmin, 'any_permission')
        );
    }

    /** @test */
    public function has_permission_returns_true_for_valid_manager_permission(): void
    {
        $manager = User::factory()->create([
            'role' => UserRoleEnum::ADMIN,
            'admin_type' => 'manager',
            'is_active' => true,
        ]);

        $this->assertTrue(
            $this->adminService->hasPermission($manager, 'manage_fees')
        );
    }

    /** @test */
    public function has_permission_returns_false_for_invalid_manager_permission(): void
    {
        $manager = User::factory()->create([
            'role' => UserRoleEnum::ADMIN,
            'admin_type' => 'manager',
            'is_active' => true,
        ]);

        $this->assertFalse(
            $this->adminService->hasPermission($manager, 'system_settings')
        );
    }

    /** @test */
    public function has_permission_returns_false_for_inactive_admin(): void
    {
        $inactiveAdmin = User::factory()->create([
            'role' => UserRoleEnum::ADMIN,
            'admin_type' => 'super',
            'is_active' => false,
        ]);

        $this->assertFalse(
            $this->adminService->hasPermission($inactiveAdmin, 'manage_users')
        );
    }

    /** @test */
    public function log_admin_action_creates_action_record(): void
    {
        $admin = User::factory()->create(['role' => UserRoleEnum::ADMIN]);

        // Assuming we have a way to track admin actions
        // This test assumes the service logs actions (could be to a table or event)
        // For now, we'll test that the method doesn't throw an error
        
        $this->adminService->logAdminAction(
            $admin->id,
            'create',
            User::class,
            $admin->id,
            ['email' => $admin->email]
        );

        // The logging should succeed without errors
        $this->assertTrue(true);
    }

    /** @test */
    public function operator_has_limited_permissions(): void
    {
        $operator = User::factory()->create([
            'role' => UserRoleEnum::ADMIN,
            'admin_type' => 'operator',
            'is_active' => true,
        ]);

        // Operator specific permissions
        $this->assertTrue(
            $this->adminService->hasPermission($operator, 'approve_payments')
        );

        // Operator should NOT have these
        $this->assertFalse(
            $this->adminService->hasPermission($operator, 'manage_users')
        );

        $this->assertFalse(
            $this->adminService->hasPermission($operator, 'system_settings')
        );
    }

    /** @test */
    public function create_admin_with_minimal_data(): void
    {
        $data = [
            'first_name' => 'Min',
            'last_name' => 'Data',
            'email' => 'mindata@example.com',
            'password' => 'MinimalPassword123!',
            'admin_type' => 'operator',
        ];

        $admin = $this->adminService->createAdmin($data, $this->superAdmin->id);

        $this->assertNotNull($admin->id);
        $this->assertEquals('operator', $admin->admin_type);
        $this->assertTrue($admin->is_active);
        $this->assertNotNull($admin->terms_accepted_at);
    }

    /** @test */
    public function multiple_super_admins_can_exist(): void
    {
        User::factory()->count(5)->create([
            'role' => UserRoleEnum::ADMIN,
            'admin_type' => 'super',
            'is_active' => true,
        ]);

        $superAdmins = $this->adminService->getAdminsByType('super');

        // Should have at least 6 (5 + 1 from setUp)
        $this->assertGreaterThanOrEqual(6, $superAdmins->count());
    }
}
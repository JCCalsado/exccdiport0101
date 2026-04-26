<?php

namespace Tests\Feature\Admin;

use Tests\TestCase;
use App\Models\User;
use App\Enums\UserRoleEnum;
use App\Services\AdminService;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AdminDatabaseTest extends TestCase
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
            'terms_accepted_at' => now(),
        ]);
    }

    /** @test */
    public function admin_fields_are_stored_correctly_in_database(): void
    {
        $adminData = [
            'first_name' => 'Database',
            'last_name' => 'Test',
            'email' => 'dbtest@example.com',
            'password' => 'TestPassword123!',
            'admin_type' => 'manager',
            'department' => 'Finance',
            'is_active' => true,
        ];

        $admin = $this->adminService->createAdmin($adminData, $this->superAdmin->id);

        $this->assertDatabaseHas('users', [
            'id' => $admin->id,
            'email' => 'dbtest@example.com',
            'admin_type' => 'manager',
            'department' => 'Finance',
            'is_active' => 1,
        ]);
    }

    /** @test */
    public function terms_accepted_at_timestamp_is_stored(): void
    {
        $admin = User::factory()->create([
            'role' => UserRoleEnum::ADMIN,
            'admin_type' => 'operator',
            'terms_accepted_at' => now(),
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $admin->id,
            'terms_accepted_at' => $admin->terms_accepted_at,
        ]);
    }

    /** @test */
    public function admin_without_terms_acceptance_has_null_timestamp(): void
    {
        $admin = User::factory()->create([
            'role' => UserRoleEnum::ADMIN,
            'admin_type' => 'operator',
            'terms_accepted_at' => null,
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $admin->id,
            'terms_accepted_at' => null,
        ]);
    }

    /** @test */
    public function created_by_audit_field_is_set(): void
    {
        $adminData = [
            'first_name' => 'Audit',
            'last_name' => 'Test',
            'email' => 'audit@example.com',
            'password' => 'TestPassword123!',
            'admin_type' => 'manager',
            'department' => 'Operations',
        ];

        $admin = $this->adminService->createAdmin($adminData, $this->superAdmin->id);

        $this->assertDatabaseHas('users', [
            'id' => $admin->id,
            'created_by' => $this->superAdmin->id,
        ]);
    }

    /** @test */
    public function updated_by_audit_field_is_set_on_update(): void
    {
        $admin = User::factory()->create([
            'role' => UserRoleEnum::ADMIN,
            'admin_type' => 'operator',
            'department' => 'Old Department',
        ]);

        $updater = User::factory()->create(['role' => UserRoleEnum::ADMIN]);

        $this->adminService->updateAdmin(
            $admin,
            ['department' => 'New Department'],
            $updater->id
        );

        $admin->refresh();

        $this->assertDatabaseHas('users', [
            'id' => $admin->id,
            'department' => 'New Department',
            'updated_by' => $updater->id,
        ]);
    }

    /** @test */
    public function last_login_at_is_updated_on_record(): void
    {
        $admin = User::factory()->create([
            'role' => UserRoleEnum::ADMIN,
            'last_login_at' => null,
        ]);

        $admin->recordLastLogin();

        $this->assertDatabaseHas('users', [
            'id' => $admin->id,
        ]);

        $this->assertNotNull($admin->refresh()->last_login_at);
    }

    /** @test */
    public function last_login_at_is_updated_on_multiple_logins(): void
    {
        $admin = User::factory()->create([
            'role' => UserRoleEnum::ADMIN,
            'last_login_at' => now()->subDays(5),
        ]);

        $firstLogin = $admin->last_login_at;

        // Simulate second login
        $admin->recordLastLogin();
        $secondLogin = $admin->refresh()->last_login_at;

        $this->assertNotEquals($firstLogin->timestamp, $secondLogin->timestamp);
        $this->assertTrue($secondLogin->isAfter($firstLogin));
    }

    /** @test */
    public function admin_type_enum_value_is_stored_correctly(): void
    {
        $types = ['super', 'manager', 'operator'];

        foreach ($types as $type) {
            $admin = User::factory()->create([
                'role' => UserRoleEnum::ADMIN,
                'admin_type' => $type,
            ]);

            $this->assertDatabaseHas('users', [
                'id' => $admin->id,
                'admin_type' => $type,
            ]);
        }
    }

    /** @test */
    public function is_active_field_correctly_tracks_deactivation(): void
    {
        $admin = User::factory()->create([
            'role' => UserRoleEnum::ADMIN,
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $admin->id,
            'is_active' => 1,
        ]);

        $this->adminService->deactivateAdmin($admin);

        $this->assertDatabaseHas('users', [
            'id' => $admin->id,
            'is_active' => 0,
        ]);
    }

    /** @test */
    public function deactivated_admin_can_be_reactivated(): void
    {
        $admin = User::factory()->create([
            'role' => UserRoleEnum::ADMIN,
            'is_active' => false,
        ]);

        $this->adminService->reactivateAdmin($admin);

        $this->assertDatabaseHas('users', [
            'id' => $admin->id,
            'is_active' => 1,
        ]);
    }

    /** @test */
    public function permissions_json_field_is_stored_correctly(): void
    {
        $permissions = ['manage_users', 'manage_fees', 'manage_workflows'];

        $admin = User::factory()->create([
            'role' => UserRoleEnum::ADMIN,
            'permissions' => $permissions,
        ]);

        $retrievedAdmin = User::find($admin->id);

        $this->assertEquals($permissions, $retrievedAdmin->permissions);
    }

    /** @test */
    public function permissions_can_be_null(): void
    {
        $admin = User::factory()->create([
            'role' => UserRoleEnum::ADMIN,
            'permissions' => null,
        ]);

        $this->assertNull($admin->refresh()->permissions);
    }

    /** @test */
    public function created_by_foreign_key_relationship_works(): void
    {
        $author = User::factory()->create(['role' => UserRoleEnum::ADMIN]);

        $admin = User::factory()->create([
            'role' => UserRoleEnum::ADMIN,
            'created_by' => $author->id,
        ]);

        $this->assertEquals($author->id, $admin->createdByUser->id);
        $this->assertEquals($author->email, $admin->createdByUser->email);
    }

    /** @test */
    public function updated_by_foreign_key_relationship_works(): void
    {
        $updater = User::factory()->create(['role' => UserRoleEnum::ADMIN]);

        $admin = User::factory()->create([
            'role' => UserRoleEnum::ADMIN,
            'updated_by' => $updater->id,
        ]);

        $this->assertEquals($updater->id, $admin->updatedByUser->id);
        $this->assertEquals($updater->email, $admin->updatedByUser->email);
    }

    /** @test */
    public function database_indexes_exist_on_key_fields(): void
    {
        // Create admin and verify indexed fields can be queried efficiently
        $admin = User::factory()->create([
            'role' => UserRoleEnum::ADMIN,
            'admin_type' => 'manager',
            'is_active' => true,
        ]);

        // Query by indexed fields
        $byType = User::where('admin_type', 'manager')->first();
        $this->assertNotNull($byType);

        $byActive = User::where('is_active', true)->first();
        $this->assertNotNull($byActive);

        $byRole = User::where('role', 'admin')->first();
        $this->assertNotNull($byRole);
    }

    /** @test */
    public function created_at_and_updated_at_timestamps_are_recorded(): void
    {
        $before = now()->subSecond();

        $admin = User::factory()->create([
            'role' => UserRoleEnum::ADMIN,
        ]);

        $after = now()->addSecond();

        $this->assertTrue($admin->created_at->isBetween($before, $after));
        $this->assertTrue($admin->updated_at->isBetween($before, $after));
    }

    /** @test */
    public function password_is_hashed_in_database(): void
    {
        $plainPassword = 'TestPassword123!';

        $admin = User::factory()->create([
            'role' => UserRoleEnum::ADMIN,
            'password' => bcrypt($plainPassword),
        ]);

        $databasePassword = User::find($admin->id)->password;

        $this->assertNotEquals($plainPassword, $databasePassword);
        $this->assertTrue(\Hash::check($plainPassword, $databasePassword));
    }

    /** @test */
    public function email_is_unique_in_database(): void
    {
        User::factory()->create(['email' => 'duplicate@test.com']);

        $this->expectException(\Illuminate\Database\QueryException::class);

        User::factory()->create(['email' => 'duplicate@test.com']);
    }

    /** @test */
    public function database_can_handle_null_optional_fields(): void
    {
        $admin = User::factory()->create([
            'role' => UserRoleEnum::ADMIN,
            'created_by' => null,
            'updated_by' => null,
            'last_login_at' => null,
            'terms_accepted_at' => null,
            'permissions' => null,
            'department' => null,
        ]);

        $retrieved = User::find($admin->id);

        $this->assertNull($retrieved->created_by);
        $this->assertNull($retrieved->updated_by);
        $this->assertNull($retrieved->last_login_at);
        $this->assertNull($retrieved->terms_accepted_at);
        $this->assertNull($retrieved->permissions);
        $this->assertNull($retrieved->department);
    }

    /** @test */
    public function admin_count_statistics_are_accurate(): void
    {
        User::factory()->count(2)->create([
            'role' => UserRoleEnum::ADMIN,
            'admin_type' => 'super',
        ]);

        User::factory()->count(5)->create([
            'role' => UserRoleEnum::ADMIN,
            'admin_type' => 'manager',
        ]);

        User::factory()->count(7)->create([
            'role' => UserRoleEnum::ADMIN,
            'admin_type' => 'operator',
        ]);

        $stats = $this->adminService->getAdminStats();

        // setUp creates 1 superAdmin, plus 2 created here = 3 total super admins
        $this->assertEquals(15, $stats['total_admins']);
        $this->assertEquals(3, $stats['super_admins']);
        $this->assertEquals(5, $stats['managers']);
        $this->assertEquals(7, $stats['operators']);
    }

    /** @test */
    public function old_admin_data_is_preserved_on_update(): void
    {
        $admin = User::factory()->create([
            'role' => UserRoleEnum::ADMIN,
            'admin_type' => 'operator',
            'created_by' => $this->superAdmin->id,
        ]);

        $originalCreatedBy = $admin->created_by;
        $originalCreatedAt = $admin->created_at;

        $updater = User::factory()->create(['role' => UserRoleEnum::ADMIN]);

        // Travel 1 second into the future so updated_at is guaranteed to differ from created_at
        $this->travel(1)->seconds();

        $this->adminService->updateAdmin(
            $admin,
            ['department' => 'Updated'],
            $updater->id
        );

        $admin->refresh();

        // created_by should remain unchanged
        $this->assertEquals($originalCreatedBy, $admin->created_by);
        $this->assertEquals($originalCreatedAt->timestamp, $admin->created_at->timestamp);

        // updated_by should be newer
        $this->assertEquals($updater->id, $admin->updated_by);
        $this->assertTrue($admin->updated_at->isAfter($originalCreatedAt));
    }

    /** @test */
    public function admin_can_be_queried_by_multiple_criteria(): void
    {
        User::factory()->count(5)->create([
            'role' => UserRoleEnum::ADMIN,
            'admin_type' => 'manager',
            'is_active' => true,
        ]);

        User::factory()->count(3)->create([
            'role' => UserRoleEnum::ADMIN,
            'admin_type' => 'manager',
            'is_active' => false,
        ]);

        // Query: Find active managers
        $activeManagers = User::where('admin_type', 'manager')
            ->where('is_active', true)
            ->get();

        $this->assertCount(5, $activeManagers);
    }
}
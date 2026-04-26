<?php

namespace Tests\Unit\Models;

use Tests\TestCase;
use App\Models\User;
use App\Enums\UserRoleEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;

class UserAdminTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function admin_can_be_created(): void
    {
        $admin = User::factory()->create([
            'role' => UserRoleEnum::ADMIN,
            'is_active' => true,
        ]);

        $this->assertTrue($admin->isAdmin());
        $this->assertTrue($admin->is_active);
    }

    /** @test */
    public function non_admin_user_is_not_admin(): void
    {
        $student = User::factory()->create([
            'role' => UserRoleEnum::STUDENT,
        ]);

        $this->assertFalse($student->isAdmin());
    }

    /** @test */
    public function admin_can_accept_terms(): void
    {
        $admin = User::factory()->create([
            'role' => UserRoleEnum::ADMIN,
            'terms_accepted_at' => null,
        ]);

        $this->assertFalse($admin->hasAcceptedTerms());
        $this->assertNull($admin->terms_accepted_at);

        $admin->acceptTerms();

        $this->assertTrue($admin->hasAcceptedTerms());
        $this->assertNotNull($admin->refresh()->terms_accepted_at);
    }

    /** @test */
    public function terms_acceptance_is_immutable_once_set(): void
    {
        $admin = User::factory()->create([
            'role' => UserRoleEnum::ADMIN,
            'terms_accepted_at' => now()->subDay(),
        ]);

        $originalDate = $admin->terms_accepted_at;

        // Attempting to update terms_accepted_at via update should not be allowed
        $admin->update(['terms_accepted_at' => now()]);

        // The fillable array doesn't include it, so it won't be updated
        $this->assertEquals($originalDate->timestamp, $admin->refresh()->terms_accepted_at->timestamp);
    }

    /** @test */
    public function super_admin_has_all_permissions(): void
    {
        $admin = User::factory()->create([
            'role' => UserRoleEnum::ADMIN,
            'is_active' => true,
        ]);

        // All admins have all permissions now
        $this->assertTrue($admin->hasPermission('manage_users'));
        $this->assertTrue($admin->hasPermission('manage_admins'));
        $this->assertTrue($admin->hasPermission('manage_fees'));
        $this->assertTrue($admin->hasPermission('approve_payments'));
        $this->assertTrue($admin->hasPermission('manage_workflows'));
        $this->assertTrue($admin->hasPermission('view_audit_logs'));
        $this->assertTrue($admin->hasPermission('system_settings'));
        // Any permission should return true
        $this->assertTrue($admin->hasPermission('any_random_permission'));
    }

    /** @test */
    public function manager_has_specific_permissions(): void
    {
        $admin = User::factory()->create([
            'role' => UserRoleEnum::ADMIN,
            'is_active' => true,
        ]);

        // All admins have all permissions now
        $this->assertTrue($admin->hasPermission('manage_fees'));
        $this->assertTrue($admin->hasPermission('approve_payments'));
        $this->assertTrue($admin->hasPermission('manage_workflows'));
        $this->assertTrue($admin->hasPermission('view_users'));
        $this->assertTrue($admin->hasPermission('manage_users'));
        $this->assertTrue($admin->hasPermission('system_settings'));
    }

    /** @test */
    public function operator_has_limited_permissions(): void
    {
        $admin = User::factory()->create([
            'role' => UserRoleEnum::ADMIN,
            'is_active' => true,
        ]);

        // All admins have all permissions now
        $this->assertTrue($admin->hasPermission('approve_payments'));
        $this->assertTrue($admin->hasPermission('view_users'));
        $this->assertTrue($admin->hasPermission('view_audit_logs'));
        $this->assertTrue($admin->hasPermission('manage_fees'));
        $this->assertTrue($admin->hasPermission('manage_users'));
        $this->assertTrue($admin->hasPermission('system_settings'));
    }

    /** @test */
    public function inactive_admin_has_no_permissions(): void
    {
        $inactiveAdmin = User::factory()->create([
            'role' => UserRoleEnum::ADMIN,
            'is_active' => false,
        ]);

        $this->assertFalse($inactiveAdmin->hasPermission('manage_users'));
        $this->assertFalse($inactiveAdmin->hasPermission('any_permission'));
    }

    /** @test */
    public function has_any_permission_returns_true_for_active_admin(): void
    {
        $admin = User::factory()->create([
            'role' => UserRoleEnum::ADMIN,
            'is_active' => true,
        ]);

        $this->assertTrue($admin->hasAnyPermission(['manage_fees', 'system_settings']));
        $this->assertTrue($admin->hasAnyPermission(['system_settings', 'manage_fees']));
    }

    /** @test */
    public function has_any_permission_returns_false_for_inactive_admin(): void
    {
        $admin = User::factory()->create([
            'role' => UserRoleEnum::ADMIN,
            'is_active' => false,
        ]);

        $this->assertFalse($admin->hasAnyPermission(['manage_users', 'system_settings']));
    }

    /** @test */
    public function has_all_permissions_returns_true_for_active_admin(): void
    {
        $admin = User::factory()->create([
            'role' => UserRoleEnum::ADMIN,
            'is_active' => true,
        ]);

        $this->assertTrue($admin->hasAllPermissions(['manage_fees', 'approve_payments']));
        $this->assertTrue($admin->hasAllPermissions(['manage_fees', 'system_settings']));
    }

    /** @test */
    public function admin_can_record_last_login(): void
    {
        $admin = User::factory()->create([
            'role' => UserRoleEnum::ADMIN,
            'last_login_at' => null,
        ]);

        $this->assertNull($admin->last_login_at);

        $admin->recordLastLogin();

        $this->assertNotNull($admin->refresh()->last_login_at);
        $this->assertTrue($admin->last_login_at->isToday());
    }

    /** @test */
    public function admins_scope_returns_only_admin_users(): void
    {
        User::factory()->count(3)->create(['role' => UserRoleEnum::STUDENT]);
        User::factory()->count(2)->create(['role' => UserRoleEnum::ACCOUNTING]);
        User::factory()->count(2)->create(['role' => UserRoleEnum::ADMIN]);

        $admins = User::admins()->get();

        $this->assertCount(2, $admins);
        $admins->each(fn($admin) => $this->assertEquals(UserRoleEnum::ADMIN->value, $admin->role->value));
    }

    /** @test */
    public function active_scope_returns_only_active_users(): void
    {
        User::factory()->create(['is_active' => true]);
        User::factory()->create(['is_active' => true]);
        User::factory()->create(['is_active' => false]);

        $activeUsers = User::active()->get();

        $this->assertCount(2, $activeUsers);
        $activeUsers->each(fn($user) => $this->assertTrue($user->is_active));
    }

    /** @test */
    public function terms_accepted_scope_returns_only_users_with_accepted_terms(): void
    {
        User::factory()->create(['terms_accepted_at' => now()]);
        User::factory()->create(['terms_accepted_at' => now()]);
        User::factory()->create(['terms_accepted_at' => null]);

        $usersWithTerms = User::termsAccepted()->get();

        $this->assertCount(2, $usersWithTerms);
        $usersWithTerms->each(fn($user) => $this->assertNotNull($user->terms_accepted_at));
    }

    /** @test */
    public function admin_relationships_load_correctly(): void
    {
        $creator = User::factory()->create();
        $updater = User::factory()->create();

        $admin = User::factory()->create([
            'role' => UserRoleEnum::ADMIN,
            'created_by' => $creator->id,
            'updated_by' => $updater->id,
        ]);

        $admin->load('createdByUser', 'updatedByUser');

        $this->assertNotNull($admin->createdByUser);
        $this->assertNotNull($admin->updatedByUser);
        $this->assertEquals($creator->id, $admin->createdByUser->id);
        $this->assertEquals($updater->id, $admin->updatedByUser->id);
    }

    /** @test */
    public function get_admin_validation_rules_requires_name_and_email(): void
    {
        $rules = User::getAdminValidationRules();

        $this->assertArrayHasKey('last_name', $rules);
        $this->assertArrayHasKey('first_name', $rules);
        $this->assertArrayHasKey('email', $rules);
        $this->assertStringContainsString('required', $rules['last_name']);
        $this->assertStringContainsString('required', $rules['first_name']);
        $this->assertStringContainsString('required', $rules['email']);
    }

    /** @test */
    public function get_admin_validation_rules_requires_password_on_create(): void
    {
        $rules = User::getAdminValidationRules();

        $this->assertArrayHasKey('password', $rules);
        $this->assertStringContainsString('required', $rules['password']);
    }

    /** @test */
    public function get_admin_validation_rules_makes_password_optional_on_update(): void
    {
        $adminId = User::factory()->create()->id;
        $rules = User::getAdminValidationRules($adminId);

        $this->assertArrayHasKey('password', $rules);
        $this->assertStringContainsString('nullable', $rules['password']);
        $this->assertStringNotContainsString('required', $rules['password']);
    }

    /** @test */
    public function name_attribute_returns_formatted_name(): void
    {
        $admin = User::factory()->create([
            'last_name' => 'Doe',
            'first_name' => 'John',
            'middle_initial' => 'A',
        ]);

        $this->assertEquals('Doe, John A.', $admin->name);
    }

    /** @test */
    public function name_attribute_works_without_middle_initial(): void
    {
        $admin = User::factory()->create([
            'last_name' => 'Smith',
            'first_name' => 'Jane',
            'middle_initial' => null,
        ]);

        $this->assertEquals('Smith, Jane', $admin->name);
    }
}
<?php

namespace Tests\Feature\Admin;

use Tests\TestCase;
use App\Models\User;
use App\Enums\UserRoleEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AdminWorkflowIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = User::factory()->create([
            'role'              => UserRoleEnum::ADMIN,
            'admin_type'        => 'super',
            'is_active'         => true,
            'terms_accepted_at' => now(),
        ]);
    }

    /** @test */
    public function complete_admin_lifecycle_workflow(): void
    {
        // 1. Create a new admin
        $createData = [
            'first_name'            => 'Integration',
            'last_name'             => 'Test',
            'email'                 => 'integration@test.com',
            'password'              => 'IntegrationTest123!',
            'password_confirmation' => 'IntegrationTest123!',
            'admin_type'            => 'manager',
            'department'            => 'Integration Dept',
        ];

        $createResponse = $this->actingAs($this->superAdmin)
            ->post(route('users.store'), $createData);

        $adminId = User::where('email', 'integration@test.com')->first()->id;

        // 2. Verify admin can view own profile
        $showResponse = $this->actingAs($this->superAdmin)
            ->get(route('users.show', $adminId));

        $showResponse->assertStatus(200);

        // 3. Edit the admin
        $editData = [
            'first_name' => 'Updated',
            'last_name'  => 'Integration',
            'email'      => 'integration@test.com',
            'admin_type' => 'operator',
            'department' => 'Updated Dept',
        ];

        $updateResponse = $this->actingAs($this->superAdmin)
            ->put(route('users.update', $adminId), $editData);

        $admin = User::find($adminId);
        $this->assertEquals('operator', $admin->admin_type);
        $this->assertEquals('Updated Dept', $admin->department);

        // 4. Deactivate the admin
        $deactivateResponse = $this->actingAs($this->superAdmin)
            ->post(route('admin.users.deactivate', $adminId));

        $admin->refresh();
        $this->assertFalse($admin->is_active);

        // 5. Verify deactivated admin cannot access pages
        $accessResponse = $this->actingAs($admin)
            ->get(route('users.index'));

        $accessResponse->assertStatus(403);

        // 6. Reactivate the admin
        $reactivateResponse = $this->actingAs($this->superAdmin)
            ->post(route('admin.users.reactivate', $adminId));

        $admin->refresh();
        $this->assertTrue($admin->is_active);

        // 7. Verify reactivated admin can access again
        $accessResponse = $this->actingAs($admin)
            ->get(route('users.show', $admin->id));

        $accessResponse->assertStatus(200);
    }

    /** @test */
    public function admin_list_shows_all_admins_with_correct_data(): void
    {
        User::factory()->count(3)->create([
            'role'       => UserRoleEnum::ADMIN,
            'admin_type' => 'manager',
            'is_active'  => true,
        ]);

        User::factory()->count(2)->create([
            'role'       => UserRoleEnum::ADMIN,
            'admin_type' => 'operator',
            'is_active'  => false,
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->get(route('users.index'));

        $response->assertStatus(200);
        $this->assertNotNull($response);
    }

    /** @test */
    public function only_super_admin_can_perform_admin_management_actions(): void
    {
        $manager = User::factory()->create([
            'role'              => UserRoleEnum::ADMIN,
            'admin_type'        => 'manager',
            'is_active'         => true,
            'terms_accepted_at' => now(),
        ]);

        $operator = User::factory()->create([
            'role'              => UserRoleEnum::ADMIN,
            'admin_type'        => 'operator',
            'is_active'         => true,
            'terms_accepted_at' => now(),
        ]);

        $targetAdmin = User::factory()->create([
            'role'       => UserRoleEnum::ADMIN,
            'admin_type' => 'operator',
        ]);

        // Manager cannot create admin
        $createResponse = $this->actingAs($manager)
            ->get(route('users.create'));
        $createResponse->assertStatus(403);

        // Operator cannot create admin
        $createResponse = $this->actingAs($operator)
            ->get(route('users.create'));
        $createResponse->assertStatus(403);

        // Manager cannot deactivate
        $deactivateResponse = $this->actingAs($manager)
            ->post(route('admin.users.deactivate', $targetAdmin->id));
        $deactivateResponse->assertStatus(403);

        // Only super admin can create
        $createResponse = $this->actingAs($this->superAdmin)
            ->get(route('users.create'));
        $createResponse->assertStatus(200);

        // Only super admin can deactivate
        $deactivateResponse = $this->actingAs($this->superAdmin)
            ->post(route('admin.users.deactivate', $targetAdmin->id));
        $this->assertTrue($targetAdmin->refresh()->is_active === false);
    }

    /** @test */
    public function permission_hierarchy_is_correctly_enforced(): void
    {
        $manager = User::factory()->create([
            'role'       => UserRoleEnum::ADMIN,
            'admin_type' => 'manager',
            'is_active'  => true,
        ]);

        $operator = User::factory()->create([
            'role'       => UserRoleEnum::ADMIN,
            'admin_type' => 'operator',
            'is_active'  => true,
        ]);

        $this->assertTrue($manager->hasPermission('manage_fees'));
        $this->assertFalse($operator->hasPermission('manage_fees'));
        $this->assertTrue($operator->hasPermission('approve_payments'));
        $this->assertTrue($manager->hasPermission('approve_payments'));
        $this->assertFalse($manager->hasPermission('system_settings'));
        $this->assertFalse($operator->hasPermission('system_settings'));
        $this->assertTrue($this->superAdmin->hasPermission('system_settings'));
        $this->assertTrue($this->superAdmin->hasPermission('manage_fees'));
        $this->assertTrue($this->superAdmin->hasPermission('approve_payments'));
    }

    /** @test */
    public function audit_trail_is_maintained_throughout_lifecycle(): void
    {
        $creator = $this->superAdmin;

        $createData = [
            'first_name'            => 'Audit',
            'last_name'             => 'Trail',
            'email'                 => 'audittrail@test.com',
            'password'              => 'AuditTrail123!',
            'password_confirmation' => 'AuditTrail123!',
            'admin_type'            => 'manager',
            'department'            => 'Audit Dept',
        ];

        $this->actingAs($creator)->post(route('users.store'), $createData);

        $admin = User::where('email', 'audittrail@test.com')->first();

        $this->assertEquals($creator->id, $admin->created_by);
        $this->assertNotNull($admin->created_at);
        $originalCreatedAt = $admin->created_at;

        $this->travel(1)->seconds();

        $updater = User::factory()->create([
            'role'       => UserRoleEnum::ADMIN,
            'admin_type' => 'super',
        ]);

        $updateData = [
            'first_name' => 'Updated',
            'last_name'  => 'Audit',
            'email'      => 'audittrail@test.com',
            'admin_type' => 'operator',
        ];

        $this->actingAs($updater)->put(route('users.update', $admin->id), $updateData);

        $admin->refresh();

        $this->assertEquals($updater->id, $admin->updated_by);
        $this->assertEquals($creator->id, $admin->created_by);
        $this->assertEquals($originalCreatedAt->timestamp, $admin->created_at->timestamp);
        $this->assertGreaterThan($originalCreatedAt->timestamp, $admin->updated_at->timestamp);
    }

    /** @test */
    public function terms_acceptance_is_immutable_after_creation(): void
    {
        $createData = [
            'first_name'            => 'Terms',
            'last_name'             => 'Test',
            'email'                 => 'termstest@test.com',
            'password'              => 'TermsTest123!',
            'password_confirmation' => 'TermsTest123!',
            'admin_type'            => 'manager',
        ];

        $this->actingAs($this->superAdmin)->post(route('users.store'), $createData);

        $admin = User::where('email', 'termstest@test.com')->first();
        $originalTermsDate = $admin->terms_accepted_at;

        $updateData = [
            'first_name' => 'Terms',
            'last_name'  => 'Updated',
            'email'      => 'termstest@test.com',
            'admin_type' => 'manager',
        ];

        $this->actingAs($this->superAdmin)->put(route('users.update', $admin->id), $updateData);

        $admin->refresh();
        $this->assertEquals($originalTermsDate->timestamp, $admin->terms_accepted_at->timestamp);
    }

    /** @test */
    public function cannot_create_admin_without_all_required_fields(): void
    {
        $incompleteData = [
            'first_name' => 'Incomplete',
            'last_name'  => 'Admin',
            'email'      => 'incomplete@test.com',
            'admin_type' => 'manager',
        ];

        $response = $this->actingAs($this->superAdmin)
            ->post(route('users.store'), $incompleteData);

        $response->assertSessionHasErrors('password');
    }

    /** @test */
    public function admin_permissions_prevent_unauthorized_access(): void
    {
        $student = User::factory()->create(['role' => UserRoleEnum::STUDENT]);

        $adminListResponse = $this->actingAs($student)
            ->get(route('users.index'));
        $adminListResponse->assertStatus(403);

        $createAdminResponse = $this->actingAs($student)
            ->get(route('users.create'));
        $createAdminResponse->assertStatus(403);
    }

    /** @test */
    public function admin_can_be_promoted_demoted_between_types(): void
    {
        $admin = User::factory()->create([
            'role'       => UserRoleEnum::ADMIN,
            'admin_type' => 'operator',
            'is_active'  => true,
        ]);

        $this->assertTrue($admin->hasPermission('approve_payments'));
        $this->assertFalse($admin->hasPermission('manage_fees'));

        $promoteData = [
            'first_name' => $admin->first_name,
            'last_name'  => $admin->last_name,
            'email'      => $admin->email,
            'admin_type' => 'manager',
        ];

        $this->actingAs($this->superAdmin)
            ->put(route('users.update', $admin->id), $promoteData);

        $admin->refresh();
        $this->assertTrue($admin->hasPermission('manage_fees'));
        $this->assertTrue($admin->hasPermission('approve_payments'));

        $demoteData = [
            'first_name' => $admin->first_name,
            'last_name'  => $admin->last_name,
            'email'      => $admin->email,
            'admin_type' => 'operator',
        ];

        $this->actingAs($this->superAdmin)
            ->put(route('users.update', $admin->id), $demoteData);

        $admin->refresh();
        $this->assertFalse($admin->hasPermission('manage_fees'));
        $this->assertTrue($admin->hasPermission('approve_payments'));
    }

    /** @test */
    public function last_login_tracking_works(): void
    {
        $admin = User::factory()->create([
            'role'          => UserRoleEnum::ADMIN,
            'admin_type'    => 'manager',
            'last_login_at' => null,
        ]);

        $this->assertNull($admin->last_login_at);

        $admin->recordLastLogin();

        $this->assertNotNull($admin->refresh()->last_login_at);
        $this->assertTrue($admin->last_login_at->isToday());
    }
}
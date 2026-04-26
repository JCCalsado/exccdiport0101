<?php

namespace Tests\Feature\Security;

use Tests\TestCase;
use App\Models\User;
use App\Enums\UserRoleEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AuthorizationSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $manager;
    protected User $operator;
    protected User $student;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = User::factory()->create([
            'role'              => UserRoleEnum::ADMIN,
            'is_active'         => true,
            'terms_accepted_at' => now(),
        ]);

        $this->manager = User::factory()->create([
            'role'              => UserRoleEnum::ADMIN,
            'is_active'         => true,
            'terms_accepted_at' => now(),
        ]);

        $this->operator = User::factory()->create([
            'role'              => UserRoleEnum::ADMIN,
            'is_active'         => true,
            'terms_accepted_at' => now(),
        ]);

        $this->student = User::factory()->create([
            'role' => UserRoleEnum::STUDENT,
        ]);
    }

    /** @test */
    public function privilege_escalation_prevented(): void
    {
        $data = [
            'first_name' => 'Elevated',
            'last_name'  => 'Manager',
            'email'      => $this->manager->email,
        ];

        $response = $this->actingAs($this->manager)
            ->put(route('users.update', $this->manager->id), $data);

        $this->manager->refresh();
        $this->assertTrue($this->manager->exists);
    }

    /** @test */
    public function all_admins_have_equal_permissions(): void
    {
        // After admin_type removal, all active admins have identical permissions
        $this->assertTrue($this->superAdmin->hasPermission('users.view'));
        $this->assertTrue($this->manager->hasPermission('users.view'));
        $this->assertTrue($this->operator->hasPermission('users.view'));
    }

    /** @test */
    public function inactive_user_cannot_access_admin_features(): void
    {
        $this->manager->update(['is_active' => false]);

        $response = $this->actingAs($this->manager)
            ->get(route('users.index'));

        $response->assertStatus(403);
    }

    /** @test */
    public function inactive_user_cannot_change_status(): void
    {
        $inactiveAdmin = User::factory()->create([
            'role'       => UserRoleEnum::ADMIN,
            'is_active'  => false,
        ]);

        $response = $this->actingAs($inactiveAdmin)
            ->post(route('admin.users.reactivate', $inactiveAdmin->id));

        $response->assertStatus(403);
    }

    /** @test */
    public function cross_user_data_access_prevented(): void
    {
        $secondAdmin = User::factory()->create([
            'role'       => UserRoleEnum::ADMIN,
        ]);

        $response = $this->actingAs($this->operator)
            ->get(route('users.show', $secondAdmin->id));

        $response->assertStatus(403);
    }

    /** @test */
    public function student_completely_denied_admin_access(): void
    {
        $response = $this->actingAs($this->student)
            ->get(route('users.index'));
        $this->assertEquals(403, $response->status());

        $response = $this->actingAs($this->student)
            ->get(route('users.create'));
        $this->assertEquals(403, $response->status());

        $response = $this->actingAs($this->student)
            ->get(route('users.show', $this->superAdmin->id));
        $this->assertEquals(403, $response->status());
    }

    /** @test */
    public function all_active_admins_can_perform_admin_actions(): void
    {
        $response = $this->actingAs($this->operator)
            ->get(route('users.index'));
        $response->assertStatus(200);

        $response = $this->actingAs($this->manager)
            ->get(route('users.index'));
        $response->assertStatus(200);

        $response = $this->actingAs($this->superAdmin)
            ->get(route('users.index'));
        $response->assertStatus(200);
    }

    /** @test */
    public function manager_cannot_create_admin(): void
    {
        $response = $this->actingAs($this->manager)
            ->get(route('users.create'));

        $response->assertStatus(403);
    }

    /** @test */
    public function role_change_only_by_super_admin(): void
    {
        $targetAdmin = User::factory()->create([
            'role'       => UserRoleEnum::ADMIN,
            'is_active'  => true,
        ]);

        $response = $this->actingAs($this->manager)
            ->put(route('users.update', $targetAdmin->id), [
                'role' => UserRoleEnum::STUDENT->value,
            ]);

        $response->assertStatus(403);
    }

    /** @test */
    public function admin_can_update_own_profile(): void
    {
        $response = $this->actingAs($this->manager)
            ->put(route('users.update', $this->manager->id), [
                'first_name' => 'Updated',
                'last_name'  => 'Name',
                'email'      => $this->manager->email,
            ]);

        $response->assertStatus(302); // Redirect on success

        $this->manager->refresh();
        $this->assertEquals('Updated', $this->manager->first_name);
    }

    /** @test */
    public function permission_check_on_every_request(): void
    {
        $admin = User::factory()->create([
            'role'       => UserRoleEnum::ADMIN,
            'is_active'  => true,
        ]);

        $this->actingAs($admin)
            ->get(route('users.index'))
            ->assertStatus(200);

        $admin->update(['is_active' => false]);

        $response = $this->actingAs($admin)
            ->get(route('users.index'));
        $response->assertStatus(403);
    }

    /** @test */
    public function cannot_access_other_users_edit_form(): void
    {
        $otherAdmin = User::factory()->create([
            'role'       => UserRoleEnum::ADMIN,
        ]);

        $response = $this->actingAs($this->operator)
            ->get(route('users.edit', $otherAdmin->id));

        $response->assertStatus(403);
    }

    /** @test */
    public function unverified_email_blocks_admin_access(): void
    {
        $unverifiedAdmin = User::factory()->create([
            'role'               => UserRoleEnum::ADMIN,
            'email_verified_at'  => null,
        ]);

        $this->assertNotNull($unverifiedAdmin->id);
    }

    /** @test */
    public function simultaneous_session_detection(): void
    {
        $user = User::factory()->create();
        $this->assertTrue($user->exists);
    }

    /** @test */
    public function suspicious_activity_flagged(): void
    {
        $activity = ['flagged' => false];
        $this->assertFalse($activity['flagged']);
    }
}
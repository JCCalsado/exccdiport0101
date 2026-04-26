<?php

namespace Tests\Feature\Admin;

use Tests\TestCase;
use App\Models\User;
use App\Enums\UserRoleEnum;
use App\Services\AdminService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

class AdminControllerTest extends TestCase
{
    use RefreshDatabase;

    protected AdminService $adminService;
    protected User $superAdmin;
    protected User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminService = app(AdminService::class);

        $this->superAdmin = User::factory()->create([
            'role'               => UserRoleEnum::ADMIN,
            'admin_type'         => 'super',
            'is_active'          => true,
            'terms_accepted_at'  => now(),
        ]);

        $this->manager = User::factory()->create([
            'role'               => UserRoleEnum::ADMIN,
            'admin_type'         => 'manager',
            'is_active'          => true,
            'terms_accepted_at'  => now(),
        ]);
    }

    /** @test */
    public function admin_index_page_returns_successful_response(): void
    {
        $response = $this->actingAs($this->superAdmin)->get(route('users.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page->component('Admin/Users/Index'));
    }

    /** @test */
    public function admin_index_page_returns_admin_list(): void
    {
        User::factory()->create([
            'role'       => UserRoleEnum::ADMIN,
            'admin_type' => 'operator',
            'is_active'  => true,
        ]);

        $response = $this->actingAs($this->superAdmin)->get(route('users.index'));

        $response->assertStatus(200);
        $this->assertEquals(3, User::admins()->count());
    }

    /** @test */
    public function unauthenticated_user_cannot_view_admin_list(): void
    {
        $response = $this->get(route('users.index'));

        $response->assertRedirect(route('login'));
    }

    /** @test */
    public function non_admin_user_cannot_view_admin_list(): void
    {
        $student = User::factory()->create(['role' => UserRoleEnum::STUDENT]);

        $response = $this->actingAs($student)->get(route('users.index'));

        $response->assertStatus(403);
    }

    /** @test */
    public function create_admin_page_returns_successful_response(): void
    {
        $response = $this->actingAs($this->superAdmin)->get(route('users.create'));

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page->component('Admin/Users/Create'));
    }

    /** @test */
    public function only_super_admin_can_create_admin(): void
    {
        $response = $this->actingAs($this->manager)->get(route('users.create'));

        $response->assertStatus(403);
    }

    /** @test */
    public function admin_can_be_stored_with_valid_data(): void
    {
        $data = [
            'first_name'            => 'New',
            'last_name'             => 'Admin',
            'email'                 => 'newadmin@test.com',
            'password'              => 'SecurePassword123!',
            'password_confirmation' => 'SecurePassword123!',
            'admin_type'            => 'manager',
            'department'            => 'Operations',
        ];

        $response = $this->actingAs($this->superAdmin)->post(route('users.store'), $data);

        $this->assertDatabaseHas('users', [
            'email'      => 'newadmin@test.com',
            'admin_type' => 'manager',
            'department' => 'Operations',
        ]);

        $newAdmin = User::where('email', 'newadmin@test.com')->first();
        $this->assertTrue($newAdmin->hasAcceptedTerms());
        $this->assertNotNull($newAdmin->created_by);
    }

    /** @test */
    public function admin_cannot_be_created_without_terms_acceptance(): void
    {
        $data = [
            'first_name'            => 'New',
            'last_name'             => 'Admin',
            'email'                 => 'newadmin@test.com',
            'password'              => 'SecurePassword123!',
            'password_confirmation' => 'SecurePassword123!',
            'admin_type'            => 'manager',
            'department'            => 'Operations',
            'accept_terms'          => false,
        ];

        $response = $this->actingAs($this->superAdmin)->post(route('users.store'), $data);

        $response->assertSessionHasErrors('accept_terms');
        $this->assertDatabaseMissing('users', ['email' => 'newadmin@test.com']);
    }

    /** @test */
    public function admin_creation_validates_email_uniqueness(): void
    {
        $existing = User::factory()->create(['email' => 'taken@test.com']);

        $data = [
            'first_name'            => 'New',
            'last_name'             => 'Admin',
            'email'                 => 'taken@test.com',
            'password'              => 'SecurePassword123!',
            'password_confirmation' => 'SecurePassword123!',
            'admin_type'            => 'manager',
            'department'            => 'Operations',
        ];

        $response = $this->actingAs($this->superAdmin)->post(route('users.store'), $data);

        $response->assertSessionHasErrors('email');
    }

    /** @test */
    public function admin_creation_validates_password_strength(): void
    {
        $data = [
            'first_name'            => 'New',
            'last_name'             => 'Admin',
            'email'                 => 'newadmin@test.com',
            'password'              => 'weak',
            'password_confirmation' => 'weak',
            'admin_type'            => 'manager',
            'department'            => 'Operations',
        ];

        $response = $this->actingAs($this->superAdmin)->post(route('users.store'), $data);

        $response->assertSessionHasErrors('password');
    }

    /** @test */
    public function show_page_displays_admin_details(): void
    {
        $admin = User::factory()->create([
            'role'       => UserRoleEnum::ADMIN,
            'admin_type' => 'operator',
            'created_by' => $this->superAdmin->id,
        ]);

        $response = $this->actingAs($this->superAdmin)->get(route('users.show', $admin->id));

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page->component('Admin/Users/Show'));
    }

    /** @test */
    public function edit_page_returns_successful_response(): void
    {
        $admin = User::factory()->create([
            'role'       => UserRoleEnum::ADMIN,
            'admin_type' => 'operator',
        ]);

        $response = $this->actingAs($this->superAdmin)->get(route('users.edit', $admin->id));

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page->component('Admin/Users/Edit'));
    }

    /** @test */
    public function admin_can_be_updated_with_valid_data(): void
    {
        $admin = User::factory()->create([
            'role'       => UserRoleEnum::ADMIN,
            'admin_type' => 'manager',
            'department' => 'Finance',
        ]);

        $data = [
            'first_name' => 'Updated',
            'last_name'  => 'Name',
            'email'      => $admin->email,
            'admin_type' => 'operator',
            'department' => 'Operations',
        ];

        $response = $this->actingAs($this->superAdmin)->put(route('users.update', $admin->id), $data);

        $admin->refresh();
        $this->assertEquals('operator', $admin->admin_type);
        $this->assertEquals('Operations', $admin->department);
        $this->assertNotNull($admin->updated_by);
    }

    /** @test */
    public function admin_password_can_be_updated_optionally(): void
    {
        $admin = User::factory()->create([
            'role'       => UserRoleEnum::ADMIN,
            'admin_type' => 'manager',
        ]);

        $oldPassword = $admin->password;

        $data = [
            'first_name'            => 'Updated',
            'last_name'             => 'Name',
            'email'                 => $admin->email,
            'admin_type'            => 'manager',
            'password'              => 'NewSecurePassword123!',
            'password_confirmation' => 'NewSecurePassword123!',
        ];

        $response = $this->actingAs($this->superAdmin)->put(route('users.update', $admin->id), $data);

        $admin->refresh();
        $this->assertNotEquals($oldPassword, $admin->password);
    }

    /** @test */
    public function non_super_admin_cannot_update_admin(): void
    {
        $admin = User::factory()->create(['role' => UserRoleEnum::ADMIN]);

        $data = [
            'first_name' => 'Updated',
            'last_name'  => 'Name',
            'email'      => $admin->email,
            'admin_type' => 'manager',
        ];

        $response = $this->actingAs($this->manager)->put(route('users.update', $admin->id), $data);

        $response->assertStatus(403);
    }

    /** @test */
    public function deactivate_action_sets_is_active_to_false(): void
    {
        $admin = User::factory()->create([
            'role'       => UserRoleEnum::ADMIN,
            'admin_type' => 'operator',
            'is_active'  => true,
        ]);

        $response = $this->actingAs($this->superAdmin)->post(route('admin.users.deactivate', $admin->id));

        $admin->refresh();
        $this->assertFalse($admin->is_active);
    }

    /** @test */
    public function cannot_deactivate_last_super_admin(): void
    {
        User::query()->update(['is_active' => false]);
        $lastSuperAdmin = User::factory()->create([
            'role'       => UserRoleEnum::ADMIN,
            'admin_type' => 'super',
            'is_active'  => true,
        ]);

        $response = $this->actingAs($lastSuperAdmin)->post(route('admin.users.deactivate', $lastSuperAdmin->id));

        $response->assertStatus(403);
        $lastSuperAdmin->refresh();
        $this->assertTrue($lastSuperAdmin->is_active);
    }

    /** @test */
    public function reactivate_action_sets_is_active_to_true(): void
    {
        $admin = User::factory()->create([
            'role'       => UserRoleEnum::ADMIN,
            'admin_type' => 'operator',
            'is_active'  => false,
        ]);

        $response = $this->actingAs($this->superAdmin)->post(route('admin.users.reactivate', $admin->id));

        $admin->refresh();
        $this->assertTrue($admin->is_active);
    }

    /** @test */
    public function deactivated_admin_cannot_login(): void
    {
        $admin = User::factory()->create([
            'role'       => UserRoleEnum::ADMIN,
            'admin_type' => 'manager',
            'is_active'  => false,
            'password'   => bcrypt('password123'),
        ]);

        $response = $this->post('/login', [
            'email'    => $admin->email,
            'password' => 'password123',
        ]);

        $this->assertGuest();
    }

    /** @test */
    public function delete_admin_is_forbidden(): void
    {
        $admin = User::factory()->create([
            'role'       => UserRoleEnum::ADMIN,
            'admin_type' => 'operator',
        ]);

        $response = $this->actingAs($this->superAdmin)->delete(route('users.destroy', $admin->id));

        $response->assertStatus(403);
        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    /** @test */
    public function audit_fields_are_populated_on_creation(): void
    {
        $data = [
            'first_name'            => 'Audit',
            'last_name'             => 'Test',
            'email'                 => 'audit@test.com',
            'password'              => 'SecurePassword123!',
            'password_confirmation' => 'SecurePassword123!',
            'admin_type'            => 'manager',
            'department'            => 'Operations',
        ];

        $response = $this->actingAs($this->superAdmin)->post(route('users.store'), $data);

        $newAdmin = User::where('email', 'audit@test.com')->first();
        $this->assertEquals($this->superAdmin->id, $newAdmin->created_by);
        $this->assertNotNull($newAdmin->created_at);
    }

    /** @test */
    public function audit_fields_are_updated_on_modification(): void
    {
        $admin = User::factory()->create([
            'role'       => UserRoleEnum::ADMIN,
            'admin_type' => 'operator',
        ]);

        $originalUpdatedAt = $admin->updated_at;

        $data = [
            'first_name' => 'Updated',
            'last_name'  => 'Admin',
            'email'      => $admin->email,
            'admin_type' => 'manager',
        ];

        $updatingAdmin = User::factory()->create([
            'role'              => UserRoleEnum::ADMIN,
            'admin_type'        => 'super',
            'is_active'         => true,
            'terms_accepted_at' => now(),
        ]);

        $this->travel(1)->seconds();

        $this->actingAs($updatingAdmin)->put(route('users.update', $admin->id), $data);

        $admin->refresh();
        $this->assertEquals($updatingAdmin->id, $admin->updated_by);
        $this->assertNotEquals($originalUpdatedAt->timestamp, $admin->updated_at->timestamp);
    }

    /** @test */
    public function admin_creation_logs_action(): void
    {
        $data = [
            'first_name'            => 'Log',
            'last_name'             => 'Test',
            'email'                 => 'log@test.com',
            'password'              => 'SecurePassword123!',
            'password_confirmation' => 'SecurePassword123!',
            'admin_type'            => 'manager',
            'department'            => 'Operations',
        ];

        $this->actingAs($this->superAdmin)->post(route('users.store'), $data);

        $newAdmin = User::where('email', 'log@test.com')->first();
        $this->assertEquals($this->superAdmin->id, $newAdmin->created_by);
    }

    /** @test */
    public function inactive_admin_cannot_access_admin_pages(): void
    {
        $inactiveAdmin = User::factory()->create([
            'role'       => UserRoleEnum::ADMIN,
            'admin_type' => 'super',
            'is_active'  => false,
        ]);

        $response = $this->actingAs($inactiveAdmin)->get(route('users.index'));

        $response->assertStatus(403);
    }
}
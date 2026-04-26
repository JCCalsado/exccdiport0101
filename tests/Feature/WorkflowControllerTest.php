<?php

namespace Tests\Feature;

use App\Enums\UserRoleEnum;
use App\Models\User;
use App\Models\Workflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkflowControllerTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdminUser(): User
    {
        return User::factory()->create([
            'role' => UserRoleEnum::ADMIN,
            'is_active' => true,
            'terms_accepted_at' => now(),
        ]);
    }

    public function test_can_view_workflows_index()
    {
        $user = $this->makeAdminUser();
        Workflow::factory()->count(3)->create();

        $response = $this->actingAs($user)->get('/workflows');

        $response->assertStatus(200);
    }

    public function test_can_create_workflow()
    {
        $user = $this->makeAdminUser();

        $data = [
            'name' => 'Test Workflow',
            'type' => 'general',
            'description' => 'Test description',
            'steps' => [
                ['name' => 'Step 1', 'requires_approval' => false],
                ['name' => 'Step 2', 'requires_approval' => true, 'approvers' => [$user->id]],
            ],
        ];

        $response = $this->actingAs($user)->post('/workflows', $data);

        $response->assertRedirect();
        $this->assertDatabaseHas('workflows', [
            'name' => 'Test Workflow',
            'type' => 'general',
        ]);
    }

    public function test_can_view_single_workflow()
    {
        $user = $this->makeAdminUser();
        $workflow = Workflow::factory()->create();

        $response = $this->actingAs($user)->get("/workflows/{$workflow->id}");

        $response->assertStatus(200);
    }
}
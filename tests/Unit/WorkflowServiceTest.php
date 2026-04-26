<?php

namespace Tests\Unit;

use App\Models\User;
use App\Models\Workflow;
use App\Models\Student;
use App\Enums\UserRoleEnum;
use App\Services\WorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkflowServiceTest extends TestCase
{
    use RefreshDatabase;

    protected WorkflowService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(WorkflowService::class);
    }

    public function test_can_start_workflow()
    {
        // Create an admin user to serve as approver
        User::factory()->create(['role' => UserRoleEnum::ADMIN]);
        
        $user = User::factory()->create();
        $student = Student::factory()->create();
        
        $workflow = Workflow::factory()->create([
            'steps' => [
                ['name' => 'Step 1', 'requires_approval' => true, 'approver_role' => 'admin'],
                ['name' => 'Step 2', 'requires_approval' => false],
            ],
        ]);

        $instance = $this->service->startWorkflow($workflow, $student, $user->id);

        $this->assertDatabaseHas('workflow_instances', [
            'id' => $instance->id,
            'workflow_id' => $workflow->id,
            'current_step' => 'Step 1',
            'status' => 'in_progress',
        ]);
    }

    public function test_can_advance_workflow()
    {
        // Create an admin user to serve as approver
        User::factory()->create(['role' => UserRoleEnum::ADMIN]);
        
        $user = User::factory()->create();
        $student = Student::factory()->create();
        
        $workflow = Workflow::factory()->create([
            'steps' => [
                ['name' => 'Step 1', 'requires_approval' => true, 'approver_role' => 'admin'],
                ['name' => 'Step 2', 'requires_approval' => false],
            ],
        ]);

        $instance = $this->service->startWorkflow($workflow, $student, $user->id);
        $this->service->advanceWorkflow($instance, $user->id);

        $instance->refresh();

        $this->assertEquals('Step 2', $instance->current_step);
    }

    public function test_workflow_completes_after_final_step()
    {
        $user = User::factory()->create();
        $student = Student::factory()->create();
        
        // With a single step that requires no approval, startWorkflow
        // auto-advances immediately and completes the workflow
        $workflow = Workflow::factory()->create([
            'steps' => [
                ['name' => 'Step 1', 'requires_approval' => false],
            ],
        ]);

        $instance = $this->service->startWorkflow($workflow, $student, $user->id);

        $instance->refresh();

        $this->assertEquals('completed', $instance->status);
        $this->assertNotNull($instance->completed_at);
    }
}
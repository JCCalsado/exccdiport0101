<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class WorkflowFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => $this->faker->sentence(3),
            'type' => $this->faker->randomElement(['student', 'accounting', 'general']),
            'description' => $this->faker->paragraph(),
            'steps' => [
                ['name' => 'Step 1', 'requires_approval' => false],
                ['name' => 'Step 2', 'requires_approval' => true, 'approvers' => [1]],
                ['name' => 'Step 3', 'requires_approval' => false],
            ],
            'is_active' => true,
        ];
    }
}
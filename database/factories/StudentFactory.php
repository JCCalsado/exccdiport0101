<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class StudentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'student_id' => date('Y') . '-' . str_pad($this->faker->unique()->numberBetween(1, 9999), 4, '0', STR_PAD_LEFT),
            'student_number' => 'STU-' . $this->faker->unique()->numberBetween(10000, 99999),
            'first_name' => $this->faker->firstName(),
            'last_name' => $this->faker->lastName(),
            'email' => $this->faker->unique()->safeEmail(),
            'phone' => $this->faker->phoneNumber(),
            'date_of_birth' => $this->faker->dateTimeBetween('-25 years', '-18 years'),
            'enrollment_status' => $this->faker->randomElement(['pending', 'active', 'suspended', 'graduated']),
            'enrollment_date' => $this->faker->dateTimeBetween('-2 years', 'now'),
            'course' => $this->faker->randomElement(['BS Computer Science', 'BS Information Technology', 'BS Engineering']),
            'year_level' => $this->faker->randomElement(['1st Year', '2nd Year', '3rd Year', '4th Year']),
        ];
    }
}
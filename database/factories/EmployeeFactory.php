<?php

namespace Database\Factories;

use App\Enums\Role;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Employee>
 */
class EmployeeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->firstName(),
            'role' => Role::Server,
            'pin' => (string) fake()->unique()->numberBetween(1000, 9999),
            'active' => true,
        ];
    }

    public function role(Role $role): static
    {
        return $this->state(['role' => $role]);
    }
}

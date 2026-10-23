<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\Position;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Position>
 */
class PositionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->bothify('P??-##')),
            'name' => fake()->unique()->jobTitle(),
            'department_id' => Department::factory(),
            'level' => fake()->numberBetween(1, 10),
            'description' => null,
            'is_active' => true,
        ];
    }
}

<?php

namespace Database\Factories;

use App\Enums\EmploymentStatus;
use App\Enums\Role;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Employee>
 */
class EmployeeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'nik' => strtoupper(fake()->unique()->bothify('EMP-#####')),
            'full_name' => fn (array $attributes) => User::query()->find($attributes['user_id'])?->name ?? fake()->name(),
            'gender' => fake()->randomElement(['L', 'P']),
            'birth_place' => fake()->city(),
            'birth_date' => fake()->dateTimeBetween('-50 years', '-20 years')->format('Y-m-d'),
            'phone' => '08'.fake()->numerify('##########'),
            'address' => fake()->address(),
            'department_id' => Department::factory(),
            'position_id' => fn (array $attributes) => Position::factory()->create(['department_id' => $attributes['department_id']])->id,
            'manager_id' => null,
            'employment_status' => EmploymentStatus::Permanent,
            'join_date' => fake()->dateTimeBetween('-5 years', '-1 month')->format('Y-m-d'),
            'contract_end_date' => null,
            'is_active' => true,
        ];
    }

    /** Akun karyawan diberi role tertentu (default factory: tanpa role). */
    public function withRole(Role $role): static
    {
        return $this->state(fn () => ['user_id' => User::factory()->withRole($role)]);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false, 'deactivated_at' => now()]);
    }

    public function reportsTo(Employee $manager): static
    {
        return $this->state(['manager_id' => $manager->id]);
    }
}

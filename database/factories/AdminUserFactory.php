<?php

namespace Database\Factories;

use App\Enums\AdminRole;
use App\Models\AdminUser;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AdminUser>
 */
class AdminUserFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'clerk_user_id' => 'user_'.Str::random(27),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'role' => AdminRole::Admin,
            'is_active' => true,
            'last_login_at' => null,
        ];
    }

    public function superAdmin(): static
    {
        return $this->state(fn (array $attributes) => ['role' => AdminRole::SuperAdmin]);
    }

    public function paymentsReviewer(): static
    {
        return $this->state(fn (array $attributes) => ['role' => AdminRole::PaymentsReviewer]);
    }

    public function support(): static
    {
        return $this->state(fn (array $attributes) => ['role' => AdminRole::Support]);
    }

    /**
     * Added from the dashboard; nobody has signed in with its email yet.
     */
    public function unlinked(): static
    {
        return $this->state(fn (array $attributes) => ['clerk_user_id' => null]);
    }
}

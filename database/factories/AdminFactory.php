<?php

namespace Database\Factories;

use App\Models\Admin;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Admin>
 */
class AdminFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'clerk_user_id' => 'user_'.Str::random(27),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'last_login_at' => null,
        ];
    }
}

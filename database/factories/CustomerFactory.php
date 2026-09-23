<?php

namespace Database\Factories;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    /**
     * A registered customer who installed the app.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'phone' => '+9639'.fake()->unique()->numerify('########'),
            'name' => fake()->name(),
            'birthdate' => fake()->dateTimeBetween('-60 years', '-18 years'),
            'qr_secret' => Str::random(40),
            'campaigns_muted' => false,
            'registered_at' => now(),
            'last_activity_at' => now(),
        ];
    }

    /**
     * A customer a merchant added by phone number, who has not signed up yet.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => null,
            'birthdate' => null,
            'qr_secret' => null,
            'registered_at' => null,
        ]);
    }
}

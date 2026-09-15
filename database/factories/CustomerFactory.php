<?php

namespace Database\Factories;

use App\Enums\CustomerStatus;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    /**
     * Define the model's default state: a customer who installed the app.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'phone' => '+9639'.fake()->unique()->numerify('########'),
            'name' => fake()->name(),
            'birthdate' => fake()->dateTimeBetween('-60 years', '-16 years'),
            'qr_token' => Str::random(40),
            'status' => CustomerStatus::Active,
            'locale' => 'ar',
            'phone_verified_at' => now(),
            'registered_at' => now(),
        ];
    }

    /**
     * A customer a merchant added by phone number who has not installed the app.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => null,
            'birthdate' => null,
            'qr_token' => null,
            'status' => CustomerStatus::Pending,
            'phone_verified_at' => null,
            'registered_at' => null,
        ]);
    }
}

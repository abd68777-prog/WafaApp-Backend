<?php

namespace Database\Factories;

use App\Enums\MerchantStatus;
use App\Models\Merchant;
use App\Models\Package;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<Merchant>
 */
class MerchantFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state: an approved, paying merchant.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'owner_name' => fake()->name(),
            'business_name' => fake()->company(),
            'phone' => '+9639'.fake()->unique()->numerify('########'),
            'email' => fake()->unique()->safeEmail(),
            'password' => static::$password ??= Hash::make('password'),
            'city' => fake()->city(),
            'package_id' => Package::factory(),
            'status' => MerchantStatus::Active,
            'approved_at' => now(),
            'trial_ends_at' => null,
            'subscription_ends_at' => now()->addMonth(),
            'birthday_gift_enabled' => false,
        ];
    }

    /**
     * A merchant whose sign-up request is waiting for admin review.
     */
    public function pendingReview(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => MerchantStatus::PendingReview,
            'approved_at' => null,
            'subscription_ends_at' => null,
        ]);
    }

    /**
     * A merchant still inside the free trial.
     */
    public function trial(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => MerchantStatus::Trial,
            'trial_ends_at' => now()->addDays(14),
            'subscription_ends_at' => null,
        ]);
    }

    public function suspended(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => MerchantStatus::Suspended,
            'subscription_ends_at' => now()->subDay(),
        ]);
    }
}

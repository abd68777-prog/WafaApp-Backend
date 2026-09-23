<?php

namespace Database\Factories;

use App\Enums\MerchantStatus;
use App\Models\BusinessType;
use App\Models\Governorate;
use App\Models\Merchant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<Merchant>
 */
class MerchantFactory extends Factory
{
    /**
     * A merchant who finished all three registration steps and is on trial.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'clerk_user_id' => 'user_'.Str::random(27),
            'email' => fake()->unique()->safeEmail(),
            'business_name' => fake()->company(),
            'business_type_id' => BusinessType::factory(),
            'governorate_id' => Governorate::factory(),
            'address' => null,
            'owner_name' => fake()->name(),
            'phone' => '+9639'.fake()->unique()->numerify('########'),
            'logo_path' => null,
            'pin_hash' => Hash::make('1234'),
            'status' => MerchantStatus::Trial,
        ];
    }

    /**
     * Business details saved, package not chosen yet.
     */
    public function awaitingPackage(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => null,
            'pin_hash' => null,
        ]);
    }

    /**
     * Package chosen, PIN not set yet.
     */
    public function awaitingPin(): static
    {
        return $this->state(fn (array $attributes) => ['pin_hash' => null]);
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => ['status' => MerchantStatus::Active]);
    }

    public function grace(): static
    {
        return $this->state(fn (array $attributes) => ['status' => MerchantStatus::Grace]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => ['status' => MerchantStatus::Expired]);
    }

    public function suspended(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => MerchantStatus::Suspended,
            'suspended_at' => now(),
            'suspension_reason' => 'Fraudulent stamps',
        ]);
    }
}

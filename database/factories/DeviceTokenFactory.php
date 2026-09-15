<?php

namespace Database\Factories;

use App\Enums\ClientApp;
use App\Enums\DevicePlatform;
use App\Models\Customer;
use App\Models\DeviceToken;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<DeviceToken>
 */
class DeviceTokenFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'owner_type' => (new Customer)->getMorphClass(),
            'owner_id' => Customer::factory(),
            'token' => Str::random(152),
            'platform' => DevicePlatform::Android,
            'app' => ClientApp::Customer,
            'last_seen_at' => now(),
        ];
    }
}

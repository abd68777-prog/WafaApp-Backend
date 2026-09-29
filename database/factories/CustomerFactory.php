<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Setting;
use App\Services\Customer\CustomerQrCode;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    /**
     * A registered customer who installed the app and completed the profile.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'phone' => '+9639'.fake()->unique()->numerify('########'),
            'name' => fake()->name(),
            'birthdate' => fake()->dateTimeBetween('-60 years', '-18 years'),
            'qr_id' => Str::random(CustomerQrCode::QR_ID_LENGTH),
            'qr_secret' => CustomerQrCode::newSecret(),
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
            'qr_id' => null,
            'qr_secret' => null,
            'registered_at' => null,
        ]);
    }

    /**
     * Signed in, but the name and birthdate screen is still ahead.
     */
    public function withoutProfile(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => null,
            'birthdate' => null,
        ]);
    }

    /**
     * Agreed to the current privacy policy, which the customer app requires
     * before anything past the account screen.
     */
    public function consented(): static
    {
        return $this->afterCreating(function (Customer $customer): void {
            $customer->policyConsents()->create([
                'policy_version' => (string) Setting::read('privacy_policy_version', '1.2'),
                'consented_at' => now(),
            ]);
        });
    }
}

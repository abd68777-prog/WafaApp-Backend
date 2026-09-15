<?php

namespace Database\Factories;

use App\Enums\BillingCycle;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Merchant;
use App\Models\Package;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * Define the model's default state: a transfer waiting for admin review.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'merchant_id' => Merchant::factory(),
            'package_id' => Package::factory(),
            'billing_cycle' => BillingCycle::Monthly,
            'amount_usd' => '10.00',
            'amount_syp' => '130000.00',
            'exchange_rate' => '13000.0000',
            'method' => PaymentMethod::SyriatelCash,
            'reference' => fake()->numerify('TX########'),
            'proof_path' => 'payments/proofs/'.fake()->uuid().'.jpg',
            'status' => PaymentStatus::Pending,
            'paid_at' => now(),
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PaymentStatus::Approved,
            'reviewed_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PaymentStatus::Rejected,
            'reviewed_at' => now(),
            'rejection_reason' => 'The transfer could not be found.',
        ]);
    }
}

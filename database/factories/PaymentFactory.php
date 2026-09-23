<?php

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Enums\PaymentRejectionReason;
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
     * A transfer waiting in the review queue, with its prices already copied.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'merchant_id' => Merchant::factory(),
            'package_id' => Package::factory(),
            'duration_months' => 1,
            'price_usd' => '10.00',
            'exchange_rate' => '13000.0000',
            'amount_syp' => '130000.00',
            'method' => PaymentMethod::SyriatelCash,
            'reference' => fake()->numerify('TX########'),
            'proof_path' => 'payments/proofs/'.fake()->uuid().'.jpg',
            'status' => PaymentStatus::Pending,
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
            'rejection_reason' => PaymentRejectionReason::TransferNotReceived,
            'reviewed_at' => now(),
        ]);
    }
}

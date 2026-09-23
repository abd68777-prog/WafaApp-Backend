<?php

namespace Database\Factories;

use App\Enums\DeletionSource;
use App\Enums\DeletionSubjectType;
use App\Models\Customer;
use App\Models\DeletionRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DeletionRequest>
 */
class DeletionRequestFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'subject_type' => DeletionSubjectType::Customer,
            'subject_id' => Customer::factory(),
            'source' => DeletionSource::App,
            'requested_at' => now(),
            'executes_at' => null,
        ];
    }

    /**
     * A merchant deletion waits thirty days and can be cancelled meanwhile.
     */
    public function forMerchant(int $subjectId): static
    {
        return $this->state(fn (array $attributes) => [
            'subject_type' => DeletionSubjectType::Merchant,
            'subject_id' => $subjectId,
            'source' => DeletionSource::Support,
            'executes_at' => now()->addDays(30),
        ]);
    }
}

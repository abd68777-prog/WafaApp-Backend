<?php

namespace Database\Factories;

use App\Models\TrialEmailHash;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TrialEmailHash>
 */
class TrialEmailHashFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'email_hash' => TrialEmailHash::fingerprint(fake()->unique()->safeEmail()),
        ];
    }
}

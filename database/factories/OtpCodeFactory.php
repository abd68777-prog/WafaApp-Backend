<?php

namespace Database\Factories;

use App\Models\OtpCode;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<OtpCode>
 */
class OtpCodeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'phone' => '+9639'.fake()->numerify('########'),
            'code_hash' => Hash::make('123456'),
            'channel' => 'whatsapp',
            'attempts' => 0,
            'expires_at' => now()->addMinutes(5),
            'ip_address' => fake()->ipv4(),
        ];
    }
}

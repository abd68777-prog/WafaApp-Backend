<?php

namespace Database\Factories;

use App\Models\AdminUser;
use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AuditLog>
 */
class AuditLogFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'admin_user_id' => AdminUser::factory(),
            'action' => 'payment.approved',
            'subject_type' => 'payment',
            'subject_id' => 1,
            'before' => null,
            'after' => null,
            'ip_address' => fake()->ipv4(),
        ];
    }
}

<?php

namespace App\Models;

use Database\Factories\PolicyConsentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Which version of the privacy policy a customer accepted, and when — needed
 * to prove consent after the policy changes.
 */
#[Fillable(['policy_version', 'consented_at'])]
class PolicyConsent extends Model
{
    /** @use HasFactory<PolicyConsentFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'consented_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}

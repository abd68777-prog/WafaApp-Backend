<?php

namespace App\Http\Resources;

use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * A payment as the merchant sees it (contract Payment). Amounts are decimal
 * strings, and the proof is a short-lived signed link, never a storage path.
 * Needs `package`.
 *
 * @mixin Payment
 */
class PaymentResource extends JsonResource
{
    /**
     * How long the link to the proof image stays valid.
     */
    private const PROOF_URL_MINUTES = 30;

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'package' => new PackageSummaryResource($this->package),
            'duration_months' => $this->duration_months,
            'price_usd' => (string) $this->price_usd,
            'exchange_rate' => (string) $this->exchange_rate,
            'amount_syp' => (string) $this->amount_syp,
            'method' => $this->method->value,
            'reference' => $this->reference,
            'proof_url' => Storage::temporaryUrl($this->proof_path, now()->addMinutes(self::PROOF_URL_MINUTES)),
            'status' => $this->status->value,
            'rejection_reason' => $this->rejection_reason?->value,
            'created_at' => $this->created_at->toIso8601ZuluString(),
            'reviewed_at' => $this->reviewed_at?->toIso8601ZuluString(),
        ];
    }
}

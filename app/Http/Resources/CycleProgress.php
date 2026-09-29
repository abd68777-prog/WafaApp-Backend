<?php

namespace App\Http\Resources;

use App\Models\CardCycle;

/**
 * A customer's progress on one card (contract CycleProgress).
 *
 * Built from null when no cycle is open — before the first stamp, or after a
 * reward was handed over, since the next cycle opens with the next stamp
 * (decision 1) — and shown as zero progress with `id` null. A plain array
 * rather than a JsonResource, which would collapse to null when nested.
 */
final class CycleProgress
{
    /**
     * @return array{id: int|null, stamps_count: int, status: string, completed_at: string|null}
     */
    public static function of(?CardCycle $cycle): array
    {
        return [
            'id' => $cycle?->id,
            'stamps_count' => $cycle->stamps_count ?? 0,
            'status' => $cycle?->status->value ?? 'COLLECTING',
            'completed_at' => $cycle?->completed_at?->toIso8601ZuluString(),
        ];
    }
}

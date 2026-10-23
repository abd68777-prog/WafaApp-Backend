<?php

namespace App\Http\Resources;

use App\Models\DeletionRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An account deletion request (requirements §3.6 and §5.4). A shop's request
 * waits 30 days and shows its business name, which outlives the deletion; a
 * customer's is carried out at once, so nothing is left to name it by.
 *
 * Needs `handledBy` loaded; the controller sets `subject_name` for the page.
 *
 * @mixin DeletionRequest
 */
class DeletionRequestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'subject_type' => $this->subject_type->value,
            'subject_id' => $this->subject_id,
            'subject_name' => $this->getAttribute('subject_name'),
            'source' => $this->source->value,
            'status' => match (true) {
                $this->executed_at !== null => 'executed',
                $this->cancelled_at !== null => 'cancelled',
                default => 'pending',
            },
            'note' => $this->note,
            'requested_at' => $this->requested_at->toIso8601ZuluString(),
            'executes_at' => $this->executes_at?->toIso8601ZuluString(),
            'executed_at' => $this->executed_at?->toIso8601ZuluString(),
            'cancelled_at' => $this->cancelled_at?->toIso8601ZuluString(),
            'handled_by' => $this->handledBy === null ? null : [
                'id' => $this->handledBy->id,
                'name' => $this->handledBy->name,
            ],
        ];
    }
}

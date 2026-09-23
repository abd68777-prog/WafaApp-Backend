<?php

namespace App\Models;

use App\Enums\DeletionSource;
use App\Enums\DeletionSubjectType;
use Database\Factories\DeletionRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Account deletion requests from customers and merchants.
 *
 * The subject is a type plus an id because both kinds live here, and requests
 * also arrive from the web page or by email — a path Google requires for
 * people who cannot open the app.
 */
#[Fillable(['subject_type', 'subject_id', 'source', 'requested_at', 'executes_at', 'note'])]
class DeletionRequest extends Model
{
    /** @use HasFactory<DeletionRequestFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'subject_type' => DeletionSubjectType::class,
            'source' => DeletionSource::class,
            'requested_at' => 'datetime',
            'executes_at' => 'datetime',
            'executed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function isPending(): bool
    {
        return $this->executed_at === null && $this->cancelled_at === null;
    }

    /**
     * @return BelongsTo<AdminUser, $this>
     */
    public function handledBy(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'handled_by_admin_id');
    }
}

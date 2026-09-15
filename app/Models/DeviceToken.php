<?php

namespace App\Models;

use App\Enums\ClientApp;
use App\Enums\DevicePlatform;
use Database\Factories\DeviceTokenFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable(['token', 'platform', 'app', 'last_seen_at'])]
class DeviceToken extends Model
{
    /** @use HasFactory<DeviceTokenFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'platform' => DevicePlatform::class,
            'app' => ClientApp::class,
            'last_seen_at' => 'datetime',
        ];
    }

    /**
     * The customer or merchant the device belongs to.
     *
     * @return MorphTo<Customer|Merchant, $this>
     */
    public function owner(): MorphTo
    {
        return $this->morphTo();
    }
}

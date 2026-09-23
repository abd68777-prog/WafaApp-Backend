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

    /**
     * Record the push token of the device the owner is signed in on.
     *
     * A token identifies a device, not a person: when someone else signs in
     * on the same phone, the token moves to them, so the previous account
     * stops receiving that phone's notifications.
     */
    public static function register(Customer|Merchant $owner, string $token, DevicePlatform $platform): self
    {
        $deviceToken = static::query()->firstOrNew(['token' => $token]);

        $deviceToken->fill([
            'platform' => $platform,
            'app' => $owner instanceof Merchant ? ClientApp::Merchant : ClientApp::Customer,
            'last_seen_at' => now(),
        ]);

        $deviceToken->owner()->associate($owner)->save();

        return $deviceToken;
    }
}

<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use Database\Factories\PaymentAccountFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * Where a payment method takes the money, as the merchant's payment screen
 * shows it: Sham Cash by its account code and QR image, Al-Haram by the
 * recipient's name and number. One row per PaymentMethod, managed from the
 * dashboard; only active rows reach the merchant app.
 */
#[Fillable(['is_active', 'sort_order', 'recipient_name', 'account', 'note'])]
class PaymentAccount extends Model
{
    /** @use HasFactory<PaymentAccountFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'method' => PaymentMethod::class,
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * The methods the merchant app offers, in the dashboard's order.
     *
     * @return Builder<PaymentAccount>
     */
    public static function offered(): Builder
    {
        return self::query()->where('is_active', true)->orderBy('sort_order')->orderBy('id');
    }

    public function qrUrl(): ?string
    {
        return $this->qr_path !== null ? Storage::disk(config('filesystems.media_disk'))->url($this->qr_path) : null;
    }
}

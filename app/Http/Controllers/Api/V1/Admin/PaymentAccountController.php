<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\UpdatePaymentAccountRequest;
use App\Http\Requests\Api\V1\Admin\UploadPaymentQrRequest;
use App\Http\Resources\AdminPaymentAccountResource;
use App\Models\AdminUser;
use App\Models\PaymentAccount;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Where each payment method takes the money (part of the billing settings):
 * the account, the recipient, an optional QR image, a short note, whether
 * the merchant app offers it and in which position. Every change is in the
 * audit trail.
 */
class PaymentAccountController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function update(UpdatePaymentAccountRequest $request, PaymentAccount $paymentAccount): JsonResponse
    {
        $this->recordChange($request, $paymentAccount, function () use ($request, $paymentAccount): void {
            $paymentAccount->update($request->validated());
        });

        return $this->paymentAccount($paymentAccount);
    }

    /**
     * A new QR image; the old file goes.
     */
    public function qr(UploadPaymentQrRequest $request, PaymentAccount $paymentAccount): JsonResponse
    {
        $disk = config('filesystems.media_disk');
        $path = $request->file('qr')->store('payment-methods', $disk)
            ?: throw new RuntimeException('The QR image could not be stored.');

        $previous = $paymentAccount->qr_path;

        $this->recordChange($request, $paymentAccount, function () use ($paymentAccount, $path): void {
            $paymentAccount->forceFill(['qr_path' => $path])->save();
        });

        if ($previous !== null) {
            Storage::disk($disk)->delete($previous);
        }

        return $this->paymentAccount($paymentAccount);
    }

    /**
     * No QR any more; removing it again changes nothing.
     */
    public function destroyQr(Request $request, PaymentAccount $paymentAccount): JsonResponse
    {
        $previous = $paymentAccount->qr_path;

        if ($previous !== null) {
            $this->recordChange($request, $paymentAccount, function () use ($paymentAccount): void {
                $paymentAccount->forceFill(['qr_path' => null])->save();
            });

            Storage::disk(config('filesystems.media_disk'))->delete($previous);
        }

        return $this->paymentAccount($paymentAccount);
    }

    private function recordChange(Request $request, PaymentAccount $paymentAccount, callable $change): void
    {
        /** @var AdminUser $admin */
        $admin = $request->user();
        $before = $this->auditedFields($paymentAccount);

        $change();

        $this->audit->record($admin, 'settings.payment_method_updated', null, $before, $this->auditedFields($paymentAccount->refresh()), $request->ip());
    }

    /**
     * @return array<string, mixed>
     */
    private function auditedFields(PaymentAccount $paymentAccount): array
    {
        return [
            'method' => $paymentAccount->method->value,
            ...$paymentAccount->only(['is_active', 'sort_order', 'recipient_name', 'account', 'qr_path', 'note']),
        ];
    }

    private function paymentAccount(PaymentAccount $paymentAccount): JsonResponse
    {
        return (new AdminPaymentAccountResource($paymentAccount->refresh()))->response();
    }
}

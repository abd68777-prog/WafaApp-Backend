<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\PaymentRejectionReason;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\RejectPaymentRequest;
use App\Http\Resources\AdminPaymentResource;
use App\Models\AdminUser;
use App\Models\Payment;
use App\Services\Billing\PaymentReview;
use App\Support\CursorPage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The payments reviewer's queue (requirements §5.2). Pending payments come
 * oldest first, so nobody waits behind newer uploads; reviewed ones newest
 * first, for looking back.
 */
class PaymentController extends Controller
{
    public function __construct(private readonly PaymentReview $review) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['sometimes', Rule::enum(PaymentStatus::class)],
        ]);
        $status = PaymentStatus::from($validated['status'] ?? PaymentStatus::Pending->value);

        return CursorPage::respond(
            $request,
            Payment::query()
                ->with(['package', 'merchant', 'reviewedBy'])
                ->where('status', $status)
                ->orderBy('id', $status === PaymentStatus::Pending ? 'asc' : 'desc'),
            AdminPaymentResource::class,
        );
    }

    public function show(Payment $payment): JsonResponse
    {
        return (new AdminPaymentResource($payment->load(['package', 'merchant', 'reviewedBy'])))->response();
    }

    public function approve(Request $request, Payment $payment): JsonResponse
    {
        $payment = $this->review->approve($payment, $this->reviewer($request), $request->ip());

        return (new AdminPaymentResource($payment->refresh()->load(['package', 'merchant', 'reviewedBy'])))->response();
    }

    public function reject(RejectPaymentRequest $request, Payment $payment): JsonResponse
    {
        $payment = $this->review->reject(
            $payment,
            $request->enum('reason', PaymentRejectionReason::class),
            $this->reviewer($request),
            $request->ip(),
        );

        return (new AdminPaymentResource($payment->refresh()->load(['package', 'merchant', 'reviewedBy'])))->response();
    }

    private function reviewer(Request $request): AdminUser
    {
        return $request->user();
    }
}

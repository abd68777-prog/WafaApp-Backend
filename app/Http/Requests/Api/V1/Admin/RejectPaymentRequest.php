<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Enums\PaymentRejectionReason;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A rejection names one reason from the closed list (requirements §3.3).
 */
class RejectPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', Rule::enum(PaymentRejectionReason::class)],
        ];
    }
}

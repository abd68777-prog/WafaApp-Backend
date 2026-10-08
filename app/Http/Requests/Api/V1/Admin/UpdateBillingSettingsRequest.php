<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The payment screen's details and the subscription timings (requirements
 * §5.5). Send only the fields to change.
 */
class UpdateBillingSettingsRequest extends FormRequest
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
            'exchange_rate_syp' => ['sometimes', 'numeric', 'min:1', 'max:10000000'],
            'syriatel_cash_number' => ['sometimes', 'nullable', 'string', 'max:30'],
            'bank_transfer_details' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'payment_review_sla_hours' => ['sometimes', 'integer', 'min:1', 'max:168'],
            'trial_days' => ['sometimes', 'integer', 'min:1', 'max:90'],
            'grace_days' => ['sometimes', 'integer', 'min:0', 'max:30'],
        ];
    }
}

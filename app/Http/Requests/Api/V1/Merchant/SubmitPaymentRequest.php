<?php

namespace App\Http\Requests\Api\V1\Merchant;

use App\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A transfer proof (contract §5.9). No amount is read from the request: the
 * server copies the price and the exchange rate in at upload time.
 */
class SubmitPaymentRequest extends FormRequest
{
    public const DURATIONS = [1, 3, 12];

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
            'package_id' => ['required', 'integer', Rule::exists('packages', 'id')->where('is_active', true)],
            'duration_months' => ['required', 'integer', Rule::in(self::DURATIONS)],
            'method' => ['required', Rule::enum(PaymentMethod::class)],
            'reference' => ['sometimes', 'nullable', 'string', 'max:100'],
            'proof' => ['required', 'image', 'mimes:jpeg,png,webp', 'max:5120'],
            'keep_card_ids' => ['sometimes', 'array'],
            'keep_card_ids.*' => ['integer', 'distinct'],
        ];
    }
}

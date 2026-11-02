<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Models\PaymentAccount;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Where one payment method takes the money, whether the merchant app offers
 * it, and its position. Send only the fields to change; null clears a text.
 */
class UpdatePaymentAccountRequest extends FormRequest
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
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:1000'],
            'recipient_name' => ['sometimes', 'nullable', 'string', 'max:100'],
            'account' => ['sometimes', 'nullable', 'string', 'max:100'],
            'note' => ['sometimes', 'nullable', 'string', 'max:500'],
        ];
    }

    /**
     * An offered method must say where to send the money, so a merchant is
     * never shown one with nothing to pay to.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                /** @var PaymentAccount $paymentAccount */
                $paymentAccount = $this->route('paymentAccount');
                $active = $this->boolean('is_active', $paymentAccount->is_active);
                $account = $this->exists('account') ? $this->input('account') : $paymentAccount->account;

                if ($active && blank($account)) {
                    $validator->errors()->add('account', 'An offered payment method needs an account.');
                }
            },
        ];
    }
}

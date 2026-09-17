<?php

namespace App\Http\Requests\Api\V1\Merchant;

use App\Rules\SyrianPhone;
use App\Support\PhoneNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterMerchantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normalize the phone before validation so the uniqueness check compares
     * `0933…` and `+963933…` as the same number.
     */
    protected function prepareForValidation(): void
    {
        $normalized = PhoneNumber::normalize((string) $this->input('phone', ''));

        if ($normalized !== null) {
            $this->merge(['phone' => $normalized]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'business_name' => ['required', 'string', 'max:255'],
            'owner_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', new SyrianPhone, Rule::unique('merchants', 'phone')],
            'email' => ['sometimes', 'nullable', 'email:rfc', 'max:255', Rule::unique('merchants', 'email')],
            'city' => ['sometimes', 'nullable', 'string', 'max:100'],
            'address' => ['sometimes', 'nullable', 'string', 'max:255'],
            'package_code' => ['required', 'string', Rule::exists('packages', 'code')->where('is_active', true)],
        ];
    }
}

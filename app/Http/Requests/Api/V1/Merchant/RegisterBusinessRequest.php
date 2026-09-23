<?php

namespace App\Http\Requests\Api\V1\Merchant;

use App\Rules\SyrianPhone;
use App\Support\PhoneNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Step one of merchant registration: the business details.
 *
 * The business name and type can only be corrected by support afterwards, so
 * they are validated against the managed lists here.
 */
class RegisterBusinessRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normalize the phone before validation so `0933…` and `+963933…` are
     * compared as the same number.
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
            'business_type_id' => ['required', Rule::exists('business_types', 'id')->where('is_active', true)],
            'governorate_id' => ['required', Rule::exists('governorates', 'id')->where('is_active', true)],
            'address' => ['sometimes', 'nullable', 'string', 'max:255'],
            'owner_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', new SyrianPhone, Rule::unique('merchants', 'phone')],
            'logo' => ['sometimes', 'nullable', 'image', 'max:2048'],
        ];
    }
}

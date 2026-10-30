<?php

namespace App\Http\Requests\Api\V1\Merchant;

use App\Enums\ShopLink;
use App\Rules\ShopLinkUrl;
use App\Rules\SyrianPhone;
use App\Support\PhoneNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * What the merchant may change about the shop (contract §5.10): the owner's
 * name, the contact phone, the address and the shop's links. The business name, type and
 * governorate are changed by support only.
 */
class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('phone')) {
            $normalized = PhoneNumber::normalize((string) $this->input('phone'));

            if ($normalized !== null) {
                $this->merge(['phone' => $normalized]);
            }
        }

        if (filled($this->input('contact_phone'))) {
            $this->merge(['contact_phone' => PhoneNumber::normalizeContact((string) $this->input('contact_phone')) ?? $this->input('contact_phone')]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'owner_name' => ['sometimes', 'string', 'min:2', 'max:80'],
            'phone' => ['sometimes', 'string', new SyrianPhone, Rule::unique('merchants', 'phone')->ignore($this->user())],
            'address' => ['sometimes', 'nullable', 'string', 'max:255'],
            // Shown to every customer in the shops list; null hides it.
            'contact_phone' => ['sometimes', 'nullable', 'string', 'regex:/^\+963[1-9]\d{7,8}$/'],
            // Icons on the shop's page; null removes one.
            'instagram_url' => ['sometimes', 'nullable', 'string', 'max:255', new ShopLinkUrl(ShopLink::Instagram)],
            'facebook_url' => ['sometimes', 'nullable', 'string', 'max:255', new ShopLinkUrl(ShopLink::Facebook)],
            'maps_url' => ['sometimes', 'nullable', 'string', 'max:255', new ShopLinkUrl(ShopLink::Maps)],
        ];
    }
}

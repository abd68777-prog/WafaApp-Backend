<?php

namespace App\Http\Requests\Api\V1\Merchant;

use App\Rules\SyrianPhone;
use App\Support\PhoneNumber;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The card chosen on the scan screen, and either the raw text the camera read
 * or a number the cashier typed — exactly one of the two.
 */
class ResolveScanRequest extends FormRequest
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
            'card_id' => ['required', 'integer'],
            'qr' => ['required_without:phone', 'prohibits:phone', 'string', 'max:64'],
            'phone' => ['required_without:qr', 'string', new SyrianPhone],
        ];
    }

    /**
     * The typed number in E.164 form, or null when the code was scanned.
     */
    public function phoneE164(): ?string
    {
        return $this->filled('phone') ? PhoneNumber::normalize($this->string('phone')->value()) : null;
    }
}

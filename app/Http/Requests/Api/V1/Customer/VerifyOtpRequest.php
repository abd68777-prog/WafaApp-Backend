<?php

namespace App\Http\Requests\Api\V1\Customer;

use App\Rules\SyrianPhone;
use App\Support\PhoneNumber;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The code from WhatsApp, and the privacy policy version shown on the phone
 * number screen. Name and birthdate come later, in `POST /customer/me/profile`.
 */
class VerifyOtpRequest extends FormRequest
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
            'phone' => ['required', 'string', new SyrianPhone],
            'code' => ['required', 'string', 'regex:/^\d{'.(int) config('otp.length').'}$/'],
            'policy_version' => ['required', 'string', 'max:16'],
        ];
    }

    /**
     * The validated phone number in E.164 form.
     */
    public function phoneE164(): string
    {
        return PhoneNumber::normalize($this->string('phone')->value());
    }
}

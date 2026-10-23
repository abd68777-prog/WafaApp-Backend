<?php

namespace App\Http\Requests\Api\V1\Public;

use App\Rules\SyrianPhone;
use App\Support\PhoneNumber;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Deleting a customer account from the web page, without the app: the number
 * and the WhatsApp code that proves it is yours.
 */
class DeleteAccountRequest extends FormRequest
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

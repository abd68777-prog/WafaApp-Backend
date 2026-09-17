<?php

namespace App\Http\Requests\Api\V1\Customer;

use App\Rules\SyrianPhone;
use App\Support\PhoneNumber;
use Illuminate\Foundation\Http\FormRequest;

class RequestOtpRequest extends FormRequest
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

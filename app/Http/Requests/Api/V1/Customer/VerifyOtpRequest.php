<?php

namespace App\Http\Requests\Api\V1\Customer;

use App\Rules\SyrianPhone;
use App\Support\PhoneNumber;
use Illuminate\Foundation\Http\FormRequest;

class VerifyOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * `name` is only required when the account is being created, which is
     * decided in the controller after the code itself has been checked.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'phone' => ['required', 'string', new SyrianPhone],
            'code' => ['required', 'string'],
            'name' => ['sometimes', 'string', 'max:255'],
            'birthdate' => ['sometimes', 'nullable', 'date', 'before:today'],
            'device_name' => ['sometimes', 'string', 'max:255'],
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

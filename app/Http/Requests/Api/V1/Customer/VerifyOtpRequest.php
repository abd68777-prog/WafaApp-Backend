<?php

namespace App\Http\Requests\Api\V1\Customer;

use App\Models\Setting;
use App\Rules\SyrianPhone;
use App\Support\PhoneNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VerifyOtpRequest extends FormRequest
{
    /**
     * Nobody under 13 may hold an account (privacy policy §1.2).
     */
    public const MINIMUM_AGE = 13;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * `name`, `birthdate` and `policy_version` complete a new account. They are
     * required in the controller once the code proves the caller owns the
     * number, so the endpoint never reveals who is registered.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'phone' => ['required', 'string', new SyrianPhone],
            'code' => ['required', 'string'],
            'name' => ['sometimes', 'string', 'max:255'],
            'birthdate' => [
                'sometimes',
                'date',
                'before_or_equal:'.now()->subYears(self::MINIMUM_AGE)->toDateString(),
            ],
            'policy_version' => ['sometimes', 'string', Rule::in([Setting::read('privacy_policy_version', '1.2')])],
            'device_name' => ['sometimes', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'birthdate.before_or_equal' => 'You must be at least '.self::MINIMUM_AGE.' years old to use Wafa.',
            'policy_version.in' => 'This version of the privacy policy is no longer current.',
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

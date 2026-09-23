<?php

namespace App\Http\Requests\Api\V1\Customer;

use App\Models\Setting;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Agreement to a new version of the privacy policy, after the app showed the
 * customer what changed.
 */
class AcceptPolicyRequest extends FormRequest
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
            'policy_version' => ['required', 'string', Rule::in([Setting::read('privacy_policy_version', '1.2')])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'policy_version.in' => 'This version of the privacy policy is no longer current.',
        ];
    }
}

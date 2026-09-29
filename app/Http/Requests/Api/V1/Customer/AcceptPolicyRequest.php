<?php

namespace App\Http\Requests\Api\V1\Customer;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Agreement to a new version of the privacy policy, after the app showed the
 * customer what changed. A version that is not the current one is refused
 * with POLICY_VERSION_OUTDATED by the controller.
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
            'policy_version' => ['required', 'string', 'max:16'],
        ];
    }
}

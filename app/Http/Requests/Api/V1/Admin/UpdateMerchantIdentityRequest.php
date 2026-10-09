<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Correcting a shop's business name or type (requirements §5.3), which the
 * merchant app cannot change. Send only the fields to change, with a reason.
 *
 * Support corrects these within the first 7 days after sign-up; that is an
 * operating rule, so the registration date is shown and nothing is enforced.
 */
class UpdateMerchantIdentityRequest extends FormRequest
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
            'business_name' => ['sometimes', 'string', 'min:2', 'max:80'],
            'business_type_id' => ['sometimes', 'integer', Rule::exists('business_types', 'id')->where('is_active', true)],
            'reason' => ['required', 'string', 'min:3', 'max:255'],
        ];
    }
}

<?php

namespace App\Http\Requests\Api\V1\Customer;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The only account setting the app can change: switching off every
 * merchant's campaigns. Name and birthdate are not editable from the app.
 */
class UpdateMeRequest extends FormRequest
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
            'campaigns_muted' => ['required', 'boolean'],
        ];
    }
}

<?php

namespace App\Http\Requests\Api\V1\Customer;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The name and birthdate, asked once after the first sign-in. The minimum age
 * is checked by the controller so the app gets UNDER_AGE, not a field error.
 */
class CompleteProfileRequest extends FormRequest
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
            'name' => ['required', 'string', 'min:2', 'max:60'],
            'birthdate' => ['required', 'date_format:Y-m-d', 'before:today'],
        ];
    }
}

<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Correcting a customer's birthdate (requirements §5.4), done by support once
 * the customer's identity is confirmed. The app never lets them change it.
 */
class UpdateCustomerBirthdateRequest extends FormRequest
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
            'birthdate' => ['required', 'date_format:Y-m-d', 'before:today'],
            'reason' => ['required', 'string', 'min:3', 'max:255'],
        ];
    }
}

<?php

namespace App\Http\Requests\Api\V1\Merchant;

use Illuminate\Foundation\Http\FormRequest;

/**
 * A new PIN for an owner who forgot the old one, right after signing in to
 * Clerk again.
 */
class ResetPinRequest extends FormRequest
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
            'new_pin' => ['required', 'string', SetPinRequest::PIN_RULE],
        ];
    }
}

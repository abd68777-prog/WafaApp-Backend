<?php

namespace App\Http\Requests\Api\V1\Merchant;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Changing the PIN from the settings tab: the current PIN, then the new one.
 */
class ChangePinRequest extends FormRequest
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
            'current_pin' => ['required', 'string', SetPinRequest::PIN_RULE],
            'new_pin' => ['required', 'string', SetPinRequest::PIN_RULE],
        ];
    }
}

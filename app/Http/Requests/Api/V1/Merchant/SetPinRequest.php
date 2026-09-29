<?php

namespace App\Http\Requests\Api\V1\Merchant;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The PIN that guards the sensitive tabs, set in registration step three.
 */
class SetPinRequest extends FormRequest
{
    /**
     * Four to six digits (contract Pin).
     */
    public const PIN_RULE = 'regex:/^\d{4,6}$/';

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
            'pin' => ['required', 'string', self::PIN_RULE],
        ];
    }
}

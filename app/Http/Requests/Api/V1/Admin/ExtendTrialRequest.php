<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * A manual trial extension always carries its reason (requirements §5.3).
 */
class ExtendTrialRequest extends FormRequest
{
    public const MAX_DAYS = 90;

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
            'days' => ['required', 'integer', 'min:1', 'max:'.self::MAX_DAYS],
            'reason' => ['required', 'string', 'min:3', 'max:255'],
        ];
    }
}

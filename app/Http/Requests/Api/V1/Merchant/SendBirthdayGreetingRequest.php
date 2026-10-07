<?php

namespace App\Http\Requests\Api\V1\Merchant;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The merchant's own birthday message, and an optional gift offered with it.
 * Links are refused by the controller with CAMPAIGN_CONTAINS_LINK, as in
 * campaigns.
 */
class SendBirthdayGreetingRequest extends FormRequest
{
    public const MESSAGE_MAX = 300;

    public const GIFT_MAX = 60;

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
            'message' => ['required', 'string', 'max:'.self::MESSAGE_MAX],
            'gift' => ['sometimes', 'nullable', 'string', 'max:'.self::GIFT_MAX],
        ];
    }
}

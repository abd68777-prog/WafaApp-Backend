<?php

namespace App\Http\Requests\Api\V1\Merchant;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Confirming a stamp: the token from the preview and the id the app generated
 * for this tap of "confirm", reused as is when the request is retried.
 */
class StoreStampRequest extends FormRequest
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
            'scan_token' => ['required', 'string'],
            'client_uuid' => ['required', 'uuid'],
        ];
    }
}

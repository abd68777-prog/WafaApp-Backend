<?php

namespace App\Http\Requests\Api\V1\Merchant;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Handing over a reward: the token from the preview of a scanned code, and
 * the cycle whose reward is handed over.
 */
class StoreRedemptionRequest extends FormRequest
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
            'cycle_id' => ['required', 'integer'],
        ];
    }
}

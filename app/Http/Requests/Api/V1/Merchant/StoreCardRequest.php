<?php

namespace App\Http\Requests\Api\V1\Merchant;

use App\Models\Setting;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A new card (contract CreateCardRequest). The stamp range comes from the
 * runtime settings, the same values `GET /merchant/lookups` gives the app.
 */
class StoreCardRequest extends FormRequest
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
            'stamps_required' => [
                'required',
                'integer',
                'min:'.(int) Setting::read('card_stamps_min', 3),
                'max:'.(int) Setting::read('card_stamps_max', 10),
            ],
            'reward_description' => ['required', 'string', 'min:2', 'max:120'],
            'terms' => ['sometimes', 'nullable', 'string', 'max:500'],
            'icon_id' => ['required', 'integer', Rule::exists('icons', 'id')->where('is_active', true)],
        ];
    }
}

<?php

namespace App\Http\Requests\Api\V1\Merchant;

use App\Enums\CardColor;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A published card's new color, from the fixed palette.
 */
class UpdateCardColorRequest extends FormRequest
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
            'color' => ['required', Rule::enum(CardColor::class)],
        ];
    }
}

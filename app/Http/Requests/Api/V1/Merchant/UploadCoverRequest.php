<?php

namespace App\Http\Requests\Api\V1\Merchant;

use Illuminate\Foundation\Http\FormRequest;

/**
 * A new cover for the shop's page: JPEG, PNG or WebP up to 5 MB, sent as
 * multipart by POST. A phone photo is larger than a logo; the server crops
 * and shrinks it anyway, so what is kept is small.
 */
class UploadCoverRequest extends FormRequest
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
            'cover' => ['required', 'image', 'mimes:jpeg,png,webp', 'max:5120', 'dimensions:min_width=1,min_height=1'],
        ];
    }
}

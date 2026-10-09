<?php

namespace App\Http\Requests\Api\V1\Merchant;

use Illuminate\Foundation\Http\FormRequest;

/**
 * A new shop logo, sent as multipart by POST: PHP reads no files from a
 * PATCH request.
 */
class UploadLogoRequest extends FormRequest
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
            'logo' => ['required', ...RegisterBusinessRequest::LOGO_RULES],
        ];
    }
}

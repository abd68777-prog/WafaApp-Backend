<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Http\Requests\Api\V1\Merchant\RegisterBusinessRequest;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The QR code a merchant scans to pay with a method (Sham Cash), sent as
 * multipart by POST like the shop logo.
 */
class UploadPaymentQrRequest extends FormRequest
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
            'qr' => ['required', ...RegisterBusinessRequest::LOGO_RULES],
        ];
    }
}

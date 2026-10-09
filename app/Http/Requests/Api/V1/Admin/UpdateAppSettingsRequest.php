<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Models\Setting;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Stamps, campaigns, the merchant app's versions, the legal links and the PIN
 * unlock (requirements §5.5). Send only the fields to change.
 */
class UpdateAppSettingsRequest extends FormRequest
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
            'stamp_interval_minutes' => ['sometimes', 'integer', 'min:0', 'max:1440'],
            'card_stamps_min' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'card_stamps_max' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'qr_period_seconds' => ['sometimes', 'integer', 'min:15', 'max:600'],
            'campaign_title_max' => ['sometimes', 'integer', 'min:10', 'max:255'],
            'campaign_body_max' => ['sometimes', 'integer', 'min:20', 'max:2000'],
            'merchant_min_app_version' => ['sometimes', 'nullable', 'string', 'regex:/^\d+\.\d+\.\d+$/'],
            'merchant_app_download_url' => ['sometimes', 'nullable', 'url:https', 'max:500'],
            'privacy_policy_url' => ['sometimes', 'nullable', 'url:https', 'max:500'],
            'customer_terms_url' => ['sometimes', 'nullable', 'url:https', 'max:500'],
            'merchant_terms_url' => ['sometimes', 'nullable', 'url:https', 'max:500'],
            'pin_unlock_hours' => ['sometimes', 'integer', 'min:1', 'max:72'],
        ];
    }

    /**
     * A card's stamp count must fit between the two limits, so the lower one
     * may never pass the upper one, even when only one of them is sent.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->hasAny(['card_stamps_min', 'card_stamps_max'])) {
                    return;
                }

                $min = (int) $this->input('card_stamps_min', Setting::read('card_stamps_min', 3));
                $max = (int) $this->input('card_stamps_max', Setting::read('card_stamps_max', 10));

                if ($min > $max) {
                    $validator->errors()->add($this->has('card_stamps_min') ? 'card_stamps_min' : 'card_stamps_max', 'The stamps minimum cannot exceed the maximum.');
                }
            },
        ];
    }
}

<?php

namespace App\Http\Requests\Api\V1\Merchant;

use App\Models\Setting;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A campaign is a title and a text, nothing else (requirements §7.3). The
 * lengths come from the settings, as the app reads them in lookups; links
 * are refused by the controller with CAMPAIGN_CONTAINS_LINK.
 */
class SendCampaignRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:'.(int) Setting::read('campaign_title_max', 60)],
            'body' => ['required', 'string', 'max:'.(int) Setting::read('campaign_body_max', 300)],
        ];
    }
}

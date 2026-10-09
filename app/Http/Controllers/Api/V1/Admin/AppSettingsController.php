<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\UpdateAppSettingsRequest;
use App\Models\AdminUser;
use App\Models\Setting;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * The settings outside billing (requirements §5.5): the stamp interval and
 * card limits, the QR lifetime, campaign lengths, the oldest merchant app
 * still served, the legal links and how long a PIN unlock lasts. The apps
 * read them from the lookups and config endpoints, so a change needs no
 * release.
 */
class AppSettingsController extends Controller
{
    /**
     * Each setting with the default the code falls back on.
     */
    private const DEFAULTS = [
        'stamp_interval_minutes' => 60,
        'card_stamps_min' => 3,
        'card_stamps_max' => 10,
        'qr_period_seconds' => 60,
        'campaign_title_max' => 60,
        'campaign_body_max' => 300,
        'merchant_min_app_version' => '',
        'merchant_app_download_url' => '',
        'privacy_policy_url' => '',
        'customer_terms_url' => '',
        'merchant_terms_url' => '',
        'pin_unlock_hours' => 12,
    ];

    public function show(): JsonResponse
    {
        return response()->json(['data' => $this->current()]);
    }

    public function update(UpdateAppSettingsRequest $request, AuditLogger $audit): JsonResponse
    {
        /** @var AdminUser $admin */
        $admin = $request->user();

        DB::transaction(function () use ($request, $admin, $audit): void {
            $before = $this->current();

            foreach ($request->validated() as $key => $value) {
                Setting::write($key, is_int(self::DEFAULTS[$key]) ? (int) $value : (string) ($value ?? ''));
            }

            $audit->record($admin, 'settings.app_updated', null, $before, $this->current(), $request->ip());
        });

        return response()->json(['data' => $this->current()]);
    }

    /**
     * @return array<string, int|string>
     */
    private function current(): array
    {
        $settings = [];

        foreach (self::DEFAULTS as $key => $default) {
            $value = Setting::read($key, $default);
            $settings[$key] = is_int($default) ? (int) $value : (string) $value;
        }

        return $settings;
    }
}

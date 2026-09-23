<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

/**
 * Default runtime settings. Existing values are never overwritten, so anything
 * an admin changed survives a re-seed.
 *
 * The numbers the requirements fixed are used as they are (grace 3 days,
 * stamps 3–10, QR one minute); the rest are safe starting points until Deep
 * Code confirms them.
 */
class SettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $defaults = [
            // Subscription
            'trial_days' => 14,
            'grace_days' => 3,
            'payment_review_sla_hours' => 24,

            // Stamps and QR
            'stamp_interval_minutes' => 60,
            'card_stamps_min' => 3,
            'card_stamps_max' => 10,
            'qr_period_seconds' => 60,

            // Campaigns
            'campaign_title_max' => 60,
            'campaign_body_max' => 300,

            // Payment details shown on the merchant payment screen
            'exchange_rate_syp' => 13000,
            'syriatel_cash_number' => '',
            'bank_transfer_details' => '',

            // Policy and links
            'privacy_policy_version' => '1.2',
            'privacy_policy_url' => '',
            'customer_terms_url' => '',
            'merchant_terms_url' => '',

            // Merchant app is distributed outside the stores, so the server
            // refuses versions older than this and the app shows an update screen.
            'merchant_min_app_version' => '1.0.0',
            'merchant_app_download_url' => '',

            // A correct PIN unlocks the protected tabs until the app closes;
            // this caps how long that unlock can live if it never does.
            'pin_unlock_hours' => 12,

            // Retention rules from the privacy policy
            'pending_customer_retention_months' => 12,
            'merchant_deletion_grace_days' => 30,
        ];

        foreach ($defaults as $key => $value) {
            Setting::query()->firstOrCreate(['key' => $key], ['value' => $value]);
        }
    }
}

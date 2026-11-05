<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The published terms, privacy policy and support chat. A link already
     * set from the dashboard is kept; only a missing or empty one is filled.
     */
    public function up(): void
    {
        $links = [
            'privacy_policy_url' => 'https://wafacard.app/privacy',
            'customer_terms_url' => 'https://wafacard.app/terms',
            'merchant_terms_url' => 'https://wafacard.app/terms',
            'support_url' => 'https://wa.me/963991734151',
        ];

        foreach ($links as $key => $url) {
            $current = DB::table('settings')->where('key', $key)->value('value');

            if ($current === null) {
                DB::table('settings')->insert(['key' => $key, 'value' => json_encode($url), 'created_at' => now(), 'updated_at' => now()]);
            } elseif (json_decode($current) === '' || json_decode($current) === null) {
                DB::table('settings')->where('key', $key)->update(['value' => json_encode($url), 'updated_at' => now()]);
            }
        }
    }

    /**
     * Links an admin may have edited since; nothing to restore.
     */
    public function down(): void {}
};

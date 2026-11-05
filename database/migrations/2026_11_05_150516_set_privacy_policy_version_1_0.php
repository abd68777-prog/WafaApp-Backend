<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The published privacy policy is version 1.0. A customer who agreed to
     * another version is asked to agree again.
     */
    public function up(): void
    {
        DB::table('settings')->updateOrInsert(
            ['key' => 'privacy_policy_version'],
            ['value' => json_encode('1.0'), 'created_at' => now(), 'updated_at' => now()],
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('settings')->where('key', 'privacy_policy_version')->update(['value' => json_encode('1.2'), 'updated_at' => now()]);
    }
};

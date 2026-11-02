<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Syriatel Cash and bank transfers are no longer offered. No real
     * payment used them; any test rows move to Sham Cash so they still read,
     * and the two settings that described them go.
     */
    public function up(): void
    {
        DB::table('payments')->whereNotIn('method', ['sham_cash', 'haram'])->update(['method' => 'sham_cash']);

        DB::table('settings')->whereIn('key', ['syriatel_cash_number', 'bank_transfer_details'])->delete();
    }

    /**
     * The old methods' details are gone; nothing to restore.
     */
    public function down(): void {}
};

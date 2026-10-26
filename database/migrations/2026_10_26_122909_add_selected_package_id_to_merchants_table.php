<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // The package chosen at registration. A shop whose email already had
        // a trial starts EXPIRED with no period at all, and the apps still
        // show the package it picked.
        Schema::table('merchants', function (Blueprint $table) {
            $table->foreignId('selected_package_id')->nullable()->after('governorate_id')->constrained('packages')->nullOnDelete();
        });

        $this->backfill();
    }

    /**
     * Existing shops: the package of their latest period, or else of their
     * latest payment.
     */
    public function backfill(): void
    {
        DB::table('merchants')->update([
            'selected_package_id' => DB::raw('coalesce(
                (select package_id from subscription_periods where subscription_periods.merchant_id = merchants.id order by starts_at desc, id desc limit 1),
                (select package_id from payments where payments.merchant_id = merchants.id order by id desc limit 1)
            )'),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('merchants', function (Blueprint $table) {
            $table->dropConstrainedForeignId('selected_package_id');
        });
    }
};

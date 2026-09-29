<?php

use App\Services\Customer\CustomerQrCode;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * The QR code carries a random `qr_id` instead of the sequential customer id
 * (API contract, decision 3), so a code reveals neither who the customer is nor
 * how many customers exist. Emptied with the rest of the account on deletion.
 *
 * Existing registered customers get a `qr_id`, and a new Base32 secret: the
 * earlier secrets were not Base32, so no TOTP library could read them.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('qr_id', 12)->nullable()->unique()->after('birthdate');
        });

        DB::table('customers')
            ->whereNotNull('registered_at')
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->each(function (object $customer): void {
                DB::table('customers')->where('id', $customer->id)->update([
                    'qr_id' => Str::random(12),
                    'qr_secret' => CustomerQrCode::newSecret(),
                ]);
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropUnique(['qr_id']);
            $table->dropColumn('qr_id');
        });
    }
};

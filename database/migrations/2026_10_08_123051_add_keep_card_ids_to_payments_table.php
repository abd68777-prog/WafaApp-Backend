<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The cards a merchant moving to a smaller package chose to keep. The
     * others are suspended when the new period starts (contract §5.9).
     */
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->json('keep_card_ids')->nullable()->after('proof_path');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('keep_card_ids');
        });
    }
};

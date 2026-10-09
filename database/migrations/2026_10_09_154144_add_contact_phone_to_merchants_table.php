<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // The number customers see in the shops list. Separate from `phone`,
        // which is often the owner's own mobile and was never agreed to be
        // shown; the shop chooses to publish this one.
        Schema::table('merchants', function (Blueprint $table) {
            $table->string('contact_phone', 20)->nullable()->after('phone');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('merchants', function (Blueprint $table) {
            $table->dropColumn('contact_phone');
        });
    }
};

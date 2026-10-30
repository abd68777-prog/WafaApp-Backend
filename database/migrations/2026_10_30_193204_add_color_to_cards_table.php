<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The card's color key from the fixed palette (App\Enums\CardColor).
     * Cards published before colors existed stay NULL, which the apps draw
     * in the default color.
     */
    public function up(): void
    {
        Schema::table('cards', function (Blueprint $table) {
            $table->string('color', 16)->nullable()->after('icon_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cards', function (Blueprint $table) {
            $table->dropColumn('color');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The card's color key from the fixed palette (App\Enums\CardColor),
     * chosen with every new card. Cards published before colors existed
     * take the default, navy.
     */
    public function up(): void
    {
        Schema::table('cards', function (Blueprint $table) {
            $table->string('color', 16)->default('navy')->after('icon_id');
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

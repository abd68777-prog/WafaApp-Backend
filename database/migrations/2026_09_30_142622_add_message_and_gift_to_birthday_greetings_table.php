<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The merchant writes the birthday greeting, and may offer a gift with it.
 * Both are kept so the greeting of the day can be shown again when the send
 * button is tapped twice.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('birthday_greetings', function (Blueprint $table) {
            $table->string('message', 300)->default('')->after('greeted_on');
            $table->string('gift', 60)->nullable()->after('message');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('birthday_greetings', function (Blueprint $table) {
            $table->dropColumn(['message', 'gift']);
        });
    }
};

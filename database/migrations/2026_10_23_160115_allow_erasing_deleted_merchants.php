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
        // Thirty days after a shop asks to leave, its sign-in, owner name and
        // phone are erased while the business name and its cards stay for
        // the customers' rewards (requirements §3.6).
        Schema::table('merchants', function (Blueprint $table) {
            $table->string('clerk_user_id', 64)->nullable()->change();
            $table->string('owner_name')->nullable()->change();
            $table->string('phone', 20)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('merchants', function (Blueprint $table) {
            $table->string('clerk_user_id', 64)->nullable(false)->change();
            $table->string('owner_name')->nullable(false)->change();
            $table->string('phone', 20)->nullable(false)->change();
        });
    }
};

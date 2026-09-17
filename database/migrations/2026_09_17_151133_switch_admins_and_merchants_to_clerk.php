<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Merchants and admins now sign in through Clerk: a row is linked to its
     * Clerk user by `clerk_user_id` (the token's `sub`), and passwords are no
     * longer stored here. Customers keep their own phone + OTP flow.
     */
    public function up(): void
    {
        Schema::table('admins', function (Blueprint $table) {
            $table->string('clerk_user_id', 64)->nullable()->unique()->after('id');
            $table->dropColumn(['password', 'remember_token']);
        });

        Schema::table('merchants', function (Blueprint $table) {
            $table->string('clerk_user_id', 64)->nullable()->unique()->after('id');
            $table->dropColumn('password');
        });
    }

    /**
     * Reverse the migrations.
     *
     * The password columns come back empty and nullable: the original hashes
     * were dropped and cannot be restored.
     */
    public function down(): void
    {
        Schema::table('merchants', function (Blueprint $table) {
            $table->dropUnique(['clerk_user_id']);
            $table->dropColumn('clerk_user_id');
            $table->string('password')->nullable();
        });

        Schema::table('admins', function (Blueprint $table) {
            $table->dropUnique(['clerk_user_id']);
            $table->dropColumn('clerk_user_id');
            $table->string('password')->nullable();
            $table->rememberToken();
        });
    }
};

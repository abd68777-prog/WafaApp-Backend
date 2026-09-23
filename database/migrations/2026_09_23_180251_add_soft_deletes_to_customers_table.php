<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A deleted customer's row stays, emptied of everything personal, so the
     * stamps and visits it holds keep counting in merchant statistics without
     * pointing at anyone (privacy policy §10). The phone becomes nullable so
     * the same number can sign up again as a new account.
     */
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('phone', 20)->nullable()->change();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->string('phone', 20)->nullable(false)->change();
        });
    }
};

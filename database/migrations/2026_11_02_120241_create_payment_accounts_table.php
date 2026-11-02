<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per payment method (App\Enums\PaymentMethod) with where it
     * takes the money. Both start paused: the dashboard fills in the account
     * before a method reaches the merchant app.
     */
    public function up(): void
    {
        Schema::create('payment_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('method', 32)->unique();
            $table->boolean('is_active')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->string('recipient_name')->nullable();
            $table->string('account')->nullable();
            $table->string('qr_path')->nullable();
            $table->string('note', 500)->nullable();
            $table->timestamps();
        });

        DB::table('payment_accounts')->insert([
            ['method' => 'sham_cash', 'sort_order' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['method' => 'haram', 'sort_order' => 2, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_accounts');
    }
};

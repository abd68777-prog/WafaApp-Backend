<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Reward history (PRD 8 "RewardHistory"). A card-completion reward is
     * `ready` when stamps reach the card's target and `redeemed` once the
     * merchant confirms it, which is when the card counter resets (PRD 3.3).
     *
     * `period_key` holds the year for birthday gifts: with the unique index a
     * daily birthday job can never gift the same customer twice in one year.
     * It stays null for card rewards, which may repeat.
     */
    public function up(): void
    {
        Schema::create('rewards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('merchant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('loyalty_card_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_card_progress_id')->nullable()->constrained('customer_card_progress')->nullOnDelete();
            $table->string('type', 32);
            $table->string('description');
            $table->string('status', 16)->default('ready');
            $table->string('period_key', 16)->nullable();
            $table->timestamp('earned_at');
            $table->timestamp('redeemed_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->unique(['customer_id', 'merchant_id', 'type', 'period_key']);
            $table->index(['customer_id', 'status']);
            $table->index(['merchant_id', 'redeemed_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rewards');
    }
};

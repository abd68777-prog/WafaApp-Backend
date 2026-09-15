<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * A customer's progress on one card, created automatically on the first
     * stamp (auto-enrollment, PRD 7.1). The unique pair stops two concurrent
     * scans from enrolling the same customer twice. `merchant_id` is copied
     * from the card so merchant customer lists and stats avoid a join.
     */
    public function up(): void
    {
        Schema::create('customer_card_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('loyalty_card_id')->constrained()->cascadeOnDelete();
            $table->foreignId('merchant_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('current_stamps')->default(0);
            $table->unsignedInteger('total_stamps')->default(0);
            $table->unsignedInteger('rewards_earned')->default(0);
            $table->string('enrolled_via', 16);
            $table->timestamp('last_stamped_at')->nullable();
            $table->timestamps();

            $table->unique(['customer_id', 'loyalty_card_id']);
            $table->index(['merchant_id', 'last_stamped_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_card_progress');
    }
};

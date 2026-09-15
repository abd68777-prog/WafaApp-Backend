<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * One row per stamp operation. The merchant app works offline and syncs
     * later (PRD 9), so each operation carries a client-generated UUID: a
     * retried sync hits the unique index instead of double-stamping.
     * `stamped_at` is when it happened on the device; `created_at` is when the
     * server received it.
     */
    public function up(): void
    {
        Schema::create('stamp_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_card_progress_id')->constrained('customer_card_progress')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('loyalty_card_id')->constrained()->cascadeOnDelete();
            $table->foreignId('merchant_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('quantity')->default(1);
            $table->string('source', 16);
            $table->uuid('client_uuid')->nullable()->unique();
            $table->string('device_id', 100)->nullable();
            $table->timestamp('stamped_at');
            $table->timestamps();

            $table->index(['merchant_id', 'stamped_at']);
            $table->index(['loyalty_card_id', 'stamped_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stamp_logs');
    }
};

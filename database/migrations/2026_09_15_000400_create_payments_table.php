<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Manual transfers (PRD 4.1): the price is fixed in USD, the merchant pays
     * the SYP equivalent at the day's rate, and an admin reviews the proof.
     */
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id')->constrained()->restrictOnDelete();
            $table->foreignId('package_id')->constrained()->restrictOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained()->nullOnDelete();
            $table->string('billing_cycle', 16);
            $table->decimal('amount_usd', 10, 2);
            $table->decimal('amount_syp', 15, 2)->nullable();
            $table->decimal('exchange_rate', 12, 4)->nullable();
            $table->string('method', 32);
            $table->string('reference')->nullable();
            $table->string('proof_path');
            $table->string('status', 16)->default('pending');
            $table->foreignId('reviewed_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['merchant_id', 'status']);
            $table->index(['status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};

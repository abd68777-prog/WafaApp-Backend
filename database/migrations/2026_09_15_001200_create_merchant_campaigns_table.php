<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Manual promotions a merchant sends to their customers (PRD 4.8). Each
     * delivered message lands in the `notifications` table.
     */
    public function up(): void
    {
        Schema::create('merchant_campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('loyalty_card_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->text('body');
            $table->string('audience', 16)->default('all');
            $table->json('audience_filter')->nullable();
            $table->unsignedInteger('recipients_count')->default(0);
            $table->string('status', 16)->default('draft');
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['merchant_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('merchant_campaigns');
    }
};

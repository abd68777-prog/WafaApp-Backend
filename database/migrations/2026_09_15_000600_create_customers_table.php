<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The phone number is the customer's identity. A merchant can create a
     * `pending` customer with only a phone number; `qr_token` is issued when
     * that number is verified by OTP (PRD 7.1 scenarios C and D).
     */
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('phone', 20)->unique();
            $table->string('name')->nullable();
            $table->date('birthdate')->nullable();
            $table->string('qr_token', 64)->nullable()->unique();
            $table->string('status', 16)->default('pending');
            $table->string('avatar_path')->nullable();
            $table->string('locale', 8)->default('ar');
            $table->timestamp('phone_verified_at')->nullable();
            $table->timestamp('registered_at')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};

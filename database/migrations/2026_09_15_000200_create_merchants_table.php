<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * One merchant account is exactly one business (PRD v1.1): there is no
     * separate business-profile table.
     */
    public function up(): void
    {
        Schema::create('merchants', function (Blueprint $table) {
            $table->id();
            $table->string('owner_name');
            $table->string('business_name');
            $table->string('phone', 20)->unique();
            $table->string('email')->nullable()->unique();
            $table->string('password');
            $table->string('logo_path')->nullable();
            $table->string('address')->nullable();
            $table->string('city', 100)->nullable();
            $table->foreignId('package_id')->constrained()->restrictOnDelete();
            $table->string('status', 32)->default('pending_review');
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('subscription_ends_at')->nullable();
            $table->boolean('birthday_gift_enabled')->default(false);
            $table->string('birthday_gift_description')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'trial_ends_at']);
            $table->index(['status', 'subscription_ends_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('merchants');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The shop's Instagram, Facebook and Google Maps links, shown as icons on
     * its page in the customer app (App\Enums\ShopLink says which sites).
     */
    public function up(): void
    {
        Schema::table('merchants', function (Blueprint $table) {
            $table->string('instagram_url')->nullable()->after('contact_phone');
            $table->string('facebook_url')->nullable()->after('instagram_url');
            $table->string('maps_url')->nullable()->after('facebook_url');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('merchants', function (Blueprint $table) {
            $table->dropColumn(['instagram_url', 'facebook_url', 'maps_url']);
        });
    }
};

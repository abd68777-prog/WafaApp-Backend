<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The Wafa domain schema, built from the database diagram that accompanies the
 * requirements document (v1.0, Deep Code).
 *
 * Two ideas run through it:
 * - What never changes is referenced (cards), what changes is copied (payments).
 * - A customer's progress is one row per cycle (`card_cycles`), not a counter
 *   that resets, so the history of completed cards stays readable.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // --- Managed lists -------------------------------------------------
        Schema::create('governorates', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('business_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Cards use icons from this library instead of uploaded images, so every
        // card looks consistent and nothing needs moderation.
        Schema::create('icons', function (Blueprint $table) {
            $table->id();
            $table->string('key', 64)->unique();
            $table->string('name', 100);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100)->unique();
            $table->json('value');
            $table->timestamps();
        });

        // --- Identity ------------------------------------------------------

        /**
         * A customer added by a merchant with only a phone number is a row here
         * with `registered_at` null. Signing up fills the blanks in place, so
         * merging is filling fields, never moving data.
         */
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('phone', 20)->unique();
            $table->string('name')->nullable();
            $table->date('birthdate')->nullable();
            // Seed for the rotating QR code the customer app generates offline.
            $table->string('qr_secret', 64)->nullable();
            $table->boolean('campaigns_muted')->default(false);
            $table->timestamp('registered_at')->nullable();
            // Drives the 12-month deletion of phone-only customers.
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->timestamps();

            $table->index('last_activity_at');
        });

        Schema::create('policy_consents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('policy_version', 16);
            $table->timestamp('consented_at');
            $table->timestamps();

            $table->unique(['customer_id', 'policy_version']);
        });

        Schema::create('admin_users', function (Blueprint $table) {
            $table->id();
            $table->string('clerk_user_id', 64)->nullable()->unique();
            $table->string('name');
            $table->string('email')->unique();
            // super_admin | admin | payments_reviewer | support
            $table->string('role', 32);
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_login_at')->nullable();
            $table->timestamps();
        });

        // --- Merchant ------------------------------------------------------

        /**
         * One account shared by the owner and the cashier; sensitive tabs are
         * behind `pin_hash`.
         *
         * Registration fills this row in three steps, so `status` stays null
         * until a package is chosen and `pin_hash` until the PIN is set.
         */
        Schema::create('merchants', function (Blueprint $table) {
            $table->id();
            $table->string('clerk_user_id', 64)->unique();
            $table->string('business_name');
            $table->foreignId('business_type_id')->constrained()->restrictOnDelete();
            $table->foreignId('governorate_id')->constrained()->restrictOnDelete();
            $table->string('address')->nullable();
            $table->string('owner_name');
            $table->string('phone', 20)->unique();
            $table->string('logo_path')->nullable();
            $table->string('pin_hash')->nullable();
            $table->string('status', 32)->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->string('suspension_reason')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index(['governorate_id', 'business_type_id']);
        });

        /**
         * Deliberately has no foreign key: the fingerprint outlives the merchant
         * account so the free trial cannot be taken twice by deleting and
         * signing up again.
         */
        Schema::create('trial_email_hashes', function (Blueprint $table) {
            $table->id();
            $table->string('email_hash', 64)->unique();
            $table->timestamps();
        });

        // --- Packages and subscriptions -------------------------------------
        Schema::create('packages', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->unsignedTinyInteger('cards_limit');
            $table->unsignedTinyInteger('weekly_campaigns_limit');
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // One row per package/duration pair: the price matrix from the dashboard.
        Schema::create('package_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('package_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('duration_months');
            $table->decimal('price_usd', 8, 2);
            $table->timestamps();

            $table->unique(['package_id', 'duration_months']);
        });

        // A row per period keeps the full subscription history; renewals extend
        // from the previous end date, not from the approval date.
        Schema::create('subscription_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id')->constrained()->restrictOnDelete();
            $table->foreignId('package_id')->constrained()->restrictOnDelete();
            $table->string('type', 16);
            $table->unsignedTinyInteger('duration_months')->nullable();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            // Grace follows a paid period only, never a trial.
            $table->timestamp('grace_ends_at')->nullable();
            $table->foreignId('created_by_admin_id')->nullable()->constrained('admin_users')->nullOnDelete();
            $table->string('note')->nullable();
            $table->timestamps();

            $table->index(['merchant_id', 'ends_at']);
        });

        /**
         * Prices are copied into the row when the proof is uploaded: the price
         * table says what the price is now, the payment says what this merchant
         * actually paid then.
         */
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id')->constrained()->restrictOnDelete();
            $table->foreignId('package_id')->constrained()->restrictOnDelete();
            $table->unsignedTinyInteger('duration_months');
            $table->decimal('price_usd', 8, 2);
            $table->decimal('exchange_rate', 12, 4);
            $table->decimal('amount_syp', 15, 2);
            $table->string('method', 32);
            $table->string('reference')->nullable();
            $table->string('proof_path');
            $table->string('status', 16)->default('PENDING');
            $table->string('rejection_reason', 32)->nullable();
            $table->foreignId('reviewed_by_admin_id')->nullable()->constrained('admin_users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('subscription_period_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            // Only one payment may await review per merchant. MySQL has no
            // partial indexes, so a generated column carries the merchant id
            // only while the payment is pending, and that column is unique.
            // It is virtual, not stored: MySQL forbids cascading foreign keys
            // on a column a stored generated column is built from.
            $table->unsignedBigInteger('pending_for_merchant')
                ->nullable()
                ->virtualAs("CASE WHEN status = 'PENDING' THEN merchant_id ELSE NULL END");
            $table->unique('pending_for_merchant');

            $table->index(['merchant_id', 'status']);
            $table->index(['status', 'created_at']);
        });

        // --- Cards, cycles and stamps ---------------------------------------

        // A published card is never edited, only suspended, so cycles can point
        // at it safely for years.
        Schema::create('cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('icon_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->unsignedTinyInteger('stamps_required');
            $table->string('reward_description');
            $table->text('terms')->nullable();
            $table->string('status', 16)->default('active');
            $table->timestamp('suspended_at')->nullable();
            $table->timestamps();

            $table->index(['merchant_id', 'status']);
        });

        /**
         * The heart of the system: one row per cycle, from the first stamp to
         * the reward being handed over. A new empty cycle opens on redemption.
         */
        Schema::create('card_cycles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('card_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            // Copied from the card so merchant lists and stats avoid a join.
            $table->foreignId('merchant_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('stamps_count')->default(0);
            $table->string('status', 16)->default('COLLECTING');
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('redeemed_at')->nullable();
            $table->timestamps();

            // A customer may have only one unfinished cycle per card; finished
            // ones are kept, so the uniqueness applies to open cycles only.
            // These two columns carry the pair while the cycle is open and are
            // null once it is redeemed, and a unique index over both lets any
            // number of redeemed cycles coexist (null never equals null).
            //
            // Virtual rather than stored, because MySQL refuses cascading
            // foreign keys on the base columns of a stored generated column.
            $table->unsignedBigInteger('open_card_id')
                ->nullable()
                ->virtualAs("CASE WHEN status <> 'REDEEMED' THEN card_id ELSE NULL END");
            $table->unsignedBigInteger('open_customer_id')
                ->nullable()
                ->virtualAs("CASE WHEN status <> 'REDEEMED' THEN customer_id ELSE NULL END");
            $table->unique(['open_card_id', 'open_customer_id']);

            $table->index(['customer_id', 'status']);
            $table->index(['merchant_id', 'updated_at']);
        });

        Schema::create('stamps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('card_cycle_id')->constrained()->cascadeOnDelete();
            $table->foreignId('card_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('merchant_id')->constrained()->cascadeOnDelete();
            // qr | phone — the merchant sees how many were added by phone.
            $table->string('method', 16);
            // Generated by the merchant app per operation: a double tap or a
            // retried request can never add two stamps.
            $table->uuid('client_uuid')->unique();
            $table->timestamp('stamped_at');
            // A cancelled stamp is marked, never deleted.
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason')->nullable();
            $table->foreignId('cancelled_by_admin_id')->nullable()->constrained('admin_users')->nullOnDelete();
            $table->timestamps();

            $table->index(['merchant_id', 'stamped_at']);
            $table->index(['card_id', 'stamped_at']);
        });

        // --- Campaigns and preferences --------------------------------------
        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('body');
            $table->unsignedInteger('recipients_count')->default(0);
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            // Used to count this week's campaigns against the package limit.
            $table->index(['merchant_id', 'sent_at']);
        });

        Schema::create('merchant_mutes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('merchant_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['customer_id', 'merchant_id']);
        });

        // The unique triple stops a second greeting on the same day, however
        // many times the merchant taps send.
        Schema::create('birthday_greetings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->date('greeted_on');
            $table->timestamps();

            $table->unique(['merchant_id', 'customer_id', 'greeted_on']);
        });

        // --- Requests and audit ---------------------------------------------

        // Holds requests from customers and from merchants, so the subject is a
        // type plus an id rather than one foreign key.
        Schema::create('deletion_requests', function (Blueprint $table) {
            $table->id();
            $table->string('subject_type', 16);
            $table->unsignedBigInteger('subject_id');
            $table->string('source', 16);
            $table->timestamp('requested_at');
            $table->timestamp('executes_at')->nullable();
            $table->timestamp('executed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('handled_by_admin_id')->nullable()->constrained('admin_users')->nullOnDelete();
            $table->string('note')->nullable();
            $table->timestamps();

            $table->index(['subject_type', 'subject_id']);
            $table->index('executes_at');
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_user_id')->nullable()->constrained('admin_users')->nullOnDelete();
            $table->string('action', 64);
            $table->string('subject_type', 32)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['admin_user_id', 'created_at']);
            $table->index(['subject_type', 'subject_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('deletion_requests');
        Schema::dropIfExists('birthday_greetings');
        Schema::dropIfExists('merchant_mutes');
        Schema::dropIfExists('campaigns');
        Schema::dropIfExists('stamps');
        Schema::dropIfExists('card_cycles');
        Schema::dropIfExists('cards');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('subscription_periods');
        Schema::dropIfExists('package_prices');
        Schema::dropIfExists('packages');
        Schema::dropIfExists('trial_email_hashes');
        Schema::dropIfExists('merchants');
        Schema::dropIfExists('admin_users');
        Schema::dropIfExists('policy_consents');
        Schema::dropIfExists('customers');
        Schema::dropIfExists('settings');
        Schema::dropIfExists('icons');
        Schema::dropIfExists('business_types');
        Schema::dropIfExists('governorates');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * BOOKSHOP_PLAN slice B9c: a shop's newsletter sign-up and abandoned-cart
 * reminders (both B9 "later, on request"). Additive only (rule 9).
 *
 *  - `vendor_newsletter_subscribers` (§6.3 "Newsletter: collect emails for
 *    the vendor's news, with consent"): the address, when consent was
 *    given, a token for the one-click unsubscribe link the shop puts in
 *    its mailings, and when they left. One row per shop and address.
 *  - `carts.reminded_at`: a signed-in customer's cart left for a day is
 *    reminded once, and again only after they touch it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_newsletter_subscribers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
            $table->string('email', 255);
            $table->string('name', 120)->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('token', 64)->unique();
            $table->timestamp('consented_at');
            $table->timestamp('unsubscribed_at')->nullable();
            $table->timestamps();

            $table->unique(['vendor_id', 'email']);
            $table->index(['vendor_id', 'unsubscribed_at']);
        });

        Schema::table('carts', function (Blueprint $table) {
            $table->timestamp('reminded_at')->nullable()->after('session_token');
        });
    }

    public function down(): void
    {
        Schema::table('carts', fn (Blueprint $table) => $table->dropColumn('reminded_at'));
        Schema::dropIfExists('vendor_newsletter_subscribers');
    }
};

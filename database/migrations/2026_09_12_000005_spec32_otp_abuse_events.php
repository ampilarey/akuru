<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SPEC §32: "OTP abuse event logging for admin review."
 *
 * Nothing recorded a tripped OTP limit. The limits threw a validation error at
 * the person and left no trace, so an admin could not tell the difference
 * between one confused parent and somebody burning the school's SMS credit —
 * which is the case §32 exists to catch, since it says so directly: "This
 * protects future Dhiraagu SMS integration from cost abuse and spam."
 *
 * The contact is stored **hashed**, like the library reading log. Knowing that
 * one number tripped the limit forty times needs only a stable identifier, not
 * the number itself, and these rows are the ones most likely to be exported and
 * mailed around while somebody investigates.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('otp_abuse_events', function (Blueprint $table) {
            $table->id();
            // 'send_rate' | 'resend_cooldown' | 'verify_rate' | 'code_attempts'
            $table->string('kind', 32)->index();
            $table->string('purpose', 32)->nullable();
            $table->string('channel', 16)->nullable();
            // sha256 of the normalised contact, peppered with the app key.
            $table->string('contact_hash', 64)->index();
            // The last four characters, so a human can recognise their own
            // number in a list without the list being a phone book.
            $table->string('contact_tail', 8)->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('observed')->default(0);
            $table->unsignedInteger('threshold')->default(0);
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['contact_hash', 'occurred_at'], 'otp_abuse_contact_time');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('otp_abuse_events');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SPEC §38 "Payment-Ready Design" names the fields the payments table must
 * carry. Three were missing.
 *
 * **Course offering ID.** §38 lists it beside Course ID, and the same section
 * ends "Offerings may override course price" — so the price a student paid can
 * differ per offering, and the payment had no way to say which offering it
 * paid for.
 *
 * **Payment method.** §38 lists it *separately* from Gateway, and rule 12
 * makes the distinction load bearing ("Gift cards = payment method"). Only the
 * gateway existed. How money arrived was free text in `notes`, prompted by a
 * form placeholder reading "Note (e.g. cash at office)".
 *
 * **Metadata JSON.** The table has four purpose-specific payload columns
 * (`callback_payload`, `bml_status_raw`, `redirect_return_payload`,
 * `webhook_payload`), all owned by the BML flow, and nowhere for a caller to
 * record what the payment was for.
 *
 * **Enrollment ID is deliberately not added**, though §38 lists it.
 * `payable_type` / `payable_id` already carries it — `course_enrollment` is a
 * registered morph alias and every engine payment is created against it. A
 * second column holding the same fact is exactly the drift rule 11 exists to
 * stop; the morph pair is the single source and stays that way.
 *
 * Rule 9: additive only. Nothing is dropped, renamed, or backfilled here —
 * existing rows keep null in all three, which reads correctly as "not
 * recorded" rather than as a wrong value.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->unsignedBigInteger('course_offering_id')->nullable()->after('course_id');
            $table->string('payment_method', 32)->nullable()->after('provider');
            $table->json('metadata')->nullable()->after('notes');

            $table->index('course_offering_id');
            $table->index('payment_method');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex(['course_offering_id']);
            $table->dropIndex(['payment_method']);
            $table->dropColumn(['course_offering_id', 'payment_method', 'metadata']);
        });
    }
};

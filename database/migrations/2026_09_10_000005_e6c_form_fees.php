<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * E6c — a sign-up that costs money.
 *
 * The invoice is raised through Finance's own action (rule 11: one invoice
 * system) and paid through the portal's existing BML flow, so **money rule 12
 * is satisfied by construction** — nothing here confirms a payment, and access
 * still follows the webhook rather than a return URL.
 *
 * Paid state is deliberately **not** copied onto the response. It is read from
 * the invoice, because two records of whether a family has paid is one more
 * than a school can reconcile.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('forms', function (Blueprint $table) {
            $table->decimal('fee_amount', 10, 2)->nullable()->after('requires_parent_confirmation');
        });

        Schema::table('form_responses', function (Blueprint $table) {
            // Which pupil the answer is about. An invoice is student-scoped, so
            // a fee cannot be raised without knowing this.
            $table->unsignedBigInteger('student_id')->nullable()->after('user_id');
            $table->foreignId('invoice_id')->nullable()->after('student_id')
                ->constrained('invoices')->nullOnDelete();

            $table->foreign('student_id')->references('id')->on('students')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('form_responses', function (Blueprint $table) {
            $table->dropForeign(['student_id']);
            $table->dropConstrainedForeignId('invoice_id');
            $table->dropColumn('student_id');
        });
        Schema::table('forms', function (Blueprint $table) {
            $table->dropColumn('fee_amount');
        });
    }
};

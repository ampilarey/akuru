<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * BLOG_PLAN BL6a (STATUS §5sm, §7.1): a reader tips a blog's writer.
 *
 *  - `library_tips`: one tip — payer, writer, the post it was on, amount,
 *    how it was paid, the payment, and its state (`pending` until BML's
 *    webhook says `paid`; `failed`). A refund is a new row
 *    (`reverses_tip_id`, a negative amount), never an edit or a delete. The
 *    reader's optional thank-you note is checked like a comment
 *    (`note_status`).
 *  - `writer_earnings.kind`: `sale` (every existing row), `tip` or `reward`;
 *    a tip's earning points at its tip, so the sale columns become nullable.
 *  - `writer_profiles.tips_enabled`: the writer's switch, off by default.
 *
 * Additive, and the two nullable columns only relax a constraint (rule 9).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('library_tips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('writer_id')->constrained('writer_profiles')->restrictOnDelete();
            $table->foreignId('library_item_id')->nullable()->constrained('library_items')->nullOnDelete();
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('MVR');
            $table->string('method', 10);
            $table->unsignedBigInteger('payment_id')->nullable()->index();
            $table->string('status', 20)->default('pending');
            $table->foreignId('reverses_tip_id')->nullable()->constrained('library_tips')->restrictOnDelete();
            $table->string('note', 300)->nullable();
            $table->string('note_status', 20)->default('none');
            $table->foreignId('note_decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('note_decided_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['writer_id', 'status']);
        });

        Schema::table('writer_earnings', function (Blueprint $table) {
            $table->string('kind', 20)->default('sale')->after('writer_id')->index();
            $table->foreignId('library_tip_id')->nullable()->unique()->after('library_purchase_id')->constrained('library_tips')->restrictOnDelete();
            $table->unsignedBigInteger('library_item_id')->nullable()->change();
            $table->unsignedBigInteger('library_purchase_id')->nullable()->change();
        });

        Schema::table('writer_profiles', function (Blueprint $table) {
            $table->boolean('tips_enabled')->default(false)->after('comments_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('writer_profiles', function (Blueprint $table) {
            $table->dropColumn('tips_enabled');
        });
        Schema::table('writer_earnings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('library_tip_id');
            $table->dropIndex(['kind']);
            $table->dropColumn('kind');
        });
        Schema::dropIfExists('library_tips');
    }
};

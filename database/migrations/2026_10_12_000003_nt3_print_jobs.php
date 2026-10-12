<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * NAME_TAGS_PLAN §6, §8 and §11 NT3 (STATUS §5rq): a customer's configured
 * print job — the design copied from a template, the names it prints, the
 * options it is priced on, the proof it was shown and the moment it was
 * approved and locked.
 *
 *  - `design` is the job's own copy: an office edit to the template never
 *    changes a job (plan §4.7).
 *  - `entry` is what the customer typed: one row repeated (same) or a list,
 *    and a subject set's subjects with the language they print in. The
 *    renderer expands it; nothing else does.
 *  - A guest's job belongs to their cart (`cart_id`) until they sign in;
 *    the cart's merge hands it to the account.
 *  - `proof_document_ids` are the watermarked sheet images, `print_document_id`
 *    the print-ready PDF — both private documents, rendered at proof time
 *    and never again once approved.
 *  - Something that happens in time: `academic_year_id` (rule 10), the year
 *    the job was started in, nullable where no year is set up.
 *
 * Soft deletes: a customer may remove a design no order holds. Additive
 * (rule 9).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('print_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('cart_id')->nullable()->constrained('carts')->nullOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->foreignId('print_template_id')->nullable()->constrained('print_templates')->nullOnDelete();
            $table->foreignId('print_layout_id')->nullable()->constrained('print_layouts')->nullOnDelete();
            $table->foreignId('print_paper_option_id')->nullable()->constrained('print_paper_options')->nullOnDelete();
            $table->boolean('colour')->default(true);
            $table->json('finishing')->nullable();
            $table->unsignedInteger('sheets')->default(1);
            $table->json('entry')->nullable();
            $table->foreignId('print_subject_set_id')->nullable()->constrained('print_subject_sets')->nullOnDelete();
            $table->json('design');
            $table->string('source', 20)->default('template');
            $table->json('preflight')->nullable();
            $table->decimal('price_per_sheet', 10, 2)->default(0);
            $table->decimal('price_total', 10, 2)->default(0);
            $table->json('price_breakdown')->nullable();
            $table->json('approved_price_snapshot')->nullable();
            $table->json('proof_document_ids')->nullable();
            $table->unsignedBigInteger('print_document_id')->nullable();
            $table->string('status', 20)->default('draft');
            $table->string('name', 120)->nullable();
            $table->foreignId('academic_year_id')->nullable()->constrained('academic_years')->nullOnDelete();
            $table->timestamp('proofed_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('ordered_at')->nullable();
            $table->timestamp('printing_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('printed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('finished_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'status']);
            $table->index(['cart_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('print_jobs');
    }
};

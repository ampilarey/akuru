<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Product questions and answers (STATUS §5le): a signed-in customer asks
 * about a product, the shop answers in public, the office may hide either.
 * A question is shown on the product page once the shop has answered it.
 * Additive: one new table, like `product_reviews`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->text('question');
            $table->text('answer')->nullable();
            $table->timestamp('answered_at')->nullable();
            $table->foreignId('answered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('published'); // published / hidden
            $table->timestamp('moderated_at')->nullable();
            $table->foreignId('moderated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('moderation_note', 500)->nullable();
            $table->timestamps();

            $table->index(['product_id', 'status', 'answered_at']);
            $table->index(['vendor_id', 'answered_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_questions');
    }
};

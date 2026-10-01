<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * COMMERCE_PARITY_PLAN P7a: a customer reports a problem with an order
 * (damaged, something missing, the wrong item, late, other — with a photo
 * if they have one); the office answers, the customer hears, the shop sees
 * its own. Commerce, so no `academic_year_id` (as every bookstore table).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_complaints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('kind', 20);
            $table->text('body');
            $table->foreignId('photo_media_file_id')->nullable()->constrained('media_files')->nullOnDelete();
            $table->string('status', 20)->default('open');
            $table->text('reply')->nullable();
            $table->foreignId('replied_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('replied_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
            $table->index(['vendor_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_complaints');
    }
};

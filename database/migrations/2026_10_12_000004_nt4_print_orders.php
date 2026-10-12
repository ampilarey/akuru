<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * NAME_TAGS_PLAN §3.8, §4.8, §8 and §11 NT4 (STATUS §5rr): an approved print
 * job in the cart and in an order, and the desk's reprints.
 *
 *  - `cart_items.print_job_id`, `order_items.print_job_id`: the line's job.
 *    A print line's quantity is its sheets (decision D15) and its price is
 *    the job's held price, never the product's.
 *  - `print_job_reprints`: append-only — a reprint is recorded, never
 *    edited or removed; it charges nothing.
 *
 * Additive (rule 9). The reprint happens in time but belongs to its job,
 * which carries the academic year (rule 10).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cart_items', function (Blueprint $table) {
            $table->foreignId('print_job_id')->nullable()->after('quote_item_id')->constrained('print_jobs')->nullOnDelete();
        });
        Schema::table('order_items', function (Blueprint $table) {
            $table->foreignId('print_job_id')->nullable()->after('quote_item_id')->constrained('print_jobs')->nullOnDelete();
        });
        Schema::create('print_job_reprints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('print_job_id')->constrained('print_jobs')->cascadeOnDelete();
            $table->string('reason', 500);
            $table->foreignId('by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('print_job_reprints');
        Schema::table('order_items', fn (Blueprint $table) => $table->dropConstrainedForeignId('print_job_id'));
        Schema::table('cart_items', fn (Blueprint $table) => $table->dropConstrainedForeignId('print_job_id'));
    }
};

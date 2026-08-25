<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * S4.1 — invoice year/term scoping, fee-item trilingual names, receipts.
 * applicable_grades kept. payment_plan_id is additive without an FK (S4.4).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->unsignedBigInteger('academic_year_id')->nullable()->after('student_id');
            $table->unsignedBigInteger('term_id')->nullable()->after('academic_year_id');
            $table->string('invoice_type', 32)->default('school_fees')->after('term_id');
            $table->unsignedBigInteger('payment_plan_id')->nullable()->after('invoice_type');

            $table->index(['academic_year_id', 'term_id'], 'inv_year_term_idx');
            $table->foreign('academic_year_id')->references('id')->on('academic_years')->nullOnDelete();
            $table->foreign('term_id')->references('id')->on('terms')->nullOnDelete();
        });

        Schema::table('fee_items', function (Blueprint $table) {
            $table->string('name_arabic')->nullable()->after('name');
            $table->string('name_dhivehi')->nullable()->after('name_arabic');
        });

        Schema::create('receipts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('invoice_id');
            $table->unsignedBigInteger('payment_id')->nullable();
            $table->string('receipt_number')->unique();
            $table->decimal('amount', 10, 2);
            $table->string('method', 32);
            $table->unsignedBigInteger('received_by')->nullable();
            $table->timestamp('received_at');
            $table->unsignedBigInteger('document_id')->nullable();
            $table->timestamps();

            $table->index(['invoice_id', 'received_at'], 'rcpt_invoice_received_idx');
            $table->foreign('invoice_id')->references('id')->on('invoices')->restrictOnDelete();
            $table->foreign('payment_id')->references('id')->on('payments')->nullOnDelete();
            $table->foreign('received_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('document_id')->references('id')->on('documents')->nullOnDelete();
        });

        Permission::firstOrCreate(['name' => 'finance.manage', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'finance.record-manual-payment', 'guard_name' => 'web']);
        foreach (['super_admin', 'admin', 'headmaster'] as $roleName) {
            if (Role::query()->where('name', $roleName)->exists()) {
                Role::findByName($roleName)->givePermissionTo([
                    'finance.manage',
                    'finance.record-manual-payment',
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('receipts');

        Schema::table('fee_items', function (Blueprint $table) {
            $table->dropColumn(['name_arabic', 'name_dhivehi']);
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropForeign(['academic_year_id']);
            $table->dropForeign(['term_id']);
            $table->dropIndex('inv_year_term_idx');
            $table->dropColumn(['academic_year_id', 'term_id', 'invoice_type', 'payment_plan_id']);
        });
    }
};

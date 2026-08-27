<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Phase 4 P4.3 (SPEC §49 "Refunded" payment status, LIBRARY_PLAN §24):
 * refunds recorded against confirmed payments. APPEND-ONLY — a refund row
 * is never updated or deleted; a mistaken refund is corrected by the
 * operator crediting/debiting the wallet with its own ledger row. The
 * actual BML money return is an operator action outside the system
 * (destination "manual"); destination "wallet" credits the user's wallet
 * through the Commerce ledger. Additive only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained('payments')->cascadeOnDelete();
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('MVR');
            $table->string('destination', 20); // wallet / manual
            $table->string('reason', 500)->nullable();
            $table->foreignId('refunded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['payment_id', 'created_at']);
        });

        // Additive enum widening: 'cancelled' (code already filtered on it but
        // the column never allowed it) and 'refunded' for the refund flow.
        DB::statement("ALTER TABLE course_enrollments MODIFY COLUMN status ENUM('pending', 'approved', 'rejected', 'active', 'completed', 'cancelled') NOT NULL DEFAULT 'pending'");
        DB::statement("ALTER TABLE course_enrollments MODIFY COLUMN payment_status ENUM('not_required', 'required', 'pending', 'confirmed', 'refunded') NOT NULL DEFAULT 'not_required'");

        Permission::firstOrCreate(['name' => 'payments.refund', 'guard_name' => 'web']);
        foreach (['super_admin', 'admin'] as $roleName) {
            $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();
            $role?->givePermissionTo('payments.refund');
        }
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE course_enrollments MODIFY COLUMN status ENUM('pending', 'approved', 'rejected', 'active', 'completed') NOT NULL DEFAULT 'pending'");
        DB::statement("ALTER TABLE course_enrollments MODIFY COLUMN payment_status ENUM('not_required', 'required', 'pending', 'confirmed') NOT NULL DEFAULT 'not_required'");
        Schema::dropIfExists('payment_refunds');
    }
};

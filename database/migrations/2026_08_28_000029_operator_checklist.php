<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Admin operator checklist (docs/OPERATOR_CHECKLIST.md, in-app): the
 * close-out walks, flag decisions, and deploy gates as a SHARED page —
 * a check records who ticked it and when, visible to every operator.
 * The checklist DEFINITIONS live in code (ListOperatorChecklistAction)
 * so they version with the repo; only the tick state lives here.
 * STATUS.md remains the evidence of record — this page tracks progress,
 * it does not replace recorded gate evidence. Additive only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operator_checklist_checks', function (Blueprint $table) {
            $table->id();
            $table->string('item_key', 40)->unique();
            $table->foreignId('checked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('checked_at');
            $table->timestamps();
        });

        Permission::firstOrCreate(['name' => 'operations.manage', 'guard_name' => 'web']);
        foreach (['super_admin', 'admin'] as $roleName) {
            $role = Role::where('name', $roleName)->first();
            $role?->givePermissionTo('operations.manage');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('operator_checklist_checks');
    }
};

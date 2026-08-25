<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * S2.10 — general requests. teacher_leave approval writes teacher_absences
 * and open substitution_requests (existing overlay).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('requests', function (Blueprint $table) {
            $table->id();
            $table->string('type', 32);
            $table->unsignedBigInteger('requester_id');
            $table->string('regarding_type')->nullable();
            $table->unsignedBigInteger('regarding_id')->nullable();
            $table->json('payload')->nullable();
            $table->text('reason');
            $table->string('status', 20)->default('pending');
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->timestamps();

            $table->index(['type', 'status']);
            $table->index(['requester_id', 'status']);
        });

        Permission::firstOrCreate(['name' => 'requests.submit', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'requests.review', 'guard_name' => 'web']);

        foreach (['super_admin', 'admin', 'headmaster', 'supervisor', 'teacher', 'parent'] as $roleName) {
            if (Role::query()->where('name', $roleName)->exists()) {
                Role::findByName($roleName)->givePermissionTo('requests.submit');
            }
        }

        foreach (['super_admin', 'admin', 'headmaster', 'supervisor'] as $roleName) {
            if (Role::query()->where('name', $roleName)->exists()) {
                Role::findByName($roleName)->givePermissionTo('requests.review');
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('requests');
    }
};

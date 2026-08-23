<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * S1.2 — custom field definitions + values (People).
 *
 * Central database/migrations/ (domain folders still unwired).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('custom_field_definitions', function (Blueprint $table) {
            $table->id();
            $table->string('entity_type', 64);
            $table->string('key', 64);
            $table->string('label_en');
            $table->string('label_dv')->nullable();
            $table->string('label_ar')->nullable();
            $table->string('field_type', 32);
            $table->json('options')->nullable();
            $table->boolean('required')->default(false);
            $table->boolean('show_in_profile')->default(true);
            $table->boolean('show_in_admission_form')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['entity_type', 'key']);
            $table->index(['entity_type', 'active', 'sort_order']);
        });

        Schema::create('custom_field_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('definition_id')->constrained('custom_field_definitions')->restrictOnDelete();
            $table->string('entity_type', 64);
            $table->unsignedBigInteger('entity_id');
            $table->json('value');
            $table->timestamps();

            $table->unique(['definition_id', 'entity_type', 'entity_id'], 'custom_field_values_definition_entity_unique');
            $table->index(['entity_type', 'entity_id']);
        });

        foreach (['custom_fields.manage', 'students.view-sensitive'] as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        foreach (['super_admin', 'admin'] as $roleName) {
            if (! Role::query()->where('name', $roleName)->exists()) {
                continue;
            }

            Role::findByName($roleName)->givePermissionTo([
                'custom_fields.manage',
                'students.view-sensitive',
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_field_values');
        Schema::dropIfExists('custom_field_definitions');
    }
};

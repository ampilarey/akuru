<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Dhivehi translation overrides: the lang/dv files carry machine-made
 * strings a native speaker needs to correct without a deploy. Overrides
 * live in the DB and win over the file value; clearing one falls back
 * to the file. Schema supports any locale; the admin UI exposes dv
 * only. UI strings are not year-scoped data (rule 10 n/a). Additive.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('translation_overrides', function (Blueprint $table) {
            $table->id();
            $table->string('locale', 5);
            $table->string('group', 40);
            $table->string('key', 191);
            $table->text('value');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['locale', 'group', 'key'], 'translation_overrides_locale_group_key_unique');
        });

        Permission::firstOrCreate(['name' => 'translations.manage', 'guard_name' => 'web']);
        foreach (['super_admin', 'admin'] as $roleName) {
            $role = Role::where('name', $roleName)->first();
            $role?->givePermissionTo('translations.manage');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('translation_overrides');
    }
};

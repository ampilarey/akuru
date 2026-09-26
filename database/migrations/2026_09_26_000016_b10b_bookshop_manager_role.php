<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * B10b: a Bookstore admin (the owner, 2026-09-26: "need admin for
 * bookshops"). `bookshop_manager` holds `bookshop.manage` — the office's
 * permission since B1a — so it runs /admin/bookshop without being a full
 * admin of the school system.
 */
return new class extends Migration
{
    public function up(): void
    {
        Permission::firstOrCreate(['name' => 'bookshop.manage', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'bookshop_manager', 'guard_name' => 'web'])->givePermissionTo('bookshop.manage');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Role::where('name', 'bookshop_manager')->where('guard_name', 'web')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};

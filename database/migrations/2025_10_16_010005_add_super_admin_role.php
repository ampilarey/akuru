<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Create Super Admin role
        $superAdmin = Role::firstOrCreate(['name' => 'super_admin']);
        
        // Super Admin gets ALL permissions
        $superAdmin->givePermissionTo(Permission::all());
        
        // Update existing admin role description (if using guard_name)
        $admin = Role::where('name', 'admin')->first();
        if ($admin) {
            // Admin role stays as is - will be for school administrators
            // They handle day-to-day operations, fees, payments, etc.
        }
        
        // Note: To make a user Super Admin, run:
        // User::find(1)->assignRole('super_admin');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $superAdmin = Role::where('name', 'super_admin')->first();
        if ($superAdmin) {
            $superAdmin->delete();
        }
    }
};

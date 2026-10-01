<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * COMMERCE_PARITY_PLAN P6b: Akuru's drivers. The office keeps a list of
 * drivers (each a person who signs in, with the `driver` role — by migration,
 * as ADR-040's roles are) and assigns an order Akuru delivers to one; the
 * driver marks it picked up, then delivered with a photo from the phone.
 * Commerce, so no `academic_year_id` (as every bookstore table, B1a).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_drivers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('phone', 30)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::create('order_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->unique()->constrained('orders')->cascadeOnDelete();
            $table->foreignId('delivery_driver_id')->constrained('delivery_drivers')->restrictOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at');
            $table->timestamp('picked_up_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->foreignId('proof_media_file_id')->nullable()->constrained('media_files')->nullOnDelete();
            $table->string('note', 255)->nullable();
            $table->timestamps();
            $table->index(['delivery_driver_id', 'delivered_at']);
        });

        Permission::firstOrCreate(['name' => 'bookshop.deliver', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'driver', 'guard_name' => 'web'])->givePermissionTo('bookshop.deliver');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::dropIfExists('order_deliveries');
        Schema::dropIfExists('delivery_drivers');
        Role::where('name', 'driver')->where('guard_name', 'web')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};

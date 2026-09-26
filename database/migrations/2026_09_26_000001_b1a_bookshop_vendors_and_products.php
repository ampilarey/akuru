<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * BOOKSHOP_PLAN slice B1a: vendors, their members, the shared catalogue
 * taxonomy (categories, brands) and products with photos and variants.
 *
 * Money is decimal(10,2) MVR, the same as the wallet, gift cards and
 * discount codes it will be paid with (plan §8 said "integer laari like the
 * wallet"; the wallet is decimal, so this follows the wallet).
 *
 * No `academic_year_id`: commerce records, not a term's (every commerce
 * table's precedent, rule 10). No polymorphic columns; the seven models
 * carry morph aliases in config/morph-map.php like every domain model (B2
 * adds `bookshop_checkout` as a payable).
 *
 * `bookshop.manage` is the office's permission; `vendor` is the role a
 * vendor member carries on the unified identity, the way `writer` is — the
 * membership row says which vendor and in what capacity.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendors', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug', 80)->unique();
            $table->string('code', 3)->unique();
            $table->string('tagline')->nullable();
            $table->string('legal_name')->nullable();
            $table->string('tin', 40)->nullable();
            $table->boolean('gst_registered')->default(false);
            $table->string('status', 20)->default('active');
            $table->decimal('commission_rate', 5, 2)->nullable();
            $table->string('contact_email')->nullable();
            $table->string('contact_phone', 40)->nullable();
            $table->text('address')->nullable();
            $table->text('opening_hours')->nullable();
            $table->string('custom_host')->nullable()->unique();
            $table->json('settings')->nullable();
            $table->date('holiday_from')->nullable();
            $table->date('holiday_until')->nullable();
            $table->string('holiday_notice')->nullable();
            $table->text('office_notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('status');
        });

        Schema::create('vendor_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('role', 20)->default('staff');
            $table->timestamp('agreement_accepted_at')->nullable();
            $table->foreignId('added_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['vendor_id', 'user_id']);
            $table->index('user_id');
        });

        Schema::create('product_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('product_categories')->nullOnDelete();
            $table->string('name');
            $table->string('name_dv')->nullable();
            $table->string('name_ar')->nullable();
            $table->string('slug', 80)->unique();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('brands', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug', 80)->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
            $table->foreignId('product_category_id')->nullable()->constrained('product_categories')->nullOnDelete();
            $table->foreignId('brand_id')->nullable()->constrained('brands')->nullOnDelete();
            $table->string('slug', 120)->unique();
            $table->string('title');
            $table->string('title_dv')->nullable();
            $table->string('title_ar')->nullable();
            $table->string('summary', 500)->nullable();
            $table->string('summary_dv', 500)->nullable();
            $table->string('summary_ar', 500)->nullable();
            $table->longText('description')->nullable();
            $table->longText('description_dv')->nullable();
            $table->longText('description_ar')->nullable();
            $table->decimal('price', 10, 2);
            $table->decimal('compare_at_price', 10, 2)->nullable();
            $table->decimal('cost', 10, 2)->nullable();
            $table->string('currency', 3)->default('MVR');
            $table->string('tax_class', 20)->default('standard');
            $table->string('sku', 64)->nullable();
            $table->string('barcode', 64)->nullable();
            $table->unsignedInteger('weight_grams')->nullable();
            $table->string('dimensions', 60)->nullable();
            $table->boolean('track_stock')->default(true);
            $table->integer('stock')->default(0);
            $table->unsignedInteger('low_stock_at')->nullable();
            $table->unsignedSmallInteger('lead_days')->nullable();
            $table->string('status', 20)->default('draft');
            $table->string('visibility', 20)->default('shop');
            $table->boolean('featured')->default(false);
            $table->unsignedBigInteger('library_item_id')->nullable();
            $table->json('tags')->nullable();
            // Book and educational fields. Not `attributes`: that name is
            // Eloquent's own property and a column called it is a trap.
            $table->json('details')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['vendor_id', 'sku']);
            $table->index(['vendor_id', 'status']);
            $table->index(['status', 'visibility']);
        });

        Schema::create('product_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('media_file_id')->constrained('media_files')->cascadeOnDelete();
            $table->string('alt_text')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['product_id', 'sort_order']);
        });

        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('name');
            $table->string('sku', 64)->nullable();
            $table->decimal('price', 10, 2)->nullable();
            $table->integer('stock')->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Permission::firstOrCreate(['name' => 'bookshop.manage', 'guard_name' => 'web']);
        foreach (['super_admin', 'admin'] as $roleName) {
            $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();
            $role?->givePermissionTo('bookshop.manage');
        }
        Role::firstOrCreate(['name' => 'vendor', 'guard_name' => 'web']);
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variants');
        Schema::dropIfExists('product_images');
        Schema::dropIfExists('products');
        Schema::dropIfExists('brands');
        Schema::dropIfExists('product_categories');
        Schema::dropIfExists('vendor_members');
        Schema::dropIfExists('vendors');
    }
};

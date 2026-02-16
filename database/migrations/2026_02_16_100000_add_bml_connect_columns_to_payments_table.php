<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->uuid('uuid')->nullable()->after('id');
            $table->string('payable_type')->nullable()->after('user_id');
            $table->unsignedBigInteger('payable_id')->nullable()->after('payable_type');
            $table->decimal('amount_mvr', 10, 2)->nullable()->after('amount');
            $table->unsignedBigInteger('amount_laar')->nullable()->after('amount_mvr');
            $table->string('local_id', 191)->nullable()->after('provider');
            $table->string('bml_transaction_id', 191)->nullable()->after('local_id');
            $table->text('payment_url')->nullable()->after('redirect_url');
            $table->json('bml_status_raw')->nullable()->after('callback_payload');
            $table->json('redirect_return_payload')->nullable()->after('bml_status_raw');
            $table->json('webhook_payload')->nullable()->after('redirect_return_payload');
            $table->timestamp('paid_at')->nullable()->after('confirmed_at');
            $table->timestamp('failed_at')->nullable()->after('paid_at');
            $table->text('notes')->nullable()->after('failed_at');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->index('uuid');
            $table->index(['payable_type', 'payable_id']);
            $table->unique('local_id');
            $table->index('bml_transaction_id');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropUnique(['local_id']);
        });
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn([
                'uuid', 'payable_type', 'payable_id', 'amount_mvr', 'amount_laar',
                'local_id', 'bml_transaction_id', 'payment_url', 'bml_status_raw',
                'redirect_return_payload', 'webhook_payload', 'paid_at', 'failed_at', 'notes',
            ]);
        });
    }
};

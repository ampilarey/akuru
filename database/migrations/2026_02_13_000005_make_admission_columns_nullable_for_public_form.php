<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Makes legacy admin columns nullable so the simplified public form can create records.
     */
    public function up(): void
    {
        $columns = [
            'date_of_birth',
            'gender',
            'parent_name',
            'parent_phone',
            'parent_email',
            'address',
            'grade_applying_for',
            'guardian_relationship',
            'emergency_contact_name',
            'emergency_contact_phone',
            'emergency_contact_relationship',
        ];

        foreach ($columns as $column) {
            if (\Schema::hasColumn('admission_applications', $column)) {
                $type = match (true) {
                    in_array($column, ['date_of_birth']) => 'date',
                    in_array($column, ['gender']) => "enum('male','female','other')",
                    in_array($column, ['address']) => 'text',
                    default => 'varchar(255)',
                };
                DB::statement("ALTER TABLE admission_applications MODIFY COLUMN `{$column}` {$type} NULL");
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Reverting would require default values; leave as nullable for safety
    }
};

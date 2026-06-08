<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        $permissions = [
            'view_hifz_programs',
            'manage_hifz_programs',
            'assign_hifz_supervisors',
            'create_hifz_sessions',
            'update_hifz_sessions',
            'review_hifz_sessions',
            'lock_hifz_sessions',
            'create_hifz_session_records',
            'update_hifz_session_records',
            'review_hifz_session_records',
            'override_hifz_records',
            'create_hifz_mistakes',
            'view_hifz_mistakes',
            'recommend_hifz_milestones',
            'review_hifz_milestones',
            'approve_hifz_milestones',
            'manage_quran_mushaf',
            'approve_quran_mushaf',
            'review_quran_mapping',
            'view_hifz_reports',
            'export_hifz_reports',
            'record_hifz_as_substitute',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        $this->assignIfRoleExists('super_admin', $permissions);
        $this->assignIfRoleExists('admin', $permissions);

        $this->assignIfRoleExists('headmaster', [
            'view_hifz_programs', 'manage_hifz_programs', 'assign_hifz_supervisors',
            'create_hifz_sessions', 'update_hifz_sessions', 'review_hifz_sessions', 'lock_hifz_sessions',
            'create_hifz_session_records', 'update_hifz_session_records', 'review_hifz_session_records', 'override_hifz_records',
            'create_hifz_mistakes', 'view_hifz_mistakes',
            'recommend_hifz_milestones', 'review_hifz_milestones', 'approve_hifz_milestones',
            'manage_quran_mushaf', 'approve_quran_mushaf', 'review_quran_mapping',
            'view_hifz_reports', 'export_hifz_reports', 'record_hifz_as_substitute',
        ]);

        $this->assignIfRoleExists('supervisor', [
            'view_hifz_programs',
            'review_hifz_sessions', 'review_hifz_session_records',
            'view_hifz_mistakes',
            'recommend_hifz_milestones', 'review_hifz_milestones',
            'view_hifz_reports', 'export_hifz_reports',
        ]);

        $this->assignIfRoleExists('teacher', [
            'view_hifz_programs',
            'create_hifz_sessions', 'update_hifz_sessions',
            'create_hifz_session_records', 'update_hifz_session_records',
            'create_hifz_mistakes', 'view_hifz_mistakes',
            'recommend_hifz_milestones',
        ]);

        $this->assignIfRoleExists('student', ['view_hifz_programs', 'view_hifz_mistakes']);
        $this->assignIfRoleExists('parent', ['view_hifz_programs', 'view_hifz_mistakes']);
    }

    protected function assignIfRoleExists(string $roleName, array $permissions): void
    {
        $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();

        if ($role) {
            $role->givePermissionTo($permissions);
        }
    }

    public function down(): void
    {
        $permissions = [
            'view_hifz_programs', 'manage_hifz_programs', 'assign_hifz_supervisors',
            'create_hifz_sessions', 'update_hifz_sessions', 'review_hifz_sessions', 'lock_hifz_sessions',
            'create_hifz_session_records', 'update_hifz_session_records', 'review_hifz_session_records', 'override_hifz_records',
            'create_hifz_mistakes', 'view_hifz_mistakes',
            'recommend_hifz_milestones', 'review_hifz_milestones', 'approve_hifz_milestones',
            'manage_quran_mushaf', 'approve_quran_mushaf', 'review_quran_mapping',
            'view_hifz_reports', 'export_hifz_reports', 'record_hifz_as_substitute',
        ];

        Permission::whereIn('name', $permissions)->delete();
    }
};

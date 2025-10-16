<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create roles
        $roles = [
            'super_admin',  // System owner - full access
            'admin',        // School admin - fees, payments, operations
            'headmaster',   // Academic leadership
            'supervisor',   // Academic monitoring
            'teacher',      // Teaching staff
            'student',      // Students
            'parent'        // Parents/Guardians
        ];

        foreach ($roles as $role) {
            Role::firstOrCreate(['name' => $role]);
        }

        // Create permissions
        $permissions = [
            // School management
            'manage_school',
            'view_school',
            
            // User management
            'manage_users',
            'view_users',
            'create_users',
            'edit_users',
            'delete_users',
            
            // Student management
            'manage_students',
            'view_students',
            'create_students',
            'edit_students',
            'delete_students',
            
            // Teacher management
            'manage_teachers',
            'view_teachers',
            'create_teachers',
            'edit_teachers',
            'delete_teachers',
            
            // Class management
            'manage_classes',
            'view_classes',
            'create_classes',
            'edit_classes',
            'delete_classes',
            
            // Subject management
            'manage_subjects',
            'view_subjects',
            'create_subjects',
            'edit_subjects',
            'delete_subjects',
            
            // Grade management
            'manage_grades',
            'view_grades',
            'create_grades',
            'edit_grades',
            'delete_grades',
            
            // Attendance management
            'manage_attendance',
            'view_attendance',
            'mark_attendance',
            
            // Quran progress
            'manage_quran_progress',
            'view_quran_progress',
            'update_quran_progress',
            
            // Timetable management
            'manage_timetables',
            'view_timetables',
            'create_timetables',
            'edit_timetables',
            'delete_timetables',
            
            // Announcements
            'manage_announcements',
            'view_announcements',
            'create_announcements',
            'edit_announcements',
            'delete_announcements',
            
            // Reports
            'view_reports',
            'generate_reports',
        ];

        foreach ($permissions as $permission) {
            Permission::create(['name' => $permission]);
        }

        // Assign permissions to roles
        
        // Super Admin gets ALL permissions
        $superAdmin = Role::findByName('super_admin');
        $superAdmin->givePermissionTo(Permission::all());
        
        // Admin gets most permissions (school operations, not system-level)
        $admin = Role::findByName('admin');
        $admin->givePermissionTo(Permission::all());

        $headmaster = Role::findByName('headmaster');
        $headmaster->givePermissionTo([
            'view_school',
            'manage_users',
            'view_users',
            'create_users',
            'edit_users',
            'manage_students',
            'view_students',
            'create_students',
            'edit_students',
            'manage_teachers',
            'view_teachers',
            'create_teachers',
            'edit_teachers',
            'manage_classes',
            'view_classes',
            'create_classes',
            'edit_classes',
            'manage_subjects',
            'view_subjects',
            'create_subjects',
            'edit_subjects',
            'view_grades',
            'view_attendance',
            'view_quran_progress',
            'manage_timetables',
            'view_timetables',
            'create_timetables',
            'edit_timetables',
            'manage_announcements',
            'view_announcements',
            'create_announcements',
            'edit_announcements',
            'view_reports',
            'generate_reports',
        ]);

        $supervisor = Role::findByName('supervisor');
        $supervisor->givePermissionTo([
            'view_school',
            'view_users',
            'view_students',
            'view_teachers',
            'view_classes',
            'view_subjects',
            'view_grades',
            'view_attendance',
            'view_quran_progress',
            'view_timetables',
            'view_announcements',
            'view_reports',
        ]);

        $teacher = Role::findByName('teacher');
        $teacher->givePermissionTo([
            'view_school',
            'view_students',
            'view_classes',
            'view_subjects',
            'manage_grades',
            'view_grades',
            'create_grades',
            'edit_grades',
            'manage_attendance',
            'view_attendance',
            'mark_attendance',
            'manage_quran_progress',
            'view_quran_progress',
            'update_quran_progress',
            'view_timetables',
            'view_announcements',
        ]);

        $student = Role::findByName('student');
        $student->givePermissionTo([
            'view_school',
            'view_grades',
            'view_attendance',
            'view_quran_progress',
            'view_timetables',
            'view_announcements',
        ]);

        $parent = Role::findByName('parent');
        $parent->givePermissionTo([
            'view_school',
            'view_grades',
            'view_attendance',
            'view_quran_progress',
            'view_timetables',
            'view_announcements',
        ]);
    }
}

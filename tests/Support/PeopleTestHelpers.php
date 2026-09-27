<?php

use App\Domains\Identity\Models\User;
use App\Domains\People\Enums\CustomFieldEntityType;
use App\Domains\People\Enums\CustomFieldType;
use App\Domains\People\Models\CustomFieldDefinition;
use App\Domains\People\Models\ParentGuardian;
use App\Domains\People\Models\StaffProfile;
use App\Domains\People\Models\Student;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

function makeStudent(array $overrides = []): Student
{
    $user = User::factory()->create();

    return Student::query()->create(array_merge([
        'user_id' => $user->id,
        'first_name' => 'Aisha',
        'last_name' => 'Ali',
        'date_of_birth' => '2012-03-01',
        'gender' => 'female',
    ], $overrides));
}

function makeStaffProfile(array $overrides = []): StaffProfile
{
    if (! array_key_exists('user_id', $overrides)) {
        $overrides['user_id'] = User::factory()->create()->id;
    }

    return StaffProfile::query()->create(array_merge([
        'first_name' => 'Mariyam',
        'last_name' => 'Didi',
        'employment_type' => 'full_time',
        'status' => 'active',
        // See uniqueFixtureSuffix() for why the previous `fake()->unique()`
        // guaranteed nothing.
        'staff_number' => 'STF-'.uniqueFixtureSuffix(),
    ], $overrides));
}

function makeGuardian(): ParentGuardian
{
    $user = User::factory()->create();

    return ParentGuardian::query()->create([
        'user_id' => $user->id,
        'first_name' => 'Hassan',
        'last_name' => 'Ali',
        'phone' => '7820288',
        'email' => $user->email ?? 'guardian@example.com',
        'address' => 'Malé',
        'relationship' => 'father',
    ]);
}

/**
 * A course registrant as registration now writes one: a `students` row, with
 * no account unless `user_id` is given. Replaced `makeRegistrationStudent()`
 * and `attachLegacyGuardian()` when Deploy 3 archived `registration_students`
 * and `student_guardians` (STATUS §5gh). Accepts the old `dob` key.
 */
function makeCourseStudent(array $overrides = []): Student
{
    if (array_key_exists('dob', $overrides)) {
        $overrides['date_of_birth'] = $overrides['dob'];
        unset($overrides['dob']);
    }

    return Student::query()->create(array_merge([
        'user_id' => null,
        'first_name' => 'Aisha',
        'last_name' => 'Ali',
        'date_of_birth' => '2012-03-01',
        'gender' => 'female',
    ], $overrides));
}

/**
 * An educational admin holding exactly `$permissions`.
 *
 * The `admin` role carries the educational admin's real set by migration
 * (`RoleGrants::educationalAdmin()`, ADR-040 slice 2), so a bare `assignRole`
 * would hand the actor forty-nine abilities and "forbids X without
 * `x.manage`" could never be asserted. These tests say which abilities the
 * actor holds — the gate under test is the permission, not the role — so
 * the role is stripped to a bare key inside the test's transaction. The real
 * set is asserted where it is the subject: `EducationalAdminPermissionSetTest`.
 */
function actingPeopleAdmin(array $permissions = ['custom_fields.manage', 'students.view-sensitive']): User
{
    Role::findOrCreate('admin', 'web')->syncPermissions([]);

    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    $user = User::factory()->create();
    $user->assignRole('admin');
    $user->givePermissionTo($permissions);
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    return $user;
}

/**
 * The system admin (`super_admin`) — the Institute's screens admit that role
 * alone (ADR-040 slice 2). The role is migration-created and the Institute
 * migrations grant it their permissions, so `$permissions` is for the ones a
 * test wants to be explicit about; a "without" case revokes from the role.
 */
function actingSystemAdmin(array $permissions = []): User
{
    Role::findOrCreate('super_admin', 'web');

    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    $user = User::factory()->create();
    $user->assignRole('super_admin');
    if ($permissions !== []) {
        $user->givePermissionTo($permissions);
    }
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    return $user;
}

function makeCustomFieldDefinition(array $overrides = []): CustomFieldDefinition
{
    return CustomFieldDefinition::query()->create(array_merge([
        'entity_type' => CustomFieldEntityType::Students->value,
        'key' => 'blood_group',
        'label_en' => 'Blood group',
        'label_dv' => 'ލޭގެ ގްރޫޕް',
        'label_ar' => 'فصيلة الدم',
        'field_type' => CustomFieldType::Select->value,
        'options' => [
            ['value' => 'A+', 'label' => 'A+'],
            ['value' => 'O+', 'label' => 'O+'],
        ],
        'required' => false,
        'show_in_profile' => true,
        'show_in_admission_form' => false,
        'sort_order' => 0,
        'active' => true,
    ], $overrides));
}

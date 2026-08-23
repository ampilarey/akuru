<?php

use App\Domains\Identity\Models\User;
use App\Domains\People\Enums\CustomFieldEntityType;
use App\Domains\People\Enums\CustomFieldType;
use App\Domains\People\Models\CustomFieldDefinition;
use App\Domains\People\Models\ParentGuardian;
use App\Domains\People\Models\RegistrationStudent;
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

function makeRegistrationStudent(array $overrides = []): RegistrationStudent
{
    if (! array_key_exists('user_id', $overrides)) {
        $overrides['user_id'] = User::factory()->create()->id;
    }

    return RegistrationStudent::query()->create(array_merge([
        'first_name' => 'Aisha',
        'last_name' => 'Ali',
        'dob' => '2012-03-01',
        'gender' => 'female',
    ], $overrides));
}

function actingPeopleAdmin(array $permissions = ['custom_fields.manage', 'students.view-sensitive']): User
{
    Role::findOrCreate('admin', 'web');

    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    $user = User::factory()->create();
    $user->assignRole('admin');
    $user->givePermissionTo($permissions);

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

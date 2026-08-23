<?php

use App\Domains\Identity\Models\User;
use App\Domains\People\Models\ParentGuardian;
use App\Domains\People\Models\RegistrationStudent;
use App\Domains\People\Models\Student;

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

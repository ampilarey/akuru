<?php

use App\Domains\Identity\Models\User;
use App\Domains\People\Enums\EmploymentType;
use App\Domains\People\Enums\StaffStatus;
use App\Domains\People\Models\StaffProfile;
use App\Domains\People\Models\Teacher;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('creates a staff profile with qualifications and unique user_id', function () {
    $admin = actingPeopleAdmin();
    $user = User::factory()->create();

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->post(route('people.staff.store'), [
            'user_id' => $user->id,
            'first_name' => 'Ibrahim',
            'last_name' => 'Didi',
            'employment_type' => EmploymentType::FullTime->value,
            'status' => StaffStatus::Active->value,
            'staff_number' => 'STF-1',
        ])
        ->assertRedirect();

    $profile = StaffProfile::query()->where('user_id', $user->id)->sole();

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->post(route('people.staff.qualifications.store', $profile), [
            'title' => 'BA Islamic Studies',
            'institution' => 'IAU',
            'year' => 2018,
        ])
        ->assertRedirect();

    expect($profile->qualifications()->count())->toBe(1);

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->get(route('people.staff.show', $profile))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('People/Staff/Show')
            ->has('staff.qualifications', 1)
        );

    $other = User::factory()->create();
    StaffProfile::query()->create([
        'user_id' => $other->id,
        'first_name' => 'Other',
        'last_name' => 'Staff',
    ]);

    expect(fn () => StaffProfile::query()->create([
        'user_id' => $user->id,
        'first_name' => 'Dup',
        'last_name' => 'Staff',
    ]))->toThrow(QueryException::class);
});

it('does not change teacher columns except the additive staff_profile_id', function () {
    expect(Schema::hasColumn('teachers', 'staff_profile_id'))->toBeTrue()
        ->and(Schema::hasColumn('teachers', 'teacher_id'))->toBeTrue()
        ->and((new Teacher)->getFillable())->toContain('teacher_id')
        ->and((new Teacher)->getFillable())->not->toContain('staff_profile_id');
});

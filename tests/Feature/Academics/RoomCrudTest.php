<?php

use App\Domains\Academics\Actions\SyncRoomsFromTimetableStringsAction;
use App\Domains\Academics\Enums\RoomType;
use App\Domains\Academics\Models\Room;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function insertTimetableRoom(?string $room, ?string $arabic = null, ?string $dhivehi = null): void
{
    Schema::disableForeignKeyConstraints();
    DB::table('timetables')->insert([
        'class_id' => 1,
        'subject_id' => 1,
        'teacher_id' => 1,
        'day_of_week' => 'monday',
        'start_time' => '08:00:00',
        'end_time' => '08:45:00',
        'room' => $room,
        'room_arabic' => $arabic,
        'room_dhivehi' => $dhivehi,
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    Schema::enableForeignKeyConstraints();
}

it('creates updates and exports rooms', function () {
    $admin = actingPeopleAdmin(['rooms.manage']);

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->post(route('academics.rooms.store'), [
            'name' => 'Lab 1',
            'name_arabic' => 'مختبر 1',
            'name_dhivehi' => 'ލެބް 1',
            'building' => 'Main',
            'capacity' => 24,
            'type' => RoomType::Lab->value,
            'bookable' => true,
            'active' => true,
        ])
        ->assertRedirect(route('academics.rooms.index'));

    $room = Room::query()->where('name', 'Lab 1')->first();
    expect($room)->not->toBeNull()
        ->and($room->type)->toBe(RoomType::Lab)
        ->and($room->building)->toBe('Main')
        ->and($room->capacity)->toBe(24);

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->put(route('academics.rooms.update', $room), [
            'name' => 'Lab 1',
            'name_arabic' => 'مختبر 1',
            'name_dhivehi' => 'ލެބް 1',
            'building' => 'East wing',
            'capacity' => 30,
            'type' => RoomType::Lab->value,
            'bookable' => false,
            'active' => true,
        ])
        ->assertRedirect(route('academics.rooms.index'));

    expect($room->fresh()->building)->toBe('East wing')
        ->and($room->fresh()->capacity)->toBe(30)
        ->and($room->fresh()->bookable)->toBeFalse();

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->get(route('academics.rooms.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Academics/Rooms/Index')
            ->has('rooms', 1)
            ->where('rooms.0.name', 'Lab 1')
        );

    $csv = $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->get(route('academics.rooms.export'))
        ->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8')
        ->streamedContent();

    expect($csv)->toContain('Lab 1')
        ->and($csv)->toContain('East wing');
});

it('rejects a duplicate room name', function () {
    $admin = actingPeopleAdmin(['rooms.manage']);
    Room::query()->create([
        'name' => 'Hall A',
        'type' => RoomType::Hall,
        'bookable' => true,
        'active' => true,
    ]);

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->from(route('academics.rooms.index'))
        ->post(route('academics.rooms.store'), [
            'name' => 'Hall A',
            'type' => RoomType::Hall->value,
        ])
        ->assertRedirect(route('academics.rooms.index'))
        ->assertSessionHasErrors('name');
});

it('forbids the rooms screen without rooms.manage', function () {
    $admin = actingPeopleAdmin([]);

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->get(route('academics.rooms.index'))
        ->assertForbidden();
});

it('syncs rooms from timetable strings and is idempotent', function () {
    insertTimetableRoom('Lab 1', 'مختبر 1', 'ލެބް 1');
    insertTimetableRoom('Lab 1', null, null);
    insertTimetableRoom('Hall A');
    insertTimetableRoom('  ');
    insertTimetableRoom(null);

    $first = app(SyncRoomsFromTimetableStringsAction::class)->execute();

    expect($first['created'])->toBe(2)
        ->and($first['reused'])->toBe(0)
        ->and($first['skipped_blank'])->toBe(2)
        ->and(Room::query()->count())->toBe(2);

    $lab = Room::query()->where('name', 'Lab 1')->first();
    expect($lab->name_arabic)->toBe('مختبر 1')
        ->and($lab->name_dhivehi)->toBe('ލެބް 1')
        ->and($lab->type)->toBe(RoomType::Classroom);

    $second = app(SyncRoomsFromTimetableStringsAction::class)->execute();

    expect($second['created'])->toBe(0)
        ->and($second['reused'])->toBe(2)
        ->and(Room::query()->count())->toBe(2)
        ->and($lab->fresh()->name_arabic)->toBe('مختبر 1');
});

it('fills empty translations on a second sync without duplicating', function () {
    insertTimetableRoom('Studio');
    app(SyncRoomsFromTimetableStringsAction::class)->execute();

    insertTimetableRoom('Studio', 'استوديو', 'ސްޓޫޑިއޯ');
    app(SyncRoomsFromTimetableStringsAction::class)->execute();

    $room = Room::query()->where('name', 'Studio')->sole();
    expect(Room::query()->count())->toBe(1)
        ->and($room->name_arabic)->toBe('استوديو')
        ->and($room->name_dhivehi)->toBe('ސްޓޫޑިއޯ');
});

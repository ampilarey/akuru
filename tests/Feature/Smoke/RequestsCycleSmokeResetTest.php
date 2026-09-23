<?php

use Database\Seeders\SmokeMarkerSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * `scripts/smoke/requests.mjs` files two family requests and has the office
 * decide them, which raises notices for the class teacher, the office and
 * the parent. `SmokeMarkerSeeder::requestsCycle()` clears all of it and
 * makes teacher@ the pupil's class teacher — so the walk can run twice,
 * and the seeder must too.
 */
it('clears a run\'s family requests and notices, sets the class teacher, keeps the planted request, and runs twice', function () {
    $this->seed();
    $this->seed(SmokeMarkerSeeder::class);
    $this->seed(SmokeMarkerSeeder::class);

    $parentId = (int) DB::table('users')->where('email', 'parent@akuru.edu.mv')->value('id');
    $teacherId = (int) DB::table('users')->where('email', 'teacher@akuru.edu.mv')->value('id');

    // What a run leaves behind.
    $requestId = DB::table('requests')->insertGetId([
        'type' => 'parent_general', 'requester_id' => $parentId, 'regarding_type' => 'student', 'regarding_id' => 1,
        'reason' => 'SMOKE-Family-Request: left over', 'status' => 'rejected', 'review_notes' => 'SMOKE-Rejected: left over',
        'payload' => json_encode([]), 'created_at' => now(), 'updated_at' => now(),
    ]);
    foreach ([[$teacherId, 'New request from Parent', 'A parent general request about a pupil: SMOKE-Family-Request: left over'], [$parentId, 'Your request was rejected', 'Your parent general request was rejected. SMOKE-Rejected: left over']] as [$userId, $title, $message]) {
        DB::table('user_notifications')->insert(['user_id' => $userId, 'type' => 'in_app', 'category' => 'academics', 'title' => $title, 'message' => $message, 'status' => 'sent', 'created_at' => now(), 'updated_at' => now()]);
    }

    $this->seed(SmokeMarkerSeeder::class);

    $classId = (int) DB::table('class_student')->where('student_id', (int) DB::table('consents')->where('source', 'admission_form')->value('person_id'))->where('status', 'active')->value('class_id');

    expect(DB::table('requests')->where('id', $requestId)->exists())->toBeFalse()
        // The sweep's own planted request is not this walk's residue.
        ->and(DB::table('requests')->where('reason', 'SMOKE-Request')->count())->toBe(1)
        ->and(DB::table('user_notifications')->where('message', 'like', '%SMOKE-Family-Request%')->exists())->toBeFalse()
        ->and(DB::table('user_notifications')->where('message', 'like', '%SMOKE-Rejected%')->exists())->toBeFalse()
        ->and((int) DB::table('classes')->where('id', $classId)->value('class_teacher_id'))->toBe($teacherId);
});

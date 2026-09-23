<?php

use Database\Seeders\SmokeMarkerSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * `scripts/smoke/family.mjs` writes homework into a register, posts a notice,
 * starts two message threads (one with a poll) and ticks the homework.
 * `SmokeMarkerSeeder::familyCycle()` plants nothing and clears all of it,
 * blanking the homework rather than deleting the register — so the walk can
 * run twice, and the seeder must too.
 */
it('clears a run\'s threads, poll, notifications, notice and ticks, blanks the homework, and runs twice', function () {
    $this->seed();
    $this->seed(SmokeMarkerSeeder::class);
    $this->seed(SmokeMarkerSeeder::class);

    $userId = (int) DB::table('users')->where('email', 'parent@akuru.edu.mv')->value('id');
    $yearId = (int) DB::table('academic_years')->orderBy('id')->value('id');

    // What a run leaves behind.
    $threadId = DB::table('message_threads')->insertGetId([
        'subject' => 'SMOKE-Poll', 'created_by' => $userId, 'reply_policy' => 'all', 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('message_participants')->insert(['message_thread_id' => $threadId, 'user_id' => $userId, 'role' => 'author', 'created_at' => now(), 'updated_at' => now()]);
    DB::table('messages')->insert(['thread_id' => $threadId, 'sender_id' => $userId, 'recipient_id' => $userId, 'subject' => 'SMOKE-Poll', 'content' => 'left over', 'created_at' => now(), 'updated_at' => now()]);
    $pollId = DB::table('message_polls')->insertGetId(['message_thread_id' => $threadId, 'academic_year_id' => $yearId, 'question' => 'SMOKE-Poll-Question', 'options' => json_encode(['SMOKE-Yes', 'SMOKE-No']), 'created_at' => now(), 'updated_at' => now()]);
    DB::table('message_poll_responses')->insert(['message_poll_id' => $pollId, 'user_id' => $userId, 'academic_year_id' => $yearId, 'choice' => 0, 'responded_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
    DB::table('user_notifications')->insert(['user_id' => $userId, 'type' => 'in_app', 'category' => 'message', 'title' => 'New message: SMOKE-Message', 'message' => 'left over', 'status' => 'sent', 'created_at' => now(), 'updated_at' => now()]);
    $noticeId = DB::table('announcements')->insertGetId([
        'school_id' => DB::table('schools')->orderBy('id')->value('id'), 'created_by' => $userId, 'title' => 'SMOKE-Notice', 'content' => 'left over',
        'type' => 'general', 'priority' => 'medium', 'target_audience' => json_encode(['all']), 'publish_date' => now()->toDateString(), 'is_published' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $logId = DB::table('lesson_logs')->insertGetId([
        'teacher_id' => (int) DB::table('teachers')->orderBy('id')->value('id'), 'subject_id' => (int) DB::table('subjects')->orderBy('id')->value('id'),
        'classroom_id' => (int) DB::table('classes')->orderBy('id')->value('id'), 'academic_year_id' => $yearId, 'date' => now()->toDateString(),
        'homework' => 'SMOKE-Homework: read page 12', 'homework_due_date' => now()->addDay()->toDateString(), 'status' => 'submitted',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('homework_ticks')->insert(['lesson_log_id' => $logId, 'student_id' => (int) DB::table('students')->orderBy('id')->value('id'), 'academic_year_id' => $yearId, 'ticked_at' => now(), 'created_at' => now(), 'updated_at' => now()]);

    $this->seed(SmokeMarkerSeeder::class);

    expect(DB::table('message_poll_responses')->where('message_poll_id', $pollId)->exists())->toBeFalse()
        ->and(DB::table('message_polls')->where('id', $pollId)->exists())->toBeFalse()
        ->and(DB::table('message_threads')->where('id', $threadId)->exists())->toBeFalse()
        ->and(DB::table('messages')->where('thread_id', $threadId)->exists())->toBeFalse()
        ->and(DB::table('user_notifications')->where('title', 'like', '%SMOKE-%')->exists())->toBeFalse()
        ->and(DB::table('announcements')->where('id', $noticeId)->exists())->toBeFalse()
        ->and(DB::table('homework_ticks')->where('lesson_log_id', $logId)->exists())->toBeFalse()
        ->and(DB::table('lesson_logs')->where('id', $logId)->exists())->toBeTrue()
        ->and(DB::table('lesson_logs')->where('id', $logId)->value('homework'))->toBeNull();
});

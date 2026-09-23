<?php

use App\Domains\People\Actions\HasActiveConsentAction;
use Database\Seeders\SmokeMarkerSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * `scripts/smoke/consent.mjs` records consents from the pupil's screen, every
 * one an `admin` row. The gate reads the newest row, so a revoke the walk left
 * behind would outrank the seeded grant on the next run; the cycle clears the
 * office's rows for the two types the walk touches and plants the photo
 * document the public page offers — so the walk can run twice, and the
 * seeder must too.
 */
it('clears the office\'s consent rows for the walked types, keeps the seeded grant on top, plants the photo, and runs twice', function () {
    $this->seed();
    $this->seed(SmokeMarkerSeeder::class);
    $this->seed(SmokeMarkerSeeder::class);

    $studentId = (int) DB::table('consents')->where('source', 'admission_form')->where('consent_type', 'photo_media_use')->value('person_id');
    expect($studentId)->toBeGreaterThan(0);

    // The seeded row must be a real source: the tab casts it to the enum.
    $admin = actingPeopleAdmin(['people.students.view']);
    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->get('/people/students/'.$studentId.'?tab=consents')
        ->assertOk();
    $adminId = (int) DB::table('users')->where('email', 'admin@akuru.edu.mv')->value('id');

    // What a run leaves behind: a revoke on top of the seeded grant, and a
    // marketing consent granted then revoked.
    foreach ([['photo_media_use', 0], ['marketing_messages', 1], ['marketing_messages', 0]] as [$type, $granted]) {
        DB::table('consents')->insert([
            'person_type' => 'student', 'person_id' => $studentId, 'consent_type' => $type, 'granted' => $granted,
            'granted_by' => $adminId, 'granted_at' => now(), 'revoked_at' => $granted ? null : now(), 'source' => 'admin',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    expect(app(HasActiveConsentAction::class)->execute('student', $studentId, 'photo_media_use'))->toBeFalse();

    $this->seed(SmokeMarkerSeeder::class);

    expect(DB::table('consents')->where('person_id', $studentId)->where('source', 'admin')->exists())->toBeFalse()
        ->and(DB::table('consents')->where('person_id', $studentId)->where('consent_type', 'marketing_messages')->exists())->toBeFalse()
        ->and(app(HasActiveConsentAction::class)->execute('student', $studentId, 'photo_media_use'))->toBeTrue()
        ->and(DB::table('documents')->where('title', 'SMOKE-Photo')->where('documentable_type', 'student')->where('documentable_id', $studentId)->where('document_type', 'photo')->count())->toBe(1);
});

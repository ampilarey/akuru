<?php

use App\Domains\Courses\Models\Course;
use App\Domains\Identity\Actions\IdentityVerificationAction;
use App\Domains\Identity\Models\IdentityVerification;
use App\Domains\Identity\Models\Otp;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Models\UserContact;
use App\Domains\Media\Models\MediaFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/**
 * C17 slice R3 (STATUS §5of). The owner, 2026-10-03: "How about adding front
 * page only. Because all the important informations are on front page only.
 * And how about saving a reduced file size of id to same space".
 *
 * - The front of the card is enough, for every identity check; the back is
 *   optional, and the registration form no longer asks for it.
 * - A photo is kept smaller, in the same private store: scaled to 1600 px on
 *   its long side and re-saved as JPEG (which drops the camera's metadata).
 *   A PDF is kept as sent.
 */
beforeEach(function () {
    config(['identity.verification.enforce' => true]);
    Storage::fake('local');
    Storage::fake('public');
    Mail::fake();
});

/** A phone-camera-sized photo: smooth light, some print, a little grain — like a card on a table. */
function bigCardPhoto(int $w = 4000, int $h = 3000): UploadedFile
{
    $image = imagecreatetruecolor($w, $h);
    for ($y = 0; $y < $h; $y++) {
        $shade = 170 + (int) (60 * $y / $h);
        imageline($image, 0, $y, $w, $y, imagecolorallocate($image, $shade, $shade - 10, $shade - 25));
    }
    $ink = imagecolorallocate($image, 30, 30, 40);
    for ($line = 0; $line < 40; $line++) {
        imagestring($image, 5, 200 + ($line % 3) * 900, 200 + $line * 65, 'REPUBLIC OF MALDIVES  NATIONAL IDENTITY CARD  A000000', $ink);
    }
    mt_srand(7);
    for ($i = 0; $i < 400000; $i++) {
        $g = mt_rand(120, 230);
        imagesetpixel($image, mt_rand(0, $w - 1), mt_rand(0, $h - 1), imagecolorallocate($image, $g, $g, $g));
    }
    $path = tempnam(sys_get_temp_dir(), 'card').'.jpg';
    imagejpeg($image, $path, 92);
    imagedestroy($image);

    return new UploadedFile($path, 'front.jpg', 'image/jpeg', null, true);
}

/** A small photo that is nothing but detail: a smaller JPEG of it would be larger. */
function denseSmallPhoto(): UploadedFile
{
    $image = imagecreatetruecolor(2000, 1200);
    mt_srand(11);
    for ($y = 0; $y < 1200; $y += 8) {
        for ($x = 0; $x < 2000; $x += 8) {
            imagefilledrectangle($image, $x, $y, $x + 7, $y + 7, imagecolorallocate($image, mt_rand(0, 255), mt_rand(0, 255), mt_rand(0, 255)));
        }
    }
    $path = tempnam(sys_get_temp_dir(), 'dense').'.jpg';
    imagejpeg($image, $path, 40);
    imagedestroy($image);

    return new UploadedFile($path, 'dense.jpg', 'image/jpeg', null, true);
}

it('enrols with the front of the card alone, and keeps the photo smaller in the same store', function () {
    $user = User::factory()->create(['force_password_change' => false]);
    $contact = UserContact::create(['user_id' => $user->id, 'type' => 'mobile', 'value' => '7'.random_int(100000, 999999), 'is_primary' => true, 'verified_at' => now()]);
    $course = Course::factory()->create(['registration_fee_amount' => 0, 'requires_admin_approval' => false]);
    $photo = bigCardPhoto();
    $sentBytes = $photo->getSize();

    // The form asks for the front only.
    $this->withoutLocalizationMiddleware()->actingAs($user)->withSession(['pending_selected_course_ids' => [$course->id]])
        ->get(route('courses.register.continue'))->assertOk()
        ->assertSee('data-testid="learner-id-front"', false)
        ->assertDontSee('name="id_back"', false);

    $this->withoutLocalizationMiddleware()->actingAs($user)->post(route('courses.register.enroll'), [
        'flow' => 'adult', 'course_ids' => [$course->id], 'first_name' => 'Aishath', 'last_name' => 'Nasir',
        'dob' => now()->subYears(22)->format('Y-m-d'), 'gender' => 'female', 'id_type' => 'national_id', 'national_id' => 'A'.random_int(100000, 999999),
        'id_front' => $photo,
    ])->assertSessionHasNoErrors()->assertRedirect(route('courses.register.enroll.otp'));
    Otp::createForContact($contact, 'login', '654321');
    $this->withoutLocalizationMiddleware()->actingAs($user)->post(route('courses.register.enroll.confirm'), ['otp_code' => '654321', 'terms_accepted' => '1'])
        ->assertRedirect(route('courses.register.complete'));

    $card = IdentityVerification::query()->sole();
    expect($card->back_media_file_id)->toBeNull();

    // Same private store, a smaller JPEG no more than 1600 px on its long side.
    $media = MediaFile::query()->findOrFail($card->front_media_file_id);
    expect($media->disk)->toBe('local')
        ->and($media->visibility)->toBe('private')
        ->and($media->mime)->toBe('image/jpeg')
        ->and($media->path)->toEndWith('.jpg')
        ->and((int) $media->size)->toBeLessThan($sentBytes / 2);
    $stored = Storage::disk('local')->get($media->path);
    [$width, $height] = getimagesizefromstring($stored);
    expect(max($width, $height))->toBe(IdentityVerificationAction::MAX_SIDE)
        ->and((int) $media->size)->toBe(strlen($stored));

    // The office sees the front, and no back link.
    $row = collect(app(IdentityVerificationAction::class)->forStudents([(int) $card->student_id]))->first();
    expect($row['front_url'])->not->toBeNull()->and($row['back_url'])->toBeNull()
        ->and(app(IdentityVerificationAction::class)->document($card->id, 'back'))->toBeNull()
        ->and(app(IdentityVerificationAction::class)->document($card->id, 'front')['mime'])->toBe('image/jpeg');
});

it('takes the back too when it is sent, never enlarges a small photo, never stores a bigger file, and keeps a PDF as sent', function () {
    $user = User::factory()->create();
    $identity = app(IdentityVerificationAction::class);

    $dense = denseSmallPhoto();
    $denseBytes = $dense->getSize();
    $denseCard = $identity->submit($user->id, 'vendor', $dense);
    expect((int) MediaFile::query()->findOrFail($denseCard->front_media_file_id)->size)->toBeLessThanOrEqual($denseBytes);

    $card = $identity->submit($user->id, 'lender', UploadedFile::fake()->image('front.png', 800, 500), UploadedFile::fake()->image('back.png', 800, 500));
    expect($card->back_media_file_id)->not->toBeNull();
    [$width] = getimagesizefromstring(Storage::disk('local')->get(MediaFile::query()->findOrFail($card->front_media_file_id)->path));
    expect($width)->toBe(800);

    $pdf = UploadedFile::fake()->create('card.pdf', 40, 'application/pdf');
    $pdfCard = $identity->submit($user->id, 'writer', $pdf);
    $media = MediaFile::query()->findOrFail($pdfCard->front_media_file_id);
    expect($media->mime)->toBe('application/pdf')->and($pdfCard->back_media_file_id)->toBeNull();
});

it('asks every form for the front only', function () {
    $rules = IdentityVerificationAction::fileRules();
    expect($rules['id_front'])->toContain('required')
        ->and($rules['id_back'])->toContain('nullable')
        ->and($rules['id_back'])->not->toContain('required');

    foreach (['en', 'dv', 'ar'] as $locale) {
        expect(trans('account.id_learner_needed', [], $locale))->not->toBe('account.id_learner_needed');
    }
    expect(trans('account.id_back', [], 'en'))->toBe('Back (optional)');
});

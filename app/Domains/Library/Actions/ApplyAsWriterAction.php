<?php

namespace App\Domains\Library\Actions;

use App\Domains\Identity\Actions\IdentityVerificationAction;
use App\Domains\Library\Models\WriterApplication;
use App\Domains\Library\Models\WriterProfile;
use App\Domains\Media\Actions\StorePublicMediaAction;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

/**
 * L5 (§43.1/§43.2): writers cannot publish directly and must be approved —
 * the application is the front door. One pending application per user;
 * the §31 writer agreement must be accepted at submission time.
 *
 * B9 (§11.1): the applicant may add a portrait, their previous
 * publications and an identity document. The portrait is public media
 * (it becomes the author page's face at approval); the identity document
 * is private media the office opens from the queue and nobody else sees.
 */
class ApplyAsWriterAction
{
    /** @var list<string> */
    public const PHOTO_MIMES = ['image/jpeg', 'image/png', 'image/webp'];

    /** @var list<string> */
    public const ID_DOCUMENT_MIMES = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];

    /** An identity document is a scan or a photo; 8 MB is generous for either. */
    public const ID_DOCUMENT_MAX_BYTES = 8 * 1048576;

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(int $userId, array $data, ?UploadedFile $photo = null, ?UploadedFile $idFront = null, ?UploadedFile $idBack = null): WriterApplication
    {
        if (WriterProfile::query()->where('user_id', $userId)->exists()) {
            throw ValidationException::withMessages(['application' => 'You are already an approved writer.']);
        }
        if (WriterApplication::query()->where('user_id', $userId)->where('status', 'pending')->exists()) {
            throw ValidationException::withMessages(['application' => 'Your application is already pending review.']);
        }
        if (empty($data['agreement_accepted'])) {
            throw ValidationException::withMessages(['agreement_accepted' => 'You must accept the writer agreement.']);
        }

        $name = trim((string) $data['display_name']);
        $photoId = null;
        if ($photo !== null) {
            $photoId = app(StorePublicMediaAction::class)->execute($photo, $userId, self::PHOTO_MIMES, ['alt' => $name], 'writer-portraits')['id'];
        }
        // COMMERCE_PARITY_PLAN P2: both sides of the ID card, kept by Identity and
        // checked by the office (the old single `id_document` is read for old rows only).
        if ($idFront !== null) {
            app(IdentityVerificationAction::class)->submit($userId, 'writer', $idFront, $idBack);
        } elseif (IdentityVerificationAction::enforced()) {
            throw ValidationException::withMessages(['id_front' => __('validation.required', ['attribute' => __('account.id_front')])]);
        }

        $application = WriterApplication::query()->create([
            'user_id' => $userId,
            'display_name' => $name,
            'bio' => $data['bio'] ?? null,
            'qualifications' => $data['qualifications'] ?? null,
            'expertise' => $data['expertise'] ?? null,
            'motivation' => $data['motivation'] ?? null,
            'previous_publications' => $data['previous_publications'] ?? null,
            'photo_media_file_id' => $photoId,
            'agreement_accepted_at' => now(),
            'status' => 'pending',
        ]);

        // §41: the office hears there is an application to decide.
        app(NotifyLibraryUserAction::class)->office(
            'New writer application',
            $application->display_name.' applied to write for the library.',
            '/admin/library',
        );

        return $application;
    }
}

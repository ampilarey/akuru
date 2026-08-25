<?php

use App\Domains\Media\Actions\ReadPrivateMediaAction;
use App\Domains\Media\Actions\StorePrivateMediaAction;
use App\Domains\Media\Contracts\ImageProcessorInterface;
use App\Domains\Media\Jobs\ProcessMediaFileJob;
use App\Domains\Media\Models\MediaFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

it('stores private media on the local disk and queues processing', function () {
    Storage::fake('local');
    Queue::fake();
    $admin = actingPeopleAdmin(['courses.manage']);

    $stored = app(StorePrivateMediaAction::class)->execute(
        UploadedFile::fake()->image('diagram.jpg', 20, 20),
        $admin->id,
        ['image/jpeg'],
    );

    expect($stored['visibility'])->toBe('private')
        ->and($stored['process_status'])->toBe('pending')
        ->and($stored['original_name'])->toBe('diagram.jpg');

    $file = MediaFile::query()->findOrFail($stored['id']);
    expect($file->disk)->toBe('local')
        ->and($file->path)->not->toContain('storage/')
        ->and(Storage::disk('local')->exists($file->path))->toBeTrue();

    Queue::assertPushed(ProcessMediaFileJob::class, fn (ProcessMediaFileJob $job) => $job->mediaFileId === $file->id);

    (new ProcessMediaFileJob($file->id))->handle(app(ImageProcessorInterface::class));

    expect($file->fresh()->process_status)->toBe('processed')
        ->and($file->fresh()->processed_at)->not->toBeNull();

    $read = app(ReadPrivateMediaAction::class)->execute($file->id);
    expect($read['mime'])->toBe('image/jpeg')
        ->and($read['contents'])->not->toBe('');
});

it('rejects a mime that is not allowed for the upload', function () {
    Storage::fake('local');

    expect(fn () => app(StorePrivateMediaAction::class)->execute(
        UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf'),
        null,
        ['image/jpeg'],
    ))->toThrow(ValidationException::class);
});

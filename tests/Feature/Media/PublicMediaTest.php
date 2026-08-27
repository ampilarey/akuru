<?php

use App\Domains\Media\Actions\ListPublicMediaFilesAction;
use App\Domains\Media\Actions\StorePrivateMediaAction;
use App\Domains\Media\Actions\StorePublicMediaAction;
use App\Domains\Media\Models\MediaFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

it('stores public logos on the public disk without queuing private processing', function () {
    Storage::fake('public');
    Queue::fake();

    $stored = app(StorePublicMediaAction::class)->execute(
        UploadedFile::fake()->image('partner.png', 40, 20),
        null,
        [],
        ['alt' => 'Partner Ministry'],
    );

    expect($stored['visibility'])->toBe('public')
        ->and($stored['url'])->toContain('trust-logos/');

    $file = MediaFile::query()->findOrFail($stored['id']);
    expect($file->disk)->toBe('public')
        ->and($file->isPublic())->toBeTrue()
        ->and($file->process_status)->toBe('processed')
        ->and(Storage::disk('public')->exists($file->path))->toBeTrue();

    Queue::assertNothingPushed();
});

it('lists public media in the requested order and skips private ids', function () {
    Storage::fake('public');
    Storage::fake('local');
    Queue::fake();

    $first = app(StorePublicMediaAction::class)->execute(UploadedFile::fake()->image('a.png', 10, 10), null, [], ['alt' => 'Alpha']);
    $second = app(StorePublicMediaAction::class)->execute(UploadedFile::fake()->image('b.png', 10, 10), null, [], ['alt' => 'Beta']);
    $private = app(StorePrivateMediaAction::class)->execute(UploadedFile::fake()->image('secret.jpg', 10, 10));

    $listed = app(ListPublicMediaFilesAction::class)->execute([$second['id'], $private['id'], $first['id'], 0, 'nope']);

    expect($listed)->toHaveCount(2)
        ->and($listed[0]['id'])->toBe($second['id'])
        ->and($listed[0]['alt'])->toBe('Beta')
        ->and($listed[1]['id'])->toBe($first['id'])
        ->and($listed[1]['alt'])->toBe('Alpha');
});

it('rejects a mime that is not allowed for public media', function () {
    Storage::fake('public');

    expect(fn () => app(StorePublicMediaAction::class)->execute(
        UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf'),
    ))->toThrow(ValidationException::class);
});

it('stores an allowed PDF under a custom public directory', function () {
    Storage::fake('public');
    Queue::fake();

    $stored = app(StorePublicMediaAction::class)->execute(
        UploadedFile::fake()->create('paper.pdf', 20, 'application/pdf'),
        null,
        ['application/pdf'],
        ['alt' => 'W25 paper'],
        'research-pdfs',
    );

    expect($stored['visibility'])->toBe('public')
        ->and($stored['url'])->toContain('research-pdfs/')
        ->and($stored['url'])->not->toContain('trust-logos/');

    $file = MediaFile::query()->findOrFail($stored['id']);
    expect($file->path)->toStartWith('research-pdfs/')
        ->and(Storage::disk('public')->exists($file->path))->toBeTrue();
});

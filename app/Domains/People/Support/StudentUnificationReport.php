<?php

namespace App\Domains\People\Support;

use Illuminate\Support\Facades\File;

final class StudentUnificationReport
{
    public const FILENAME = 's11b-student-unification-report.json';

    /**
     * @param  array{user_id: int, national_id: int, name_dob: int, already_mapped: int}  $mapped
     * @param  array{active: int, prospective: int}  $created
     * @param  list<array<string, mixed>>  $ambiguous
     * @param  list<array<string, mixed>>  $collisions
     * @param  list<array<string, mixed>>  $unresolved
     * @param  array<string, mixed>  $guardians
     * @param  array<string, mixed>  $enrollments
     * @param  array<string, mixed>  $verification
     * @param  array{national_id_unusable_skips: int, national_id_contradiction_fallthroughs: int}  $matcher
     */
    public function __construct(
        public array $mapped = [
            'user_id' => 0,
            'national_id' => 0,
            'name_dob' => 0,
            'already_mapped' => 0,
        ],
        public array $created = [
            'active' => 0,
            'prospective' => 0,
        ],
        public array $ambiguous = [],
        public array $collisions = [],
        public array $unresolved = [],
        public array $guardians = [
            'source' => 0,
            'migrated' => 0,
            'created_profiles' => 0,
            'skipped_existing' => 0,
            'unmapped' => [],
        ],
        public array $enrollments = [
            'filled' => 0,
            'already_set' => 0,
            'missing' => [],
        ],
        public array $verification = [
            'ok' => false,
            'failures' => [],
        ],
        public array $matcher = [
            'national_id_unusable_skips' => 0,
            'national_id_contradiction_fallthroughs' => 0,
        ],
        public ?string $generatedAt = null,
        public ?string $path = null,
    ) {}

    public static function empty(): self
    {
        return new self;
    }

    public static function path(): string
    {
        return storage_path('app/'.self::FILENAME);
    }

    public static function load(): self
    {
        if (! File::exists(self::path())) {
            return self::empty();
        }

        /** @var array<string, mixed> $payload */
        $payload = json_decode(File::get(self::path()) ?: '{}', true) ?: [];

        return self::fromArray($payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        $empty = self::empty();

        return new self(
            mapped: $payload['mapped'] ?? $empty->mapped,
            created: $payload['created'] ?? $empty->created,
            ambiguous: $payload['ambiguous'] ?? [],
            collisions: $payload['collisions'] ?? [],
            unresolved: $payload['unresolved'] ?? [],
            guardians: $payload['guardians'] ?? $empty->guardians,
            enrollments: $payload['enrollments'] ?? $empty->enrollments,
            verification: $payload['verification'] ?? $empty->verification,
            matcher: $payload['matcher'] ?? $empty->matcher,
            generatedAt: $payload['generated_at'] ?? null,
            path: $payload['path'] ?? null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'generated_at' => $this->generatedAt,
            'path' => $this->path,
            'mapped' => $this->mapped,
            'created' => $this->created,
            'ambiguous' => $this->ambiguous,
            'collisions' => $this->collisions,
            'unresolved' => $this->unresolved,
            'guardians' => $this->guardians,
            'enrollments' => $this->enrollments,
            'verification' => $this->verification,
            'matcher' => $this->matcher,
        ];
    }

    public function write(): string
    {
        $this->generatedAt = now()->toIso8601String();
        $this->path = self::path();

        File::ensureDirectoryExists(dirname($this->path));
        File::put(
            $this->path,
            json_encode($this->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."\n"
        );

        return $this->path;
    }

    public function passed(): bool
    {
        if (array_key_exists('verdict', $this->verification)) {
            return ($this->verification['unexpected_failures'] ?? ['missing']) === [];
        }

        return ($this->verification['ok'] ?? false) === true;
    }
}

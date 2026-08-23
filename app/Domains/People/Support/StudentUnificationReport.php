<?php

namespace App\Domains\People\Support;

use Illuminate\Support\Facades\Storage;

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
     * @param  array{ok: bool, failures: list<string>}  $verification
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
        public ?string $generatedAt = null,
        public ?string $path = null,
    ) {}

    public static function empty(): self
    {
        return new self;
    }

    public static function load(): self
    {
        if (! Storage::disk('local')->exists(self::FILENAME)) {
            return self::empty();
        }

        /** @var array<string, mixed> $payload */
        $payload = json_decode(Storage::disk('local')->get(self::FILENAME) ?: '{}', true) ?: [];

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
        ];
    }

    public function write(): string
    {
        $this->generatedAt = now()->toIso8601String();
        $this->path = Storage::disk('local')->path(self::FILENAME);

        Storage::disk('local')->put(
            self::FILENAME,
            json_encode($this->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."\n"
        );

        return $this->path;
    }

    public function passed(): bool
    {
        return $this->verification['ok'] === true;
    }
}

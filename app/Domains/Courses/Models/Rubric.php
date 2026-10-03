<?php

namespace App\Domains\Courses\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A marking rubric (Moodle parity slice M2, STATUS §5oi): criteria, each with
 * levels worth points. `criteria` is a list of
 * `{id, title, levels: [{id, label, points}]}`; SaveRubricAction is the only
 * writer and keeps that shape.
 */
class Rubric extends Model
{
    protected $fillable = [
        'course_id',
        'title',
        'description',
        'criteria',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'criteria' => 'array',
        ];
    }

    /** The most a learner can get: the best level of every criterion. */
    public function maxPoints(): int
    {
        return (int) collect($this->criteria ?? [])
            ->sum(fn (array $criterion): int => (int) collect($criterion['levels'] ?? [])->max('points'));
    }

    /**
     * @return array{id: int, title: string, description: ?string, criteria: list<array<string, mixed>>, max_points: int}
     */
    public function present(): array
    {
        return [
            'id' => (int) $this->id,
            'title' => (string) $this->title,
            'description' => $this->description,
            'criteria' => array_values($this->criteria ?? []),
            'max_points' => $this->maxPoints(),
        ];
    }
}

<?php

namespace App\Domains\Academics\Actions;

use App\Domains\Academics\Models\TeachingMaterial;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The material library, searchable.
 *
 * Searchable is the whole point: a library you can only scroll is the
 * comma-separated string it replaces.
 */
class ListTeachingMaterialsAction
{
    /**
     * @param  array{q?: ?string, subject_id?: ?int, tag?: ?string, mine_for?: ?int, include_general?: bool, include_ids?: list<int>}  $filters
     * @return Collection<int, array<string, mixed>>
     */
    public function execute(array $filters = []): Collection
    {
        // Materials already attached to a lesson must appear in that lesson's
        // picker whatever the filters say. The picker syncs, so a hidden
        // attachment is an attachment silently deleted on the next save.
        $keep = array_values(array_unique(array_filter(
            array_map('intval', $filters['include_ids'] ?? []),
        )));

        $rows = TeachingMaterial::query()
            ->when($filters['subject_id'] ?? null, function ($query, $id) use ($filters) {
                $query->where(function ($q) use ($id, $filters) {
                    $q->where('subject_id', $id);
                    // A material with no subject is a general one — "class rules
                    // handout" belongs in every lesson, not none.
                    if ($filters['include_general'] ?? false) {
                        $q->orWhereNull('subject_id');
                    }
                });
            })
            ->when($filters['mine_for'] ?? null, fn ($q, $id) => $q->where('created_by', $id))
            ->when($filters['q'] ?? null, function ($query, string $term) {
                $like = '%'.$term.'%';
                $query->where(fn ($q) => $q->where('title', 'like', $like)->orWhere('body', 'like', $like));
            })
            ->when($keep !== [], fn ($q) => $q->orWhereIn('id', $keep))
            ->with(['subject', 'files'])
            ->orderBy('title')
            ->get();

        // Tags are JSON, so filtering them in SQL portably is more trouble than
        // it is worth at this size; the set is a school's worth of materials.
        if (($filters['tag'] ?? null) !== null && $filters['tag'] !== '') {
            $tag = mb_strtolower(trim((string) $filters['tag']));
            $rows = $rows->filter(fn (TeachingMaterial $m): bool => in_array($tag, $m->tags ?? [], true)
                || in_array((int) $m->id, $keep, true));
        }

        $authors = DB::table('users')
            ->whereIn('id', $rows->pluck('created_by')->unique())
            ->pluck('name', 'id');

        return $rows->map(fn (TeachingMaterial $m): array => [
            'id' => (int) $m->id,
            'title' => (string) $m->title,
            'body' => $m->body,
            'subject_id' => $m->subject_id ? (int) $m->subject_id : null,
            'subject' => $m->subject?->name,
            'tags' => $m->tags ?? [],
            'created_by' => (int) $m->created_by,
            'author' => $authors[$m->created_by] ?? 'Unknown',
            'files' => $m->files->map(fn ($file): array => [
                'id' => (int) $file->id,
                'name' => (string) $file->original_name,
                'mime' => (string) $file->mime,
                'size' => (int) $file->size,
            ])->values()->all(),
        ])->values();
    }
}

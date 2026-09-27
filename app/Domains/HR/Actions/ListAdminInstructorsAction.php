<?php

namespace App\Domains\HR\Actions;

use App\Domains\HR\Models\Instructor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;

/**
 * Instructors (docs/ADMIN_PANEL.md; C9 slice 3, STATUS §5je): the roster of
 * the instructors shown on the public website, as the system admin reads
 * and edits it. One query serves the screen and its CSV, so the download
 * cannot drift from the list it claims to copy.
 */
class ListAdminInstructorsAction
{
    public const PER_PAGE = 20;

    public function query(): Builder
    {
        return Instructor::withCount('courses')->ordered();
    }

    /**
     * @return array{instructors: list<array<string, mixed>>, pagination: array<string, mixed>, total: int}
     */
    public function execute(): array
    {
        $page = $this->query()->paginate(self::PER_PAGE)->withQueryString();

        return [
            'instructors' => collect($page->items())->map(fn (Instructor $row) => $this->row($row))->values()->all(),
            'pagination' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'prev' => $page->previousPageUrl(),
                'next' => $page->nextPageUrl(),
            ],
            'total' => $page->total(),
        ];
    }

    /**
     * The one instructor, as the form edits it.
     *
     * @return array<string, mixed>
     */
    public function one(Instructor $instructor): array
    {
        return $this->row($instructor);
    }

    /**
     * @return array<string, mixed>
     */
    private function row(Instructor $row): array
    {
        return [
            'id' => $row->id,
            'name' => $row->name,
            'bio' => $row->bio,
            'qualification' => $row->qualification,
            'specialization' => $row->specialization,
            'email' => $row->email,
            'phone' => $row->phone,
            'is_active' => (bool) $row->is_active,
            'sort_order' => (int) ($row->sort_order ?? 0),
            'courses_count' => (int) ($row->courses_count ?? $row->courses()->count()),
            'photo_url' => $row->photo ? Storage::disk('public')->url($row->photo) : null,
        ];
    }
}

<?php

namespace App\Domains\HR\Actions;

use App\Domains\HR\Models\Instructor;
use Illuminate\Support\Facades\Storage;

class ReadPublicInstructorProfileAction
{
    /**
     * @return array{id: int, slug: string, name: string, bio: ?string, photo_url: ?string, qualification: ?string, specialization: ?string, is_active: bool}|null
     */
    public function execute(?int $id = null, ?string $slug = null, bool $activeOnly = true): ?array
    {
        $query = Instructor::query();
        if ($activeOnly) {
            $query->where('is_active', true);
        }
        if ($id !== null && $id > 0) {
            $query->whereKey($id);
        } elseif ($slug !== null && $slug !== '') {
            $query->where('slug', $slug);
        } else {
            return null;
        }

        $row = $query->first();
        if ($row === null) {
            return null;
        }

        return $this->present($row);
    }

    /**
     * @return array{id: int, slug: string, name: string, bio: ?string, photo_url: ?string, qualification: ?string, specialization: ?string, is_active: bool}
     */
    public function present(Instructor $row): array
    {
        $photo = $row->photo ? Storage::disk('public')->url($row->photo) : null;

        return [
            'id' => (int) $row->id,
            'slug' => (string) $row->slug,
            'name' => (string) $row->name,
            'bio' => $row->bio,
            'photo_url' => $photo,
            'qualification' => $row->qualification,
            'specialization' => $row->specialization,
            'is_active' => (bool) $row->is_active,
        ];
    }
}

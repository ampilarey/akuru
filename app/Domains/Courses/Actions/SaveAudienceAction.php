<?php

namespace App\Domains\Courses\Actions;

use App\Domains\Courses\Models\Audience;
use Illuminate\Support\Str;

class SaveAudienceAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data, ?Audience $audience = null): Audience
    {
        $payload = [
            'name_en' => $data['name_en'],
            'name_dv' => $data['name_dv'] ?? null,
            'name_ar' => $data['name_ar'] ?? null,
            'slug' => $data['slug'] ?? Str::slug((string) $data['name_en']),
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'active' => array_key_exists('active', $data) ? (bool) $data['active'] : true,
        ];

        if ($audience === null) {
            return Audience::query()->create($payload);
        }

        $audience->fill($payload);
        $audience->save();

        return $audience->refresh();
    }
}

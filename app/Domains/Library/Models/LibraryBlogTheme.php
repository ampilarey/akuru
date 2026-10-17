<?php

namespace App\Domains\Library\Models;

use Illuminate\Database\Eloquent\Model;

/** BLOG_PLAN BL3a (§5, §8): one theme in the blog gallery, as data (STATUS §5si). */
class LibraryBlogTheme extends Model
{
    protected $fillable = ['key', 'name_en', 'name_dv', 'name_ar', 'theme', 'active', 'sort_order'];

    protected function casts(): array
    {
        return ['theme' => 'array', 'active' => 'boolean'];
    }

    /** The theme's name in the reader's language, English when it has none. */
    public function name(?string $locale = null): string
    {
        $locale ??= app()->getLocale();

        return (string) ($this->{'name_'.$locale} ?? $this->name_en);
    }
}

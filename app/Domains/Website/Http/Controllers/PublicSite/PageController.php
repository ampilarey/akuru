<?php

namespace App\Domains\Website\Http\Controllers\PublicSite;

use App\Domains\Website\Models\Page;
use App\Http\Controllers\Controller;

class PageController extends Controller
{
    public function show($slug)
    {
        $page = Page::where('slug', $slug)
            ->where('is_published', true)
            ->firstOrFail();

        return view('public.page.show', compact('page'));
    }
}

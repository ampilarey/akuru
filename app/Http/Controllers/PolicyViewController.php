<?php

namespace App\Http\Controllers;

use App\Models\Page;
use Illuminate\View\View;

/**
 * Serves compliance policy pages. Uses CMS Page if slug exists and is published, else static view.
 */
class PolicyViewController extends Controller
{
    public function terms(): View
    {
        return $this->pageOrView('terms', 'policy.terms');
    }

    public function privacy(): View
    {
        return $this->pageOrView('privacy-policy', 'policy.privacy');
    }

    public function refunds(): View
    {
        return $this->pageOrView('refunds', 'policy.refunds');
    }

    public function services(): View
    {
        return $this->pageOrView('services', 'policy.services');
    }

    private function pageOrView(string $slug, string $viewName): View
    {
        $page = Page::where('slug', $slug)->where('is_published', true)->first();
        if ($page) {
            return view('public.page.show', ['page' => $page]);
        }
        if (view()->exists($viewName)) {
            return view($viewName);
        }
        return view('policy.placeholder', ['slug' => $slug]);
    }
}

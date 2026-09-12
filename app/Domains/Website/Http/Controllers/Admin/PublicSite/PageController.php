<?php

namespace App\Domains\Website\Http\Controllers\Admin\PublicSite;

use App\Domains\Website\Models\Page;
use App\Http\Controllers\Controller;
use App\Support\Html\HtmlSanitizer;
use Illuminate\Http\Request;

class PageController extends Controller
{
    public function index()
    {
        $pages = Page::orderBy('title')->paginate(15);

        return view('admin.public-site.pages.index', compact('pages'));
    }

    public function create()
    {
        return view('admin.public-site.pages.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:pages',
            'excerpt' => 'nullable|string',
            'body' => 'required|string',
            'cover_image' => 'nullable|string|max:255',
            'is_published' => 'boolean',
            'published_at' => 'nullable|date',
        ]);

        // Authored HTML is rendered raw on the public site
        // (`public/page/show.blade.php` uses `{!! $page->body !!}`), and this
        // path validated it as `string` and nothing more. Sanitised on write so
        // the stored value is the safe one — every reader of it is then safe,
        // rather than each render site having to remember.
        $validated['body'] = app(HtmlSanitizer::class)
            ->clean($validated['body'], HtmlSanitizer::PROFILE_CMS);

        $validated['is_published'] = $request->has('is_published');
        $validated['published_at'] = $validated['is_published'] ? ($validated['published_at'] ?? now()) : null;

        Page::create($validated);

        return redirect()->route('admin.pages.index')
            ->with('success', 'Page created successfully.');
    }

    public function show(Page $page)
    {
        return view('admin.public-site.pages.show', compact('page'));
    }

    public function edit(Page $page)
    {
        return view('admin.public-site.pages.edit', compact('page'));
    }

    public function update(Request $request, Page $page)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:pages,slug,'.$page->id,
            'excerpt' => 'nullable|string',
            'body' => 'required|string',
            'cover_image' => 'nullable|string|max:255',
            'is_published' => 'boolean',
            'published_at' => 'nullable|date',
        ]);

        $validated['body'] = app(HtmlSanitizer::class)
            ->clean($validated['body'], HtmlSanitizer::PROFILE_CMS);

        $validated['is_published'] = $request->has('is_published');
        $validated['published_at'] = $validated['is_published'] ? ($validated['published_at'] ?? now()) : null;

        $page->update($validated);

        return redirect()->route('admin.pages.index')
            ->with('success', 'Page updated successfully.');
    }

    public function destroy(Page $page)
    {
        $page->delete();

        return redirect()->route('admin.pages.index')
            ->with('success', 'Page deleted successfully.');
    }
}

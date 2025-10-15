<?php

namespace App\Http\Controllers\PublicSite;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class GalleryController extends Controller
{
    public function index()
    {
        // For now, return empty collection until MediaGallery integration
        $galleries = collect();
        
        // If MediaGallery model exists, use it
        if (class_exists(\App\Models\MediaGallery::class)) {
            $galleries = \App\Models\MediaGallery::with('items')
                ->where('is_public', true)
                ->latest()
                ->paginate(12);
        }

        return view('public.gallery.index', compact('galleries'));
    }

    public function show($id)
    {
        // For now, return 404 until MediaGallery integration
        if (!class_exists(\App\Models\MediaGallery::class)) {
            abort(404);
        }

        $gallery = \App\Models\MediaGallery::with('items')
            ->where('is_public', true)
            ->findOrFail($id);

        return view('public.gallery.show', compact('gallery'));
    }
}
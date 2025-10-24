<?php

namespace App\Http\Controllers\PublicSite;

use App\Http\Controllers\Controller;
use App\Models\{GalleryAlbum, GalleryItem};
use Illuminate\Http\Request;

class GalleryController extends Controller
{
    public function index(Request $request)
    {
        $query = GalleryAlbum::published()->public()->with(['items' => function($q) {
            $q->public()->ordered()->limit(4);
        }]);
        
        // Filter by type
        if ($request->filled('type')) {
            $query->byType($request->type);
        }
        
        // Filter by featured
        if ($request->filled('featured')) {
            $query->featured();
        }
        
        // Search functionality
        if ($request->filled('search')) {
            $searchTerm = $request->search;
            $query->where(function($q) use ($searchTerm) {
                $q->where('title', 'like', "%{$searchTerm}%")
                  ->orWhere('description', 'like', "%{$searchTerm}%");
            });
        }
        
        // Sort options
        $sortBy = $request->get('sort', 'recent');
        switch ($sortBy) {
            case 'title':
                $query->orderBy('title');
                break;
            case 'type':
                $query->orderBy('type')->orderBy('title');
                break;
            case 'featured':
                $query->orderBy('is_featured', 'desc')->orderBy('title');
                break;
            case 'items':
                $query->orderBy('item_count', 'desc');
                break;
            default:
                $query->orderBy('created_at', 'desc');
        }
        
        $albums = $query->paginate(12)->withQueryString();
        
        // Get featured albums for sidebar
        $featuredAlbums = GalleryAlbum::published()
                                     ->public()
                                     ->featured()
                                     ->with(['items' => function($q) {
                                         $q->public()->ordered()->limit(1);
                                     }])
                                     ->take(6)
                                     ->get();
        
        // Get recent albums for sidebar
        $recentAlbums = GalleryAlbum::published()
                                   ->public()
                                   ->with(['items' => function($q) {
                                       $q->public()->ordered()->limit(1);
                                   }])
                                   ->orderBy('created_at', 'desc')
                                   ->take(6)
                                   ->get();
        
        // Get album types for filter
        $albumTypes = GalleryAlbum::published()
                                 ->public()
                                 ->select('type')
                                 ->distinct()
                                 ->pluck('type')
                                 ->sort();
        
        return view('public.gallery.index', compact('featuredAlbums', 'recentAlbums', 'albumTypes'))->with('galleries', $albums);
    }

    public function show(GalleryAlbum $album)
    {
        // Ensure album is published and public
        if ($album->status !== 'published' || !$album->is_public) {
            abort(404);
        }
        
        // Load items with pagination
        $items = $album->publicItems()
                       ->ordered()
                       ->paginate(20)
                       ->withQueryString();
        
        // Get featured items for sidebar
        $featuredItems = GalleryItem::public()
                                   ->featured()
                                   ->where('gallery_album_id', '!=', $album->id)
                                   ->with('album')
                                   ->take(6)
                                   ->get();
        
        // Get recent albums for sidebar
        $recentAlbums = GalleryAlbum::published()
                                   ->public()
                                   ->where('id', '!=', $album->id)
                                   ->with(['items' => function($q) {
                                       $q->public()->ordered()->limit(1);
                                   }])
                                   ->orderBy('created_at', 'desc')
                                   ->take(6)
                                   ->get();
        
        return view('public.gallery.show', compact('album', 'items', 'featuredItems', 'recentAlbums'));
    }

    public function item(GalleryItem $item)
    {
        // Ensure item is public
        if (!$item->is_public) {
            abort(404);
        }
        
        // Load album
        $item->load('album');
        
        // Increment view count
        $item->incrementViewCount();
        
        // Get related items from the same album
        $relatedItems = $item->getRelatedItems(8);
        
        // Get featured items for sidebar
        $featuredItems = GalleryItem::public()
                                   ->featured()
                                   ->where('id', '!=', $item->id)
                                   ->with('album')
                                   ->take(6)
                                   ->get();
        
        // Get recent albums for sidebar
        $recentAlbums = GalleryAlbum::published()
                                   ->public()
                                   ->with(['items' => function($q) {
                                       $q->public()->ordered()->limit(1);
                                   }])
                                   ->orderBy('created_at', 'desc')
                                   ->take(6)
                                   ->get();
        
        return view('public.gallery.item', compact('item', 'relatedItems', 'featuredItems', 'recentAlbums'));
    }

    public function download(GalleryItem $item)
    {
        // Ensure item is public
        if (!$item->is_public) {
            abort(404);
        }
        
        // Increment download count
        $item->incrementDownloadCount();
        
        // Return file download
        $filePath = storage_path('app/public/' . $item->file_path);
        
        if (!file_exists($filePath)) {
            abort(404, 'File not found');
        }
        
        return response()->download($filePath, $item->title . '.' . pathinfo($item->file_path, PATHINFO_EXTENSION));
    }

    public function search(Request $request)
    {
        $query = $request->get('q');
        
        if (!$query) {
            return redirect()->route('public.gallery.index');
        }
        
        // Search albums
        $albums = GalleryAlbum::published()
                             ->public()
                             ->where(function($q) use ($query) {
                                 $q->where('title', 'like', "%{$query}%")
                                   ->orWhere('description', 'like', "%{$query}%");
                             })
                             ->with(['items' => function($q) {
                                 $q->public()->ordered()->limit(4);
                             }])
                             ->paginate(12)
                             ->withQueryString();
        
        // Search items
        $items = GalleryItem::public()
                           ->where(function($q) use ($query) {
                               $q->where('title', 'like', "%{$query}%")
                                 ->orWhere('description', 'like', "%{$query}%")
                                 ->orWhere('caption', 'like', "%{$query}%");
                           })
                           ->with('album')
                           ->paginate(20)
                           ->withQueryString();
        
        // Get featured albums for sidebar
        $featuredAlbums = GalleryAlbum::published()
                                     ->public()
                                     ->featured()
                                     ->with(['items' => function($q) {
                                         $q->public()->ordered()->limit(1);
                                     }])
                                     ->take(6)
                                     ->get();
        
        // Get recent albums for sidebar
        $recentAlbums = GalleryAlbum::published()
                                   ->public()
                                   ->with(['items' => function($q) {
                                       $q->public()->ordered()->limit(1);
                                   }])
                                   ->orderBy('created_at', 'desc')
                                   ->take(6)
                                   ->get();
        
        return view('public.gallery.search', compact('query', 'albums', 'items', 'featuredAlbums', 'recentAlbums'));
    }
}
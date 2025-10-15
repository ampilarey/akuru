<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Announcement;
use App\Models\School;

class AnnouncementController extends Controller
{
    public function index()
    {
        $announcements = Announcement::with('createdBy')
            ->where('is_published', true)
            ->where('publish_date', '<=', now())
            ->where(function($query) {
                $query->whereNull('expiry_date')
                      ->orWhere('expiry_date', '>=', now());
            })
            ->latest()
            ->get();
            
        return view('announcements.index', compact('announcements'));
    }
    
    public function create()
    {
        return view('announcements.create');
    }
    
    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'title_arabic' => 'nullable|string|max:255',
            'title_dhivehi' => 'nullable|string|max:255',
            'content' => 'required|string',
            'content_arabic' => 'nullable|string',
            'content_dhivehi' => 'nullable|string',
            'type' => 'required|in:general,academic,quran,event,holiday,emergency',
            'priority' => 'required|in:low,medium,high,urgent',
            'target_audience' => 'nullable|array',
            'target_classes' => 'nullable|array',
            'publish_date' => 'required|date',
            'expiry_date' => 'nullable|date|after:publish_date',
        ]);
        
        $announcement = Announcement::create([
            'school_id' => 1, // Assuming single school
            'created_by' => auth()->id(),
            'title' => $request->title,
            'title_arabic' => $request->title_arabic,
            'title_dhivehi' => $request->title_dhivehi,
            'content' => $request->content,
            'content_arabic' => $request->content_arabic,
            'content_dhivehi' => $request->content_dhivehi,
            'type' => $request->type,
            'priority' => $request->priority,
            'target_audience' => $request->target_audience,
            'target_classes' => $request->target_classes,
            'publish_date' => $request->publish_date,
            'expiry_date' => $request->expiry_date,
            'is_published' => true,
        ]);
        
        return redirect()->route('announcements.index')
            ->with('success', 'Announcement created successfully!');
    }
    
    public function show(Announcement $announcement)
    {
        return view('announcements.show', compact('announcement'));
    }
}

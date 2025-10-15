<?php

namespace App\Http\Controllers\PublicSite;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class EventController extends Controller
{
    public function index()
    {
        // For now, return empty collection until CalendarEvent integration
        $events = collect();
        
        // If CalendarEvent model exists, use it
        if (class_exists(\App\Models\CalendarEvent::class)) {
            $events = \App\Models\CalendarEvent::where('audience', 'public')
                ->where('date', '>=', now())
                ->orderBy('date')
                ->paginate(12);
        }

        return view('public.events.index', compact('events'));
    }

    public function show($id)
    {
        // For now, return 404 until CalendarEvent integration
        if (!class_exists(\App\Models\CalendarEvent::class)) {
            abort(404);
        }

        $event = \App\Models\CalendarEvent::where('audience', 'public')
            ->findOrFail($id);

        return view('public.events.show', compact('event'));
    }
}
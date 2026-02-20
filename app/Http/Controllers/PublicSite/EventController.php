<?php

namespace App\Http\Controllers\PublicSite;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventRegistration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class EventController extends Controller
{
    public function index(Request $request)
    {
        $query = Event::published()->public();
        
        // Filter by type
        if ($request->filled('type')) {
            $query->byType($request->type);
        }
        
        // Filter by location
        if ($request->filled('location')) {
            $query->byLocation($request->location);
        }
        
        // Filter by date range
        if ($request->filled('date_from')) {
            $query->where('start_date', '>=', $request->date_from);
        }
        
        if ($request->filled('date_to')) {
            $query->where('start_date', '<=', $request->date_to);
        }
        
        // Filter by registration type
        if ($request->filled('registration')) {
            if ($request->registration === 'required') {
                $query->where('registration_type', 'required');
            } elseif ($request->registration === 'optional') {
                $query->where('registration_type', 'optional');
            } elseif ($request->registration === 'none') {
                $query->where('registration_type', 'none');
            }
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
                  ->orWhere('description', 'like', "%{$searchTerm}%")
                  ->orWhere('location', 'like', "%{$searchTerm}%");
            });
        }
        
        // Sort options
        $sortBy = $request->get('sort', 'start_date');
        switch ($sortBy) {
            case 'title':
                $query->orderBy('title');
                break;
            case 'type':
                $query->orderBy('type')->orderBy('start_date');
                break;
            case 'location':
                $query->orderBy('location')->orderBy('start_date');
                break;
            case 'featured':
                $query->orderBy('is_featured', 'desc')->orderBy('start_date');
                break;
            default:
                $query->orderBy('start_date');
        }
        
        $events = $query->paginate(12)->withQueryString();
        
        // Get featured events for sidebar
        $featuredEvents = Event::published()
                              ->public()
                              ->featured()
                              ->upcoming()
                              ->take(3)
                              ->get();
        
        // Get event types for filter
        $eventTypes = Event::published()
                          ->public()
                          ->select('type')
                          ->distinct()
                          ->pluck('type')
                          ->sort();
        
        // Get locations for filter
        $locations = Event::published()
                         ->public()
                         ->select('location')
                         ->distinct()
                         ->pluck('location')
                         ->sort();
        
        return view('public.events.index', compact('events', 'featuredEvents', 'eventTypes', 'locations'));
    }

    public function show(Event $event)
    {
        // Ensure event is published and public
        if ($event->status !== 'published' || !$event->is_public) {
            abort(404);
        }
        
        // Load related data
        $event->load('registrations');
        
        // Get related events
        $relatedEvents = Event::published()
                             ->public()
                             ->where('id', '!=', $event->id)
                             ->where('type', $event->type)
                             ->upcoming()
                             ->take(3)
                             ->get();
        
        // Get featured events for sidebar
        $featuredEvents = Event::published()
                              ->public()
                              ->featured()
                              ->where('id', '!=', $event->id)
                              ->upcoming()
                              ->take(3)
                              ->get();
        
        // Get registration stats
        $registrationStats = $event->getRegistrationStats();
        
        return view('public.events.show', compact('event', 'relatedEvents', 'featuredEvents', 'registrationStats'));
    }

    public function register(Request $request, Event $event)
    {
        // Validate event can accept registrations
        if (!$event->canRegister()) {
            return back()->with('error', 'Registration is not available for this event.');
        }
        
        // Validate registration data
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:20',
            'organization' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:1000',
            'dietary_requirements' => 'boolean',
            'dietary_notes' => 'nullable|string|max:500',
            'transportation_needed' => 'boolean',
            'transportation_notes' => 'nullable|string|max:500',
            'accommodation_needed' => 'boolean',
            'accommodation_notes' => 'nullable|string|max:500',
        ]);
        
        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }
        
        // Check if email already registered for this event
        $existingRegistration = EventRegistration::where('event_id', $event->id)
                                                ->where('email', $request->email)
                                                ->whereIn('status', ['pending', 'confirmed'])
                                                ->first();
        
        if ($existingRegistration) {
            return back()->with('error', 'You are already registered for this event.');
        }
        
        // Check if event is full
        if ($event->is_full) {
            return back()->with('error', 'This event is full. Please try another event.');
        }
        
        // Create registration
        $registration = EventRegistration::create([
            'event_id' => $event->id,
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'organization' => $request->organization,
            'notes' => $request->notes,
            'dietary_requirements' => $request->boolean('dietary_requirements'),
            'dietary_notes' => $request->dietary_notes,
            'transportation_needed' => $request->boolean('transportation_needed'),
            'transportation_notes' => $request->transportation_notes,
            'accommodation_needed' => $request->boolean('accommodation_needed'),
            'accommodation_notes' => $request->accommodation_notes,
            'registration_source' => 'website',
            'status' => $event->registration_type === 'required' ? 'pending' : 'confirmed',
            'amount_paid' => $event->registration_fee ?? 0,
        ]);
        
        // Auto-confirm if registration is optional
        if ($event->registration_type === 'optional') {
            $registration->confirm();
        }
        
        // Generate QR code
        $registration->generateQrCode();
        
        // Update event attendee count
        $event->updateAttendeeCount();
        
        return redirect()->route('public.events.registration.success', $registration->id)
                        ->with('success', 'Registration submitted successfully!');
    }

    public function registrationSuccess(EventRegistration $registration)
    {
        return view('public.events.registration-success', compact('registration'));
    }

    public function qrCode($qrCode)
    {
        $registration = EventRegistration::where('qr_code', $qrCode)->firstOrFail();
        
        return view('public.events.qr-code', compact('registration'));
    }

    public function downloadCalendar(Event $event)
    {
        if ($event->status !== 'published' || !$event->is_public) {
            abort(404);
        }

        $start = \Carbon\Carbon::parse($event->start_date);
        $end   = $event->end_date ? \Carbon\Carbon::parse($event->end_date) : $start->copy()->addHours(2);

        $summary     = addcslashes(strip_tags($event->title), ',;\\');
        $description = addcslashes(strip_tags($event->short_description ?? $event->description ?? ''), ',;\\');
        $location    = addcslashes($event->location ?? '', ',;\\');
        $uid         = 'event-' . $event->id . '@akuru.edu.mv';
        $url         = route('public.events.show', $event->slug);

        $ics = implode("\r\n", [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Akuru Institute//EN',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'BEGIN:VEVENT',
            'UID:' . $uid,
            'DTSTAMP:' . now()->format('Ymd\THis\Z'),
            'DTSTART:' . $start->format('Ymd\THis'),
            'DTEND:'   . $end->format('Ymd\THis'),
            'SUMMARY:' . $summary,
            'DESCRIPTION:' . $description,
            'LOCATION:' . $location,
            'URL:' . $url,
            'END:VEVENT',
            'END:VCALENDAR',
        ]);

        $filename = \Str::slug($event->title) . '.ics';

        return response($ics, 200, [
            'Content-Type'        => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    public function calendar()
    {
        $events = Event::published()
                      ->public()
                      ->upcoming()
                      ->orderBy('start_date')
                      ->get();
        
        return view('public.events.calendar', compact('events'));
    }
}
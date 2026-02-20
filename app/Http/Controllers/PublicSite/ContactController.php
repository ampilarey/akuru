<?php

namespace App\Http\Controllers\PublicSite;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use App\Notifications\NewContactMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;

class ContactController extends Controller
{
    public function create()
    {
        return view('public.contact.create');
    }
    
    public function store(Request $request)
    {
        // Honeypot check — bots fill the hidden "website" field, humans leave it empty
        if ($request->filled('website')) {
            return back()->with('success', __('public.contact_message_sent'));
        }

        // Rate limit: max 5 contact messages per IP per 10 minutes
        $rateLimitKey = 'contact.' . $request->ip();
        if (RateLimiter::tooManyAttempts($rateLimitKey, 5)) {
            $seconds = RateLimiter::availableIn($rateLimitKey);
            return back()->withErrors(['message' => "Too many messages. Please wait {$seconds} seconds before trying again."]);
        }
        RateLimiter::hit($rateLimitKey, 600);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:20',
            'message' => 'required|string|max:2000',
        ]);
        
        $validated['ip'] = $request->ip();
        $validated['user_agent'] = $request->userAgent();
        
        $contactMessage = ContactMessage::create($validated);
        
        // Notify administrators
        $adminUsers = \App\Models\User::role('admin')->get();
        if ($adminUsers->count() > 0) {
            Notification::send($adminUsers, new NewContactMessage($contactMessage));
        }
        
        return back()->with('success', __('public.contact_message_sent'));
    }
}

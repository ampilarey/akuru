<?php

namespace App\Http\Controllers\PublicSite;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use App\Notifications\NewContactMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;

class ContactController extends Controller
{
    public function create()
    {
        return view('public.contact.create');
    }
    
    public function store(Request $request)
    {
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

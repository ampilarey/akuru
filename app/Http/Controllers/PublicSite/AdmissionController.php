<?php

namespace App\Http\Controllers\PublicSite;

use App\Http\Controllers\Controller;
use App\Models\{AdmissionApplication, Course};
use App\Notifications\NewAdmissionApplication;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;

class AdmissionController extends Controller
{
    public function create(Request $request)
    {
        $courses = Course::open()
            ->with('category')
            ->orderBy('title')
            ->get();
        
        $selectedCourse = null;
        if ($request->filled('course')) {
            $selectedCourse = Course::where('slug', $request->course)->first();
        }
        
        return view('public.admissions.create', compact('courses', 'selectedCourse'));
    }
    
    public function store(Request $request)
    {
        $validated = $request->validate([
            'course_id' => 'nullable|exists:courses,id',
            'full_name' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
            'email' => 'nullable|email|max:255',
            'guardian_name' => 'nullable|string|max:255',
            'message' => 'nullable|string|max:1000',
            'source' => 'in:web,social,whatsapp,other',
        ]);
        
        $validated['locale'] = app()->getLocale();
        $validated['ip'] = $request->ip();
        $validated['user_agent'] = $request->userAgent();
        $validated['source'] = $validated['source'] ?? 'web';
        
        $application = AdmissionApplication::create($validated);
        
        // Notify administrators
        $adminUsers = \App\Models\User::role('admin')->get();
        if ($adminUsers->count() > 0) {
            Notification::send($adminUsers, new NewAdmissionApplication($application));
        }
        
        return redirect()->route('public.admissions.thanks', app()->getLocale())
                        ->with('success', __('public.admission_submitted'));
    }
    
    public function thanks()
    {
        return view('public.admissions.thanks');
    }
}

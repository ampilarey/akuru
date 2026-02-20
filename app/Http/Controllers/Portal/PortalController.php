<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\{CourseEnrollment, Payment};
use Illuminate\Http\Request;

class PortalController extends Controller
{
    public function dashboard(Request $request)
    {
        $user = $request->user();

        $enrollments = CourseEnrollment::with(['course', 'student', 'payment'])
            ->where('created_by_user_id', $user->id)
            ->latest()
            ->take(5)
            ->get();

        $recentPayments = Payment::where('user_id', $user->id)
            ->whereIn('status', ['paid', 'completed'])
            ->with('items.course')
            ->latest()
            ->take(5)
            ->get();

        $activeEnrollments  = CourseEnrollment::where('created_by_user_id', $user->id)->where('status', 'active')->count();
        $pendingEnrollments = CourseEnrollment::where('created_by_user_id', $user->id)->whereIn('status', ['pending', 'pending_payment'])->count();

        return view('portal.dashboard', compact('user', 'enrollments', 'recentPayments', 'activeEnrollments', 'pendingEnrollments'));
    }

    public function enrollments(Request $request)
    {
        $user = $request->user();

        $enrollments = CourseEnrollment::with(['course', 'student', 'payment'])
            ->where('created_by_user_id', $user->id)
            ->latest()
            ->get()
            ->groupBy('status');

        return view('portal.enrollments', compact('user', 'enrollments'));
    }

    public function payments(Request $request)
    {
        $user = $request->user();

        $payments = Payment::where('user_id', $user->id)
            ->with(['items.course', 'items.enrollment.student'])
            ->latest()
            ->paginate(15);

        return view('portal.payments', compact('user', 'payments'));
    }

    public function certificates(Request $request)
    {
        $user = $request->user();

        // Placeholder — certificates will be generated when LMS is integrated
        $activeEnrollments = CourseEnrollment::with('course')
            ->where('created_by_user_id', $user->id)
            ->where('status', 'active')
            ->get();

        return view('portal.certificates', compact('user', 'activeEnrollments'));
    }

    public function profile(Request $request)
    {
        $user = $request->user();
        $contacts = $user->contacts()->get()->keyBy('type');
        return view('portal.profile', compact('user', 'contacts'));
    }

    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'name'     => 'required|string|max:255',
            'password' => 'nullable|string|min:8|confirmed',
        ]);

        $user->name = $request->input('name');
        if ($request->filled('password')) {
            $user->password = \Hash::make($request->input('password'));
        }
        $user->save();

        return redirect()->route('portal.profile')->with('success', 'Profile updated successfully.');
    }
}

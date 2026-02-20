<?php

namespace App\Http\Controllers;

use App\Models\CourseEnrollment;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MyEnrollmentsController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $enrollments = CourseEnrollment::with(['course', 'student', 'payment'])
            ->where('created_by_user_id', $user->id)
            ->orderByDesc('created_at')
            ->get();

        return view('my-enrollments.index', compact('enrollments'));
    }
}

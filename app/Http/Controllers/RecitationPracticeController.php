<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\RecitationPractice;
use App\Models\TajweedFeedback;
use App\Models\Surah;
use App\Models\Student;
use Illuminate\Support\Facades\Storage;

class RecitationPracticeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $user = auth()->user();
        
        if ($user->hasRole('teacher') || $user->hasRole('admin') || $user->hasRole('headmaster')) {
            // Teachers and admins can see all practices
            $practices = RecitationPractice::with(['student', 'surah', 'evaluator', 'tajweedFeedback'])
                ->latest()
                ->paginate(20);
        } else {
            // Students can only see their own practices
            $practices = RecitationPractice::with(['surah', 'evaluator', 'tajweedFeedback'])
                ->where('student_id', $user->student->id)
                ->latest()
                ->paginate(20);
        }

        return view('recitation-practices.index', compact('practices'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $surahs = Surah::active()->ordered()->get();
        return view('recitation-practices.create', compact('surahs'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'surah_id' => 'required|exists:surahs,id',
            'ayah_from' => 'required|integer|min:1',
            'ayah_to' => 'required|integer|min:1|gte:ayah_from',
            'audio_file' => 'nullable|file|mimes:mp3,wav,m4a|max:10240', // 10MB max
        ]);

        $student = auth()->user()->student;
        if (!$student) {
            return redirect()->back()->with('error', 'Student profile not found.');
        }

        $data = $request->only(['surah_id', 'ayah_from', 'ayah_to']);
        $data['student_id'] = $student->id;

        // Handle audio file upload
        if ($request->hasFile('audio_file')) {
            $audioFile = $request->file('audio_file');
            $filename = 'recitation_' . $student->id . '_' . time() . '.' . $audioFile->getClientOriginalExtension();
            $path = $audioFile->storeAs('recitations', $filename, 'public');
            $data['audio_path'] = $path;
        }

        RecitationPractice::create($data);

        return redirect()->route('recitation-practices.index')
            ->with('success', 'Recitation practice submitted successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(RecitationPractice $recitationPractice)
    {
        $recitationPractice->load(['student', 'surah', 'evaluator', 'tajweedFeedback']);
        
        // Check if user can view this practice
        $user = auth()->user();
        if ($user->hasRole('student') && $recitationPractice->student_id !== $user->student->id) {
            abort(403, 'Unauthorized access.');
        }

        return view('recitation-practices.show', compact('recitationPractice'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(RecitationPractice $recitationPractice)
    {
        $surahs = Surah::active()->ordered()->get();
        return view('recitation-practices.edit', compact('recitationPractice', 'surahs'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, RecitationPractice $recitationPractice)
    {
        $request->validate([
            'surah_id' => 'required|exists:surahs,id',
            'ayah_from' => 'required|integer|min:1',
            'ayah_to' => 'required|integer|min:1|gte:ayah_from',
            'audio_file' => 'nullable|file|mimes:mp3,wav,m4a|max:10240',
        ]);

        $data = $request->only(['surah_id', 'ayah_from', 'ayah_to']);

        // Handle audio file upload
        if ($request->hasFile('audio_file')) {
            // Delete old audio file
            if ($recitationPractice->audio_path) {
                Storage::disk('public')->delete($recitationPractice->audio_path);
            }

            $audioFile = $request->file('audio_file');
            $filename = 'recitation_' . $recitationPractice->student_id . '_' . time() . '.' . $audioFile->getClientOriginalExtension();
            $path = $audioFile->storeAs('recitations', $filename, 'public');
            $data['audio_path'] = $path;
        }

        $recitationPractice->update($data);

        return redirect()->route('recitation-practices.index')
            ->with('success', 'Recitation practice updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(RecitationPractice $recitationPractice)
    {
        // Delete audio file if exists
        if ($recitationPractice->audio_path) {
            Storage::disk('public')->delete($recitationPractice->audio_path);
        }

        $recitationPractice->delete();

        return redirect()->route('recitation-practices.index')
            ->with('success', 'Recitation practice deleted successfully.');
    }

    /**
     * Evaluate a recitation practice
     */
    public function evaluate(Request $request, RecitationPractice $recitationPractice)
    {
        $request->validate([
            'accuracy_score' => 'required|integer|min:0|max:100',
            'tajweed_score' => 'required|integer|min:0|max:100',
            'fluency_score' => 'required|integer|min:0|max:100',
            'teacher_feedback' => 'nullable|string|max:1000',
            'status' => 'required|in:pending,evaluated,approved,needs_revision',
        ]);

        $data = $request->only([
            'accuracy_score', 'tajweed_score', 'fluency_score', 
            'teacher_feedback', 'status'
        ]);
        $data['evaluated_by'] = auth()->id();
        $data['evaluated_at'] = now();

        $recitationPractice->update($data);

        return redirect()->route('recitation-practices.show', $recitationPractice)
            ->with('success', 'Recitation practice evaluated successfully.');
    }

    /**
     * Add tajweed feedback
     */
    public function addTajweedFeedback(Request $request, RecitationPractice $recitationPractice)
    {
        $request->validate([
            'rule_name' => 'required|string|max:255',
            'comment' => 'required|string|max:1000',
            'severity' => 'required|in:info,warning,critical',
            'ayah_number' => 'nullable|integer|min:1',
            'word_position' => 'nullable|string|max:255',
        ]);

        $recitationPractice->tajweedFeedback()->create($request->all());

        return redirect()->route('recitation-practices.show', $recitationPractice)
            ->with('success', 'Tajweed feedback added successfully.');
    }
}
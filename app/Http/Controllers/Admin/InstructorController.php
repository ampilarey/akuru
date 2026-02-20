<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Instructor;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class InstructorController extends Controller
{
    public function index()
    {
        $instructors = Instructor::withCount('courses')->ordered()->paginate(20);
        return view('admin.instructors.index', compact('instructors'));
    }

    public function create()
    {
        return view('admin.instructors.form', ['instructor' => new Instructor]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'           => 'required|string|max:255',
            'bio'            => 'nullable|string',
            'qualification'  => 'nullable|string|max:255',
            'specialization' => 'nullable|string|max:255',
            'email'          => 'nullable|email|max:255',
            'phone'          => 'nullable|string|max:30',
            'is_active'      => 'boolean',
            'sort_order'     => 'integer|min:0',
            'photo'          => 'nullable|image|max:2048',
        ]);

        $data['slug'] = Str::slug($data['name']);
        $data['is_active'] = $request->boolean('is_active', true);

        if ($request->hasFile('photo')) {
            $data['photo'] = $request->file('photo')->store('instructors', 'public');
        }

        Instructor::create($data);

        return redirect()->route('admin.instructors.index')
            ->with('success', 'Instructor created.');
    }

    public function edit(Instructor $instructor)
    {
        return view('admin.instructors.form', compact('instructor'));
    }

    public function update(Request $request, Instructor $instructor)
    {
        $data = $request->validate([
            'name'           => 'required|string|max:255',
            'bio'            => 'nullable|string',
            'qualification'  => 'nullable|string|max:255',
            'specialization' => 'nullable|string|max:255',
            'email'          => 'nullable|email|max:255',
            'phone'          => 'nullable|string|max:30',
            'is_active'      => 'boolean',
            'sort_order'     => 'integer|min:0',
            'photo'          => 'nullable|image|max:2048',
        ]);

        $data['is_active'] = $request->boolean('is_active', true);

        if ($request->hasFile('photo')) {
            $data['photo'] = $request->file('photo')->store('instructors', 'public');
        }

        $instructor->update($data);

        return redirect()->route('admin.instructors.index')
            ->with('success', 'Instructor updated.');
    }

    public function destroy(Instructor $instructor)
    {
        $instructor->courses()->detach();
        $instructor->delete();

        return redirect()->route('admin.instructors.index')
            ->with('success', 'Instructor deleted.');
    }
}

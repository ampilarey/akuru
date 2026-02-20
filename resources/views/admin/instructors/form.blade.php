@extends('layouts.app')
@section('title', isset($instructor->id) ? 'Edit Instructor' : 'Add Instructor')

@section('content')
<div class="container mx-auto px-4 py-8 max-w-2xl">
    <div class="flex items-center gap-3 mb-6">
        <a href="{{ route('admin.instructors.index') }}" class="text-sm text-gray-500 hover:text-brandMaroon-600">← Instructors</a>
        <span class="text-gray-300">/</span>
        <span class="text-gray-700 text-sm">{{ isset($instructor->id) ? 'Edit' : 'New Instructor' }}</span>
    </div>

    @if($errors->any())
        <div class="mb-4 p-3 bg-red-100 text-red-800 rounded text-sm">{{ $errors->first() }}</div>
    @endif

    <div class="card p-6">
        <form method="POST"
              action="{{ isset($instructor->id) ? route('admin.instructors.update', $instructor) : route('admin.instructors.store') }}"
              enctype="multipart/form-data">
            @csrf
            @if(isset($instructor->id)) @method('PUT') @endif

            <div class="grid sm:grid-cols-2 gap-4 mb-4">
                <div class="sm:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Full name <span class="text-red-500">*</span></label>
                    <input type="text" name="name" value="{{ old('name', $instructor->name) }}" required
                           class="form-input w-full">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Qualification</label>
                    <input type="text" name="qualification" value="{{ old('qualification', $instructor->qualification) }}"
                           placeholder="e.g. Bachelor of Islamic Studies"
                           class="form-input w-full">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Specialization</label>
                    <input type="text" name="specialization" value="{{ old('specialization', $instructor->specialization) }}"
                           placeholder="e.g. Quran Memorization"
                           class="form-input w-full">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                    <input type="email" name="email" value="{{ old('email', $instructor->email) }}"
                           class="form-input w-full">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
                    <input type="text" name="phone" value="{{ old('phone', $instructor->phone) }}"
                           class="form-input w-full">
                </div>
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Bio</label>
                <textarea name="bio" rows="4" class="form-input w-full">{{ old('bio', $instructor->bio) }}</textarea>
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Photo</label>
                @if(isset($instructor->id) && $instructor->photo)
                    <div class="mb-2">
                        <img src="{{ asset('storage/' . $instructor->photo) }}" class="w-20 h-20 rounded-full object-cover">
                    </div>
                @endif
                <input type="file" name="photo" accept="image/*" class="block text-sm text-gray-600">
                <p class="text-xs text-gray-500 mt-1">Max 2MB. JPEG or PNG.</p>
            </div>

            <div class="grid sm:grid-cols-2 gap-4 mb-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Sort order</label>
                    <input type="number" name="sort_order" value="{{ old('sort_order', $instructor->sort_order ?? 0) }}"
                           min="0" class="form-input w-full">
                </div>
                <div class="flex items-center gap-2 pt-6">
                    <input type="checkbox" name="is_active" id="is_active" value="1"
                           {{ old('is_active', $instructor->is_active ?? true) ? 'checked' : '' }}
                           class="rounded border-gray-300 text-brandMaroon-600">
                    <label for="is_active" class="text-sm font-medium text-gray-700">Active (show publicly)</label>
                </div>
            </div>

            <div class="flex gap-3">
                <button type="submit" class="btn-primary">{{ isset($instructor->id) ? 'Save changes' : 'Create instructor' }}</button>
                <a href="{{ route('admin.instructors.index') }}" class="btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection

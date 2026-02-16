@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="mb-6">
        <a href="{{ route('admin.courses.index') }}" class="text-brandBlue-600 hover:text-brandBlue-800 flex items-center">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
            </svg>
            Back to Courses
        </a>
        <h1 class="text-3xl font-bold text-gray-900 mt-2">Create New Course</h1>
    </div>

    <div class="card">
        <form action="{{ route('admin.courses.store') }}" method="POST" class="p-6 space-y-6">
            @csrf

            <div>
                <label for="course_category_id" class="block text-sm font-medium text-gray-700 mb-1">Category <span class="text-red-500">*</span></label>
                <select name="course_category_id" id="course_category_id" required class="form-input w-full rounded-md @error('course_category_id') border-red-500 @enderror">
                    <option value="">Select Category</option>
                    @foreach($categories as $cat)
                        <option value="{{ $cat->id }}" {{ old('course_category_id') == $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
                    @endforeach
                </select>
                @error('course_category_id')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="title" class="block text-sm font-medium text-gray-700 mb-1">Title <span class="text-red-500">*</span></label>
                <input type="text" name="title" id="title" value="{{ old('title') }}" required class="form-input w-full rounded-md @error('title') border-red-500 @enderror">
                @error('title')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="slug" class="block text-sm font-medium text-gray-700 mb-1">Slug <span class="text-red-500">*</span></label>
                <input type="text" name="slug" id="slug" value="{{ old('slug') }}" required placeholder="url-friendly-slug" class="form-input w-full rounded-md @error('slug') border-red-500 @enderror">
                @error('slug')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="short_desc" class="block text-sm font-medium text-gray-700 mb-1">Short Description <span class="text-red-500">*</span></label>
                <textarea name="short_desc" id="short_desc" rows="2" required class="form-input w-full rounded-md @error('short_desc') border-red-500 @enderror">{{ old('short_desc') }}</textarea>
                @error('short_desc')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="body" class="block text-sm font-medium text-gray-700 mb-1">Content <span class="text-red-500">*</span></label>
                <textarea name="body" id="body" rows="8" required class="form-input w-full rounded-md @error('body') border-red-500 @enderror">{{ old('body') }}</textarea>
                @error('body')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="language" class="block text-sm font-medium text-gray-700 mb-1">Language <span class="text-red-500">*</span></label>
                    <select name="language" id="language" required class="form-input w-full rounded-md @error('language') border-red-500 @enderror">
                        @foreach(['en' => 'English', 'ar' => 'Arabic', 'dv' => 'Dhivehi', 'mixed' => 'Mixed'] as $val => $label)
                            <option value="{{ $val }}" {{ old('language') == $val ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('language')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label for="level" class="block text-sm font-medium text-gray-700 mb-1">Level <span class="text-red-500">*</span></label>
                    <select name="level" id="level" required class="form-input w-full rounded-md @error('level') border-red-500 @enderror">
                        @foreach(['kids' => 'Kids', 'youth' => 'Youth', 'adult' => 'Adult', 'all' => 'All'] as $val => $label)
                            <option value="{{ $val }}" {{ old('level') == $val ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('level')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="cover_image" class="block text-sm font-medium text-gray-700 mb-1">Cover Image URL <span class="text-red-500">*</span></label>
                    <input type="text" name="cover_image" id="cover_image" value="{{ old('cover_image') }}" required placeholder="e.g. /images/course.jpg" class="form-input w-full rounded-md @error('cover_image') border-red-500 @enderror">
                    @error('cover_image')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label for="status" class="block text-sm font-medium text-gray-700 mb-1">Status <span class="text-red-500">*</span></label>
                    <select name="status" id="status" required class="form-input w-full rounded-md @error('status') border-red-500 @enderror">
                        @foreach(['open' => 'Open', 'closed' => 'Closed', 'upcoming' => 'Upcoming'] as $val => $label)
                            <option value="{{ $val }}" {{ old('status', 'open') == $val ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('status')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="fee" class="block text-sm font-medium text-gray-700 mb-1">Fee (MVR)</label>
                    <input type="number" name="fee" id="fee" value="{{ old('fee') }}" min="0" step="0.01" class="form-input w-full rounded-md @error('fee') border-red-500 @enderror">
                    @error('fee')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label for="seats" class="block text-sm font-medium text-gray-700 mb-1">Seats</label>
                    <input type="number" name="seats" id="seats" value="{{ old('seats') }}" min="1" class="form-input w-full rounded-md @error('seats') border-red-500 @enderror">
                    @error('seats')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="flex justify-end space-x-3">
                <a href="{{ route('admin.courses.index') }}" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300">Cancel</a>
                <button type="submit" class="btn-primary">Create Course</button>
            </div>
        </form>
    </div>
</div>
@endsection

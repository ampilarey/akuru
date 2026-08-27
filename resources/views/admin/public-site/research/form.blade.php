@extends('layouts.app')

@section('content')
@php
    $item = $item ?? null;
    $selectedInstructors = old('instructor_ids', $item['instructor_ids'] ?? []);
    if (! is_array($selectedInstructors)) {
        $selectedInstructors = [$selectedInstructors];
    }
@endphp
<div class="container mx-auto px-4 py-8 max-w-3xl">
    <h1 class="text-3xl font-bold text-gray-900 mb-6">{{ $item ? 'Edit research post' : 'New research post' }}</h1>

    @if(session('success'))
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="mb-4 p-3 bg-red-100 text-red-800 rounded text-sm">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ $item ? route('admin.research.update', $item['id']) : route('admin.research.store') }}" enctype="multipart/form-data" class="card p-6 space-y-4">
        @csrf
        @if($item)
            @method('PUT')
        @endif

        <div>
            <label class="block text-xs text-gray-500 mb-1">Title</label>
            <input type="text" name="title" value="{{ old('title', $item['title'] ?? '') }}" required class="form-input rounded-md w-full">
        </div>
        <div>
            <label class="block text-xs text-gray-500 mb-1">Slug</label>
            <input type="text" name="slug" value="{{ old('slug', $item['slug'] ?? '') }}" class="form-input rounded-md w-full" placeholder="optional">
        </div>
        <div>
            <label class="block text-xs text-gray-500 mb-1">Abstract</label>
            <textarea name="abstract" rows="3" class="form-input rounded-md w-full">{{ old('abstract', $item['abstract'] ?? '') }}</textarea>
        </div>
        <div>
            <label class="block text-xs text-gray-500 mb-1">Body</label>
            <textarea name="body" rows="8" class="form-input rounded-md w-full">{{ old('body', $item['body'] ?? '') }}</textarea>
        </div>
        <div>
            <label class="block text-xs text-gray-500 mb-1">Citation note</label>
            <textarea name="citation_note" rows="2" class="form-input rounded-md w-full">{{ old('citation_note', $item['citation_note'] ?? '') }}</textarea>
        </div>
        <div>
            <label class="block text-xs text-gray-500 mb-1">Instructor authors</label>
            <select name="instructor_ids[]" multiple class="form-input rounded-md w-full" size="6">
                @foreach($instructors as $instructor)
                    <option value="{{ $instructor['id'] }}" @selected(in_array($instructor['id'], array_map('intval', $selectedInstructors), true))>{{ $instructor['name'] }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs text-gray-500 mb-1">External authors (one per line)</label>
            <textarea name="external_names" rows="3" class="form-input rounded-md w-full">{{ old('external_names', $item['external_names_text'] ?? '') }}</textarea>
        </div>
        <div>
            <label class="block text-xs text-gray-500 mb-1">PDF (optional)</label>
            <input type="file" name="pdf" accept="application/pdf" class="form-input rounded-md w-full">
            @if(! empty($item['pdf']['url']))
                <p class="text-xs text-gray-500 mt-1"><a href="{{ $item['pdf']['url'] }}" class="text-brandBlue-600" target="_blank" rel="noopener">Current PDF</a></p>
            @endif
        </div>
        <div class="grid md:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs text-gray-500 mb-1">Published at</label>
                <input type="datetime-local" name="published_at" value="{{ old('published_at', $item['published_at_local'] ?? '') }}" class="form-input rounded-md w-full">
            </div>
            <div class="flex items-end pb-2">
                <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                    <input type="hidden" name="is_published" value="0">
                    <input type="checkbox" name="is_published" value="1" @checked(old('is_published', $item['is_published_flag'] ?? false))>
                    Published
                </label>
            </div>
        </div>

        <div>
            <button type="submit" class="btn-primary">Save</button>
            <a href="{{ route('admin.research.index') }}" class="text-sm text-gray-500 ml-3">Back</a>
        </div>
    </form>
</div>
@endsection

@extends('layouts.app')
@section('title', 'Upload Mushaf')
@section('content')
<div class="min-h-screen bg-gray-50 py-6"><div class="max-w-2xl mx-auto px-4">
<h1 class="text-2xl font-bold mb-6">Upload Quran Mushaf</h1>
<form method="POST" action="{{ route('hifz.quran.mushafs.store') }}" enctype="multipart/form-data" class="card p-6 space-y-4">@csrf
<div><label class="block text-sm font-medium mb-1">Name</label><input name="name" class="form-input w-full" required></div>
<div><label class="block text-sm font-medium mb-1">Description</label><textarea name="description" class="form-input w-full" rows="2"></textarea></div>
<div><label class="block text-sm font-medium mb-1">PDF or Word file</label><input type="file" name="source_file" accept=".pdf,.doc,.docx" class="form-input w-full"></div>
<div><label class="block text-sm font-medium mb-1">Page count (for placeholders)</label><input type="number" name="page_count" min="1" max="604" class="form-input w-full"></div>
<button type="submit" class="btn btn-primary">Create Mushaf</button>
</form></div></div>
@endsection

@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-8 max-w-2xl">
    <h1 class="text-3xl font-bold mb-6">{{ $group ? 'Edit group' : 'New group' }}</h1>
    @if(session('success'))
        <div class="bg-green-100 text-green-800 p-3 rounded mb-4">{{ session('success') }}</div>
    @endif
    <form method="POST" action="{{ $group ? route('admin.prayer-times.groups.update', $group) : route('admin.prayer-times.groups.store') }}" class="bg-white p-6 rounded border space-y-4">
        @csrf
        @if($group) @method('PUT') @endif
        <div><label class="block text-sm">Name (EN)</label><input name="name_en" class="form-input w-full" value="{{ old('name_en', $group->name_en ?? '') }}" required></div>
        <div><label class="block text-sm">Name (DV)</label><input name="name_dv" class="form-input w-full" value="{{ old('name_dv', $group->name_dv ?? '') }}"></div>
        <div><label class="block text-sm">Name (AR)</label><input name="name_ar" class="form-input w-full" value="{{ old('name_ar', $group->name_ar ?? '') }}"></div>
        <div><label class="block text-sm">Description</label><textarea name="description" class="form-input w-full">{{ old('description', $group->description ?? '') }}</textarea></div>
        <div>
            <label class="block text-sm">Member user IDs (comma or JSON refs)</label>
            <textarea name="member_refs" class="form-input w-full" rows="4">{{ old('member_refs', $group ? json_encode($group->member_refs, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) : '') }}</textarea>
        </div>
        <input type="hidden" name="is_active" value="0">
        <label class="flex items-center gap-2"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $group->is_active ?? true))> Active</label>
        <button class="btn-primary">Save</button>
        <a href="{{ route('admin.prayer-times.groups.index') }}" class="text-sm text-gray-500 ml-3">Back</a>
    </form>
</div>
@endsection

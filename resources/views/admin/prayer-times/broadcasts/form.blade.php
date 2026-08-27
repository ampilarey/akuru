@extends('layouts.app')

@section('content')
@php $snapshot = $broadcast->preview_snapshot ?? null; @endphp
<div class="container mx-auto px-4 py-8 max-w-3xl">
    <h1 class="text-3xl font-bold mb-6">{{ $broadcast ? 'Broadcast '.$broadcast->id : 'New broadcast' }}</h1>
    @if(session('success'))
        <div class="bg-green-100 text-green-800 p-3 rounded mb-4">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="mb-4 p-3 bg-red-100 text-red-800 rounded text-sm">{{ $errors->first() }}</div>
    @endif
    <form method="POST" action="{{ $broadcast ? route('admin.prayer-times.broadcasts.update', $broadcast) : route('admin.prayer-times.broadcasts.store') }}" class="bg-white p-6 rounded border space-y-4 mb-6">
        @csrf
        @if($broadcast) @method('PUT') @endif
        <div>
            <label class="block text-sm">Mode</label>
            <select name="mode" class="form-input w-full">
                @foreach(['daily','range','change_only'] as $mode)
                    <option value="{{ $mode }}" @selected(old('mode', $broadcast->mode->value ?? 'daily')===$mode)>{{ $mode }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-sm">Island</label>
            <select name="island_id" class="form-input w-full" required>
                @foreach($islands as $island)
                    <option value="{{ $island->id }}" @selected((int) old('island_id', $broadcast->island_id ?? 0)===$island->id)>{{ $island->nameEn }}</option>
                @endforeach
            </select>
        </div>
        <div class="grid grid-cols-2 gap-3">
            <div><label class="block text-sm">Date from</label><input type="date" name="date_from" class="form-input w-full" value="{{ old('date_from', optional($broadcast?->date_from)->toDateString()) }}"></div>
            <div><label class="block text-sm">Date to</label><input type="date" name="date_to" class="form-input w-full" value="{{ old('date_to', optional($broadcast?->date_to)->toDateString()) }}"></div>
        </div>
        <div>
            <label class="block text-sm">Language</label>
            <select name="language" class="form-input w-full">
                @foreach(['en','dv','ar'] as $lang)
                    <option value="{{ $lang }}" @selected(old('language', $broadcast->language->value ?? 'en')===$lang)>{{ $lang }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-sm">Recipient group</label>
            <select name="recipient_group_id" class="form-input w-full">
                <option value="">Ad-hoc only</option>
                @foreach($groups as $group)
                    <option value="{{ $group->id }}" @selected((int) old('recipient_group_id', $broadcast->recipient_group_id ?? 0)===$group->id)>{{ $group->name_en }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-sm">Ad-hoc user IDs / JSON refs</label>
            <textarea name="recipient_refs" class="form-input w-full" rows="3">{{ old('recipient_refs', $broadcast && $broadcast->recipient_refs ? json_encode($broadcast->recipient_refs) : '') }}</textarea>
        </div>
        <button class="btn-primary">Save draft</button>
        <a href="{{ route('admin.prayer-times.broadcasts.index') }}" class="text-sm text-gray-500 ml-3">Back</a>
    </form>

    @if($broadcast)
        <form method="POST" action="{{ route('admin.prayer-times.broadcasts.preview', $broadcast) }}" class="mb-4">
            @csrf
            <button class="btn-secondary">Preview</button>
        </form>
        @if($snapshot)
            <div class="bg-white border rounded p-4 mb-4 text-sm space-y-2">
                <p><strong>Included:</strong> {{ $snapshot['included_count'] ?? 0 }} · <strong>Excluded:</strong> {{ $snapshot['excluded_count'] ?? 0 }} · <strong>Cost:</strong> {{ $snapshot['estimated_cost'] ?? 0 }} MVR</p>
                @if(!empty($snapshot['needs_split']))
                    <p class="text-red-700">Times change in this range. Split into the suggested blocks before confirm.</p>
                    <pre class="bg-gray-50 p-2 overflow-x-auto">{{ json_encode($snapshot['range']['blocks'] ?? [], JSON_PRETTY_PRINT) }}</pre>
                @endif
                <p class="font-medium">EN</p><pre class="whitespace-pre-wrap">{{ $snapshot['messages']['en'] ?? '' }}</pre>
                <p class="font-medium">DV</p><pre class="whitespace-pre-wrap">{{ $snapshot['messages']['dv'] ?? '' }}</pre>
                <p class="font-medium">AR</p><pre class="whitespace-pre-wrap">{{ $snapshot['messages']['ar'] ?? '' }}</pre>
            </div>
            <form method="POST" action="{{ route('admin.prayer-times.broadcasts.confirm', $broadcast) }}">
                @csrf
                <button class="btn-primary" @disabled(($snapshot['included_count'] ?? 0) < 1 || !empty($snapshot['needs_split']))>Confirm &amp; queue</button>
            </form>
        @endif
    @endif
</div>
@endsection

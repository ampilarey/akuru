@extends('layouts.app')
@section('title', $mushaf->name)
@section('content')
<div class="min-h-screen bg-gray-50 py-6"><div class="max-w-4xl mx-auto px-4">
@include('hifz.partials.alerts')
<h1 class="text-2xl font-bold mb-2">{{ $mushaf->name }}</h1>
<p class="text-sm text-gray-600 mb-4">Hash: {{ $mushaf->source_hash ?? '—' }} · Pages: {{ $mushaf->pages_count }} · Ayahs: {{ $mushaf->ayahs_count }}</p>
<div class="flex gap-2 mb-6">
@can('approve', $mushaf)<form method="POST" action="{{ route('hifz.quran.mushafs.approve', $mushaf) }}">@csrf<button class="btn btn-primary">Approve & Activate</button></form>@endcan
@can('lock', $mushaf)<form method="POST" action="{{ route('hifz.quran.mushafs.lock', $mushaf) }}">@csrf<button class="btn btn-secondary">Lock</button></form>@endcan
<a href="{{ route('hifz.quran.pages.show', [$mushaf, 1]) }}" class="btn btn-secondary">Map Page 1</a>
</div>
@can('manage', App\Models\QuranMushaf::class)
<div class="card p-4">
<h3 class="font-semibold mb-3">Import Ayah / Words</h3>
<form method="POST" action="{{ route('hifz.quran.mushafs.import-ayah', $mushaf) }}" class="space-y-3">@csrf
<div class="grid grid-cols-3 gap-2">
<input type="number" name="surah_number" placeholder="Surah" class="form-input" min="1" max="114" required>
<input type="number" name="ayah_number" placeholder="Ayah" class="form-input" required>
<input type="number" name="page_number" placeholder="Page" class="form-input">
</div>
<textarea name="text_uthmani" rows="2" class="form-input w-full" placeholder="Ayah text (Uthmani)" required dir="rtl"></textarea>
<input type="text" name="words[]" class="form-input w-full" placeholder="Word 1" dir="rtl">
<input type="text" name="words[]" class="form-input w-full" placeholder="Word 2" dir="rtl">
<button type="submit" class="btn btn-secondary">Import</button>
</form>
</div>
@endcan
</div></div>
@endsection

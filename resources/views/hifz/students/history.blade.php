@extends('layouts.app')
@section('title', 'Hifz History')
@section('content')
<div class="min-h-screen bg-gray-50 py-6"><div class="max-w-5xl mx-auto px-4">
<h1 class="text-2xl font-bold mb-2">Hifz History — {{ $student->full_name }}</h1>
<h3 class="font-semibold mt-6 mb-3">Session Records</h3>
@foreach($records as $r)
<div class="card p-4 mb-3 text-sm">
<div class="flex justify-between"><span class="font-medium">{{ $r->session->session_date->format('d M Y') }}</span><span>{{ $r->overall_status?->value }}</span></div>
<p class="text-gray-600 mt-1">Mistakes: {{ $r->mistake_count }} (Haraka: {{ $r->haraka_mistakes }})</p>
@if($r->parent_visible_note && (auth()->user()->isParent() || auth()->user()->isStudent()))<p>{{ $r->parent_visible_note }}</p>@endif
</div>
@endforeach
{{ $records->links() }}
@if($legacyProgress->isNotEmpty())
<h3 class="font-semibold mt-8 mb-3 text-gray-500">Legacy Quran Progress (summary)</h3>
@foreach($legacyProgress as $p)<div class="text-sm text-gray-600 py-1">{{ $p->surah_name }} — {{ $p->status }}</div>@endforeach
@endif
</div></div>
@endsection

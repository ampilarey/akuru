@extends('layouts.app')
@section('title', 'Quran Page — Mistakes')
@section('content')
<div class="min-h-screen bg-gray-50 py-4" x-data="quranMistakePanel({{ $record->id }})">
<div class="max-w-7xl mx-auto px-4">
@include('hifz.partials.alerts')
<div class="mb-4">
<h1 class="text-xl font-bold">{{ $record->student->full_name }} — Quran Page</h1>
<p class="text-sm text-gray-600">{{ $record->program->name }} · {{ $record->session->session_date->format('d M Y') }}</p>
</div>
<div class="grid lg:grid-cols-2 gap-4">
<div class="card p-4">
<div class="flex gap-2 mb-3">
<input type="number" x-model="pageNumber" class="form-input w-24 text-sm" min="1" max="604">
<button type="button" @click="loadPage()" class="btn btn-secondary text-sm">Go</button>
</div>
<div class="relative bg-gray-100 min-h-[400px] rounded overflow-hidden" id="quran-page-container">
@if($page && $page->image_path)
<img src="{{ asset('storage/'.$page->image_path) }}" class="w-full" alt="Page {{ $pageNumber }}">
@foreach($page->wordPositions ?? [] as $pos)
<div class="absolute border-2 border-red-400/60 cursor-pointer hover:bg-red-200/30"
     style="left:{{ $pos->x }}%;top:{{ $pos->y }}%;width:{{ $pos->width }}%;height:{{ $pos->height }}%"
     @click="selectWord({{ $pos->quran_word_id }}, {{ $pos->word->surah_number ?? 0 }}, {{ $pos->word->ayah_number ?? 0 }}, {{ $pos->word->word_number ?? 0 }})"></div>
@endforeach
@else
<div class="flex items-center justify-center h-64 text-gray-500 text-sm">Page image not available. Use ayah/word list below.</div>
@endif
</div>
</div>
<div class="space-y-4">
<div class="card p-4">
<h3 class="font-semibold mb-2">Ayah List</h3>
<div class="max-h-48 overflow-y-auto text-right" dir="rtl">
@forelse($ayahs as $ayah)
<button type="button" class="block w-full text-right py-2 px-2 hover:bg-gray-50 border-b text-sm"
        @click="selectAyah({{ $ayah->surah_number }}, {{ $ayah->ayah_number }}, {{ $ayah->id }})">
<span class="text-gray-500 text-xs">{{ $ayah->surah_number }}:{{ $ayah->ayah_number }}</span> {{ Str::limit($ayah->text_uthmani, 80) }}
</button>
@empty
<p class="text-gray-500 text-sm">No ayahs mapped for this page.</p>
@endforelse
</div>
</div>
<div class="card p-4">
<h3 class="font-semibold mb-2">Word List</h3>
<div class="flex flex-wrap gap-2" dir="rtl">
<template x-for="word in words" :key="word.id">
<button type="button" class="px-2 py-1 bg-gray-100 rounded text-lg hover:bg-brandGold-100"
        @click="selectWord(word.id, word.surah_number, word.ayah_number, word.word_number)" x-text="word.word_text"></button>
</template>
</div>
<p x-show="words.length===0" class="text-gray-500 text-sm">Select an ayah to load words.</p>
</div>
<div class="card p-4" x-show="showMistakeForm">
<h3 class="font-semibold mb-3">Record Mistake</h3>
<form @submit.prevent="saveMistake">
<div class="grid grid-cols-2 gap-2 mb-3">
<select x-model="form.mistake_type" class="form-input text-sm" required>
@foreach(['haraka','wrong_letter','wrong_word','skipped_word','extra_word','wrong_ayah','wrong_order','hesitation','weak_fluency','waqf_ibtida','tajweed','other'] as $t)
<option value="{{ $t }}">{{ str_replace('_',' ',$t) }}</option>
@endforeach
</select>
<select x-model="form.severity" class="form-input text-sm" required>
<option value="minor">Minor</option><option value="medium">Medium</option><option value="major">Major</option>
</select>
</div>
<input type="text" x-model="form.teacher_note" placeholder="Note" class="form-input w-full text-sm mb-3">
<label class="text-sm flex items-center gap-2 mb-3"><input type="checkbox" x-model="form.parent_visible"> Parent visible</label>
<button type="submit" class="btn btn-primary w-full">Save Mistake</button>
</form>
<p class="mt-3 text-sm">Counts: <span x-text="counts.mistake_count"></span> total · <span x-text="counts.haraka_mistakes"></span> haraka</p>
</div>
<div class="card p-4">
<h3 class="font-semibold mb-2">Mistakes</h3>
@foreach($record->mistakes as $m)
<div class="text-sm py-2 border-b flex justify-between">
<span>{{ str_replace('_',' ',$m->mistake_type->value) }} ({{ $m->severity->value }})</span>
<form method="POST" action="{{ route('hifz.mistakes.destroy', $m) }}">@csrf @method('DELETE')<button class="text-red-600 text-xs">Remove</button></form>
</div>
@endforeach
</div>
</div>
</div>
<div class="mt-4 flex gap-3">
<select id="manual-surah" class="form-input text-sm">@foreach($surahs as $s)<option value="{{ $s->index }}">{{ $s->index }}. {{ $s->english_name }}</option>@endforeach</select>
<input type="number" id="manual-ayah" placeholder="Ayah" class="form-input w-20 text-sm">
<input type="number" id="manual-word" placeholder="Word" class="form-input w-20 text-sm">
<button type="button" class="btn btn-secondary text-sm" @click="manualSelect()">Manual Select</button>
<a href="{{ route('hifz.sessions.edit', $record->session) }}" class="btn btn-secondary">Back to Session</a>
</div>
</div>
</div>
<script>
function quranMistakePanel(recordId) {
    return {
        recordId, pageNumber: {{ $pageNumber }}, words: [], showMistakeForm: false,
        counts: { mistake_count: {{ $record->mistake_count }}, haraka_mistakes: {{ $record->haraka_mistakes }} },
        form: { mistake_type: 'haraka', severity: 'minor', teacher_note: '', parent_visible: false,
            quran_ayah_id: null, quran_word_id: null, surah_number: null, ayah_number: null, word_number: null },
        selectAyah(surah, ayah, ayahId) {
            this.form.surah_number = surah; this.form.ayah_number = ayah; this.form.quran_ayah_id = ayahId;
            fetch(`{{ url('hifz/quran/mushafs') }}/{{ $mushaf?->id ?? 0 }}/words?surah_number=${surah}&ayah_number=${ayah}`, { headers: { 'Accept': 'application/json' } })
                .then(r => r.json()).then(data => { this.words = data; this.showMistakeForm = true; });
        },
        selectWord(wordId, surah, ayah, wordNum) {
            this.form.quran_word_id = wordId; this.form.surah_number = surah; this.form.ayah_number = ayah; this.form.word_number = wordNum;
            this.showMistakeForm = true;
        },
        manualSelect() {
            this.form.surah_number = document.getElementById('manual-surah').value;
            this.form.ayah_number = document.getElementById('manual-ayah').value;
            this.form.word_number = document.getElementById('manual-word').value;
            this.showMistakeForm = true;
        },
        saveMistake() {
            fetch('{{ route('hifz.mistakes.store') }}', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept': 'application/json' },
                body: JSON.stringify({ ...this.form, hifz_session_record_id: this.recordId })
            }).then(r => r.json()).then(data => { if(data.counts) this.counts = data.counts; location.reload(); });
        }
    }
}
</script>
@endsection

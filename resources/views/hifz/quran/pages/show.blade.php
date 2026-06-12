@extends('layouts.app')
@section('title', 'Page '.$pageNumber)
@section('content')
<div class="min-h-screen bg-gray-50 py-6" x-data="{
    mapping: false,
    selectedWord: null,
    box: { x: 10, y: 10, width: 5, height: 3 },
    async savePosition() {
        if (!this.selectedWord) return;
        await fetch('{{ route('hifz.quran.pages.positions.store', [$mushaf, $page]) }}', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept': 'application/json' },
            body: JSON.stringify({ quran_word_id: this.selectedWord, ...this.box })
        });
        location.reload();
    }
}">
<div class="max-w-5xl mx-auto px-4">
<h1 class="text-2xl font-bold mb-4">{{ $mushaf->name }} — Page {{ $pageNumber }}</h1>
@can('manage', App\Domains\Hifz\Models\QuranMushaf::class)
<button @click="mapping=!mapping" class="btn btn-secondary mb-4 text-sm" x-text="mapping ? 'Stop Mapping' : 'Map Word Positions'"></button>
@endcan
<div class="grid lg:grid-cols-2 gap-4">
<div class="card p-4 relative" id="page-map">
@if($page->image_path)<img src="{{ asset('storage/'.$page->image_path) }}" class="w-full rounded">@else<div class="h-96 bg-gray-200 flex items-center justify-center text-gray-500">No image — upload page images to enable overlay</div>@endif
@foreach($positions as $pos)
<div class="absolute border border-red-400" style="left:{{ $pos->x }}%;top:{{ $pos->y }}%;width:{{ $pos->width }}%;height:{{ $pos->height }}%"></div>
@endforeach
</div>
<div class="card p-4">
<h3 class="font-semibold mb-2">Ayahs on page</h3>
@foreach($ayahs as $ayah)<p class="text-right text-sm py-1 border-b" dir="rtl">{{ $ayah->surah_number }}:{{ $ayah->ayah_number }} {{ Str::limit($ayah->text_uthmani, 60) }}</p>@endforeach
@can('manage', App\Domains\Hifz\Models\QuranMushaf::class)
<div x-show="mapping" class="mt-4 border-t pt-4">
<p class="text-sm mb-2">Select word then adjust box % and save:</p>
<select x-model="selectedWord" class="form-input w-full text-sm mb-2">
<option value="">Select word</option>
@foreach($mushaf->words()->where('page_number', $pageNumber)->get() as $w)
<option value="{{ $w->id }}">{{ $w->surah_number }}:{{ $w->ayah_number }} #{{ $w->word_number }} {{ $w->word_text }}</option>
@endforeach
</select>
<div class="grid grid-cols-4 gap-1 mb-2">
<input type="number" x-model="box.x" placeholder="X%" class="form-input text-xs">
<input type="number" x-model="box.y" placeholder="Y%" class="form-input text-xs">
<input type="number" x-model="box.width" placeholder="W%" class="form-input text-xs">
<input type="number" x-model="box.height" placeholder="H%" class="form-input text-xs">
</div>
<button type="button" class="btn btn-primary text-sm" @click="savePosition()">Save Position</button>
</div>
@endcan
</div>
</div>
</div>
</div>
@endsection

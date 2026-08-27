@extends('layouts.app')

@section('content')
@php
    $item = $item ?? null;
    $type = old('content_type', $item['content_type'] ?? $type ?? 'ayah');
@endphp
<div class="container mx-auto px-4 py-8 max-w-3xl">
    <h1 class="text-3xl font-bold text-gray-900 mb-6">{{ $item ? 'Edit daily content' : 'New daily content' }}</h1>

    @if(session('success'))
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="mb-4 p-3 bg-red-100 text-red-800 rounded text-sm">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ $item ? route('admin.daily-content.update', $item['id']) : route('admin.daily-content.store') }}" class="card p-6 space-y-4" data-ayah-preview="{{ route('admin.daily-content.ayah-preview') }}">
        @csrf
        @if($item)
            @method('PUT')
        @endif

        <div class="grid md:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs text-gray-500 mb-1">Type</label>
                <select name="content_type" id="content_type" class="form-input rounded-md w-full" @disabled($item)>
                    @foreach(['ayah','hadith','saying','reminder'] as $option)
                        <option value="{{ $option }}" @selected($type === $option)>{{ $option }}</option>
                    @endforeach
                </select>
                @if($item)
                    <input type="hidden" name="content_type" value="{{ $item['content_type'] }}">
                @endif
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">Publish date</label>
                <input type="date" name="publish_date" value="{{ old('publish_date', $item['publish_date'] ?? '') }}" required class="form-input rounded-md w-full">
            </div>
        </div>

        <div id="fields-ayah" class="space-y-3">
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Surah number</label>
                    <input type="number" name="surah_number" id="surah_number" min="1" max="114" value="{{ old('surah_number', $item['ayah']['surah_number'] ?? 1) }}" class="form-input rounded-md w-full">
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Ayah number</label>
                    <input type="number" name="ayah_number" id="ayah_number" min="1" value="{{ old('ayah_number', $item['ayah']['ayah_number'] ?? 1) }}" class="form-input rounded-md w-full">
                </div>
            </div>
        </div>

        <div id="fields-hadith" class="space-y-3 hidden">
            <textarea name="hadith_text_ar" id="hadith_text_ar" rows="2" class="form-input rounded-md w-full" placeholder="Arabic" dir="rtl">{{ old('hadith_text_ar', $item['hadith_text_ar'] ?? '') }}</textarea>
            <textarea name="hadith_text_en" id="hadith_text_en" rows="2" class="form-input rounded-md w-full" placeholder="English">{{ old('hadith_text_en', $item['hadith_text_en'] ?? '') }}</textarea>
            <textarea name="hadith_text_dv" id="hadith_text_dv" rows="2" class="form-input rounded-md w-full" placeholder="Dhivehi" dir="rtl">{{ old('hadith_text_dv', $item['hadith_text_dv'] ?? '') }}</textarea>
            <div class="grid md:grid-cols-2 gap-4">
                <input type="text" name="hadith_collection" id="hadith_collection" value="{{ old('hadith_collection', $item['hadith_collection'] ?? '') }}" placeholder="Collection (Bukhari, Muslim, …)" class="form-input rounded-md w-full">
                <input type="text" name="hadith_number" id="hadith_number" value="{{ old('hadith_number', $item['hadith_number'] ?? '') }}" placeholder="Number" class="form-input rounded-md w-full">
                <select name="hadith_grading" id="hadith_grading" class="form-input rounded-md w-full">
                    <option value="">Grading</option>
                    @foreach(['sahih','hasan','daif'] as $grade)
                        <option value="{{ $grade }}" @selected(old('hadith_grading', $item['hadith_grading'] ?? '') === $grade)>{{ $grade }}</option>
                    @endforeach
                </select>
                <input type="text" name="grading_source" id="grading_source" value="{{ old('grading_source', $item['grading_source'] ?? '') }}" placeholder="Grading source" class="form-input rounded-md w-full">
            </div>
        </div>

        <div id="fields-text" class="space-y-3 hidden">
            <textarea name="text_en" id="text_en" rows="2" class="form-input rounded-md w-full" placeholder="English">{{ old('text_en', $item['text_en'] ?? '') }}</textarea>
            <textarea name="text_dv" id="text_dv" rows="2" class="form-input rounded-md w-full" placeholder="Dhivehi" dir="rtl">{{ old('text_dv', $item['text_dv'] ?? '') }}</textarea>
            <textarea name="text_ar" id="text_ar" rows="2" class="form-input rounded-md w-full" placeholder="Arabic (optional)" dir="rtl">{{ old('text_ar', $item['text_ar'] ?? '') }}</textarea>
            <input type="text" name="attribution" id="attribution" value="{{ old('attribution', $item['attribution'] ?? '') }}" placeholder="Attribution / source" class="form-input rounded-md w-full">
        </div>

        <input type="text" name="theme_tag" value="{{ old('theme_tag', $item['theme_tag'] ?? '') }}" placeholder="Theme tag" class="form-input rounded-md w-full">
        <textarea name="notes_internal" rows="2" class="form-input rounded-md w-full" placeholder="Internal notes">{{ old('notes_internal', $item['notes_internal'] ?? '') }}</textarea>

        @if($item)
            <input type="hidden" name="status" value="draft">
            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" name="status" value="archived" @checked(($item['status'] ?? '') === 'archived')>
                Archive
            </label>
        @endif

        <div id="live-preview" class="bg-gray-50 rounded-lg p-4 space-y-2 text-sm">
            <p class="text-xs uppercase tracking-wide text-gray-500">Live preview</p>
            <p id="preview-ar" class="text-xl leading-loose" dir="rtl"></p>
            <p id="preview-en" dir="ltr"></p>
            <p id="preview-dv" dir="rtl"></p>
            <p id="preview-meta" class="text-xs text-gray-500"></p>
        </div>

        <button class="btn-primary" type="submit">Save draft</button>
        <a href="{{ route('admin.daily-content.index') }}" class="text-sm text-gray-500 ml-3">Back</a>
    </form>
</div>
<script>
(function(){
    var form = document.querySelector('[data-ayah-preview]');
    var previewUrl = form ? form.getAttribute('data-ayah-preview') : '';
    var timer = null;

    function val(id) {
        var el = document.getElementById(id);
        return el ? el.value : '';
    }

    function setPreview(ar, en, dv, meta) {
        document.getElementById('preview-ar').textContent = ar || '';
        document.getElementById('preview-en').textContent = en || '';
        document.getElementById('preview-dv').textContent = dv || '';
        document.getElementById('preview-meta').textContent = meta || '';
    }

    function syncFields() {
        var type = document.getElementById('content_type').value;
        document.getElementById('fields-ayah').style.display = type === 'ayah' ? 'block' : 'none';
        document.getElementById('fields-hadith').style.display = type === 'hadith' ? 'block' : 'none';
        document.getElementById('fields-text').style.display = (type === 'saying' || type === 'reminder') ? 'block' : 'none';
        refreshPreview();
    }

    function refreshPreview() {
        var type = document.getElementById('content_type').value;
        if (type === 'hadith') {
            setPreview(val('hadith_text_ar'), val('hadith_text_en'), val('hadith_text_dv'),
                [val('hadith_collection'), val('hadith_number'), val('hadith_grading'), val('grading_source')].filter(Boolean).join(' · '));
            return;
        }
        if (type === 'saying' || type === 'reminder') {
            setPreview(val('text_ar'), val('text_en'), val('text_dv'), val('attribution'));
            return;
        }
        if (!previewUrl) {
            return;
        }
        clearTimeout(timer);
        timer = setTimeout(function () {
            var url = previewUrl + '?surah_number=' + encodeURIComponent(val('surah_number')) + '&ayah_number=' + encodeURIComponent(val('ayah_number'));
            fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function (res) { return res.json(); })
                .then(function (payload) {
                    if (!payload) {
                        setPreview('', 'That ayah is not in the active mushaf.', '', '');
                        return;
                    }
                    var meanings = payload.meanings || {};
                    setPreview(payload.text_uthmani || '', meanings.en || '', meanings.dv || '', payload.meaning_source || '');
                })
                .catch(function () {
                    setPreview('', 'Could not load ayah preview.', '', '');
                });
        }, 250);
    }

    document.getElementById('content_type').addEventListener('change', syncFields);
    ['surah_number','ayah_number','hadith_text_ar','hadith_text_en','hadith_text_dv','hadith_collection','hadith_number','hadith_grading','grading_source','text_en','text_dv','text_ar','attribution'].forEach(function (id) {
        var el = document.getElementById(id);
        if (el) {
            el.addEventListener('input', refreshPreview);
            el.addEventListener('change', refreshPreview);
        }
    });
    syncFields();
})();
</script>
@endsection

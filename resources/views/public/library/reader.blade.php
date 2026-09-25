@extends('public.layouts.public')

@section('title', $reader['title'] . ' - ' . config('app.name'))

@section('content')
<div class="container mx-auto px-4 py-8 max-w-3xl">
    <nav class="text-sm text-gray-500 mb-4 flex items-center justify-between">
        <a href="{{ route('public.library.show', $reader['slug']) }}" class="hover:text-brandMaroon-600">‹ {{ $reader['title'] }}</a>
        <span>{{ __('public.Page') }} {{ $reader['page'] }} / {{ max($reader['total_pages'], 1) }}</span>
    </nav>

    @if($reader['is_preview'] ?? false)
        {{-- §9.4. Said before the pages, not after them: a reader who does not
             know this is a sample will read to the cap and conclude the book
             is broken. --}}
        <div class="mb-4 rounded border border-amber-200 bg-amber-50 px-4 py-3 text-sm">
            {{ __('public.Free preview of :count pages', ['count' => $reader['readable_pages']]) }}
            <a class="underline" href="{{ route('public.library.show', $reader['slug']) }}">{{ __('public.Get the full item') }}</a>
        </div>
    @endif

    {{-- §9.1 reading comfort: text size, light/sepia/dark, direction, full
         screen. Kept in this browser (localStorage) — a preference, not a
         record — and applied before first paint so the page does not flash. --}}
    <div class="mb-3 flex flex-wrap items-center gap-2 text-sm" data-testid="reader-tools" role="toolbar" aria-label="{{ __('public.Reading options') }}">
        <button type="button" class="btn-secondary px-2 py-1" data-reader="font-down" aria-label="{{ __('public.Smaller text') }}">A−</button>
        <button type="button" class="btn-secondary px-2 py-1" data-reader="font-up" aria-label="{{ __('public.Larger text') }}">A+</button>
        <span class="ms-2 text-gray-500">{{ __('public.Theme') }}</span>
        <button type="button" class="btn-secondary px-2 py-1" data-reader="theme" data-value="light">{{ __('public.Light') }}</button>
        <button type="button" class="btn-secondary px-2 py-1" data-reader="theme" data-value="sepia">{{ __('public.Sepia') }}</button>
        <button type="button" class="btn-secondary px-2 py-1" data-reader="theme" data-value="dark">{{ __('public.Dark') }}</button>
        <label class="ms-2 text-gray-500">{{ __('public.Text direction') }}
            <select class="form-input ms-1 py-1" data-reader="dir">
                <option value="auto">{{ __('public.Auto') }}</option>
                <option value="ltr">{{ __('public.Left to right') }}</option>
                <option value="rtl">{{ __('public.Right to left') }}</option>
            </select>
        </label>
        <button type="button" class="btn-secondary px-2 py-1 ms-auto" data-reader="fullscreen">{{ __('public.Full screen') }}</button>
    </div>

    @if($reader['completed'] ?? false)
        <div class="mb-4 rounded border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800" data-testid="completed">
            ✓ {{ __('public.You have finished this item.') }}
            <a class="underline" href="{{ route('public.library.my') }}">{{ __('public.My Library') }}</a>
        </div>
    @endif

    <div id="reader-page" class="relative rounded-lg border bg-white p-8 select-none" style="print-color-adjust: exact;" data-testid="reader-page">
        <div class="pointer-events-none absolute inset-0 flex items-center justify-center overflow-hidden" aria-hidden="true">
            <span class="rotate-[-30deg] text-2xl text-gray-300/60 whitespace-nowrap">{{ $reader['watermark'] }}</span>
        </div>
        @if($reader['content'])
            <div class="prose max-w-none relative" id="reader-content">{!! $reader['content'] !!}</div>
        @elseif($reader['total_pages'] > 0)
            {{-- A page of a PDF that carried no text: a figure, a photograph, a blank leaf. --}}
            <p class="text-gray-500 relative">{{ __('public.This page has no text — it may be a picture or a blank page.') }}</p>
        @else
            <p class="text-gray-500 relative">{{ __('public.This item has no reader pages yet.') }}</p>
        @endif
    </div>

    @auth
        {{-- §9.1 private notes. Everything behind this box already existed —
             the column, the action argument, the controller's validation, and
             My Library's rendering of it. There was simply nowhere to type. --}}
        <form method="POST" action="{{ route('public.library.note', $reader['slug']) }}" class="mt-4">
            @csrf
            <input type="hidden" name="page" value="{{ $reader['page'] }}">
            <label class="block text-sm text-gray-600 mb-1" for="reader-note">
                {{ __('public.Your private note on this page') }}
            </label>
            <textarea id="reader-note" name="note" rows="2" maxlength="500"
                      class="w-full rounded border px-3 py-2 text-sm">{{ $reader['note'] }}</textarea>
            <button type="submit" class="btn-secondary mt-2 text-sm">{{ __('public.Save note') }}</button>
        </form>
    @endauth

    <div class="mt-4 flex items-center justify-between">
        <div>
            @if($reader['page'] > 1)
                <a class="btn-secondary" href="{{ route('public.library.read', ['slug' => $reader['slug'], 'page' => $reader['page'] - 1]) }}">‹ {{ __('public.Previous') }}</a>
            @endif
        </div>
        <div class="flex items-center gap-2">
            @auth
                <form method="POST" action="{{ route('public.library.bookmark', $reader['slug']) }}">
                    @csrf
                    <input type="hidden" name="page" value="{{ $reader['page'] }}">
                    <button type="submit" class="btn-secondary">{{ $reader['bookmarked'] ? __('public.Remove bookmark') : __('public.Bookmark this page') }}</button>
                </form>
            @endauth
            {{-- `readable_pages`, not `total_pages`: at the end of a sample the
                 next step is buying it, not a page that will be clamped back. --}}
            @auth
                {{-- §9.1 "mark as completed": the last page completes on its own;
                     this is for the reader who is done before it. --}}
                @if($reader['can_read'] && ! ($reader['completed'] ?? false) && $reader['total_pages'] > 0)
                    <form method="POST" action="{{ route('public.library.progress', $reader['slug']) }}">
                        @csrf
                        <input type="hidden" name="page" value="{{ $reader['total_pages'] }}">
                        <button type="submit" class="btn-secondary" data-testid="mark-completed">{{ __('public.Mark as completed') }}</button>
                    </form>
                @endif
            @endauth
            @if($reader['page'] < $reader['readable_pages'])
                <a class="btn-primary" href="{{ route('public.library.read', ['slug' => $reader['slug'], 'page' => $reader['page'] + 1]) }}">{{ __('public.Next') }} ›</a>
            @elseif($reader['is_preview'] ?? false)
                <a class="btn-primary" href="{{ route('public.library.show', $reader['slug']) }}">{{ __('public.Get the full item') }}</a>
            @endif
        </div>
    </div>
</div>
<style>
    #reader-page[data-theme="sepia"] { background: #f7f1e3; color: #3b2f1e; }
    #reader-page[data-theme="dark"] { background: #1f2327; color: #e6e6e6; border-color: #3a3f44; }
    #reader-page[data-theme="dark"] .prose { color: #e6e6e6; }
    #reader-page[data-theme="dark"] .prose :is(h1,h2,h3,h4,strong) { color: #fff; }
    #reader-page:fullscreen { overflow: auto; padding: 3rem; }
</style>
<script>
    (function () {
        var page = document.getElementById('reader-page');
        var content = document.getElementById('reader-content');
        var key = 'akuru.reader';
        var prefs = { size: 100, theme: 'light', dir: 'auto' };
        try { prefs = Object.assign(prefs, JSON.parse(localStorage.getItem(key) || '{}')); } catch (e) {}
        function apply() {
            if (content) {
                content.style.fontSize = prefs.size + '%';
                content.setAttribute('dir', prefs.dir);
            }
            page.setAttribute('data-theme', prefs.theme);
            page.setAttribute('data-size', String(prefs.size));
            var select = document.querySelector('[data-reader="dir"]');
            if (select) { select.value = prefs.dir; }
        }
        function save() { try { localStorage.setItem(key, JSON.stringify(prefs)); } catch (e) {} }
        document.querySelectorAll('[data-reader]').forEach(function (el) {
            var kind = el.getAttribute('data-reader');
            if (kind === 'dir') {
                el.addEventListener('change', function () { prefs.dir = el.value; apply(); save(); });
                return;
            }
            el.addEventListener('click', function () {
                if (kind === 'font-up') { prefs.size = Math.min(180, prefs.size + 10); }
                if (kind === 'font-down') { prefs.size = Math.max(70, prefs.size - 10); }
                if (kind === 'theme') { prefs.theme = el.getAttribute('data-value'); }
                if (kind === 'fullscreen') {
                    if (document.fullscreenElement) { document.exitFullscreen(); } else if (page.requestFullscreen) { page.requestFullscreen(); }
                    return;
                }
                apply(); save();
            });
        });
        apply();
    })();
</script>
@endsection

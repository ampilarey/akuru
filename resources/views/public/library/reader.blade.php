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

    {{-- B7 (§9.1): search inside this item, over the pages this reader may open. --}}
    <form method="GET" action="{{ route('public.library.read', $reader['slug']) }}" class="mb-3 flex items-center gap-2 text-sm" role="search" data-testid="reader-search">
        <input type="hidden" name="page" value="{{ $reader['page'] }}">
        <label class="sr-only" for="reader-search-q">{{ __('public.Search in this item') }}</label>
        <input id="reader-search-q" type="search" name="q" value="{{ $search['term'] ?? '' }}" minlength="2" maxlength="100" placeholder="{{ __('public.Search in this item') }}" class="form-input flex-1 py-1">
        <button type="submit" class="btn-secondary px-3 py-1">{{ __('public.Search') }}</button>
    </form>
    @if(($search['term'] ?? '') !== '')
        <div class="mb-4 rounded border bg-white px-4 py-3 text-sm" data-testid="reader-search-hits">
            @if(count($search['hits']) === 0)
                <p class="text-gray-600">{{ __('public.No pages match ":term".', ['term' => $search['term']]) }}</p>
            @else
                <p class="mb-2 text-gray-600">{{ trans_choice('public.:count page matches ":term".|:count pages match ":term".', count($search['hits']), ['count' => count($search['hits']), 'term' => $search['term']]) }}@if($search['truncated']) {{ __('public.Showing the first :count.', ['count' => count($search['hits'])]) }}@endif</p>
                <ul class="space-y-1">
                    @foreach($search['hits'] as $hit)
                        <li><a class="underline text-brandMaroon-700" href="{{ route('public.library.read', ['slug' => $reader['slug'], 'page' => $hit['page'], 'q' => $search['term']]) }}">{{ __('public.Page :page', ['page' => $hit['page']]) }}</a> <span class="text-gray-600">{{ $hit['snippet'] }}</span></li>
                    @endforeach
                </ul>
            @endif
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
        @if($reader['can_read'] && $reader['total_pages'] > 0)
            {{-- §9.1 reading time (STATUS §5ju). `total_reading_seconds`, the
                 endpoint's `seconds` and the action's argument had existed since
                 L2 for "the beacon" — and no reader ever sent one. This is it:
                 the seconds this page was *visible* (a tab in the background is
                 not reading), sent when the page is left or hidden, as time only
                 so a late beacon cannot move the reader back a page. Not on a
                 preview: a sample is not reading (§9.4). --}}
            <form id="reading-time" method="POST" action="{{ route('public.library.progress', $reader['slug']) }}" data-testid="reading-time" hidden>
                @csrf
                <input type="hidden" name="page" value="{{ $reader['page'] }}">
                <input type="hidden" name="time_only" value="1">
                <input type="hidden" name="seconds" value="0">
            </form>
        @endif
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
    (function () {
        // The reading-time beacon (see the form above). Visible time only,
        // banked across hide/show, sent on hide and on leaving; a second or
        // less is not a reading and is dropped.
        var form = document.getElementById('reading-time');
        if (!form || !navigator.sendBeacon) { return; }
        var visibleSince = document.visibilityState === 'visible' ? Date.now() : null;
        var banked = 0;
        function bank() {
            if (visibleSince !== null) { banked += Date.now() - visibleSince; visibleSince = null; }
        }
        function send() {
            bank();
            var seconds = Math.min(3600, Math.round(banked / 1000));
            banked = 0;
            if (seconds < 1) { return; }
            form.elements.seconds.value = String(seconds);
            navigator.sendBeacon(form.action, new FormData(form));
        }
        document.addEventListener('visibilitychange', function () {
            if (document.visibilityState === 'hidden') { send(); } else { visibleSince = Date.now(); }
        });
        window.addEventListener('pagehide', send);
    })();
</script>
@endsection

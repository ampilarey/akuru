@extends('public.layouts.public')

@section('title', $gallery->title . ' - ' . config('app.name'))

@section('content')
<div class="bg-white py-16">
    <div class="container mx-auto px-4">
        <div class="max-w-6xl mx-auto">
            <!-- Gallery Header -->
            <div class="mb-8">
                <div class="flex items-center text-sm text-brandGray-500 mb-4">
                    <a href="{{ route('public.gallery.index', app()->getLocale()) }}" class="hover:text-brandMaroon-600">
                        {{ __('public.Gallery') }}
                    </a>
                    <svg class="w-4 h-4 mx-2" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path>
                    </svg>
                    <span>{{ $gallery->title }}</span>
                </div>

                <h1 class="text-4xl font-bold text-brandGray-900 mb-4">
                    {{ $gallery->title }}
                </h1>

                @if($gallery->description)
                <p class="text-xl text-brandGray-600 max-w-3xl">
                    {{ $gallery->description }}
                </p>
                @endif
            </div>

            <!-- Gallery Items -->
            @if(isset($items) && $items->count() > 0)
            @php $imageItems = $items->filter(fn($i) => $i->file_type === 'image')->values(); @endphp
            <div class="columns-2 sm:columns-3 lg:columns-4 gap-3 space-y-3">
                @foreach($items as $idx => $item)
                @php $lightboxIdx = $imageItems->search(fn($i) => $i->id === $item->id); @endphp
                <div class="group break-inside-avoid cursor-pointer rounded-xl overflow-hidden relative shadow-sm hover:shadow-xl transition-all duration-300"
                     @if($item->file_type === 'image') onclick="openLightbox({{ $lightboxIdx !== false ? $lightboxIdx : 0 }})" @endif>
                    @if($item->file_type === 'image')
                    <x-public.picture
                        :src="$item->file_path"
                        :alt="$item->title ?? ''"
                        class="w-full object-cover group-hover:scale-105 transition-transform duration-500"
                        loading="lazy"
                    />
                    {{-- Hover overlay --}}
                    <div class="absolute inset-0 bg-black/0 group-hover:bg-black/30 transition-all duration-300 flex items-center justify-center">
                        <svg class="w-10 h-10 text-white opacity-0 group-hover:opacity-100 transition-opacity duration-300 drop-shadow-lg" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v3m0 0v3m0-3h3m-3 0H7"/>
                        </svg>
                    </div>
                    @elseif($item->file_type === 'video')
                    <div class="w-full h-48 bg-gray-200 flex flex-col items-center justify-center gap-2">
                        <svg class="w-12 h-12 text-gray-400" fill="currentColor" viewBox="0 0 20 20"><path d="M8 5v10l8-5-8-5z"/></svg>
                        @if($item->title)<p class="text-xs text-gray-500 px-3 text-center">{{ $item->title }}</p>@endif
                    </div>
                    @endif
                </div>
                @endforeach
            </div>
            @if($items->hasPages())
            <div class="mt-8 flex justify-center">{{ $items->links() }}</div>
            @endif

            {{-- ─── LIGHTBOX ─────────────────────────────────────────────── --}}
            @php
                $lbImages = $imageItems->map(fn($i) => [
                    'src'     => Storage::url($i->file_path),
                    'title'   => $i->title ?? '',
                    'caption' => $i->caption ?? $i->description ?? '',
                ])->values();
            @endphp
            <script>
            const _lbImages = @json($lbImages);
            let _lbCurrent = 0, _lbStartX = null;

            function openLightbox(idx) {
                _lbCurrent = idx;
                _renderLb();
                document.getElementById('lb').classList.remove('hidden');
                document.getElementById('lb').classList.add('flex');
                document.body.style.overflow = 'hidden';
            }
            function closeLightbox() {
                document.getElementById('lb').classList.add('hidden');
                document.getElementById('lb').classList.remove('flex');
                document.body.style.overflow = '';
            }
            function lbNav(dir) {
                _lbCurrent = (_lbCurrent + dir + _lbImages.length) % _lbImages.length;
                _renderLb();
            }
            function _renderLb() {
                const img = _lbImages[_lbCurrent];
                const el = document.getElementById('lb-img');
                el.style.opacity = '0';
                el.src = img.src;
                el.alt = img.title;
                el.onload = () => { el.style.opacity = '1'; };
                document.getElementById('lb-title').textContent = img.title;
                document.getElementById('lb-caption').textContent = img.caption;
                document.getElementById('lb-counter').textContent = (_lbCurrent + 1) + ' / ' + _lbImages.length;
            }
            document.addEventListener('keydown', e => {
                if (document.getElementById('lb').classList.contains('hidden')) return;
                if (e.key === 'Escape')  closeLightbox();
                if (e.key === 'ArrowRight') lbNav(1);
                if (e.key === 'ArrowLeft')  lbNav(-1);
            });
            </script>

            <div id="lb" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/95 p-4" onclick="if(event.target===this)closeLightbox()">
                {{-- Close --}}
                <button onclick="closeLightbox()" class="absolute top-4 right-4 w-10 h-10 flex items-center justify-center rounded-full bg-white/10 hover:bg-white/20 text-white transition-colors">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
                {{-- Counter --}}
                <span id="lb-counter" class="absolute top-4 left-1/2 -translate-x-1/2 text-white/60 text-sm"></span>
                {{-- Prev --}}
                <button onclick="lbNav(-1)" class="absolute left-3 sm:left-6 w-11 h-11 flex items-center justify-center rounded-full bg-white/10 hover:bg-white/20 text-white transition-colors">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                </button>
                {{-- Image --}}
                <div class="flex flex-col items-center max-w-5xl max-h-full w-full">
                    <img id="lb-img" src="" alt="" class="max-h-[75vh] max-w-full object-contain rounded-lg transition-opacity duration-300 select-none"
                         ontouchstart="if(event.changedTouches)_lbStartX=event.changedTouches[0].clientX"
                         ontouchend="if(event.changedTouches){const dx=event.changedTouches[0].clientX-_lbStartX;if(dx>50)lbNav(-1);else if(dx<-50)lbNav(1);}">
                    <div class="mt-4 text-center">
                        <p id="lb-title" class="text-white font-semibold text-base"></p>
                        <p id="lb-caption" class="text-white/60 text-sm mt-1"></p>
                    </div>
                </div>
                {{-- Next --}}
                <button onclick="lbNav(1)" class="absolute right-3 sm:right-6 w-11 h-11 flex items-center justify-center rounded-full bg-white/10 hover:bg-white/20 text-white transition-colors">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </button>
            </div>
            @else
            <!-- Empty State -->
            <div class="text-center py-12">
                <svg class="w-16 h-16 text-brandGray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                </svg>
                <h3 class="text-lg font-medium text-brandGray-900 mb-2">
                    {{ __('public.No items in this gallery') }}
                </h3>
                <p class="text-brandGray-600">
                    {{ __('public.gallery_empty_message') }}
                </p>
            </div>
            @endif
        </div>
    </div>
</div>

@endsection

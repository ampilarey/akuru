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
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                @foreach($items as $item)
                <div class="group cursor-pointer" onclick="openModal('{{ Storage::url($item->file_path) }}', '{{ addslashes($item->title ?? '') }}', '{{ addslashes($item->caption ?? $item->description ?? '') }}')">
                    <div class="relative overflow-hidden rounded-lg shadow-md hover:shadow-lg transition-shadow duration-300">
                        @if($item->file_type === 'image')
                        <x-public.picture
                            :src="$item->file_path"
                            :alt="$item->title ?? ''"
                            class="w-full h-48 object-cover group-hover:scale-105 transition-transform duration-300"
                            loading="lazy"
                        />
                        @elseif($item->file_type === 'video')
                        <div class="w-full h-48 bg-brandGray-200 flex items-center justify-center">
                            <svg class="w-12 h-12 text-brandGray-400" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M8 5v10l8-5-8-5z"></path>
                            </svg>
                        </div>
                        @else
                        <div class="w-full h-48 bg-brandGray-200 flex items-center justify-center">
                            <svg class="w-12 h-12 text-brandGray-400" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm12 12H4l4-8 3 6 2-4 3 6z" clip-rule="evenodd"></path>
                            </svg>
                        </div>
                        @endif
                        
                        <!-- Overlay -->
                        <div class="absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-30 transition-all duration-300 flex items-center justify-center">
                            <svg class="w-8 h-8 text-white opacity-0 group-hover:opacity-100 transition-opacity duration-300" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM9.555 7.168A1 1 0 008 8v4a1 1 0 001.555.832l3-2a1 1 0 000-1.664l-3-2z" clip-rule="evenodd"></path>
                            </svg>
                        </div>
                    </div>
                    
                    @if($item->title)
                    <h3 class="mt-2 text-sm font-medium text-brandGray-900 truncate">
                        {{ $item->title }}
                    </h3>
                    @endif
                </div>
                @endforeach
            </div>
            @if($items->hasPages())
            <div class="mt-8 flex justify-center">
                {{ $items->links() }}
            </div>
            @endif
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

<!-- Modal -->
<div id="galleryModal" class="fixed inset-0 bg-black bg-opacity-75 z-50 hidden flex items-center justify-center p-4">
    <div class="max-w-4xl max-h-full bg-white rounded-lg overflow-hidden">
        <div class="flex justify-between items-center p-4 border-b">
            <h3 id="modalTitle" class="text-lg font-semibold text-brandGray-900"></h3>
            <button onclick="closeModal()" class="text-brandGray-400 hover:text-brandGray-600">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>
        <div class="p-4">
            <img id="modalImage" src="" alt="" class="max-w-full max-h-96 mx-auto">
            <p id="modalDescription" class="mt-4 text-brandGray-600 text-center"></p>
        </div>
    </div>
</div>

<script>
function openModal(imageSrc, title, description) {
    document.getElementById('modalImage').src = imageSrc;
    document.getElementById('modalImage').alt = title;
    document.getElementById('modalTitle').textContent = title;
    document.getElementById('modalDescription').textContent = description;
    document.getElementById('galleryModal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function closeModal() {
    document.getElementById('galleryModal').classList.add('hidden');
    document.body.style.overflow = 'auto';
}

// Close modal on escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeModal();
    }
});

// Close modal on background click
document.getElementById('galleryModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeModal();
    }
});
</script>
@endsection

@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-brandBlue-600 mb-4">{{ __('public.Upcoming Events') }}</h1>
        <p class="text-brandGray-600">{{ __('public.Join us for our upcoming events and activities') }}</p>
    </div>

    @if($events->count() > 0)
        <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
            @foreach($events as $event)
                <div class="bg-white rounded-lg shadow-sm border hover:shadow-md transition-shadow">
                    <div class="p-6">
                        <div class="flex items-center justify-between mb-4">
                            <div class="bg-brandBlue-100 text-brandBlue-600 px-3 py-1 rounded-full text-sm font-medium">
                                {{ \Carbon\Carbon::parse($event->date)->format('M d') }}
                            </div>
                            <div class="text-sm text-brandGray-500">
                                {{ \Carbon\Carbon::parse($event->date)->format('Y') }}
                            </div>
                        </div>
                        
                        <h2 class="text-xl font-semibold text-brandBlue-600 mb-3">
                            <a href="{{ route('public.events.show', [app()->getLocale(), $event->id]) }}" 
                               class="hover:text-brandBlue-700">
                                {{ $event->title }}
                            </a>
                        </h2>
                        
                        @if(isset($event->description))
                            <p class="text-brandGray-600 mb-4">{{ Str::limit($event->description, 100) }}</p>
                        @endif
                        
                        <div class="flex items-center justify-between">
                            <div class="text-sm text-brandGray-500">
                                @if(isset($event->time))
                                    <span>{{ $event->time }}</span>
                                @endif
                            </div>
                            <a href="{{ route('public.events.show', [app()->getLocale(), $event->id]) }}" 
                               class="text-brandBlue-600 hover:text-brandBlue-700 font-medium text-sm">
                                {{ __('public.Learn More') }} →
                            </a>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <!-- Pagination -->
        @if(method_exists($events, 'hasPages') && $events->hasPages())
            <div class="flex justify-center">
                {{ $events->links() }}
            </div>
        @endif
    @else
        <div class="text-center py-12">
            <div class="text-brandGray-400 mb-4">
                <svg class="w-16 h-16 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                </svg>
            </div>
            <h3 class="text-xl font-medium text-brandGray-600 mb-2">{{ __('public.No Events Scheduled') }}</h3>
            <p class="text-brandGray-500">{{ __('public.Check back later for upcoming events and activities') }}</p>
        </div>
    @endif
</div>
@endsection

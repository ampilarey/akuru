@extends('public.layouts.public')

@section('title', $event->title . ' - ' . config('app.name'))

@section('content')
<div class="bg-white py-16">
    <div class="container mx-auto px-4">
        <div class="max-w-4xl mx-auto">
            <!-- Event Header -->
            <div class="mb-8">
                <div class="flex items-center text-sm text-brandGray-500 mb-4">
                    <a href="{{ route('public.events.index', app()->getLocale()) }}" class="hover:text-brandMaroon-600">
                        {{ __('public.Events') }}
                    </a>
                    <svg class="w-4 h-4 mx-2" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path>
                    </svg>
                    <span>{{ $event->title }}</span>
                </div>

                <h1 class="text-4xl font-bold text-brandGray-900 mb-4">
                    {{ $event->title }}
                </h1>

                <!-- Event Meta -->
                <div class="flex flex-wrap items-center gap-6 text-brandGray-600 mb-6">
                    <div class="flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd"></path>
                        </svg>
                        <span>{{ $event->date->format('F j, Y') }}</span>
                    </div>
                    
                    @if($event->time)
                    <div class="flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"></path>
                        </svg>
                        <span>{{ $event->time }}</span>
                    </div>
                    @endif

                    @if($event->location)
                    <div class="flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M5.05 4.05a7 7 0 119.9 9.9L10 18.9l-4.95-4.95a7 7 0 010-9.9zM10 11a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd"></path>
                        </svg>
                        <span>{{ $event->location }}</span>
                    </div>
                    @endif
                </div>
            </div>

            <!-- Event Content -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <!-- Main Content -->
                <div class="lg:col-span-2">
                    @if($event->image)
                    <div class="mb-8">
                        <img src="{{ Storage::url($event->image) }}" 
                             alt="{{ $event->title }}" 
                             class="w-full h-64 object-cover rounded-lg">
                    </div>
                    @endif

                    <div class="prose max-w-none">
                        {!! $event->description !!}
                    </div>

                    @if($event->requirements)
                    <div class="mt-8">
                        <h3 class="text-xl font-semibold text-brandGray-900 mb-4">
                            {{ __('public.Requirements') }}
                        </h3>
                        <div class="prose max-w-none">
                            {!! $event->requirements !!}
                        </div>
                    </div>
                    @endif
                </div>

                <!-- Sidebar -->
                <div class="space-y-6">
                    <!-- Event Details Card -->
                    <div class="bg-brandMaroon-50 rounded-lg p-6">
                        <h3 class="text-lg font-semibold text-brandGray-900 mb-4">
                            {{ __('public.Event Details') }}
                        </h3>
                        
                        <div class="space-y-4">
                            <div>
                                <span class="text-sm font-medium text-brandGray-700">{{ __('public.Date') }}</span>
                                <p class="text-brandGray-900">{{ $event->date->format('F j, Y') }}</p>
                            </div>
                            
                            @if($event->time)
                            <div>
                                <span class="text-sm font-medium text-brandGray-700">{{ __('public.Time') }}</span>
                                <p class="text-brandGray-900">{{ $event->time }}</p>
                            </div>
                            @endif
                            
                            @if($event->location)
                            <div>
                                <span class="text-sm font-medium text-brandGray-700">{{ __('public.Location') }}</span>
                                <p class="text-brandGray-900">{{ $event->location }}</p>
                            </div>
                            @endif
                            
                            @if($event->audience)
                            <div>
                                <span class="text-sm font-medium text-brandGray-700">{{ __('public.Audience') }}</span>
                                <p class="text-brandGray-900">{{ ucfirst($event->audience) }}</p>
                            </div>
                            @endif
                        </div>
                    </div>

                    <!-- Registration -->
                    @if($event->registration_required)
                    <div class="bg-white border border-brandGray-200 rounded-lg p-6">
                        <h3 class="text-lg font-semibold text-brandGray-900 mb-4">
                            {{ __('public.Registration') }}
                        </h3>
                        <p class="text-brandGray-600 mb-4">
                            {{ __('public.registration_required_message') }}
                        </p>
                        <a href="{{ route('public.contact.create', app()->getLocale()) }}" 
                           class="btn-primary w-full text-center">
                            {{ __('public.Register Now') }}
                        </a>
                    </div>
                    @endif

                    <!-- Contact for More Info -->
                    <div class="bg-brandGray-50 rounded-lg p-6">
                        <h3 class="text-lg font-semibold text-brandGray-900 mb-4">
                            {{ __('public.Need More Information?') }}
                        </h3>
                        <p class="text-brandGray-600 mb-4">
                            {{ __('public.contact_for_more_info') }}
                        </p>
                        <div class="space-y-2 text-sm">
                            <div class="flex items-center">
                                <svg class="w-4 h-4 mr-2 text-brandMaroon-600" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M2 3a1 1 0 011-1h2.153a1 1 0 01.986.836l.74 4.435a1 1 0 01-.54 1.06l-1.548.773a11.037 11.037 0 006.105 6.105l.774-1.548a1 1 0 011.059-.54l4.435.74a1 1 0 01.836.986V17a1 1 0 01-1 1h-2C7.82 18 2 12.18 2 5V3z"></path>
                                </svg>
                                <span>+960 797 2434</span>
                            </div>
                            <div class="flex items-center">
                                <svg class="w-4 h-4 mr-2 text-brandMaroon-600" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z"></path>
                                    <path d="M18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z"></path>
                                </svg>
                                <span>info@akuru.edu.mv</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

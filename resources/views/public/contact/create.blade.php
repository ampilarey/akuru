@extends('public.layouts.public')

@section('title', __('public.Contact Us') . ' - ' . config('app.name'))

@push('scripts')
<script type="application/ld+json">
{
  "@@context": "https://schema.org",
  "@type": "EducationalOrganization",
  "name": "Akuru Institute",
  "description": "Islamic education in the Maldives - Quran, Arabic, and Islamic Studies",
  "url": "{{ config('app.url') }}",
  "address": {
    "@type": "PostalAddress",
    "streetAddress": "M. Guldhastha Aage, Muniya Magu",
    "addressLocality": "Malé",
    "postalCode": "20026",
    "addressCountry": "MV"
  },
  "telephone": "+960-797-2434",
  "email": "info@akuru.edu.mv",
  "openingHoursSpecification": [
    {"@type": "OpeningHoursSpecification", "dayOfWeek": ["Sunday","Monday","Tuesday","Wednesday","Thursday"], "opens": "08:00", "closes": "16:00"},
    {"@type": "OpeningHoursSpecification", "dayOfWeek": "Friday", "opens": "08:00", "closes": "12:00"}
  ]
}
</script>
@endpush

@section('content')
<div class="bg-white py-16">
    <div class="container mx-auto px-4">
        <div class="max-w-6xl mx-auto">
            <!-- Header -->
            <div class="text-center mb-12">
                <h1 class="text-4xl font-bold text-brandGray-900 mb-4">
                    {{ __('public.Contact Us') }}
                </h1>
                <p class="text-xl text-brandGray-600 max-w-2xl mx-auto">
                    {{ __('public.contact_description') }}
                </p>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-12">
                <!-- Contact Form -->
                <div class="bg-white rounded-lg shadow-lg p-8">
                    <h2 class="text-2xl font-semibold text-brandGray-900 mb-6">
                        {{ __('public.Send us a Message') }}
                    </h2>
                    
                    @if(session('success'))
                        <div class="mb-6 p-4 bg-green-100 border border-green-400 text-green-700 rounded-md">
                            {{ session('success') }}
                        </div>
                    @endif

                    <form method="POST" action="{{ route('public.contact.store', app()->getLocale()) }}" class="space-y-6">
                        @csrf
                        {{-- Honeypot: hidden field, bots fill it, humans don't --}}
                        <div style="display:none" aria-hidden="true">
                            <input type="text" name="website" tabindex="-1" autocomplete="off" value="">
                        </div>
                        
                        <!-- Name -->
                        <div>
                            <label for="name" class="block text-sm font-medium text-brandGray-700 mb-2">
                                {{ __('public.Full Name') }} <span class="text-red-500">*</span>
                            </label>
                            <input type="text" name="name" id="name" 
                                   value="{{ old('name') }}" required
                                   class="w-full px-3 py-2 border border-brandGray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-brandMaroon-500">
                            @error('name')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Email -->
                        <div>
                            <label for="email" class="block text-sm font-medium text-brandGray-700 mb-2">
                                {{ __('public.Email Address') }}
                            </label>
                            <input type="email" name="email" id="email" 
                                   value="{{ old('email') }}"
                                   class="w-full px-3 py-2 border border-brandGray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-brandMaroon-500">
                            @error('email')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Phone -->
                        <div>
                            <label for="phone" class="block text-sm font-medium text-brandGray-700 mb-2">
                                {{ __('public.Phone Number') }}
                            </label>
                            <input type="tel" name="phone" id="phone" 
                                   value="{{ old('phone') }}"
                                   class="w-full px-3 py-2 border border-brandGray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-brandMaroon-500">
                            @error('phone')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Message -->
                        <div>
                            <label for="message" class="block text-sm font-medium text-brandGray-700 mb-2">
                                {{ __('public.Message') }} <span class="text-red-500">*</span>
                            </label>
                            <textarea name="message" id="message" rows="6" required
                                      class="w-full px-3 py-2 border border-brandGray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-brandMaroon-500"
                                      placeholder="{{ __('public.contact_message_placeholder') }}">{{ old('message') }}</textarea>
                            @error('message')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Submit Button -->
                        <div>
                            <button type="submit" class="btn-primary w-full">
                                {{ __('public.Send Message') }}
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Contact Information -->
                <div class="space-y-8">
                    <!-- Contact Details -->
                    <div class="bg-brandMaroon-50 rounded-lg p-8">
                        <h2 class="text-2xl font-semibold text-brandGray-900 mb-6">
                            {{ __('public.Get in Touch') }}
                        </h2>
                        
                        <div class="space-y-6">
                            <!-- Address -->
                            <div class="flex items-start">
                                <div class="w-10 h-10 bg-brandMaroon-600 text-white rounded-full flex items-center justify-center mr-4 flex-shrink-0">
                                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M5.05 4.05a7 7 0 119.9 9.9L10 18.9l-4.95-4.95a7 7 0 010-9.9zM10 11a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd"></path>
                                    </svg>
                                </div>
                        <div>
                            <h3 class="font-semibold text-brandGray-900 mb-1">{{ __('public.Address') }}</h3>
                            <a href="https://maps.google.com/?q=M.+Guldhastha+Aage,+Muniya+Magu,+Malé+20026,+Maldives" target="_blank" rel="noopener" class="text-brandGray-600 hover:text-brandMaroon-600 transition-colors">{{ __('public.address') }}</a>
                        </div>
                            </div>

                            <!-- Phone -->
                            <div class="flex items-start">
                                <div class="w-10 h-10 bg-brandMaroon-600 text-white rounded-full flex items-center justify-center mr-4 flex-shrink-0">
                                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M2 3a1 1 0 011-1h2.153a1 1 0 01.986.836l.74 4.435a1 1 0 01-.54 1.06l-1.548.773a11.037 11.037 0 006.105 6.105l.774-1.548a1 1 0 011.059-.54l4.435.74a1 1 0 01.836.986V17a1 1 0 01-1 1h-2C7.82 18 2 12.18 2 5V3z"></path>
                                    </svg>
                                </div>
                                <div>
                                    <h3 class="font-semibold text-brandGray-900 mb-1">{{ __('public.Phone') }}</h3>
                                    <a href="tel:+9607972434" class="text-brandGray-600 hover:text-brandMaroon-600 transition-colors">+960 797 2434</a>
                                </div>
                            </div>

                            <!-- Email -->
                            <div class="flex items-start">
                                <div class="w-10 h-10 bg-brandMaroon-600 text-white rounded-full flex items-center justify-center mr-4 flex-shrink-0">
                                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z"></path>
                                        <path d="M18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z"></path>
                                    </svg>
                                </div>
                                <div>
                                    <h3 class="font-semibold text-brandGray-900 mb-1">{{ __('public.Email') }}</h3>
                                    <a href="mailto:info@akuru.edu.mv" class="text-brandGray-600 hover:text-brandMaroon-600 transition-colors">info@akuru.edu.mv</a>
                                </div>
                            </div>

                            <!-- Viber -->
                            <div class="flex items-start">
                                <div class="w-10 h-10 bg-purple-600 text-white rounded-full flex items-center justify-center mr-4 flex-shrink-0">
                                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M11.993 0h-.036C5.5 0 .527 4.972.527 11.473c0 3.107 1.2 5.943 3.17 8.053V23l2.953-1.628a11.03 11.03 0 005.343 1.364h.036c6.457 0 11.43-4.972 11.43-11.472C23.459 4.813 18.487 0 11.993 0zm1.164 15.516s-.27-.031-.406-.186l-.92-.999c-.283.09-.576.135-.872.135-1.637 0-2.97-1.25-2.97-2.793 0-1.543 1.333-2.793 2.97-2.793s2.97 1.25 2.97 2.793c0 .64-.226 1.228-.606 1.7l.644.7c.15.161.176.396.056.583a.44.44 0 01-.366.193l-.5-.333zm3.454 1.04c-.18.504-.885 1.02-1.46 1.141-.383.08-.877.143-2.55-.548-2.143-.882-3.523-3.05-3.63-3.192-.107-.14-.873-1.162-.873-2.217s.55-1.553.76-1.77a.8.8 0 01.576-.27c.143 0 .286.003.41.01.132.007.309-.05.483.37.18.43.611 1.493.663 1.601.053.108.088.235.017.376-.07.14-.105.226-.211.347-.107.12-.225.269-.32.361-.107.103-.219.215-.094.421.125.207.557.92 1.196 1.49.82.73 1.513.956 1.725 1.063.212.107.334.09.457-.054.125-.143.536-.626.68-.84.143-.212.285-.177.481-.107.197.07 1.253.592 1.467.7.214.107.356.16.408.25.053.09.053.52-.127 1.024zm-6.01-5.12a1.49 1.49 0 110 2.98 1.49 1.49 0 010-2.98z"/></svg>
                                </div>
                                <div>
                                    <h3 class="font-semibold text-brandGray-900 mb-1">Viber</h3>
                                    <a href="viber://chat?number=%2B9607972434" class="text-brandGray-600 hover:text-purple-600 transition-colors">+960 797 2434</a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Office Hours -->
                    <div class="bg-brandGray-50 rounded-lg p-8">
                        <h3 class="text-xl font-semibold text-brandGray-900 mb-4">
                            {{ __('public.Office Hours') }}
                        </h3>
                        <div class="space-y-2 text-brandGray-600">
                            <div class="flex justify-between">
                                <span>{{ __('public.Sunday - Thursday') }}</span>
                                <span>8:00 AM - 4:00 PM</span>
                            </div>
                            <div class="flex justify-between">
                                <span>{{ __('public.Friday') }}</span>
                                <span>8:00 AM - 12:00 PM</span>
                            </div>
                            <div class="flex justify-between">
                                <span>{{ __('public.Saturday') }}</span>
                                <span>{{ __('public.Closed') }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Map -->
                    <div class="bg-white border border-brandGray-200 rounded-lg p-4 overflow-hidden">
                        <h3 class="text-xl font-semibold text-brandGray-900 mb-4">{{ __('public.Our Location') }}</h3>
                        <div class="aspect-video bg-brandGray-100 rounded-lg">
                            {{-- Set GOOGLE_MAPS_EMBED_URL in .env for exact location embed from Google Maps > Share > Embed --}}
                            <iframe src="{{ config('services.google.maps_embed_url') }}" width="100%" height="100%" style="border:0;" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade" title="Akuru Institute - M. Guldhastha Aage, Muniya Magu, Malé"></iframe>
                        </div>
                        <p class="text-sm text-brandGray-500 mt-2">{{ __('public.View larger map') }}</p>
                    </div>

                    <!-- Quick Links -->
                    <div class="bg-white border border-brandGray-200 rounded-lg p-8">
                        <h3 class="text-xl font-semibold text-brandGray-900 mb-4">
                            {{ __('public.Quick Links') }}
                        </h3>
                        <div class="space-y-3">
                            <a href="{{ route('public.admissions.create', app()->getLocale()) }}" 
                               class="block text-brandMaroon-600 hover:text-brandMaroon-800 transition-colors">
                                {{ __('public.Apply for Admission') }}
                            </a>
                            <a href="{{ route('public.courses.index') }}" 
                               class="block text-brandMaroon-600 hover:text-brandMaroon-800 transition-colors">
                                {{ __('public.View Courses') }}
                            </a>
                            <a href="{{ route('public.news.index', app()->getLocale()) }}" 
                               class="block text-brandMaroon-600 hover:text-brandMaroon-800 transition-colors">
                                {{ __('public.Latest News') }}
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

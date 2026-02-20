@extends('public.layouts.public')

@section('content')
<section class="py-8 bg-brandBeige-50 min-h-screen">
    <div class="container mx-auto px-4">
        <div class="flex flex-col lg:flex-row gap-8">

            {{-- Sidebar --}}
            <aside class="lg:w-56 shrink-0">
                <div class="card p-4">
                    <p class="text-xs text-gray-500 uppercase tracking-wider font-semibold mb-3 px-2">My Portal</p>
                    <nav class="space-y-1">
                        @php
                            $portalLinks = [
                                ['route' => 'portal.dashboard',     'label' => 'Dashboard',    'icon' => 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6'],
                                ['route' => 'portal.enrollments',   'label' => 'Enrollments',  'icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2'],
                                ['route' => 'portal.payments',      'label' => 'Payments',     'icon' => 'M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z'],
                                ['route' => 'portal.certificates',  'label' => 'Certificates', 'icon' => 'M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z'],
                                ['route' => 'portal.profile',       'label' => 'Profile',      'icon' => 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z'],
                            ];
                        @endphp
                        @foreach($portalLinks as $link)
                        <a href="{{ route($link['route']) }}"
                           class="flex items-center gap-2 px-3 py-2 rounded-lg text-sm font-medium transition-colors
                                  {{ request()->routeIs($link['route']) ? 'bg-brandMaroon-600 text-white' : 'text-gray-600 hover:bg-brandBeige-100 hover:text-brandMaroon-600' }}">
                            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $link['icon'] }}"/>
                            </svg>
                            {{ $link['label'] }}
                        </a>
                        @endforeach
                    </nav>
                </div>
            </aside>

            {{-- Main content --}}
            <div class="flex-1 min-w-0">
                @yield('portal-content')
            </div>
        </div>
    </div>
</section>
@endsection

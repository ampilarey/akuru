@extends('public.layouts.public')

@section('title', 'My Dashboard')

@section('content')
<section class="bg-gradient-to-br from-brandMaroon-50 to-brandBeige-100 py-10">
    <div class="container mx-auto px-4">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <p class="text-sm text-brandMaroon-600 font-medium uppercase tracking-wider mb-1">Welcome back</p>
                <h1 class="text-3xl font-bold text-brandMaroon-900">{{ $user->name }}</h1>
            </div>
            <div class="flex gap-3 flex-wrap">
                <a href="{{ route('my.enrollments') }}" class="btn-secondary text-sm">
                    My Enrollments
                </a>
                <a href="{{ route('public.courses.index') }}" class="btn-primary text-sm">
                    Browse Courses
                </a>
            </div>
        </div>
    </div>
</section>

<section class="py-10">
    <div class="container mx-auto px-4">

        {{-- Password setup prompt --}}
        @if(!$hasPassword)
        <div class="mb-8 p-4 bg-amber-50 border border-amber-200 rounded-xl flex flex-wrap items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <svg class="w-6 h-6 text-amber-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                </svg>
                <div>
                    <p class="font-medium text-amber-800">Set a password for easier login</p>
                    <p class="text-sm text-amber-700">Currently you can only log in via OTP. Add a password to log in with just your mobile / email.</p>
                </div>
            </div>
            <a href="{{ route('account.set-password') }}" class="shrink-0 inline-flex items-center gap-1 px-4 py-2 bg-amber-500 text-white rounded-lg text-sm font-medium hover:bg-amber-600 transition-colors">
                Set password
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                </svg>
            </a>
        </div>
        @endif

        <div class="grid lg:grid-cols-3 gap-8">

            {{-- Left / main --}}
            <div class="lg:col-span-2 space-y-8">

                {{-- Summary cards --}}
                <div class="grid sm:grid-cols-3 gap-4">
                    <div class="card p-5 text-center">
                        <p class="text-3xl font-bold text-brandMaroon-700">{{ $enrollments->count() }}</p>
                        <p class="text-sm text-gray-500 mt-1">Total enrollments</p>
                    </div>
                    <div class="card p-5 text-center">
                        <p class="text-3xl font-bold text-green-600">{{ $activeEnrollments->count() }}</p>
                        <p class="text-sm text-gray-500 mt-1">Active</p>
                    </div>
                    <div class="card p-5 text-center">
                        <p class="text-3xl font-bold text-yellow-600">{{ $pendingEnrollments->count() }}</p>
                        <p class="text-sm text-gray-500 mt-1">Pending</p>
                    </div>
                </div>

                {{-- My Enrollments --}}
                <div class="card overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                        <h2 class="text-lg font-bold text-gray-900">My Enrollments</h2>
                        <a href="{{ route('my.enrollments') }}" class="text-sm text-brandMaroon-600 hover:underline">View all →</a>
                    </div>

                    @if($enrollments->isEmpty())
                        <div class="p-8 text-center text-gray-500">
                            <svg class="w-12 h-12 mx-auto mb-3 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                            </svg>
                            <p class="font-medium text-gray-600 mb-1">No enrollments yet</p>
                            <p class="text-sm">Browse our courses and enroll today.</p>
                            <a href="{{ route('public.courses.index') }}" class="mt-4 inline-block btn-primary text-sm">Browse courses</a>
                        </div>
                    @else
                        <div class="divide-y divide-gray-100">
                            @foreach($enrollments->take(5) as $enrollment)
                            @php
                                $statusColors = [
                                    'active'          => 'bg-green-100 text-green-800',
                                    'pending'         => 'bg-yellow-100 text-yellow-800',
                                    'pending_payment' => 'bg-blue-100 text-blue-800',
                                    'cancelled'       => 'bg-red-100 text-red-800',
                                    'rejected'        => 'bg-red-100 text-red-800',
                                    'draft'           => 'bg-gray-100 text-gray-600',
                                ];
                                $cls = $statusColors[$enrollment->status] ?? 'bg-gray-100 text-gray-600';
                                $latestPayment = $enrollment->payment;
                            @endphp
                            <div class="px-6 py-4 flex items-center justify-between gap-4">
                                <div class="min-w-0">
                                    <p class="font-semibold text-gray-900 truncate">{{ $enrollment->course?->title ?? '—' }}</p>
                                    <p class="text-sm text-gray-500">{{ $enrollment->student?->full_name ?? '—' }}</p>
                                    @if($enrollment->enrolled_at)
                                        <p class="text-xs text-gray-400">{{ $enrollment->enrolled_at->format('d M Y') }}</p>
                                    @endif
                                </div>
                                <div class="shrink-0 text-right space-y-1">
                                    <span class="inline-block text-xs px-2 py-0.5 rounded-full font-medium {{ $cls }}">
                                        {{ ucfirst(str_replace('_', ' ', $enrollment->status)) }}
                                    </span>
                                    @if($latestPayment && in_array($latestPayment->status, ['paid', 'completed']) && Route::has('payment.receipt'))
                                        <br>
                                        <a href="{{ route('payment.receipt', $latestPayment) }}" class="text-xs text-brandMaroon-600 hover:underline">
                                            View receipt
                                        </a>
                                    @endif
                                </div>
                            </div>
                            @endforeach
                        </div>

                        @if($enrollments->count() > 5)
                        <div class="px-6 py-3 bg-gray-50 text-center">
                            <a href="{{ route('my.enrollments') }}" class="text-sm text-brandMaroon-600 hover:underline">
                                + {{ $enrollments->count() - 5 }} more — View all
                            </a>
                        </div>
                        @endif
                    @endif
                </div>
            </div>

            {{-- Right sidebar --}}
            <div class="space-y-6">

                {{-- Quick links --}}
                <div class="card p-6">
                    <h3 class="font-bold text-gray-900 mb-4">Quick links</h3>
                    <nav class="space-y-2">
                        <a href="{{ route('my.enrollments') }}"
                           class="flex items-center gap-3 p-2 rounded-lg hover:bg-brandBeige-50 text-gray-700 hover:text-brandMaroon-600 transition-colors">
                            <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2"/>
                            </svg>
                            <span class="text-sm font-medium">All enrollments</span>
                        </a>
                        <a href="{{ route('public.courses.index') }}"
                           class="flex items-center gap-3 p-2 rounded-lg hover:bg-brandBeige-50 text-gray-700 hover:text-brandMaroon-600 transition-colors">
                            <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                            </svg>
                            <span class="text-sm font-medium">Browse courses</span>
                        </a>
                        @if(!$hasPassword)
                        <a href="{{ route('account.set-password') }}"
                           class="flex items-center gap-3 p-2 rounded-lg hover:bg-brandBeige-50 text-amber-700 hover:text-amber-800 transition-colors">
                            <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                            </svg>
                            <span class="text-sm font-medium">Set a password</span>
                        </a>
                        @endif
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit"
                               class="w-full flex items-center gap-3 p-2 rounded-lg hover:bg-red-50 text-gray-500 hover:text-red-600 transition-colors text-left">
                                <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                                </svg>
                                <span class="text-sm font-medium">Sign out</span>
                            </button>
                        </form>
                    </nav>
                </div>

                {{-- Open courses --}}
                @if($openCourses->count() > 0)
                <div class="card p-6">
                    <h3 class="font-bold text-gray-900 mb-4">Open for enrollment</h3>
                    <div class="space-y-3">
                        @foreach($openCourses as $course)
                        <a href="{{ LaravelLocalization::localizeURL(route('public.courses.show', $course->slug)) }}"
                           class="block group">
                            <p class="text-sm font-medium text-gray-800 group-hover:text-brandMaroon-600 transition-colors leading-snug">
                                {{ $course->title }}
                            </p>
                            <p class="text-xs text-gray-500 mt-0.5">
                                @if($course->fee) {{ number_format($course->fee, 2) }} MVR @else Free @endif
                                @if($course->isFull()) &middot; <span class="text-red-500">Fully booked</span> @endif
                            </p>
                        </a>
                        @endforeach
                    </div>
                    <a href="{{ route('public.courses.index') }}" class="block mt-4 text-sm text-brandMaroon-600 hover:underline">
                        All courses →
                    </a>
                </div>
                @endif

            </div>
        </div>
    </div>
</section>
@endsection

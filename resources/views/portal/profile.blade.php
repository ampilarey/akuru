@extends('portal.layout')
@section('title', 'My Profile')

@section('portal-content')
<h1 class="text-2xl font-bold text-brandMaroon-900 mb-6">My Profile</h1>

@if(session('success'))
    <div class="mb-4 p-3 bg-green-100 text-green-800 rounded text-sm">{{ session('success') }}</div>
@endif
@if($errors->any())
    <div class="mb-4 p-3 bg-red-100 text-red-800 rounded text-sm">{{ $errors->first() }}</div>
@endif

<div class="grid lg:grid-cols-2 gap-6">

    {{-- Account info --}}
    <div class="card p-6">
        <h2 class="font-bold text-gray-900 mb-4">Account Information</h2>
        <form method="POST" action="{{ route('portal.profile.update') }}">
            @csrf

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Full name <span class="text-red-500">*</span></label>
                <input type="text" name="name" value="{{ old('name', $user->name) }}" required
                       class="form-input w-full">
            </div>

            {{-- Contact info (read-only display) --}}
            @if($contacts->get('mobile'))
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Mobile</label>
                <input type="text" value="{{ $contacts->get('mobile')->value }}" disabled
                       class="form-input w-full bg-gray-50 text-gray-500">
                <p class="text-xs text-gray-400 mt-1">Contact support to change your mobile number.</p>
            </div>
            @endif

            @if($contacts->get('email'))
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                <input type="text" value="{{ $contacts->get('email')->value }}" disabled
                       class="form-input w-full bg-gray-50 text-gray-500">
            </div>
            @endif

            <hr class="my-4">
            <h3 class="font-medium text-gray-700 mb-3">Change Password</h3>
            <p class="text-xs text-gray-500 mb-3">Leave blank to keep your current password.</p>

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">New password</label>
                <input type="password" name="password" autocomplete="new-password" minlength="8"
                       class="form-input w-full">
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Confirm password</label>
                <input type="password" name="password_confirmation" autocomplete="new-password"
                       class="form-input w-full">
            </div>

            <button type="submit" class="btn-primary">Save changes</button>
        </form>
    </div>

    {{-- Account status --}}
    <div class="space-y-4">
        <div class="card p-5">
            <h2 class="font-bold text-gray-900 mb-3">Account Status</h2>
            <dl class="space-y-2 text-sm">
                <div class="flex justify-between">
                    <dt class="text-gray-500">Member since</dt>
                    <dd class="font-medium">{{ $user->created_at->format('d M Y') }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-gray-500">Password login</dt>
                    <dd>
                        @if($user->password)
                            <span class="text-green-600 font-medium">✓ Set</span>
                        @else
                            <a href="{{ route('account.set-password') }}" class="text-amber-600 hover:underline font-medium">Set password →</a>
                        @endif
                    </dd>
                </div>
            </dl>
        </div>

        <div class="card p-5">
            <h2 class="font-bold text-gray-900 mb-3">Quick Links</h2>
            <nav class="space-y-2 text-sm">
                <a href="{{ route('portal.enrollments') }}" class="block text-brandMaroon-600 hover:underline">My Enrollments</a>
                <a href="{{ route('portal.payments') }}" class="block text-brandMaroon-600 hover:underline">My Payments</a>
                <a href="{{ route('portal.certificates') }}" class="block text-brandMaroon-600 hover:underline">My Certificates</a>
                <a href="{{ route('public.courses.index') }}" class="block text-brandMaroon-600 hover:underline">Browse Courses</a>
            </nav>
        </div>
    </div>

</div>
@endsection

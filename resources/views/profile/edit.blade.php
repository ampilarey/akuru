<x-app-layout>
    <x-slot name="header">
        <h1 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('account.profile_title') }}
        </h1>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <div class="max-w-xl">
                    @include('profile.partials.update-profile-information-form')
                </div>
            </div>

            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <div class="max-w-xl">
                    @include('profile.partials.update-password-form')
                </div>
            </div>

            {{-- A super admin deleting themselves here locks everyone out of
                 the platform (users has no soft deletes). ProfileController
                 and the User model both refuse it, but an offered button that
                 then refuses is a trap — do not show it at all. --}}
            @unless(auth()->user()->hasRole('super_admin'))
            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <div class="max-w-xl">
                    @include('profile.partials.delete-user-form')
                </div>
            </div>
            @else
            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <div class="max-w-xl">
                    <h2 class="text-lg font-medium text-gray-900">{{ __('account.profile_delete_title') }}</h2>
                    <p class="mt-1 text-sm text-gray-600">
                        {{ __('account.profile_delete_super_admin') }}
                    </p>
                </div>
            </div>
            @endunless
        </div>
    </div>
</x-app-layout>

@extends('public.layouts.public')

@section('title', __('public.My Library') . ' - ' . config('app.name'))

@section('content')
<div class="container mx-auto px-4 py-10 max-w-4xl">
    <h1 class="text-3xl font-bold text-brandMaroon-900 mb-6">{{ __('public.My Library') }}</h1>

    <h2 class="text-xl font-semibold mb-3">{{ __('public.Continue reading') }}</h2>
    <div class="mb-8 grid gap-3">
        @if(count($library['continue']) === 0)
            <p class="text-gray-500">{{ __('public.Nothing in progress — find something in the library.') }}</p>
        @endif
        @foreach($library['continue'] as $row)
            <a href="{{ route('public.library.read', ['slug' => $row['slug'], 'page' => $row['current_page']]) }}" class="flex items-center justify-between rounded-lg border bg-white p-4 hover:shadow-sm">
                <div>
                    <div class="font-medium text-brandMaroon-900">{{ $row['title'] }}</div>
                    <div class="text-sm text-gray-500">{{ __('public.Page') }} {{ $row['current_page'] }} · {{ $row['progress_percent'] }}%{{ $row['completed'] ? ' · '.__('public.Completed') : '' }}</div>
                </div>
                <span class="btn-secondary">{{ $row['completed'] ? __('public.Read again') : __('public.Continue') }}</span>
            </a>
        @endforeach
    </div>

    <h2 class="text-xl font-semibold mb-3">{{ __('public.Purchases') }}</h2>
    <div class="mb-8 grid gap-2">
        @if(count($library['purchases']) === 0)
            <p class="text-gray-500">{{ __('public.No purchases yet.') }}</p>
        @endif
        @foreach($library['purchases'] as $row)
            <div class="flex items-center justify-between rounded-lg border bg-white p-3">
                <div>
                    <span class="font-medium">{{ $row['title'] }}</span>
                    <span class="text-sm text-gray-500"> — {{ $row['currency'] }} {{ $row['amount'] }}</span>
                </div>
                <span class="text-sm text-gray-600">{{ $row['status'] }}{{ $row['purchased_at'] ? ' · '.$row['purchased_at'] : '' }}</span>
            </div>
        @endforeach
    </div>

    <h2 class="text-xl font-semibold mb-3">{{ __('public.Bookmarks') }}</h2>
    <div class="grid gap-2">
        @if(count($library['bookmarks']) === 0)
            <p class="text-gray-500">{{ __('public.No bookmarks yet.') }}</p>
        @endif
        @foreach($library['bookmarks'] as $row)
            <a href="{{ route('public.library.read', ['slug' => $row['slug'], 'page' => $row['page_number']]) }}" class="rounded-lg border bg-white p-3 hover:shadow-sm">
                <span class="font-medium">{{ $row['title'] }}</span>
                <span class="text-sm text-gray-500"> — {{ __('public.Page') }} {{ $row['page_number'] }}</span>
                @if($row['note'])
                    <div class="text-sm text-gray-600 mt-1">{{ $row['note'] }}</div>
                @endif
            </a>
        @endforeach
    </div>
</div>
@endsection

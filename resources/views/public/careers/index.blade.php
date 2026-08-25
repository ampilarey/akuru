@extends('public.layouts.public')

@section('content')
<div class="container mx-auto px-4 py-8">
    <h1 class="text-3xl font-bold text-brandMaroon-600 mb-6">{{ __('public.Careers') }}</h1>
    @if($postings->count() === 0)
        <p class="text-brandGray-600">{{ __('public.No open positions right now.') }}</p>
    @else
        <div class="grid gap-4">
            @foreach($postings as $posting)
                <article class="rounded-lg border bg-white p-6">
                    <h2 class="text-xl font-semibold text-brandMaroon-600">{{ $posting['title'] }}</h2>
                    @if(!empty($posting['department']))
                        <p class="text-sm text-brandGray-500">{{ $posting['department'] }}</p>
                    @endif
                    @if(!empty($posting['description']))
                        <p class="mt-2 text-brandGray-700">{{ $posting['description'] }}</p>
                    @endif
                    @if(!empty($posting['closes_at']))
                        <p class="mt-2 text-sm text-brandGray-500">Closes {{ $posting['closes_at'] }}</p>
                    @endif
                </article>
            @endforeach
        </div>
    @endif
</div>
@endsection

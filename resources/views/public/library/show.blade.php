@extends('public.layouts.public')

@section('title', $item['title'] . ' - ' . config('app.name'))
@section('description', $item['abstract'] ?? Str::limit(strip_tags($item['body'] ?? ''), 155))

@section('content')
<div class="container mx-auto px-4 py-10 max-w-4xl">
    <nav class="text-sm text-gray-500 mb-6 flex gap-2 items-center flex-wrap">
        <a href="{{ route('public.library.index') }}" class="hover:text-brandMaroon-600">{{ __('public.Digital Library') }}</a>
        <span>›</span>
        <span class="text-gray-700 truncate max-w-xs">{{ $item['title'] }}</span>
    </nav>

    <article>
        <header class="mb-8 flex flex-col gap-6 md:flex-row md:items-start">
          @if($item['cover_url'])
            <img src="{{ $item['cover_url'] }}" alt="{{ $item['title'] }}" class="w-40 shrink-0 rounded shadow-md object-cover" data-cover="{{ $item['slug'] }}">
          @endif
          <div class="min-w-0">
            <h1 class="text-4xl font-bold text-brandMaroon-900 mb-2 leading-tight">{{ $item['title'] }}</h1>
            @if($item['subtitle'])
                <p class="text-xl text-gray-600 mb-3">{{ $item['subtitle'] }}</p>
            @endif
            <div class="flex flex-wrap items-center gap-3 text-sm text-gray-500">
                <span class="rounded bg-brandBeige-100 px-2 py-0.5">{{ __('public.'.$item['content_type']) }}</span>
                @if($item['category'])
                    <span>{{ $item['category']['name'] }}</span>
                @endif
                @if($item['writer'])
                    <a href="{{ route('public.library.author', $item['writer']['slug']) }}" class="text-brandMaroon-700 hover:underline" rel="author">{{ $item['writer']['display_name'] }}</a>
                @endif
                @foreach($item['authors'] as $author)
                    @if(! $item['writer'] || $author !== $item['writer']['display_name'])
                        <span>{{ $author }}</span>
                    @endif
                @endforeach
                @if($item['published_at'])
                    <span>{{ $item['published_at'] }}</span>
                @endif
                @if($item['reading_time'])
                    <span>{{ $item['reading_time'] }} {{ __('public.min read') }}</span>
                @endif
            </div>
          </div>
        </header>

        @if($item['abstract'])
            <p class="text-lg text-gray-700 mb-6">{{ $item['abstract'] }}</p>
        @endif

        @if(!empty($item['toc']))
            {{-- §8.8: the table of contents, one line per entry as the writer typed it. --}}
            <details class="mb-6 rounded border bg-gray-50 p-4" open data-testid="toc">
                <summary class="cursor-pointer text-sm font-semibold text-gray-800">{{ __('public.Contents') }}</summary>
                <ol class="mt-2 list-decimal ps-5 text-sm text-gray-700">
                    @foreach(preg_split('/\r?\n/', $item['toc']) as $line)
                        @if(trim($line) !== '')
                            <li>{{ trim($line) }}</li>
                        @endif
                    @endforeach
                </ol>
            </details>
        @endif

        @if(!empty($item['affiliation']) || !empty($item['research_field']))
            <p class="mb-6 text-sm text-gray-600">
                @if(!empty($item['research_field']))<span>{{ __('public.Field') }}: {{ $item['research_field'] }}</span>@endif
                @if(!empty($item['affiliation']))<span class="{{ !empty($item['research_field']) ? 'ms-3' : '' }}">{{ __('public.Affiliation') }}: {{ $item['affiliation'] }}</span>@endif
            </p>
        @endif

        @if(!empty($item['citations']))
            <div class="mb-6 rounded border bg-gray-50 p-4">
                <h2 class="mb-2 text-sm font-semibold text-gray-800">{{ __('public.Citations') }}</h2>
                <pre class="whitespace-pre-wrap font-sans text-sm text-gray-600">{{ $item['citations'] }}</pre>
            </div>
        @endif

        @if($item['can_read'] && ($item['total_pages'] ?? 0) > 1)
            <div class="rounded-lg border bg-brandBeige-50 p-6 text-center">
                <a href="{{ route('public.library.read', ['slug' => $item['slug'], 'page' => $item['continue_page'] ?? 1]) }}" class="btn-primary">
                    {{ ($item['continue_page'] ?? 1) > 1 ? __('public.Continue reading') : __('public.Read online') }}
                </a>
                <p class="mt-2 text-sm text-gray-500">{{ $item['total_pages'] }} {{ __('public.pages') }}</p>
            </div>
        @elseif($item['can_read'] && $item['body'])
            <div class="prose max-w-none">{!! $item['body'] !!}</div>
        @elseif($item['requires_login'] && $item['access_type'] === 'paid')
            <div class="rounded-lg border bg-brandBeige-50 p-6 text-center">
                <p class="mb-3 text-gray-700">{{ $item['currency'] }} {{ $item['price'] }} — {{ __('public.Sign in to buy and read this item.') }}</p>
                <a href="{{ route('login') }}" class="btn-primary">{{ __('public.Sign in') }}</a>
            </div>
        @elseif($item['requires_login'])
            <div class="rounded-lg border bg-brandBeige-50 p-6 text-center">
                <p class="mb-3 text-gray-700">{{ __('public.Sign in to read this item for free.') }}</p>
                <a href="{{ route('login') }}" class="btn-primary">{{ __('public.Sign in') }}</a>
            </div>
        @elseif($item['locked'] && $item['access_type'] === 'paid' && $item['price'])
            <div class="rounded-lg border bg-brandBeige-50 p-6 text-center">
                <form method="POST" action="{{ route('public.library.checkout', $item['slug']) }}" class="inline-flex flex-wrap items-center justify-center gap-2">
                    @csrf
                    <input type="text" name="discount_code" class="form-input" placeholder="{{ __('public.Discount code') }}">
                    <button type="submit" class="btn-primary">{{ __('public.Buy for') }} {{ $item['currency'] }} {{ $item['price'] }}</button>
                    <button type="submit" name="pay_with_wallet" value="1" class="btn-secondary">{{ __('public.Pay with wallet') }}</button>
                </form>
                <p class="mt-2 text-sm text-gray-500">{{ __('public.Access opens as soon as the bank confirms your payment.') }}</p>
                @error('discount_code')
                    <p class="mt-2 text-sm text-red-700">{{ $message }}</p>
                @enderror
                @error('amount')
                    <p class="mt-2 text-sm text-red-700">{{ $message }}</p>
                @enderror
            </div>
        @elseif($item['locked'])
            <div class="rounded-lg border bg-brandBeige-50 p-6 text-center">
                <p class="text-gray-700">{{ __('public.This item is not yet available for online reading.') }}</p>
            </div>
        @endif

        @if(count($item['tags']))
            <div class="mt-8 flex flex-wrap gap-2">
                @foreach($item['tags'] as $tag)
                    <a href="{{ route('public.library.index', ['tag' => $tag['slug']]) }}" class="rounded-full bg-gray-100 px-3 py-1 text-sm text-gray-600 hover:bg-gray-200">#{{ $tag['name'] }}</a>
                @endforeach
            </div>
        @endif

        {{-- §8.8 copyright notice; §11.3 the AI-use declaration, when made. --}}
        <footer class="mt-8 border-t pt-4 text-xs text-gray-500" data-testid="copyright">
            <p>{{ $item['copyright_notice'] }}. {{ __('public.All rights reserved. Published by Akuru Institute.') }}
                <a href="{{ route('public.page.show', 'copyright-policy') }}" class="underline">{{ __('public.Copyright Policy') }}</a>
                · <a href="{{ route('public.page.show', 'reader-terms') }}" class="underline">{{ __('public.Reader Terms') }}</a>
            </p>
            @if($item['ai_use_declared'] ?? false)
                <p class="mt-1">{{ __('public.The author declares that AI tools were used in preparing this work.') }}</p>
            @endif
        </footer>
    </article>
</div>
@endsection

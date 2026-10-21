@extends('public.layouts.public')

@section('title', $item['title'] . ' - ' . config('app.name'))
@section('description', $item['abstract'] ?? Str::limit(strip_tags($item['body'] ?? ''), 155))

@section('content')
<div class="container mx-auto px-4 py-10 max-w-4xl">
    <nav class="text-sm text-gray-500 mb-6 flex gap-2 items-center flex-wrap">
        <a href="{{ route('public.library.index') }}" class="hover:text-brandMaroon-600">{{ __('public.Digital Library') }}</a>
        <span>›</span>
        @if($item['content_type'] === 'post')
            {{-- BLOG_PLAN BL2b: a post sits under the blogs, not the shelf. --}}
            <a href="{{ route('public.library.blogs') }}" class="hover:text-brandMaroon-600">{{ __('public.blogs_title') }}</a>
            <span>›</span>
        @endif
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
                    <span data-office-words>{{ $item['category']['label'] }}</span>
                @endif
                {{-- R1: each author leads where they can — a teacher to their profile, a writer to their author page. --}}
                @if($item['writer'] && ! collect($item['author_links'])->contains('name', $item['writer']['display_name']))
                    <a href="{{ route('public.library.author', $item['writer']['slug']) }}" class="text-brandMaroon-700 hover:underline" rel="author">{{ $item['writer']['display_name'] }}</a>
                @endif
                @foreach($item['author_links'] as $author)
                    @php($authorUrl = $author['url'] ?? (($item['writer'] && $author['name'] === $item['writer']['display_name']) ? route('public.library.author', $item['writer']['slug']) : null))
                    @if($authorUrl)
                        <a href="{{ $authorUrl }}" class="text-brandMaroon-700 hover:underline" rel="author" data-testid="author-{{ $author['kind'] }}">{{ $author['name'] }}</a>
                    @else
                        <span data-testid="author-name">{{ $author['name'] }}</span>
                    @endif
                @endforeach
                @if($item['published_at'])
                    <span>{{ $item['published_at'] }}</span>
                @endif
                @if($item['difficulty'] ?? null)
                    <span data-testid="difficulty">{{ __('public.difficulty_'.$item['difficulty']) }}</span>
                @endif
                @if($item['reading_time'])
                    <span>{{ $item['reading_time'] }} {{ __('public.min read') }}</span>
                @endif
            </div>
          </div>
        </header>

        @if($follow ?? null)
            <div class="mb-6">
                @include('public.library._follow', ['follow' => $follow])
                @include('public.library._tip', ['follow' => $follow, 'tipItemId' => $item['id']])
            </div>
        @endif

        @if($item['series'])
            {{-- BLOG_PLAN BL2b (§4.3): part n of m, with the parts either side. --}}
            <nav class="mb-6 rounded-lg border bg-brandBeige-50 p-4 text-sm" data-testid="post-series" aria-label="{{ __('public.blogs_series') }}">
                <p class="font-medium text-brandMaroon-900">{{ __('public.blogs_series_part', ['n' => $item['series']['position'], 'count' => $item['series']['count'], 'series' => $item['series']['title']]) }}</p>
                <div class="mt-2 flex flex-wrap justify-between gap-3">
                    @if($item['series']['previous'])
                        <a href="{{ route('public.library.show', $item['series']['previous']['slug']) }}" class="text-brandMaroon-700 hover:underline" rel="prev" data-testid="series-previous">{{ __('public.blogs_previous') }}: {{ $item['series']['previous']['title'] }}</a>
                    @endif
                    @if($item['series']['next'])
                        <a href="{{ route('public.library.show', $item['series']['next']['slug']) }}" class="text-brandMaroon-700 hover:underline" rel="next" data-testid="series-next">{{ __('public.blogs_next') }}: {{ $item['series']['next']['title'] }}</a>
                    @endif
                </div>
            </nav>
        @endif

        {{-- LT6: the checkout refuses on `item` too — not for sale, or already
             yours — whatever this page then shows, so it is said up here. --}}
        @error('item')
            <p class="mb-6 rounded border border-red-200 bg-red-50 p-3 text-sm text-red-700" role="alert" data-testid="item-refusal">{{ $message }}</p>
        @enderror

        @if($item['abstract'])
            <p class="text-lg text-gray-700 mb-6">{{ $item['abstract'] }}</p>
        @endif

        @if(!empty($item['toc']))
            {{-- §8.8: the table of contents, one line per entry as the writer typed it. --}}
            <details class="mb-6 rounded border bg-gray-50 p-4" open data-testid="toc">
                <summary class="cursor-pointer py-2 text-sm font-semibold text-gray-800">{{ __('public.Contents') }}</summary>
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

        {{-- R1 (D1): the file itself, where the author offers it. --}}
        @if($item['can_download'])
            <div class="mb-4 flex flex-wrap items-center gap-3 rounded-lg border bg-brandBeige-50 p-4" data-testid="library-download">
                <a href="{{ route('public.library.download', $item['slug']) }}" class="btn-primary" download data-testid="library-download-link">{{ __('public.Download PDF') }}</a>
                <span class="text-sm text-gray-600">{{ $item['reads_online'] ? __('public.Or read it online below.') : __('public.The author shares this as a file to keep.') }}</span>
            </div>
        @elseif($item['offers_download'] && ! $item['can_read'])
            <p class="mb-4 text-sm text-gray-600" data-testid="library-download-later">{{ __('public.A PDF download comes with access to this item.') }}</p>
        @endif

        @if(! $item['reads_online'] && $item['can_download'])
            {{-- Download only: nothing more to read here. --}}
        @elseif($item['can_read'] && ($item['total_pages'] ?? 0) > 1)
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
                {{-- B4: a visitor sees the offer too — it is the reason to sign in. --}}
                @if($item['promotion'])
                    <p class="mb-2 text-sm text-brandMaroon-800" data-testid="item-promotion">
                        <span class="font-semibold" data-office-words>{{ $item['promotion']['name'] }}</span> —
                        <s class="text-gray-500">{{ $item['currency'] }} {{ $item['price'] }}</s>
                        <span class="font-semibold">{{ $item['currency'] }} {{ number_format($item['promotion']['price'], 2) }}</span>
                        @if($item['promotion']['ends_on'])
                            · {{ __('public.Offer ends :date', ['date' => $item['promotion']['ends_on']]) }}
                        @endif
                    </p>
                @endif
                <p class="mb-1 text-gray-700" data-testid="item-guest-price">{{ $item['currency'] }} {{ $item['promotion'] ? number_format($item['promotion']['price'], 2) : $item['price'] }}</p>
                {{-- STATUS §5ly: buy without signing in first. --}}
                @include('public.partials.guest-checkout', ['for' => 'library', 'slug' => $item['slug']])
                <p class="mt-4 text-sm text-gray-600">{{ __('account.guest_have_account') }}
                    <a href="{{ route('phone.sign-in', ['next' => request()->getRequestUri()]) }}" class="font-semibold text-brandMaroon-700 underline" data-testid="item-sign-in-link">{{ __('account.phone_sign_in_link') }}</a>
                </p>
            </div>
        @elseif($item['requires_login'])
            <div class="rounded-lg border bg-brandBeige-50 p-6 text-center">
                <p class="mb-3 text-gray-700">{{ __('public.Sign in to read this item for free.') }}</p>
                <a href="{{ route('login') }}" class="btn-primary">{{ __('public.Sign in') }}</a>
            </div>
        @elseif($item['locked'] && $item['access_type'] === 'paid' && $item['price'])
            <div class="rounded-lg border bg-brandBeige-50 p-6 text-center">
                {{-- B4 (§18): a live campaign's price, with the old one struck through. --}}
                @if($item['promotion'])
                    <p class="mb-3 text-sm text-brandMaroon-800" data-testid="item-promotion">
                        <span class="font-semibold" data-office-words>{{ $item['promotion']['name'] }}</span> —
                        <s class="text-gray-500">{{ $item['currency'] }} {{ $item['price'] }}</s>
                        <span class="font-semibold">{{ $item['currency'] }} {{ number_format($item['promotion']['price'], 2) }}</span>
                        @if($item['promotion']['ends_on'])
                            · {{ __('public.Offer ends :date', ['date' => $item['promotion']['ends_on']]) }}
                        @endif
                    </p>
                @endif
                <form method="POST" action="{{ route('public.library.checkout', $item['slug']) }}" class="inline-flex flex-wrap items-center justify-center gap-2">
                    @csrf
                    <input type="text" name="discount_code" class="form-input" placeholder="{{ __('public.Discount code') }}" aria-label="{{ __('public.Discount code') }}">
                    <button type="submit" class="btn-primary">{{ __('public.Buy for :price', ['price' => $item['currency'].' '.($item['promotion'] ? number_format($item['promotion']['price'], 2) : $item['price'])]) }}</button>
                    <button type="submit" name="pay_with_wallet" value="1" class="btn-secondary">{{ __('public.Pay with wallet') }}</button>
                </form>
                <p class="mt-2 text-sm text-gray-500">{{ __('public.Access opens as soon as the bank confirms your payment.') }}</p>
                @error('discount_code')
                    <p class="mt-2 text-sm text-red-700" role="alert">{{ $message }}</p>
                @enderror
                @error('amount')
                    <p class="mt-2 text-sm text-red-700" role="alert">{{ $message }}</p>
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
            {{-- BP3 (BACKLOG B11): anyone may say this work copies theirs; the office decides. --}}
            @php($reportErrors = $errors->getBag('copyright'))
            <details id="copyright-report" class="mt-3" data-testid="copyright-report" @if($reportErrors->any() || session('copyright_sent')) open @endif>
                <summary class="cursor-pointer underline">{{ __('library_copyright.report_link') }}</summary>
                @if(session('copyright_sent'))
                    <p class="mt-2 rounded border border-green-200 bg-green-50 p-3 text-sm text-green-800" role="status" data-testid="copyright-sent">{{ session('copyright_sent') }}</p>
                @else
                    <form method="POST" action="{{ route('public.library.copyright', $item['slug']) }}#copyright-report" class="mt-2 grid grid-cols-1 gap-3 text-sm text-gray-700 sm:grid-cols-2">
                        @csrf
                        <p class="sm:col-span-2">{{ __('library_copyright.report_intro') }}</p>
                        @if($reportErrors->any())
                            <ul class="rounded border border-red-200 bg-red-50 p-3 text-red-700 sm:col-span-2" role="alert" data-testid="copyright-errors">
                                @foreach($reportErrors->all() as $message)<li>{{ $message }}</li>@endforeach
                            </ul>
                        @endif
                        <label class="block">{{ __('library_copyright.field_name') }}
                            <input name="name" required maxlength="120" value="{{ old('name', auth()->user()?->name) }}" class="mt-1 block w-full rounded border-gray-300">
                        </label>
                        <label class="block">{{ __('library_copyright.field_email') }}
                            <input name="email" type="email" required maxlength="190" dir="ltr" value="{{ old('email', auth()->user()?->email) }}" class="mt-1 block w-full rounded border-gray-300">
                        </label>
                        <label class="block sm:col-span-2">{{ __('library_copyright.field_work') }}
                            <input name="work_claimed" required maxlength="300" value="{{ old('work_claimed') }}" class="mt-1 block w-full rounded border-gray-300">
                        </label>
                        <label class="block sm:col-span-2">{{ __('library_copyright.field_statement') }}
                            <textarea name="statement" required maxlength="5000" rows="4" class="mt-1 block w-full rounded border-gray-300">{{ old('statement') }}</textarea>
                        </label>
                        <label class="flex items-start gap-2 sm:col-span-2">
                            <input type="checkbox" name="good_faith" value="1" class="mt-1" @checked(old('good_faith'))>
                            <span>{{ __('library_copyright.field_good_faith') }}</span>
                        </label>
                        <div class="sm:col-span-2"><button type="submit" class="btn-secondary" data-testid="copyright-send">{{ __('library_copyright.send') }}</button></div>
                    </form>
                @endif
            </details>
        </footer>
    </article>

    @if($comments ?? null)
        {{-- BLOG_PLAN BL5 (§3.5): only what the office published shows; every comment and reply is checked first. --}}
        <section class="mt-12 border-t pt-8" id="comments" data-testid="post-comments">
            <h2 class="text-2xl font-bold text-brandMaroon-900 mb-4">{{ __('blog.comments_heading') }}</h2>
            @error('comment')
                <p class="mb-4 rounded border border-red-200 bg-red-50 p-3 text-sm text-red-700" role="alert">{{ $message }}</p>
            @enderror
            @if(count($comments['list']) === 0)
                <p class="mb-4 text-gray-500" data-testid="comments-none">{{ __('blog.comments_none') }}</p>
            @endif
            @foreach($comments['list'] as $comment)
                <div class="mb-4 rounded-lg border bg-white p-4" data-comment="{{ $comment['id'] }}">
                    <p class="text-sm text-gray-500">{{ $comment['by'] }} · {{ $comment['at'] }}</p>
                    <p class="mt-1 whitespace-pre-line">{{ $comment['body'] }}</p>
                    @if($comments['signed_in'])
                        <form method="POST" action="{{ route('public.library.comments.report', $comment['id']) }}" class="mt-2">
                            @csrf
                            <button type="submit" class="text-xs text-gray-500 underline" data-testid="comment-report-{{ $comment['id'] }}">{{ __('blog.comment_report') }}</button>
                        </form>
                    @endif
                    @foreach($comment['replies'] as $reply)
                        <div class="mt-3 border-s-4 ps-3" style="border-color: #E9D8B4;" data-comment="{{ $reply['id'] }}">
                            <p class="text-sm text-gray-500">{{ $reply['by'] }} · {{ __('blog.comment_owner_reply') }} · {{ $reply['at'] }}</p>
                            <p class="mt-1 whitespace-pre-line">{{ $reply['body'] }}</p>
                        </div>
                    @endforeach
                    @if($comments['owner'] && $comments['open'])
                        <form method="POST" action="{{ route('public.library.comments.store', $comments['item_id']) }}" class="mt-3 grid gap-2">
                            @csrf
                            <input type="hidden" name="parent_id" value="{{ $comment['id'] }}">
                            <textarea name="body" rows="2" maxlength="1000" class="w-full rounded border-gray-300 text-sm" aria-label="{{ __('blog.comment_reply_label') }}" placeholder="{{ __('blog.comment_reply_label') }}" data-testid="comment-reply-{{ $comment['id'] }}"></textarea>
                            <button type="submit" class="justify-self-start rounded border px-3 py-1 text-sm">{{ __('blog.comment_reply_send') }}</button>
                        </form>
                    @endif
                </div>
            @endforeach

            @if($comments['under_13'])
                <p class="text-sm text-gray-600" data-testid="comments-closed">{{ __('blog.comments_closed_young') }}</p>
            @elseif(! $comments['open'])
                <p class="text-sm text-gray-600" data-testid="comments-closed">{{ __('blog.comments_closed') }}</p>
            @elseif(! $comments['signed_in'])
                <p class="text-sm"><a href="{{ route('login') }}" class="text-brandMaroon-700 underline" data-testid="comments-sign-in">{{ __('blog.comments_sign_in') }}</a></p>
            @elseif(! $comments['owner'])
                <form method="POST" action="{{ route('public.library.comments.store', $comments['item_id']) }}" class="grid gap-2" data-testid="comment-form">
                    @csrf
                    <label for="comment-body" class="font-medium">{{ __('blog.comment_label') }}</label>
                    <textarea id="comment-body" name="body" rows="3" maxlength="1000" class="w-full rounded border-gray-300" data-testid="comment-body">{{ old('body') }}</textarea>
                    <p class="text-xs text-gray-500">{{ __('blog.comment_help') }}</p>
                    <button type="submit" class="justify-self-start rounded bg-brandMaroon-600 px-4 py-1.5 text-white" data-testid="comment-send">{{ __('blog.comment_send') }}</button>
                </form>
            @endif
        </section>
    @endif
</div>
@endsection

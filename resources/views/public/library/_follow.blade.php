{{-- BLOG_PLAN BL4 (§3.4): follow a blog, from its page or a post. A guest is
     sent to sign in; a writer does not follow themselves. --}}
@if($follow)
<div class="flex flex-wrap items-center gap-3 text-sm" data-testid="follow-box" data-following="{{ $follow['following'] ? 'yes' : 'no' }}">
    @unless($follow['own'])
        <form method="POST" action="{{ route('public.library.author.follow', $follow['slug']) }}">
            @csrf
            <input type="hidden" name="follow" value="{{ $follow['following'] ? 0 : 1 }}">
            <button type="submit" class="rounded px-4 py-1.5 font-medium {{ $follow['following'] ? 'border' : 'bg-brandMaroon-600 text-white' }}" @if($follow['following']) style="border-color: currentColor;" @endif data-testid="follow-button">
                {{ $follow['following'] ? __('public.follow_following') : __('public.follow_follow') }}
            </button>
        </form>
        @if($follow['following'])
            <form method="POST" action="{{ route('public.library.author.follow', $follow['slug']) }}">
                @csrf
                <input type="hidden" name="follow" value="1">
                <input type="hidden" name="email" value="{{ $follow['email'] ? 0 : 1 }}">
                <button type="submit" class="underline" data-testid="follow-email">{{ $follow['email'] ? __('public.follow_email_stop') : __('public.follow_email_start') }}</button>
            </form>
        @endif
    @endunless
    <span class="opacity-70" data-testid="follow-count">{{ trans_choice('public.follow_count', $follow['count'], ['count' => $follow['count']]) }}</span>
    <a href="{{ route('public.library.author.feed', $follow['slug']) }}" class="opacity-70 underline" data-testid="follow-feed">{{ __('public.follow_feed') }}</a>
</div>
@error('follow')
    <p class="mt-2 text-sm text-red-700" role="alert">{{ $message }}</p>
@enderror
@endif

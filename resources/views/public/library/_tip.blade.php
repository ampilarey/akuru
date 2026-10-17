{{-- BLOG_PLAN BL6a (§3.6): tip the writer — a preset amount, by card through BML
     or from the wallet, with an optional note the office checks first. Paid,
     and earned, only on the bank's webhook. --}}
@if(($follow['tips'] ?? false) && ! $follow['own'])
<details class="mt-3 rounded-lg border bg-white p-4 text-sm text-gray-800" data-testid="tip-box" @if(request('tip') === 'returned' || $errors->has('tip') || $errors->has('amount')) open @endif>
    <summary class="cursor-pointer font-medium">{{ __('blog.tip_heading') }}</summary>
    @if(request('tip') === 'returned')
        <p class="mt-2 rounded bg-amber-50 p-2 text-amber-900" data-testid="tip-returned">{{ __('blog.tip_returned') }}</p>
    @endif
    @if($follow['signed_in'])
        <form method="POST" action="{{ route('public.library.author.tip', $follow['slug']) }}" class="mt-3 grid gap-3" data-testid="tip-form">
            @csrf
            @isset($tipItemId)
                <input type="hidden" name="item_id" value="{{ $tipItemId }}">
            @endisset
            <fieldset class="flex flex-wrap gap-2">
                <legend class="mb-1 font-medium">{{ __('blog.tip_amount') }}</legend>
                @foreach($follow['tip_amounts'] as $i => $amount)
                    <label class="flex items-center gap-1 rounded border px-3 py-1">
                        <input type="radio" name="amount" value="{{ $amount }}" @checked($i === 0) data-testid="tip-amount-{{ $amount }}">
                        MVR {{ $amount }}
                    </label>
                @endforeach
            </fieldset>
            <label class="grid gap-1">
                <span>{{ __('blog.tip_note') }}</span>
                <textarea name="note" rows="2" maxlength="300" class="rounded border-gray-300" data-testid="tip-note"></textarea>
                <span class="text-xs text-gray-500">{{ __('blog.tip_note_help') }}</span>
            </label>
            <label class="flex items-center gap-2">
                <input type="checkbox" name="wallet" value="1" data-testid="tip-wallet">
                {{ __('blog.tip_wallet') }}
            </label>
            @error('tip')<p class="text-red-700" role="alert">{{ $message }}</p>@enderror
            @error('amount')<p class="text-red-700" role="alert">{{ $message }}</p>@enderror
            <button type="submit" class="justify-self-start rounded bg-brandMaroon-600 px-4 py-1.5 text-white" data-testid="tip-send">{{ __('blog.tip_send') }}</button>
        </form>
    @else
        <p class="mt-2"><a href="{{ route('login') }}" class="text-brandMaroon-700 underline">{{ __('blog.tip_sign_in') }}</a></p>
    @endif
</details>
@endif

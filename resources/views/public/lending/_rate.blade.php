{{-- L2: after a return, one side rates the other once; both ratings show once given. $mine / $theirs name the keys in $loan['ratings']. --}}
<div class="w-full text-sm" data-testid="rate-{{ $loan['id'] }}">
    @if(isset($loan['ratings'][$theirs]))
        <p class="text-gray-700" data-testid="their-rating-{{ $loan['id'] }}"><span class="font-medium">{{ __('lending.their_rating') }}:</span> <span class="text-amber-700">{{ str_repeat('★', $loan['ratings'][$theirs]['stars']) }}</span>@if($loan['ratings'][$theirs]['comment']) <span dir="auto">{{ $loan['ratings'][$theirs]['comment'] }}</span>@endif</p>
    @endif
    @if(isset($loan['ratings'][$mine]))
        <p class="text-gray-700" data-testid="my-rating-{{ $loan['id'] }}"><span class="font-medium">{{ __('lending.your_rating') }}:</span> <span class="text-amber-700">{{ str_repeat('★', $loan['ratings'][$mine]['stars']) }}</span>@if($loan['ratings'][$mine]['comment']) <span dir="auto">{{ $loan['ratings'][$mine]['comment'] }}</span>@endif</p>
    @else
        <form method="POST" action="{{ route('public.lending.rate', $loan['id']) }}" class="mt-1 flex flex-wrap items-end gap-2 rounded bg-amber-50 p-2" data-testid="rate-form-{{ $loan['id'] }}">
            @csrf
            <span class="w-full text-xs text-gray-600">{{ __('lending.rate_title') }} {{ $hint }}</span>
            <label class="text-xs text-gray-600">{{ __('lending.rate_stars') }}<br>
                <select name="stars" class="form-input text-sm" data-testid="rate-stars-{{ $loan['id'] }}">@foreach([5, 4, 3, 2, 1] as $s)<option value="{{ $s }}">{{ str_repeat('★', $s) }}</option>@endforeach</select>
            </label>
            <input type="text" name="comment" maxlength="500" placeholder="{{ __('lending.rate_comment') }}" class="form-input text-sm" dir="auto" data-testid="rate-comment-{{ $loan['id'] }}">
            <button type="submit" class="btn-primary text-sm" data-testid="rate-send-{{ $loan['id'] }}">{{ __('lending.rate_send') }}</button>
        </form>
    @endif
</div>

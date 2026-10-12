{{-- NAME_TAGS_PLAN §3.9 (NT7): name tags and subject tags for the child this book list is for. --}}
@if($upsell)
<div class="mt-4 flex flex-wrap items-center gap-3 rounded-lg border border-dashed border-brandMaroon-200 bg-brandBeige-50 p-3" data-testid="print-upsell">
    <div class="min-w-0 flex-1">
        <p class="font-semibold text-brandMaroon-900">{{ __('print.upsell_heading') }}</p>
        <p class="text-sm text-gray-700">{{ $upsell['grade'] ? __('print.upsell_body_grade', ['grade' => $upsell['grade']]) : __('print.upsell_body') }}</p>
    </div>
    <form method="POST" action="{{ route('public.shop.print.start') }}">
        @csrf
        <input type="hidden" name="template" value="{{ $upsell['template'] }}">
        @if($upsell['grade'])<input type="hidden" name="grade" value="{{ $upsell['grade'] }}">@endif
        <button type="submit" class="btn-secondary" data-testid="print-upsell-start">{{ __('print.upsell_button') }}</button>
    </form>
</div>
@endif

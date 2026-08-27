@extends('public.layouts.public')

@section('title', __('public.Research') . ' - ' . config('app.name'))
@section('description', __('public.Free research and publications from Akuru Institute'))

@section('content')
<section class="bg-gradient-to-br from-brandMaroon-50 to-brandBeige-100 py-12">
    <div class="container mx-auto px-4">
        <h1 class="text-4xl font-bold text-brandMaroon-900 mb-3">{{ __('public.Research') }}</h1>
        <p class="text-xl text-brandGray-700">{{ __('public.Free research and publications from Akuru Institute') }}</p>
    </div>
</section>

<section class="bg-white border-b py-4">
    <div class="container mx-auto px-4">
        <form method="GET" action="{{ route('public.research.index') }}" class="flex flex-wrap gap-3 items-end">
            <div>
                <label class="block text-xs text-gray-500 mb-1">{{ __('public.Year') }}</label>
                <select name="year" class="form-input">
                    <option value="">{{ __('public.All years') }}</option>
                    @foreach($years as $year)
                        <option value="{{ $year }}" @selected((string) ($filters['year'] ?? '') === (string) $year)>{{ $year }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">{{ __('public.Author') }}</label>
                <select name="instructor_id" class="form-input">
                    <option value="">{{ __('public.All authors') }}</option>
                    @foreach($instructors as $instructor)
                        <option value="{{ $instructor['id'] }}" @selected((string) ($filters['instructor_id'] ?? '') === (string) $instructor['id'])>{{ $instructor['name'] }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="btn-primary">{{ __('public.Filter') }}</button>
            <a href="{{ route('public.research.export', request()->only(['year', 'instructor_id'])) }}" class="btn-secondary">{{ __('public.Export CSV') }}</a>
        </form>
    </div>
</section>

<section class="py-10">
    <div class="container mx-auto px-4 max-w-4xl space-y-6">
        @forelse($posts as $post)
            <article class="bg-white rounded-xl border border-gray-100 shadow-sm p-6">
                <h2 class="text-2xl font-bold text-brandMaroon-900 mb-2">
                    <a href="{{ route('public.research.show', $post['slug']) }}" class="hover:underline">{{ $post['title'] }}</a>
                </h2>
                <p class="text-sm text-gray-500 mb-3">
                    @if($post['year']){{ $post['year'] }} · @endif
                    {{ $post['authors_label'] !== '' ? $post['authors_label'] : __('public.Akuru Institute') }}
                </p>
                @if($post['abstract'])
                    <p class="text-gray-700">{{ $post['abstract'] }}</p>
                @endif
            </article>
        @empty
            <p class="text-gray-500">{{ __('public.No research posts yet.') }}</p>
        @endforelse
    </div>
</section>
@endsection

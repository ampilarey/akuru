@extends('public.layouts.public')

@section('title', __('public.Prayer times') . ' - ' . config('app.name'))
@section('description', __('public.Maldives prayer times by island'))

@section('content')
<section class="bg-gradient-to-br from-brandMaroon-50 to-brandBeige-100 py-12">
    <div class="container mx-auto px-4">
        <h1 class="text-4xl font-bold text-brandMaroon-900 mb-3">{{ __('public.Prayer times') }}</h1>
        <p class="text-xl text-brandGray-700">{{ $selected['name_en'] ?? '' }} · {{ $date }}
            @if(!empty($hijri['formatted']))
                · {{ $hijri['formatted'] }}
            @endif
        </p>
    </div>
</section>

<section class="bg-white py-8">
    <div class="container mx-auto px-4">
        <form method="GET" action="{{ route('public.prayer-times') }}" class="flex flex-wrap gap-3 items-end mb-6">
            <div>
                <label class="block text-xs text-gray-500 mb-1">{{ __('public.Island') }}</label>
                <select name="island_id" class="form-input">
                    @foreach($islands as $island)
                        <option value="{{ $island->id }}" @selected((int) ($selected['id'] ?? 0) === $island->id)>{{ $island->nameEn }} ({{ $island->atollLatin }})</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">{{ __('public.Date') }}</label>
                <input type="date" name="date" value="{{ $date }}" class="form-input">
            </div>
            <button type="submit" class="btn-primary">{{ __('public.Show') }}</button>
            <button type="button" class="btn-secondary" onclick="akuruUseLocation()">{{ __('public.Use my location') }}</button>
        </form>

        @if(!empty($times['available']))
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:1rem">
                @foreach($labels as $key => $label)
                    <div style="border:1px solid #E8DCC8;border-radius:.75rem;padding:1rem;background:#FBF8F2">
                        <div style="font-size:.75rem;color:#7C2D37;text-transform:uppercase;letter-spacing:.06em">{{ $label }}</div>
                        <div style="font-size:1.5rem;font-weight:800;color:#3D1219">{{ $times['times'][$key] ?? '—' }}</div>
                    </div>
                @endforeach
            </div>
        @else
            <p class="text-gray-600">{{ $times['error'] ?? __('public.Prayer times unavailable') }}</p>
        @endif
    </div>
</section>
<script>
function akuruUseLocation() {
  if (!navigator.geolocation) { return; }
  navigator.geolocation.getCurrentPosition(function (pos) {
    var url = new URL(window.location.href);
    url.searchParams.set('lat', pos.coords.latitude);
    url.searchParams.set('lng', pos.coords.longitude);
    window.location = url.toString();
  });
}
</script>
@endsection

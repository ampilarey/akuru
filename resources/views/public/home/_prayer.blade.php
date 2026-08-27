@php
  $prayer = $prayer ?? null;
@endphp
@if(! empty($prayer) && ! empty($prayer['available']))
<section id="prayer-widget" style="background:#3D1219;padding:1.75rem 0;color:#fff">
  <div class="container mx-auto px-4" style="display:flex;flex-wrap:wrap;justify-content:space-between;gap:1.25rem;align-items:center">
    <div>
      <p style="margin:0;font-size:.75rem;letter-spacing:.08em;text-transform:uppercase;color:#E8BC3C">{{ __('public.Prayer times') }}</p>
      <h2 style="margin:.2rem 0 0;font-size:1.15rem;font-weight:700">{{ $prayer['name_en'] ?? '' }} · {{ $prayer['date'] ?? '' }}</h2>
      @if(!empty($prayer['hijri']['formatted']))
        <p style="margin:.25rem 0 0;font-size:.8rem;color:rgba(255,255,255,.7)">{{ $prayer['hijri']['formatted'] }}</p>
      @endif
    </div>
    <div style="display:flex;flex-wrap:wrap;gap:.75rem 1.25rem">
      @foreach(['fajr' => __('public.Fajr'), 'sunrise' => __('public.Sunrise'), 'dhuhr' => __('public.Dhuhr'), 'asr' => __('public.Asr'), 'maghrib' => __('public.Maghrib'), 'isha' => __('public.Isha')] as $key => $label)
        <div style="min-width:4.5rem">
          <div style="font-size:.7rem;color:rgba(255,255,255,.65)">{{ $label }}</div>
          <div style="font-weight:700">{{ $prayer['times'][$key] ?? '—' }}</div>
        </div>
      @endforeach
    </div>
    <a href="{{ route('public.prayer-times') }}" style="color:#E8BC3C;font-weight:600;text-decoration:none">{{ __('public.All islands') }} →</a>
  </div>
</section>
@endif

@php
  $daily = $daily ?? ['layout' => 'stacked', 'items' => []];
@endphp
@if(! empty($daily['items']))
<section id="daily-widget" data-daily-widget data-layout="{{ $daily['layout'] }}"
         style="background:#F8F4EC;padding:2.5rem 0 3rem;border-bottom:1px solid #E8DCC8">
  <div class="container mx-auto px-4">
    <div style="display:flex;justify-content:space-between;align-items:flex-end;flex-wrap:wrap;gap:1rem;margin-bottom:1.5rem">
      <div>
        <span style="color:#7C2D37;font-weight:600;font-size:.75rem;text-transform:uppercase;letter-spacing:.08em">Today</span>
        <h2 style="font-size:clamp(1.4rem,3vw,2rem);font-weight:800;color:#3D1219;margin:.25rem 0 0">Daily content</h2>
      </div>
      <a href="{{ route('public.daily.index', ['type' => $daily['items'][0]['content_type'] ?? 'ayah']) }}"
         style="color:#7C2D37;font-weight:600;font-size:.875rem;text-decoration:none">Archive</a>
    </div>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(min(100%,280px),1fr));gap:1.25rem">
      @foreach($daily['items'] as $item)
        @include('public.daily._card', ['item' => $item, 'compact' => true])
      @endforeach
    </div>
  </div>
</section>
@endif

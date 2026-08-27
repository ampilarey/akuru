@extends('public.layouts.public')
@section('title', 'Daily '.ucfirst($type).' · Akuru Institute')
@section('description', 'Archive of curated daily '.$type.' from Akuru Institute.')

@section('content')
<section style="background:#F8F4EC;padding:2.5rem 0 3.5rem">
  <div class="container mx-auto px-4">
    <p style="color:#7C2D37;font-weight:600;font-size:.75rem;text-transform:uppercase;letter-spacing:.08em;margin:0">Daily content</p>
    <h1 style="font-size:clamp(1.6rem,3vw,2.25rem);font-weight:800;color:#3D1219;margin:.35rem 0 1.25rem">{{ ucfirst($type) }} archive</h1>

    <form method="get" action="{{ route('public.daily.index', ['type' => $type]) }}"
          style="display:flex;flex-wrap:wrap;gap:.75rem;margin-bottom:1.5rem">
      <label style="font-size:.8rem;color:#6b7280">Month
        <input type="month" name="month" value="{{ $filters['month'] ?? '' }}"
               style="display:block;margin-top:.25rem;padding:.4rem .6rem;border:1px solid #D1D5DB;border-radius:.5rem">
      </label>
      <label style="font-size:.8rem;color:#6b7280">Theme
        <input type="text" name="theme_tag" value="{{ $filters['theme_tag'] ?? '' }}" placeholder="knowledge"
               style="display:block;margin-top:.25rem;padding:.4rem .6rem;border:1px solid #D1D5DB;border-radius:.5rem">
      </label>
      <button type="submit" style="align-self:flex-end;background:#7C2D37;color:#fff;border:0;border-radius:.5rem;padding:.5rem 1rem;font-weight:600;cursor:pointer">Filter</button>
    </form>

    <p style="font-size:.85rem;color:#6b7280;margin:0 0 1rem">
      @foreach(['ayah','hadith','saying','reminder'] as $other)
        <a href="{{ route('public.daily.index', ['type' => $other]) }}"
           style="margin-right:.75rem;color:{{ $other === $type ? '#3D1219' : '#7C2D37' }};font-weight:{{ $other === $type ? '800' : '600' }};text-decoration:none">{{ ucfirst($other) }}</a>
      @endforeach
    </p>

    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(min(100%,280px),1fr));gap:1.25rem">
      @forelse($items as $item)
        @include('public.daily._card', ['item' => $item, 'compact' => true])
      @empty
        <p style="color:#6b7280">No published {{ $type }} in this archive yet.</p>
      @endforelse
    </div>

    <div style="margin-top:1.75rem">
      @if($items->hasPages())
        {{ $items->withQueryString()->links() }}
      @endif
    </div>
  </div>
</section>
@endsection

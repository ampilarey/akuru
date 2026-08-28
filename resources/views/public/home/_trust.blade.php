{{-- W1.2 trust above the fold — Settings group trust_settings, nothing hardcoded. --}}
@if(!empty($trust['has_signals']))
{{-- Years-operating stays composed (About page candidate) but is not a hero stat. --}}
<div id="hero-trust"
     @if(!empty($trust['students_taught'])) data-students="{{ (int) $trust['students_taught'] }}" @endif
     @if(!empty($trust['students_source'])) data-students-source="{{ $trust['students_source'] }}" @endif
     style="position:relative;z-index:2;padding:1.25rem 1rem 2.75rem">
  <div style="max-width:56rem;margin:0 auto;display:flex;flex-direction:column;align-items:center;gap:.85rem;text-align:center">
    @if(!empty($trust['accreditation']))
    <p id="hero-trust-accreditation" style="margin:0;color:#E8BC3C;font-size:.8rem;font-weight:600;letter-spacing:.04em;line-height:1.5">
      {{ $trust['accreditation'] }}
    </p>
    @endif

    @if(!empty($trust['students_taught']))
    <div style="display:flex;flex-wrap:wrap;justify-content:center;gap:.75rem 1.75rem">
      @if(!empty($trust['students_taught']))
      <div>
        <div style="font-size:1.65rem;font-weight:800;color:#fff;line-height:1;font-variant-numeric:tabular-nums">{{ number_format((int) $trust['students_taught']) }}</div>
        <div style="color:rgba(255,255,255,.7);font-size:.72rem;margin-top:.25rem">{{ $trust['students_label'] }}</div>
      </div>
      @endif
    </div>
    @endif

    @if(!empty($trust['logos']))
    <div id="hero-trust-logos" style="display:flex;flex-wrap:wrap;justify-content:center;align-items:center;gap:.65rem">
      @foreach($trust['logos'] as $logo)
      <img src="{{ $logo['url'] }}" alt="{{ $logo['alt'] }}"
           style="height:2.25rem;width:auto;max-width:7.5rem;object-fit:contain;background:rgba(255,255,255,.92);padding:.35rem .55rem;border-radius:.4rem">
      @endforeach
    </div>
    @endif
  </div>
</div>
@endif

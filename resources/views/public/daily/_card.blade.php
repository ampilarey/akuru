@php
  $type = $item['content_type'] ?? '';
  $date = $item['publish_date'] ?? '';
  $href = ($type && $date) ? route('public.daily.show', ['type' => $type, 'date' => $date]) : '#';
  $fallback = ! empty($item['is_fallback']);
  $theme = $item['theme_tag'] ?? '';
  $arabic = '';
  $en = '';
  $dv = '';
  $meta = '';
  if ($type === 'ayah') {
      $arabic = $item['ayah']['text_uthmani'] ?? $item['ayah']['text_simple'] ?? '';
      $en = $item['ayah']['meanings']['en'] ?? '';
      $dv = $item['ayah']['meanings']['dv'] ?? '';
      $surah = $item['ayah']['surah']['english_name'] ?? 'Ayah';
      $meta = $surah.' '.($item['ayah']['surah_number'] ?? '').':'.($item['ayah']['ayah_number'] ?? '');
  } elseif ($type === 'hadith') {
      $arabic = $item['hadith_text_ar'] ?? '';
      $en = $item['hadith_text_en'] ?? '';
      $dv = $item['hadith_text_dv'] ?? '';
      $meta = trim(($item['hadith_collection'] ?? '').' '.($item['hadith_number'] ?? '').' · '.($item['hadith_grading'] ?? ''));
  } else {
      $arabic = $item['text_ar'] ?? '';
      $en = $item['text_en'] ?? '';
      $dv = $item['text_dv'] ?? '';
      $meta = $item['attribution'] ?? '';
  }
@endphp
<article data-daily-type="{{ $type }}"
         @if($fallback) data-fallback="1" @endif
         @if($theme !== '') data-theme="{{ $theme }}" @endif
         data-date="{{ $date }}"
         style="background:#fff;border:1.5px solid #E5E7EB;border-radius:1rem;padding:1.25rem 1.35rem;display:flex;flex-direction:column;gap:.75rem">
  <div style="display:flex;justify-content:space-between;align-items:center;gap:.5rem">
    <span style="font-size:.7rem;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:#7C2D37">{{ $type }}</span>
    <time style="font-size:.75rem;color:#6b7280">{{ $date }}</time>
  </div>
  @if($arabic !== '')
  <p class="daily-ar" dir="rtl" lang="ar" style="font-size:1.15rem;line-height:1.8;color:#3D1219;margin:0;text-align:right">{{ $arabic }}</p>
  @endif
  @if($en !== '')
  <p class="daily-en" lang="en" style="font-size:.95rem;line-height:1.65;color:#374151;margin:0">{{ $en }}</p>
  @endif
  @if($dv !== '')
  <p class="daily-dv" dir="rtl" lang="dv" style="font-size:.95rem;line-height:1.8;color:#374151;margin:0;text-align:right">{{ $dv }}</p>
  @endif
  @if($meta !== '')
  <p style="font-size:.8rem;color:#7C2D37;margin:0;font-weight:600">{{ $meta }}</p>
  @endif
  <a href="{{ $href }}" style="font-size:.8rem;font-weight:600;color:#7C2D37;text-decoration:none;margin-top:auto">Open permalink</a>
</article>

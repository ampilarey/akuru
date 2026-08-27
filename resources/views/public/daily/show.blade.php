@extends('public.layouts.public')
@section('title', $seo['title'] ?? 'Daily content')
@section('description', $seo['description'] ?? '')
@section('og_title', $seo['og']['title'] ?? ($seo['title'] ?? ''))
@section('og_description', $seo['og']['description'] ?? '')
@section('og_image', $seo['og']['image'] ?? asset('images/og-default.jpg'))
@section('og_type', $seo['og']['type'] ?? 'article')

@push('jsonld')
    @include('public.partials.json_ld', ['payload' => $seo['json_ld'] ?? []])
@endpush

@section('content')
@php
  $type = $item['content_type'] ?? '';
  $date = $item['publish_date'] ?? '';
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
      $meta = trim(($item['hadith_collection'] ?? '').' '.($item['hadith_number'] ?? '').' · '.($item['hadith_grading'] ?? '').' · '.($item['grading_source'] ?? ''));
  } else {
      $arabic = $item['text_ar'] ?? '';
      $en = $item['text_en'] ?? '';
      $dv = $item['text_dv'] ?? '';
      $meta = $item['attribution'] ?? '';
  }
  $whatsapp = $seo['share']['whatsapp'] ?? '#';
  $twitter = $seo['share']['twitter'] ?? '#';
@endphp
<article data-daily-permalink data-daily-type="{{ $type }}" data-date="{{ $date }}"
         style="background:#F8F4EC;padding:2.5rem 0 3.5rem">
  <div class="container mx-auto px-4" style="max-width:42rem">
    <p style="color:#7C2D37;font-weight:600;font-size:.75rem;text-transform:uppercase;letter-spacing:.08em;margin:0">{{ $type }} · {{ $date }}</p>
    <h1 style="font-size:clamp(1.5rem,3vw,2.1rem);font-weight:800;color:#3D1219;margin:.4rem 0 1.25rem">{{ $seo['title'] ?? 'Daily content' }}</h1>

    @if($arabic !== '')
    <p class="daily-ar" dir="rtl" lang="ar" style="font-size:1.45rem;line-height:2;color:#3D1219;margin:0 0 1rem;text-align:right">{{ $arabic }}</p>
    @endif
    @if($en !== '')
    <p class="daily-en" lang="en" style="font-size:1.05rem;line-height:1.75;color:#374151;margin:0 0 1rem">{{ $en }}</p>
    @endif
    @if($dv !== '')
    <p class="daily-dv" dir="rtl" lang="dv" style="font-size:1.05rem;line-height:1.9;color:#374151;margin:0 0 1rem;text-align:right">{{ $dv }}</p>
    @endif
    @if($meta !== '')
    <p style="font-size:.9rem;color:#7C2D37;font-weight:600">{{ $meta }}</p>
    @endif

    <div style="display:flex;flex-wrap:wrap;gap:.6rem;margin-top:1.5rem">
      <a data-share="whatsapp" href="{{ $whatsapp }}" target="_blank" rel="noopener"
         style="background:#25D366;color:#fff;font-weight:700;padding:.6rem 1rem;border-radius:.6rem;text-decoration:none">WhatsApp</a>
      <a data-share="twitter" href="{{ $twitter }}" target="_blank" rel="noopener"
         style="background:#0EA5E9;color:#fff;font-weight:700;padding:.6rem 1rem;border-radius:.6rem;text-decoration:none">Twitter</a>
      <a href="{{ route('public.daily.index', ['type' => $type]) }}"
         style="color:#7C2D37;font-weight:600;padding:.6rem 0;text-decoration:none">Back to archive</a>
    </div>
  </div>
</article>
@endsection

@extends('public.layouts.public')

{{-- BOOKSHOP_PLAN §6.4 (slice B5): a page under a vendor's storefront (`/shop/<vendor>/p/<slug>`),
     built from the same sections as the home, published with it, with its own SEO fields (§6.7). --}}
@php($storefront = $vendor['storefront'])
@section('title', $page['seo']['title'] . ' - ' . $storefront['name'])
@section('description', $page['seo']['description'] ?? $vendor['tagline'] ?? __('shop.shop_intro'))
@if($page['seo']['image'])
    @section('og_image', $page['seo']['image'])
@endif
@include('public.shop._theme')

@section('content')
<div class="storefront {{ $storefront['theme']['shape']['button'] === 'outlined' ? 'sf-outlined' : '' }}" data-testid="storefront" data-preview="{{ ($preview ?? false) ? '1' : '0' }}" data-preset="{{ $storefront['theme']['preset'] ?? 'custom' }}" data-page="{{ $page['slug'] }}">
@if($preview ?? false)
    <p class="bg-amber-100 px-4 py-2 text-center text-sm text-amber-900" data-testid="preview-banner">{{ __('shop.preview_banner') }}</p>
@endif
@include('public.shop._storefront', ['part' => 'head'])
@include('public.shop._nav')

<section class="container mx-auto px-4 pt-8">
    <nav class="mb-2 text-sm opacity-70"><a href="{{ $storefront['home_url'] }}" class="hover:underline">{{ $storefront['name'] }}</a> › <span>{{ $page['title'] }}</span></nav>
    <h2 class="text-2xl md:text-3xl font-bold" dir="auto" data-testid="page-title">{{ $page['title'] }}</h2>
</section>

@if(count($page['sections']) === 0)
    <p class="container mx-auto px-4 py-8 text-sm opacity-70" data-testid="page-empty">{{ __('shop.page_empty') }}</p>
@else
    @include('public.shop._sections', ['sections' => $page['sections'], 'preview' => $preview ?? false])
@endif
</div>

@include('public.shop._bottom-bar')
@endsection

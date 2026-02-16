@extends('public.layouts.public')

@section('title', ucfirst($slug))

@section('content')
<div class="container mx-auto px-4 py-8 max-w-4xl">
    <h1 class="text-2xl font-bold text-brandMaroon-900 mb-4">{{ ucfirst(str_replace('-', ' ', $slug)) }}</h1>
    <p class="text-gray-600">This page is managed by the site administrator. Please check back later or <a href="{{ route('public.contact.create') }}" class="text-brandMaroon-600 hover:underline">contact us</a>.</p>
</div>
@endsection

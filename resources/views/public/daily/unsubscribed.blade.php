@extends('public.layouts.public')
@section('title', __('public.Unsubscribed').' · '.__('public.Akuru Institute'))

@section('content')
<section style="background:#F8F4EC;padding:3rem 0">
  <div class="container mx-auto px-4" style="max-width:36rem">
    <h1 style="font-size:1.75rem;font-weight:800;color:#3D1219;margin:0 0 .75rem">{{ __('public.Unsubscribed') }}</h1>
    <p data-unsubscribed="1" data-channel="{{ $channel }}" style="color:#4B5563">
      {{ __('public.Your daily content subscription by :channel is paused. You will not receive further messages on this channel unless you opt in again.', ['channel' => ['sms' => 'SMS', 'email' => __('public.Email'), 'push' => __('public.Push (not sent yet)')][$channel] ?? $channel]) }}
    </p>
    <p style="margin-top:1.25rem">
      <a href="{{ route('public.daily.subscribe') }}" style="color:#7C2D37;font-weight:600">{{ __('public.Manage subscriptions') }}</a>
    </p>
  </div>
</section>
@endsection

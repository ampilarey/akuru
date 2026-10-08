@extends('public.layouts.public')
@section('title', __('public.Daily content subscriptions').' · '.__('public.Akuru Institute'))
@section('description', __('public.Opt in to daily ayah, hadith, saying, or reminder by SMS or email.'))

@section('content')
<section style="background:#F8F4EC;padding:2.5rem 0 3.5rem">
  <div class="container mx-auto px-4" style="max-width:40rem">
    <p style="color:#7C2D37;font-weight:600;font-size:.75rem;text-transform:uppercase;letter-spacing:.08em;margin:0">{{ __('public.Daily content') }}</p>
    <h1 style="font-size:clamp(1.6rem,3vw,2.25rem);font-weight:800;color:#3D1219;margin:.35rem 0 1rem">{{ __('public.Subscribe') }}</h1>
    <p style="color:#4B5563;margin:0 0 1.5rem">{{ __('public.Opt-in only. One message per channel per day. SMS is a short link, not the full Arabic. Reply STOP to leave SMS.') }}</p>

    @if(session('success'))
      <p style="background:#ECFDF5;color:#065F46;padding:.75rem 1rem;border-radius:.5rem;margin-bottom:1rem">{{ session('success') }}</p>
    @endif
    @if($errors->any())
      <p style="background:#FEE2E2;color:#991B1B;padding:.75rem 1rem;border-radius:.5rem;margin-bottom:1rem">{{ $errors->first() }}</p>
    @endif

    <form method="post" action="{{ route('public.daily.subscribe.store') }}" style="background:#fff;border:1px solid #E8DCC8;border-radius:1rem;padding:1.25rem 1.35rem;margin-bottom:1.75rem">
      @csrf
      <label style="display:block;font-size:.8rem;color:#6b7280;margin-bottom:.75rem">{{ __('public.Channel') }}
        <select name="channel" required style="display:block;width:100%;margin-top:.25rem;padding:.5rem .6rem;border:1px solid #D1D5DB;border-radius:.5rem">
          <option value="sms">SMS</option>
          <option value="email">{{ __('public.Email') }}</option>
          <option value="push">{{ __('public.Push (not sent yet)') }}</option>
        </select>
      </label>
      <fieldset style="border:0;padding:0;margin:0 0 .75rem">
        <legend style="font-size:.8rem;color:#6b7280;margin-bottom:.35rem">{{ __('public.Types') }}</legend>
        @foreach(['ayah','hadith','saying','reminder'] as $type)
          <label style="display:inline-flex;align-items:center;gap:.35rem;margin-inline-end:1rem;font-size:.9rem;color:#3D1219">
            <input type="checkbox" name="content_types[]" value="{{ $type }}" @checked($type === 'ayah' || $type === 'hadith')>
            {{ __('public.daily_type_'.$type) }}
          </label>
        @endforeach
      </fieldset>
      <label style="display:block;font-size:.8rem;color:#6b7280;margin-bottom:.75rem">{{ __('public.Language') }}
        <select name="language" style="display:block;width:100%;margin-top:.25rem;padding:.5rem .6rem;border:1px solid #D1D5DB;border-radius:.5rem">
          <option value="en">English</option>
          <option value="dv">ދިވެހި</option>
        </select>
      </label>
      <label style="display:block;font-size:.8rem;color:#6b7280;margin-bottom:1rem">{{ __('public.Send time') }}
        <input type="time" name="send_time" value="06:00" style="display:block;width:100%;margin-top:.25rem;padding:.5rem .6rem;border:1px solid #D1D5DB;border-radius:.5rem">
      </label>
      <button type="submit" style="background:#7C2D37;color:#fff;border:0;border-radius:.5rem;padding:.6rem 1.1rem;font-weight:700;cursor:pointer">{{ __('public.Save subscription') }}</button>
    </form>

    <h2 style="font-size:1.1rem;font-weight:700;color:#3D1219;margin:0 0 .75rem">{{ __('public.Your channels') }}</h2>
    @forelse($subscriptions as $row)
      <div data-subscription-id="{{ $row['id'] }}" data-channel="{{ $row['channel'] }}" data-status="{{ $row['status'] }}"
           style="background:#fff;border:1px solid #E5E7EB;border-radius:.75rem;padding:1rem 1.1rem;margin-bottom:.75rem">
        <p style="margin:0 0 .35rem;font-weight:700;color:#3D1219">{{ ['sms' => 'SMS', 'email' => __('public.Email'), 'push' => __('public.Push (not sent yet)')][$row['channel']] ?? strtoupper($row['channel']) }} · {{ ['active' => __('public.Active'), 'paused' => __('public.Paused')][$row['status']] ?? $row['status'] }}</p>
        <p style="margin:0 0 .75rem;font-size:.85rem;color:#6b7280">{{ implode('، ', array_map(fn ($type) => in_array($type, ['ayah', 'hadith', 'saying', 'reminder'], true) ? __('public.daily_type_'.$type) : $type, $row['content_types'])) }} · {{ ['en' => 'English', 'dv' => 'ދިވެހި'][$row['language']] ?? $row['language'] }} · {{ $row['send_time'] }}</p>
        @if($row['status'] === 'active')
          <form method="post" action="{{ route('public.daily.subscribe.pause', $row['id']) }}">
            @csrf
            <button type="submit" style="background:transparent;border:1px solid #7C2D37;color:#7C2D37;border-radius:.5rem;padding:.4rem .8rem;font-weight:600;cursor:pointer">{{ __('public.Pause') }}</button>
          </form>
        @else
          <form method="post" action="{{ route('public.daily.subscribe.resume', $row['id']) }}">
            @csrf
            <button type="submit" style="background:#7C2D37;color:#fff;border:0;border-radius:.5rem;padding:.4rem .8rem;font-weight:600;cursor:pointer">{{ __('public.Resume') }}</button>
          </form>
        @endif
      </div>
    @empty
      <p style="color:#6b7280">{{ __('public.No subscriptions yet. Nothing is sent until you opt in.') }}</p>
    @endforelse
  </div>
</section>
@endsection

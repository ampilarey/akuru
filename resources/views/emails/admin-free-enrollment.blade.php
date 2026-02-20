<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>New Free Enrollment</title>
<style>
  body { font-family: Arial, sans-serif; background: #f4f6f8; margin: 0; padding: 0; color: #222; }
  .wrapper { max-width: 560px; margin: 28px auto; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,.08); }
  .header { background: #059669; padding: 22px 28px; }
  .header h1 { color: #fff; font-size: 18px; margin: 0; }
  .body { padding: 28px; }
  .row { display: flex; justify-content: space-between; padding: 9px 0; border-bottom: 1px solid #f0f0f0; font-size: 14px; }
  .row:last-child { border-bottom: none; }
  .label { color: #888; }
  .cta { display: inline-block; margin-top: 20px; background: #059669; color: #fff; text-decoration: none; padding: 10px 22px; border-radius: 6px; font-size: 14px; }
  .footer { background: #f4f6f8; padding: 16px 28px; text-align: center; font-size: 11px; color: #aaa; }
</style>
</head>
<body>
<div class="wrapper">
  <div class="header">
    <h1>New free enrollment received</h1>
  </div>
  <div class="body">
    <div style="background:#ecfdf5; border-radius:6px; padding:16px; margin-bottom:20px;">
      <div class="row">
        <span class="label">Course</span>
        <span>{{ $enrollment->course?->title ?? '—' }}</span>
      </div>
      <div class="row">
        <span class="label">Student</span>
        <span>{{ $enrollment->student?->full_name ?? '—' }}</span>
      </div>
      <div class="row">
        <span class="label">Enrolled by</span>
        <span>{{ $user->name ?? '—' }}</span>
      </div>
      <div class="row">
        <span class="label">Contact</span>
        <span>
          @php
            $contact = $user->mobile
                ?? $user->contacts()->where('type','mobile')->value('value')
                ?? $user->email
                ?? $user->contacts()->where('type','email')->value('value')
                ?? '—';
          @endphp
          {{ $contact }}
        </span>
      </div>
      <div class="row">
        <span class="label">Date</span>
        <span>{{ now()->format('d M Y, H:i') }}</span>
      </div>
    </div>
    <a href="{{ url('/en/admin/enrollments') }}" class="cta">View in admin panel</a>
  </div>
  <div class="footer">&copy; {{ date('Y') }} Akuru Institute</div>
</div>
</body>
</html>

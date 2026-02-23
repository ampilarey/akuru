<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Enrollment Update</title>
<style>
  body { font-family: Arial, sans-serif; background: #f9f7f4; margin: 0; padding: 0; color: #222; }
  .wrapper { max-width: 580px; margin: 32px auto; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,.07); }
  .header { background: #7c1c28; padding: 28px 32px; text-align: center; }
  .header img { height: 40px; }
  .header h1 { color: #fff; font-size: 20px; margin: 12px 0 0; }
  .body { padding: 32px; }
  .status-badge { display: inline-block; padding: 6px 16px; border-radius: 20px; font-weight: bold; font-size: 14px; margin-bottom: 24px; }
  .status-active   { background: #d1fae5; color: #065f46; }
  .status-rejected { background: #fee2e2; color: #991b1b; }
  .detail-row { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #f0ece8; font-size: 15px; }
  .detail-row:last-child { border-bottom: none; }
  .label { color: #888; }
  .footer { background: #f9f7f4; padding: 20px 32px; text-align: center; font-size: 12px; color: #aaa; }
</style>
</head>
<body>
<div class="wrapper">
  <div class="header">
    @if($newStatus === 'active')
      <h1>Your Enrollment is Confirmed!</h1>
    @elseif($newStatus === 'rejected')
      <h1>Enrollment Update</h1>
    @else
      <h1>Akuru Institute</h1>
    @endif
  </div>
  <div class="body">
    <p>Dear {{ $enrollment->creator?->name ?? 'Student' }},</p>

    @if($newStatus === 'active')
      <p>Great news! Your enrollment has been <strong>reviewed and confirmed</strong> by our team. Welcome to Akuru Institute!</p>
      <span class="status-badge status-active">✓ Enrollment Confirmed</span>
    @elseif($newStatus === 'rejected')
      <p>We regret to inform you that your enrollment application was <strong>not approved</strong> at this time.</p>
      <span class="status-badge status-rejected">✗ Not Approved</span>
      <p style="color:#666; font-size:14px;">If you have any questions, please contact us at <a href="mailto:{{ config('mail.from.address') }}">{{ config('mail.from.address') }}</a>.</p>
    @else
      <p>Your enrollment status has been updated to: <strong>{{ ucfirst($newStatus) }}</strong>.</p>
    @endif

    <div style="background:#f9f7f4; border-radius:6px; padding:16px; margin:20px 0;">
      <div class="detail-row">
        <span class="label">Course</span>
        <span>{{ $enrollment->course?->title ?? '—' }}</span>
      </div>
      <div class="detail-row">
        <span class="label">Student</span>
        <span>{{ $enrollment->student?->full_name ?? '—' }}</span>
      </div>
      @if($enrollment->enrolled_at)
      <div class="detail-row">
        <span class="label">Enrollment date</span>
        <span>{{ $enrollment->enrolled_at->format('d M Y') }}</span>
      </div>
      @endif
    </div>

    <p style="color:#666; font-size:13px;">
      Thank you for choosing Akuru Institute.<br>
      <a href="{{ config('app.url') }}" style="color:#7c1c28;">{{ config('app.url') }}</a>
    </p>
  </div>
  <div class="footer">
    &copy; {{ date('Y') }} Akuru Institute. All rights reserved.
  </div>
</div>
</body>
</html>

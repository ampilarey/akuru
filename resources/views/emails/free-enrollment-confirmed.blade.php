<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Enrollment Confirmed</title>
<style>
  body { font-family: Arial, sans-serif; background: #f9f7f4; margin: 0; padding: 0; color: #222; }
  .wrapper { max-width: 560px; margin: 32px auto; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,.07); }
  .header { background: #7c1c28; padding: 28px 32px; text-align: center; }
  .header h1 { color: #fff; font-size: 20px; margin: 0; }
  .body { padding: 32px; }
  .badge { display: inline-block; background: #d1fae5; color: #065f46; padding: 6px 16px; border-radius: 20px; font-weight: bold; font-size: 13px; margin-bottom: 20px; }
  .detail-row { display: flex; justify-content: space-between; padding: 9px 0; border-bottom: 1px solid #f0ece8; font-size: 14px; }
  .detail-row:last-child { border-bottom: none; }
  .label { color: #888; }
  .footer { background: #f9f7f4; padding: 16px 32px; text-align: center; font-size: 11px; color: #aaa; }
</style>
</head>
<body>
<div class="wrapper">
  <div class="header">
    <h1>Akuru Institute</h1>
  </div>
  <div class="body">
    <p>Dear {{ $enrollment->creator?->name ?? 'Student' }},</p>
    <p>Your enrollment has been <strong>confirmed</strong>. Welcome to the course!</p>

    <span class="badge">✓ Enrollment confirmed</span>

    <div style="background:#f9f7f4; border-radius:6px; padding:16px; margin:16px 0;">
      <div class="detail-row">
        <span class="label">Course</span>
        <span>{{ $enrollment->course?->title ?? '—' }}</span>
      </div>
      <div class="detail-row">
        <span class="label">Student</span>
        <span>{{ $enrollment->student?->full_name ?? '—' }}</span>
      </div>
      <div class="detail-row">
        <span class="label">Fee</span>
        <span style="color:#059669; font-weight:600;">Free</span>
      </div>
      @if($enrollment->enrolled_at)
      <div class="detail-row">
        <span class="label">Date</span>
        <span>{{ $enrollment->enrolled_at->format('d M Y') }}</span>
      </div>
      @endif
    </div>

    <p style="color:#666; font-size:13px;">
      If you have any questions, contact us at
      <a href="mailto:info@akuru.edu.mv" style="color:#7c1c28;">info@akuru.edu.mv</a>.
    </p>
    <p style="color:#666; font-size:13px;">Thank you for choosing Akuru Institute.</p>
  </div>
  <div class="footer">&copy; {{ date('Y') }} Akuru Institute. All rights reserved.</div>
</div>
</body>
</html>

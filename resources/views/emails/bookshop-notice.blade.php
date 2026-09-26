<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{{ $heading }}</title>
<style>
  body { font-family: Arial, sans-serif; background: #f9f7f4; margin: 0; padding: 0; color: #222; }
  .wrapper { max-width: 560px; margin: 32px auto; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,.07); }
  .header { background: #7c1c28; padding: 24px 32px; text-align: center; }
  .header h1 { color: #fff; font-size: 18px; margin: 0; }
  .body { padding: 28px 32px; }
  .body h2 { font-size: 18px; margin: 0 0 12px; color: #7c1c28; }
  .button { display: inline-block; margin-top: 18px; background: #7c1c28; color: #fff !important; text-decoration: none; padding: 10px 18px; border-radius: 6px; }
  .note { color: #777; font-size: 12px; margin-top: 24px; }
  .footer { background: #f9f7f4; padding: 16px 32px; text-align: center; font-size: 11px; color: #aaa; }
</style>
</head>
<body>
<div class="wrapper">
  <div class="header"><h1>Akuru Bookstore</h1></div>
  <div class="body">
    <p>Dear {{ $recipientName }},</p>
    <h2 dir="auto">{{ $heading }}</h2>
    <p dir="auto" style="white-space: pre-line;">{{ $body }}</p>
    @if($link)
      <a class="button" href="{{ $link }}">Open in Akuru</a>
    @endif
    <p class="note">You receive this because of an order or a shop at the Akuru Bookstore. Turn shop notices off under Notifications in your account.</p>
  </div>
  <div class="footer">Akuru Institute · {{ config('app.url') }}</div>
</div>
</body>
</html>

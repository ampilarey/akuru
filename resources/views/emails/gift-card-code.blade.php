<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Your Akuru Institute gift card</title>
<style>
  body { font-family: Arial, sans-serif; background: #f9f7f4; margin: 0; padding: 0; color: #222; }
  .wrapper { max-width: 560px; margin: 32px auto; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,.07); }
  .header { background: #7c1c28; padding: 28px 32px; text-align: center; }
  .header h1 { color: #fff; font-size: 20px; margin: 0; }
  .body { padding: 32px; }
  .code { font-family: Menlo, Consolas, monospace; font-size: 22px; letter-spacing: 2px; background: #f9f7f4; border: 1px dashed #c9b8a8; border-radius: 6px; padding: 14px 18px; text-align: center; margin: 20px 0; }
  .amount { font-size: 28px; font-weight: bold; color: #7c1c28; }
  .note { color: #555; font-size: 13px; }
  .footer { background: #f9f7f4; padding: 16px 32px; text-align: center; font-size: 11px; color: #aaa; }
</style>
</head>
<body>
<div class="wrapper">
  <div class="header">
    <h1>Akuru Institute</h1>
  </div>
  <div class="body">
    <p>Dear {{ $recipientName }},</p>
    <p>You have been given an Akuru Institute gift card worth <span class="amount">{{ $currency }} {{ number_format((float) $amount, 2) }}</span>.</p>
    @if($message)
      <blockquote style="border-left:3px solid #c9b8a8;margin:16px 0;padding:8px 16px;color:#444;">{{ $message }}</blockquote>
    @endif
    <p>Your gift card code:</p>
    <div class="code">{{ $plainCode }}</div>
    <p class="note">Sign in at {{ config('app.url') }}, open <strong>My Wallet</strong> and redeem this code. The amount becomes wallet money you can spend on books, articles and research in the Akuru Digital Library.</p>
    <p class="note">Keep this email: the code is shown only here and is not stored anywhere we can read it back.</p>
  </div>
  <div class="footer">Akuru Institute · {{ config('app.url') }}</div>
</div>
</body>
</html>

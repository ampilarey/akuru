<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $subjectLine }}</title>
</head>
<body style="font-family:Arial,sans-serif;background:#F8F4EC;margin:0;padding:20px;color:#3D1219">
<div style="max-width:640px;margin:0 auto;background:#fff;border-radius:8px;overflow:hidden">
    <div style="background:#7C2D37;color:#fff;padding:24px 32px">
        <h1 style="margin:0;font-size:20px">{{ $subjectLine }}</h1>
        <p style="margin:6px 0 0;opacity:.9;font-size:14px">Akuru Institute</p>
    </div>
    <div style="padding:24px 32px">
        @foreach($items as $item)
            <p style="margin:0 0 8px;font-weight:700;color:#7C2D37">{{ $item['label'] }}</p>
            @if(! empty($item['body']))
                <p style="margin:0 0 8px;line-height:1.6">{{ $item['body'] }}</p>
            @endif
            <p style="margin:0 0 20px"><a href="{{ $item['url'] }}" style="color:#7C2D37">{{ $item['url'] }}</a></p>
        @endforeach
        <p style="font-size:12px;color:#6b7280;margin:24px 0 0">
            Unsubscribe: <a href="{{ $unsubscribeUrl }}" style="color:#7C2D37">{{ $unsubscribeUrl }}</a>
        </p>
    </div>
</div>
</body>
</html>

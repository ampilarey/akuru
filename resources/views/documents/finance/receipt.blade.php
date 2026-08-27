<!DOCTYPE html>
<html lang="{{ $locale ?? 'en' }}" dir="{{ $dir ?? 'ltr' }}">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        body { font-family: "Noto Sans", "MV Faseyha", sans-serif; margin: 24px; color: #1f1f1f; }
        h1 { color: #7C2D37; margin-bottom: 4px; }
        table { border-collapse: collapse; margin-top: 16px; }
        th, td { text-align: start; padding: 6px 18px 6px 0; vertical-align: top; }
        th { color: #6b5d4f; font-weight: 600; }
        .amount { font-size: 1.25rem; font-weight: 700; }
    </style>
</head>
<body>
    <h1>{{ __('documents.receipt.heading') }}</h1>
    <p>{{ $title }}</p>

    <table>
        <tr>
            <th>{{ __('documents.receipt.invoice') }}</th>
            <td>{{ $invoice }}</td>
        </tr>
        <tr>
            <th>{{ __('documents.receipt.amount') }}</th>
            <td class="amount">{{ $amount }}</td>
        </tr>
        <tr>
            <th>{{ __('documents.receipt.method') }}</th>
            <td>{{ __('documents.receipt.methods.'.$method) }}</td>
        </tr>
        <tr>
            <th>{{ __('documents.receipt.received_at') }}</th>
            <td>{{ $received_at }}</td>
        </tr>
    </table>
</body>
</html>

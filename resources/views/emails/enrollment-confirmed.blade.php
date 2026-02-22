<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Enrollment Confirmed</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f5f5f5; margin: 0; padding: 20px; color: #333; }
        .wrapper { max-width: 600px; margin: 0 auto; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,.08); }
        .header { background: #7c1a2b; color: #fff; padding: 32px 40px; }
        .header h1 { margin: 0; font-size: 22px; }
        .header p { margin: 6px 0 0; opacity: .85; font-size: 14px; }
        .body { padding: 32px 40px; }
        .body p { line-height: 1.6; margin: 0 0 16px; }
        .courses { width: 100%; border-collapse: collapse; margin: 20px 0; }
        .courses th { background: #f8f0f2; text-align: left; padding: 10px 14px; font-size: 13px; color: #7c1a2b; border-bottom: 2px solid #e8d5d9; }
        .courses td { padding: 10px 14px; border-bottom: 1px solid #f0e8ea; font-size: 14px; }
        .badge { display: inline-block; padding: 2px 10px; border-radius: 12px; font-size: 12px; font-weight: 600; }
        .badge-green { background: #d1fae5; color: #065f46; }
        .badge-amber { background: #fef3c7; color: #92400e; }
        .amount-box { background: #f8f0f2; border-left: 4px solid #7c1a2b; padding: 14px 18px; border-radius: 4px; margin: 20px 0; }
        .footer { background: #f5f5f5; padding: 20px 40px; font-size: 12px; color: #888; text-align: center; }
    </style>
</head>
<body>
<div class="wrapper">
    <div class="header">
        <h1>Enrollment Confirmed</h1>
        <p>Akuru Institute – Registration Receipt</p>
    </div>
    <div class="body">
        @php
            $student = $payment->student;
            $studentName = $student ? $student->full_name : ($payment->user->name ?? 'Student');
        @endphp

        <p>Dear <strong>{{ $payment->user->name ?? 'Parent/Guardian' }}</strong>,</p>
        <p>
            Thank you! We have received your payment for <strong>{{ $studentName }}</strong>'s enrollment.
            Your enrollment is now <strong>pending admin approval</strong>.
        </p>
        <p style="color:#555;font-size:13px;">
            You will receive a confirmation SMS/email once the admin approves your enrollment.
            This usually takes 1–2 business days.
        </p>

        <table class="courses">
            <thead>
                <tr>
                    <th>Course</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach($payment->items as $item)
                    @php $enrollment = $item->enrollment; @endphp
                    <tr>
                        <td>{{ $item->course->title ?? '—' }}</td>
                        <td>
                            @if($enrollment?->status === 'active')
                                <span class="badge badge-green">Active</span>
                            @else
                                <span class="badge badge-amber">Pending approval</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="amount-box">
            <strong>Amount paid:</strong>
            {{ number_format($payment->amount, 2) }} {{ $payment->currency }}<br>
            <strong>Reference:</strong> {{ $payment->merchant_reference }}<br>
            <strong>Date:</strong> {{ $payment->paid_at?->format('d M Y, H:i') ?? now()->format('d M Y, H:i') }}
        </div>

        {{-- Receipt link --}}
        <div style="text-align:center; margin: 24px 0;">
            <a href="{{ url(route('payment.receipt', $payment, false)) }}"
               style="display:inline-block; background:#7c1a2b; color:#fff; text-decoration:none; padding:12px 28px; border-radius:6px; font-size:14px; font-weight:600;">
                View / Print Receipt
            </a>
        </div>

        <p>
            If any of the courses above require admin approval, our team will review your enrollment and contact you shortly.
        </p>
        <p>For any questions, please contact us at <a href="mailto:{{ config('mail.from.address') }}">{{ config('mail.from.address') }}</a>.</p>
        <p>Thank you for choosing Akuru Institute.</p>
    </div>
    <div class="footer">
        &copy; {{ date('Y') }} Akuru Institute. This is an automated email — please do not reply directly.
    </div>
</div>
</body>
</html>

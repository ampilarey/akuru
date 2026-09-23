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
        .muted { color: #6b5d4f; font-size: 0.9rem; }
    </style>
</head>
<body>
    <h1>{{ __('documents.payslip.heading') }}</h1>
    <p>{{ $title }}</p>

    <table>
        <tr>
            <th>{{ __('documents.payslip.staff') }}</th>
            <td>{{ $staff_name }}</td>
        </tr>
        <tr>
            <th>{{ __('documents.payslip.period') }}</th>
            <td>{{ $period }}</td>
        </tr>
        <tr>
            <th>{{ __('documents.payslip.basic_salary') }}</th>
            <td>{{ $basic_salary }}</td>
        </tr>
        <tr>
            <th>{{ __('documents.payslip.gross') }}</th>
            <td>{{ $gross }}</td>
        </tr>
        <tr>
            <th>{{ __('documents.payslip.employee_pension') }}</th>
            <td>{{ $employee_pension }}</td>
        </tr>
        <tr>
            <th>{{ __('documents.payslip.tax_withheld') }}</th>
            <td>{{ $tax_withheld }}</td>
        </tr>
        <tr>
            <th>{{ __('documents.payslip.unpaid_leave_deduction') }}</th>
            <td>{{ $unpaid_leave_deduction }}</td>
        </tr>
        <tr>
            <th>{{ __('documents.payslip.net_pay') }}</th>
            <td class="amount">{{ $net_pay }}</td>
        </tr>
    </table>

    <p class="muted">{{ __('documents.payslip.employer_pension_note', ['amount' => $employer_pension]) }}</p>
</body>
</html>

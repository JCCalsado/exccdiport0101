<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Payment Confirmation — CCDI Account Portal</title>
    <style>
        body {
            margin: 0; padding: 0;
            background-color: #f4f4f5;
            font-family: 'Segoe UI', Helvetica, Arial, sans-serif;
            color: #18181b;
        }
        .wrapper {
            max-width: 600px; margin: 40px auto;
            background: #ffffff; border-radius: 8px;
            overflow: hidden; box-shadow: 0 1px 4px rgba(0,0,0,0.08);
        }
        .header {
            background-color: #15803d;
            padding: 24px 32px; color: #ffffff;
        }
        .header h1 { margin: 0; font-size: 18px; font-weight: 600; }
        .header p  { margin: 4px 0 0; font-size: 13px; opacity: 0.85; }
        .badge {
            display: inline-block; padding: 3px 10px;
            border-radius: 99px; font-size: 11px;
            font-weight: 700; text-transform: uppercase;
            letter-spacing: 0.5px; margin-bottom: 12px;
            background: #dcfce7; color: #15803d;
        }
        .body { padding: 32px; }
        .body p {
            margin: 0 0 16px; font-size: 15px;
            line-height: 1.6; color: #3f3f46;
        }
        .amount-box {
            background: #f0fdf4; border: 1px solid #86efac;
            border-radius: 6px; padding: 16px 20px; margin: 20px 0;
        }
        .amount-box .label { font-size: 12px; color: #166534; text-transform: uppercase; letter-spacing: 0.5px; margin: 0 0 4px; }
        .amount-box .value { font-size: 28px; font-weight: 700; color: #14532d; margin: 0; }
        .receipt-table { width: 100%; border-collapse: collapse; margin: 20px 0; font-size: 14px; }
        .receipt-table td { padding: 10px 0; border-bottom: 1px solid #f4f4f5; color: #3f3f46; }
        .receipt-table td:first-child { color: #71717a; width: 45%; }
        .receipt-table td:last-child { font-weight: 600; text-align: right; }
        .action-btn {
            display: inline-block; margin-top: 8px;
            padding: 12px 28px; background-color: #15803d;
            color: #ffffff !important; text-decoration: none;
            border-radius: 6px; font-size: 14px; font-weight: 600;
        }
        .divider { border: none; border-top: 1px solid #e4e4e7; margin: 28px 0; }
        .footer {
            padding: 20px 32px; background: #f4f4f5;
            font-size: 12px; color: #71717a;
            text-align: center; line-height: 1.6;
        }
        .footer a { color: #15803d; text-decoration: none; }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="header">
            <h1>CCDI Account Portal</h1>
            <p>Computer Communication Development Institute</p>
        </div>
        <div class="body">
            <span class="badge">✓ Payment Confirmed</span>

            <p>Hello, <strong>{{ $studentName ?? 'Student' }}</strong>.</p>

            <p>
                Your payment has been successfully recorded in the CCDI Account Portal.
                Please keep this confirmation for your records.
            </p>

            <div class="amount-box">
                <p class="label">Amount Paid</p>
                <p class="value">₱{{ number_format($amount, 2) }}</p>
            </div>

            <table class="receipt-table">
                <tr>
                    <td>Reference Number</td>
                    <td>{{ $referenceNumber }}</td>
                </tr>
                <tr>
                    <td>Transaction ID</td>
                    <td>PAY-{{ $transaction->id }}</td>
                </tr>
                <tr>
                    <td>Payment Method</td>
                    <td>{{ ucwords(str_replace('_', ' ', $paymentMethod)) }}</td>
                </tr>
                <tr>
                    <td>Date Recorded</td>
                    <td>{{ $transaction->created_at->format('F d, Y h:i A') }}</td>
                </tr>
            </table>

            <a href="{{ $actionUrl }}" class="action-btn">View Payment History</a>

            <hr class="divider" />

            <p style="font-size: 13px; color: #71717a;">
                If you did not make this payment or believe this is an error,
                please contact the Accounting Office immediately.
            </p>
        </div>
        <div class="footer">
            &copy; {{ date('Y') }} Computer Communication Development Institute.
            All rights reserved.<br />
            This is an automated message from the CCDI Account Portal.
            Please do not reply to this email.
        </div>
    </div>
</body>
</html>
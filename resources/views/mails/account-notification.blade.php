<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>{{ $notifTitle }} — CCDI Account Portal</title>
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
        .header { padding: 24px 32px; color: #ffffff; }
        .header-info    { background-color: #1d4ed8; }
        .header-success { background-color: #15803d; }
        .header-warning { background-color: #b45309; }
        .header-error   { background-color: #b91c1c; }
        .header h1 { margin: 0; font-size: 18px; font-weight: 600; letter-spacing: 0.3px; }
        .header p  { margin: 4px 0 0; font-size: 13px; opacity: 0.85; }
        .badge {
            display: inline-block; padding: 3px 10px;
            border-radius: 99px; font-size: 11px; font-weight: 700;
            text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 12px;
        }
        .badge-info    { background: #dbeafe; color: #1d4ed8; }
        .badge-success { background: #dcfce7; color: #15803d; }
        .badge-warning { background: #fef9c3; color: #854d0e; }
        .badge-error   { background: #fee2e2; color: #b91c1c; }
        .body { padding: 32px; }
        .body p { margin: 0 0 16px; font-size: 15px; line-height: 1.6; color: #3f3f46; }
        .detail-card {
            border-radius: 8px; border: 1px solid #fde68a;
            background: #fffbeb; padding: 20px 24px; margin: 20px 0;
        }
        .info-card {
            border-radius: 8px; border: 1px solid #e4e4e7;
            background: #f9f9fb; padding: 16px 20px; margin: 20px 0;
        }
        .card-label {
            font-size: 11px; font-weight: 700; text-transform: uppercase;
            letter-spacing: 0.6px; color: #92400e; margin: 0 0 14px;
        }
        .info-card .card-label { color: #71717a; }
        .detail-row {
            display: table; width: 100%; border-collapse: collapse; margin-bottom: 10px;
        }
        .detail-row:last-child { margin-bottom: 0; }
        .detail-key {
            display: table-cell; width: 38%; font-size: 12px;
            color: #78350f; font-weight: 600; padding: 4px 8px 4px 0;
            vertical-align: top; white-space: nowrap;
        }
        .detail-val {
            display: table-cell; font-size: 13px; color: #1c1917;
            padding: 4px 0; vertical-align: top;
        }
        .detail-divider { border: none; border-top: 1px solid #fde68a; margin: 12px 0; }
        .action-btn {
            display: inline-block; margin-top: 8px;
            padding: 12px 28px; color: #ffffff !important;
            text-decoration: none; border-radius: 6px;
            font-size: 14px; font-weight: 600;
        }
        .btn-info    { background-color: #1d4ed8; }
        .btn-success { background-color: #15803d; }
        .btn-warning { background-color: #b45309; }
        .btn-error   { background-color: #b91c1c; }
        .divider { border: none; border-top: 1px solid #e4e4e7; margin: 28px 0; }
        .footer {
            padding: 20px 32px; background: #f4f4f5;
            font-size: 12px; color: #71717a; text-align: center; line-height: 1.6;
        }
        .footer a { color: #1d4ed8; text-decoration: none; }
    </style>
</head>
<body>
    <div class="wrapper">

        <div class="header header-{{ $notifType ?? 'info' }}">
            <h1>CCDI Account Portal</h1>
            <p>Computer Communication Development Institute</p>
        </div>

        <div class="body">
            @php
                $badgeLabels = [
                    'warning' => '⚠ Payment Due',
                    'success' => '✓ Payment Approved',
                    'error'   => '✗ Payment Rejected',
                    'info'    => 'ℹ Announcement',
                ];
                $badgeText = $badgeLabels[$notifType ?? 'info'] ?? ucfirst($notifType ?? 'info');
            @endphp
            <span class="badge badge-{{ $notifType ?? 'info' }}">{{ $badgeText }}</span>

            <p>Hello, <strong>{{ $studentName }}</strong>.</p>

            @if (($notifType ?? '') === 'warning')
                {{-- ── Payment Due: structured detail card ── --}}
                <p>{{ $notifBody }}</p>

                <div class="detail-card">
                    <p class="card-label">Payment Due Details</p>

                    <div class="detail-row">
                        <span class="detail-key">Title</span>
                        <span class="detail-val">{{ $notifTitle }}</span>
                    </div>

                    @if (!empty($notifBody))
                    <div class="detail-row">
                        <span class="detail-key">Content</span>
                        <span class="detail-val">{!! nl2br(e($notifBody)) !!}</span>
                    </div>
                    @endif

                    <hr class="detail-divider" />

                    @if (!empty($dueDate))
                    <div class="detail-row">
                        <span class="detail-key">Payment Due</span>
                        <span class="detail-val"><strong>{{ \Carbon\Carbon::parse($dueDate)->format('M d, Y') }}</strong></span>
                    </div>
                    @endif

                    @if (!empty($startDate))
                    <div class="detail-row">
                        <span class="detail-key">From</span>
                        <span class="detail-val">{{ \Carbon\Carbon::parse($startDate)->format('M d, Y') }}</span>
                    </div>
                    @endif

                    @if (!empty($endDate))
                    <div class="detail-row">
                        <span class="detail-key">To</span>
                        <span class="detail-val">{{ \Carbon\Carbon::parse($endDate)->format('M d, Y') }}</span>
                    </div>
                    @endif
                </div>

                <p style="font-size: 13px; color: #71717a; margin-top: 4px;">
                    Please settle your payment before the due date to avoid late charges
                    or disruption to your enrollment status.
                </p>

            @else
                {{-- ── General / approved / rejected ── --}}
                <p>{!! nl2br(e($notifBody)) !!}</p>

                @if (!empty($dueDate) || !empty($startDate) || !empty($endDate))
                <div class="info-card">
                    <p class="card-label">Notification Details</p>

                    @if (!empty($dueDate))
                    <div class="detail-row">
                        <span class="detail-key" style="color:#52525b;">Due Date</span>
                        <span class="detail-val">{{ \Carbon\Carbon::parse($dueDate)->format('M d, Y') }}</span>
                    </div>
                    @endif

                    @if (!empty($startDate))
                    <div class="detail-row">
                        <span class="detail-key" style="color:#52525b;">From</span>
                        <span class="detail-val">{{ \Carbon\Carbon::parse($startDate)->format('M d, Y') }}</span>
                    </div>
                    @endif

                    @if (!empty($endDate))
                    <div class="detail-row">
                        <span class="detail-key" style="color:#52525b;">To</span>
                        <span class="detail-val">{{ \Carbon\Carbon::parse($endDate)->format('M d, Y') }}</span>
                    </div>
                    @endif
                </div>
                @endif
            @endif

            @if ($actionUrl && $actionLabel)
                <a href="{{ $actionUrl }}" class="action-btn btn-{{ $notifType ?? 'info' }}">{{ $actionLabel }}</a>
            @endif

            <hr class="divider" />

            <p style="font-size: 13px; color: #71717a;">
                You can also
                <a href="{{ $dashboardUrl }}" style="color: #1d4ed8;">visit your dashboard</a>
                to review your account details.
                If you have already settled this payment, you may disregard this notice.
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
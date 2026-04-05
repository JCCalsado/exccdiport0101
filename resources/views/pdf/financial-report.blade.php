<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Financial Report — {{ $schoolYear }} {{ $semester }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; color: #1a1a1a; }

        /* ── Header ─────────────────────────────────────────────────────── */
        .header { text-align: center; margin-bottom: 24px; border-bottom: 2px solid #4f46e5; padding-bottom: 16px; }
        .header .school-name { font-size: 18px; font-weight: bold; color: #111827; }
        .header .report-title { font-size: 13px; color: #4f46e5; margin-top: 4px; font-weight: 600; }
        .header .meta { font-size: 10px; color: #9ca3af; margin-top: 4px; }

        /* ── Summary grid ───────────────────────────────────────────────── */
        .summary-grid { display: table; width: 100%; margin-bottom: 24px; border-collapse: separate; border-spacing: 6px; }
        .summary-cell { display: table-cell; width: 25%; padding: 14px 12px; background: #f9fafb; border: 1px solid #e5e7eb; text-align: center; border-radius: 6px; }
        .summary-cell .label { font-size: 9px; color: #9ca3af; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 6px; }
        .summary-cell .value { font-size: 15px; font-weight: bold; color: #111827; }
        .summary-cell .value.green { color: #059669; }
        .summary-cell .value.red { color: #dc2626; }
        .summary-cell .sub { font-size: 9px; color: #9ca3af; margin-top: 4px; }

        /* ── Section title ──────────────────────────────────────────────── */
        .section-title {
            font-size: 11px;
            font-weight: bold;
            color: #374151;
            margin-bottom: 10px;
            margin-top: 24px;
            border-left: 3px solid #4f46e5;
            padding-left: 8px;
        }

        /* ── Table ──────────────────────────────────────────────────────── */
        table { width: 100%; border-collapse: collapse; font-size: 10px; }
        thead tr { background: #f3f4f6; }
        th {
            padding: 9px 10px;
            text-align: left;
            font-weight: 600;
            color: #6b7280;
            text-transform: uppercase;
            font-size: 9px;
            letter-spacing: 0.05em;
            border-bottom: 2px solid #e5e7eb;
        }
        td { padding: 8px 10px; border-bottom: 1px solid #f3f4f6; color: #374151; }
        tr:last-child td { border-bottom: none; }
        tr:nth-child(even) { background: #fafafa; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }

        /* ── Badges ─────────────────────────────────────────────────────── */
        .badge { display: inline-block; padding: 2px 8px; border-radius: 20px; font-size: 9px; font-weight: 600; }
        .badge-amber { background: #fef3c7; color: #92400e; }

        /* ── Empty state ────────────────────────────────────────────────── */
        .empty { text-align: center; padding: 24px; color: #9ca3af; font-size: 11px; }

        /* ── Footer ─────────────────────────────────────────────────────── */
        .footer {
            margin-top: 36px;
            border-top: 1px solid #e5e7eb;
            padding-top: 12px;
            display: table;
            width: 100%;
            font-size: 9px;
            color: #9ca3af;
        }
        .footer-left { display: table-cell; }
        .footer-right { display: table-cell; text-align: right; }
    </style>
</head>
<body>

    {{-- ── Header ─────────────────────────────────────────────────────────── --}}
    <div class="header">
        <div class="school-name">CCDI Account Portal</div>
        <div class="report-title">Financial Report — {{ $schoolYear }} &bullet; {{ $semester }}</div>
        <div class="meta">Generated {{ now()->format('F j, Y \a\t g:i A') }}</div>
    </div>

    {{-- ── Summary cards ───────────────────────────────────────────────────── --}}
    <div class="summary-grid">
        <div class="summary-cell">
            <div class="label">Total Assessments</div>
            <div class="value">{{ $summary['totalAssessments'] }}</div>
            <div class="sub">students assessed</div>
        </div>
        <div class="summary-cell">
            <div class="label">Total Assessment Amount</div>
            <div class="value">&#8369;{{ number_format($summary['totalAssessmentAmount'], 2) }}</div>
            @php
                $collectionRate = $summary['totalAssessmentAmount'] > 0
                    ? round(($summary['totalPaid'] / $summary['totalAssessmentAmount']) * 100)
                    : 0;
            @endphp
            <div class="sub">{{ $collectionRate }}% collected</div>
        </div>
        <div class="summary-cell">
            <div class="label">Total Paid</div>
            <div class="value green">&#8369;{{ number_format($summary['totalPaid'], 2) }}</div>
            <div class="sub">collected so far</div>
        </div>
        <div class="summary-cell">
            <div class="label">Outstanding</div>
            <div class="value red">&#8369;{{ number_format($summary['totalOutstanding'], 2) }}</div>
            <div class="sub">remaining balance</div>
        </div>
    </div>

    {{-- ── Outstanding student balances ────────────────────────────────────── --}}
    {{--
        FIX: $students is now pre-filtered to balance > 0 only,
        sorted by highest balance, capped at 20. No more fully-paid
        students appearing here.
    --}}
    <div class="section-title">
        Top Outstanding Balances ({{ $semester }} &bullet; {{ $schoolYear }})
    </div>

    <table>
        <thead>
            <tr>
                <th>Account ID</th>
                <th>Student Name</th>
                <th>Course</th>
                <th class="text-right">Total Assessment</th>
                <th class="text-right">Outstanding Balance</th>
                <th class="text-center">Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse($students as $student)
                <tr>
                    <td>{{ $student['accountId'] }}</td>
                    <td>{{ $student['studentName'] }}</td>
                    <td>{{ $student['course'] }}</td>
                    <td class="text-right">&#8369;{{ number_format($student['total'], 2) }}</td>
                    <td class="text-right" style="color:#dc2626; font-weight:700;">
                        &#8369;{{ number_format($student['balance'], 2) }}
                    </td>
                    <td class="text-center">
                        <span class="badge badge-amber">Pending</span>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="empty">
                        No outstanding balances for {{ $semester }} {{ $schoolYear }}.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    {{-- ── Footer ──────────────────────────────────────────────────────────── --}}
    <div class="footer">
        <div class="footer-left">
            CCDI Account Portal &bullet; Financial Report &bullet; {{ $schoolYear }} {{ $semester }}
        </div>
        <div class="footer-right">
            Printed: {{ now()->format('Y-m-d H:i') }}
        </div>
    </div>

</body>
</html>
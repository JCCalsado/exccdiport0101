<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Financial Report — {{ $schoolYear }} {{ $semester }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; color: #1a1a1a; }

        .header { text-align: center; margin-bottom: 24px; border-bottom: 1px solid #e5e7eb; padding-bottom: 16px; }
        .header .school-name { font-size: 16px; font-weight: bold; color: #111827; }
        .header .report-title { font-size: 12px; color: #6b7280; margin-top: 2px; }
        .header .meta { font-size: 11px; color: #9ca3af; margin-top: 4px; }

        .summary-grid { display: table; width: 100%; margin-bottom: 20px; }
        .summary-cell { display: table-cell; width: 25%; padding: 12px; background: #f9fafb; border: 1px solid #e5e7eb; text-align: center; }
        .summary-cell .label { font-size: 9px; color: #9ca3af; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 4px; }
        .summary-cell .value { font-size: 16px; font-weight: bold; color: #111827; }
        .summary-cell .value.green { color: #059669; }
        .summary-cell .value.red   { color: #dc2626; }
        .summary-cell .sub { font-size: 9px; color: #9ca3af; margin-top: 2px; }

        .section-title { font-size: 11px; font-weight: bold; color: #374151; margin-bottom: 8px; margin-top: 20px; border-left: 3px solid #4f46e5; padding-left: 8px; }

        table { width: 100%; border-collapse: collapse; font-size: 10px; }
        thead tr { background: #f3f4f6; }
        th { padding: 8px 10px; text-align: left; font-weight: 600; color: #6b7280; text-transform: uppercase; font-size: 9px; letter-spacing: 0.05em; border-bottom: 1px solid #e5e7eb; }
        td { padding: 7px 10px; border-bottom: 1px solid #f3f4f6; color: #374151; }
        tr:last-child td { border-bottom: none; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }

        .badge { display: inline-block; padding: 2px 8px; border-radius: 20px; font-size: 9px; font-weight: 600; }
        .badge-green { background: #d1fae5; color: #065f46; }
        .badge-amber { background: #fef3c7; color: #92400e; }
        .badge-red   { background: #fee2e2; color: #991b1b; }

        .footer { margin-top: 32px; border-top: 1px solid #e5e7eb; padding-top: 12px; display: table; width: 100%; font-size: 9px; color: #9ca3af; }
        .footer-left  { display: table-cell; }
        .footer-right { display: table-cell; text-align: right; }
    </style>
</head>
<body>

    <div class="header">
        <div class="school-name">CCDI Account Portal</div>
        <div class="report-title">Financial Report — {{ $schoolYear }} &bullet; {{ $semester }}</div>
        <div class="meta">Generated {{ now()->format('F j, Y \a\t g:i A') }}</div>
    </div>

    <!-- Summary -->
    <div class="summary-grid">
        <div class="summary-cell">
            <div class="label">Total Assessments</div>
            <div class="value">{{ $summary['totalAssessments'] }}</div>
            <div class="sub">active assessments</div>
        </div>
        <div class="summary-cell">
            <div class="label">Total Assessment Amount</div>
            <div class="value green">₱{{ number_format($summary['totalAssessmentAmount'], 2) }}</div>
            @php
                $collectionRate = $summary['totalAssessmentAmount'] > 0 
                    ? round(($summary['totalPaid'] / $summary['totalAssessmentAmount']) * 100) 
                    : 0;
            @endphp
            <div class="sub">{{ $collectionRate }}% collected</div>
        </div>
        <div class="summary-cell">
            <div class="label">Total Paid</div>
            <div class="value green">₱{{ number_format($summary['totalPaid'], 2) }}</div>
            <div class="sub">collected so far</div>
        </div>
        <div class="summary-cell">
            <div class="label">Outstanding</div>
            <div class="value red">₱{{ number_format($summary['totalOutstanding'], 2) }}</div>
            <div class="sub">remaining balance</div>
        </div>
    </div>

    <!-- Student breakdown table -->
    <div class="section-title">Outstanding Student Balances (Top 20)</div>
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
            @forelse($students as $i => $student)
            <tr>
                <td>{{ $student['accountId'] }}</td>
                <td>{{ $student['studentName'] }}</td>
                <td>{{ $student['course'] }}</td>
                <td class="text-right">₱{{ number_format($student['total'], 2) }}</td>
                <td class="text-right" style="color:#dc2626;font-weight:600">₱{{ number_format($student['balance'], 2) }}</td>
                <td class="text-center">
                    @if($student['status'] === 'Paid')
                        <span class="badge badge-green">Fully paid</span>
                    @else
                        <span class="badge badge-amber">Pending</span>
                    @endif
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="6" style="text-align:center;padding:20px;color:#9ca3af">
                    No outstanding balances found.
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        <div class="footer-left">CCDI Account Portal &bullet; Financial Report &bullet; {{ $schoolYear }} {{ $semester }}</div>
        <div class="footer-right">Printed: {{ now()->format('Y-m-d H:i') }}</div>
    </div>

</body>
</html>
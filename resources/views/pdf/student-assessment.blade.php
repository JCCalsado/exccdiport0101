<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Assessment Receipt — {{ $student->account_id }}</title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 10px;
            color: #222;
            margin: 0;
            padding: 12px 16px;
        }
 
        /* ── Header ─────────────────────────────────────────────── */
        .header {
            text-align: center;
            margin-bottom: 12px;
            border-bottom: 2px solid #222;
            padding-bottom: 8px;
        }
        .header h1 {
            margin: 0 0 4px;
            font-size: 16px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .header .address {
            font-size: 9px;
            color: #555;
            margin: 2px 0;
        }
        .header .doc-title {
            margin-top: 10px;
            font-size: 13px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .header .assessment-no {
            font-size: 11px;
            color: #555;
            margin-top: 4px;
        }
 
        /* ── Section wrappers ────────────────────────────────────── */
        .section { margin-bottom: 10px; }
        .section-title {
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
            padding-bottom: 4px;
            border-bottom: 1px solid #ccc;
            color: #333;
        }
 
        /* ── Info grid (student details) ────────────────────────── */
        .info-table { width: 100%; border-collapse: collapse; }
        .info-table td { padding: 3px 6px; vertical-align: top; }
        .info-table .lbl { font-weight: bold; width: 28%; color: #444; }
        .info-table .val { color: #222; }
 
        /* ── Data tables ─────────────────────────────────────────── */
        table.data {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
        }
        table.data th,
        table.data td {
            border: 1px solid #ccc;
            padding: 4px 6px;
        }
        table.data th {
            background-color: #f2f2f2;
            font-weight: bold;
            font-size: 10px;
            text-transform: uppercase;
        }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
 
        .row-total { font-weight: bold; background-color: #f9f9f9; }
        .row-grand { font-weight: bold; background-color: #e8e8e8; font-size: 12px; }
 
        /* ── Balance summary box ─────────────────────────────────── */
        .balance-box {
            border: 2px solid #333;
            border-radius: 4px;
            padding: 10px 14px;
            margin-top: 6px;
        }
        .balance-row {
            display: flex;
            justify-content: space-between;
            padding: 3px 0;
            font-size: 11px;
        }
        .balance-row.grand {
            border-top: 2px solid #333;
            margin-top: 4px;
            padding-top: 6px;
            font-size: 14px;
            font-weight: bold;
        }
 
        /* ── Payment terms pills ─────────────────────────────────── */
        .terms-grid {
            display: table;
            width: 100%;
            border-collapse: collapse;
        }
        .term-cell {
            display: table-cell;
            border: 1px solid #ccc;
            padding: 6px;
            text-align: center;
            width: 20%;
            vertical-align: top;
        }
        .term-name { font-weight: bold; font-size: 10px; }
        .term-amount { font-size: 11px; margin: 2px 0; }
        .term-balance { font-size: 10px; color: #d00; }
        .term-status {
            display: inline-block;
            margin-top: 3px;
            padding: 1px 6px;
            border-radius: 20px;
            font-size: 9px;
            font-weight: bold;
        }
        .status-paid    { background: #d1fae5; color: #065f46; }
        .status-partial { background: #fed7aa; color: #92400e; }
        .status-pending { background: #fef9c3; color: #713f12; }
        .status-overdue { background: #fee2e2; color: #991b1b; }
 
        /* ── Signatures ──────────────────────────────────────────── */
        .signature-section {
            margin-top: 20px;
            display: table;
            width: 100%;
        }
        .sig-box {
            display: table-cell;
            width: 33%;
            text-align: center;
            padding: 0 10px;
        }
        .sig-line {
            border-top: 1px solid #555;
            margin: 24px 10px 4px;
        }
        .sig-label { font-size: 9px; color: #555; }
 
        /* ── Footer ──────────────────────────────────────────────── */
        .footer {
            margin-top: 16px;
            text-align: center;
            font-size: 9px;
            color: #888;
            border-top: 1px solid #eee;
            padding-top: 6px;
        }
    </style>
</head>
<body>
 
{{-- ══ Header ══ --}}
<div class="header">
    <h1>{{ strtoupper(config('school.name', 'Computer Communication Development Institute')) }}</h1>
    <p class="address">
        {{ config('school.main_address') }}
        @if(config('school.annex_address'))
            &nbsp;|&nbsp; {{ config('school.annex_address') }}
        @endif
    </p>
    <p class="address">
        Website: {{ config('school.website') }}
        &nbsp;|&nbsp; Hotline: {{ config('school.hotline') }}
        &nbsp;|&nbsp; CP: {{ config('school.mobile') }}
    </p>
    <p class="doc-title">Certificate of Matriculation / Assessment Form</p>
    <p class="assessment-no">Assessment No: {{ $assessment->assessment_number }}</p>
    <p class="assessment-no" style="font-size:11px; font-weight:bold; margin-top:4px;">
        {{ $assessment->year_level }} &mdash; {{ $assessment->semester }} &middot; S.Y. {{ $assessment->school_year }}
    </p>
</div>
 
{{-- ══ Student Information ══ --}}
{{-- NOTE: $student is already the User model from exportPdf() --}}
<div class="section">
    <div class="section-title">Student Information</div>
    <table class="info-table">
        <tr>
            <td class="lbl">Account ID:</td>
            <td class="val">{{ $student->account->id ?? 'N/A' }}</td>
            <td class="lbl">Student ID:</td>
            <td class="val">{{ $student->account_id }}</td>
        </tr>
        <tr>
            <td class="lbl">Full Name:</td>
            <td class="val">{{ $student->name }}</td>
            <td class="lbl">Course:</td>
            <td class="val">{{ $student->course }}</td>
        </tr>
        <tr>
            <td class="lbl">Year Level:</td>
            {{-- Use assessment year_level (accurate) not student.year_level (may be stale) --}}
            <td class="val">{{ $assessment->year_level }}</td>
            <td class="lbl">Semester:</td>
            <td class="val">{{ $assessment->semester }}</td>
        </tr>
        <tr>
            <td class="lbl">School Year:</td>
            <td class="val">{{ $assessment->school_year }}</td>
            <td class="lbl">Status:</td>
            <td class="val">{{ ucfirst($student->status) }}</td>
        </tr>
    </table>
</div>
 
{{-- ══ Fee Assessment ══ --}}
<div class="section">
    <div class="section-title">Fee Assessment</div>
    <table class="data">
        <thead>
            <tr>
                <th>Category</th>
                <th>Description</th>
                <th class="text-right">Amount</th>
            </tr>
        </thead>
        <tbody>
 
            {{-- ── Subjects / Tuition items ── --}}
            @php
                $subjects   = $assessment->subjects   ?? [];
                $feeBreak   = $assessment->fee_breakdown ?? [];
                $chargeRows = $transactions->where('kind', 'charge');
 
                // Prefer assessment data (fee_breakdown + subjects) over raw transactions.
                // Fall back to charge transactions when the assessment JSON fields are empty.
                $hasAssessmentData = !empty($subjects) || !empty($feeBreak);
            @endphp
 
            @if($hasAssessmentData)
 
                {{-- ── Tuition rows from stored subjects JSON ── --}}
                @foreach($subjects as $subject)
                <tr>
                    <td>Tuition</td>
                    <td>
                        {{ $subject['name'] ?? ($subject['code'] ?? 'Subject') }}
                        @if(!empty($subject['units']))
                            ({{ $subject['units'] }} units)
                        @endif
                    </td>
                    <td class="text-right">₱{{ number_format($subject['amount'] ?? 0, 2) }}</td>
                </tr>
                @endforeach
 
                {{-- ── Other fee rows from stored fee_breakdown JSON ── --}}
                @foreach($feeBreak as $fee)
                <tr>
                    <td>{{ $fee['category'] ?? 'Miscellaneous' }}</td>
                    <td>{{ $fee['name'] ?? ($fee['fee_name'] ?? 'Fee') }}</td>
                    <td class="text-right">₱{{ number_format($fee['amount'] ?? 0, 2) }}</td>
                </tr>
                @endforeach
 
            @else
 
                {{-- ── Fallback: derive from charge transactions ── --}}
                @foreach($chargeRows->groupBy('type') as $category => $items)
                    @foreach($items as $item)
                    <tr>
                        <td>{{ $category }}</td>
                        <td>{{ $item->meta['description'] ?? $item->type }}</td>
                        <td class="text-right">₱{{ number_format($item->amount, 2) }}</td>
                    </tr>
                    @endforeach
                @endforeach
 
                @if($chargeRows->isEmpty())
                <tr>
                    <td colspan="3" style="text-align:center; color:#999;">
                        No detailed fee breakdown on record
                    </td>
                </tr>
                @endif
 
            @endif
 
            {{-- ── Subtotals ── --}}
            <tr class="row-total">
                <td colspan="2" class="text-right">Tuition Fee Total:</td>
                <td class="text-right">₱{{ number_format($assessment->tuition_fee, 2) }}</td>
            </tr>
            <tr class="row-total">
                <td colspan="2" class="text-right">Other Fees Total:</td>
                <td class="text-right">₱{{ number_format($assessment->other_fees, 2) }}</td>
            </tr>
            <tr class="row-grand">
                <td colspan="2" class="text-right">TOTAL ASSESSMENT:</td>
                <td class="text-right">₱{{ number_format($assessment->total_assessment, 2) }}</td>
            </tr>
        </tbody>
    </table>
</div>
 
{{-- ══ Payment Schedule ══ --}}
@if($paymentTerms->count() > 0)
<div class="section">
    <div class="section-title">Payment Schedule</div>
    <div class="terms-grid">
        @foreach($paymentTerms as $term)
        <div class="term-cell">
            <div class="term-name">{{ $term->term_name }}</div>
            <div class="term-amount">₱{{ number_format($term->amount, 2) }}</div>
            @if($term->due_date)
                <div style="font-size:9px; color:#666;">Due: {{ \Carbon\Carbon::parse($term->due_date)->format('M d, Y') }}</div>
            @endif
            <div class="term-balance">
                Balance: ₱{{ number_format($term->balance, 2) }}
            </div>
            @php
                $statusClass = match($term->status) {
                    'paid'    => 'status-paid',
                    'partial' => 'status-partial',
                    'overdue' => 'status-overdue',
                    default   => 'status-pending',
                };
                $statusLabel = match($term->status) {
                    'paid'    => 'Paid',
                    'partial' => 'Partial',
                    'overdue' => 'Overdue',
                    default   => 'Unpaid',
                };
            @endphp
            <span class="term-status {{ $statusClass }}">{{ $statusLabel }}</span>
        </div>
        @endforeach
    </div>
</div>
@endif
 
{{-- ══ Payment History ══ --}}
@if($payments->count() > 0)
<div class="section">
    <div class="section-title">Payment History</div>
    <table class="data">
        <thead>
            <tr>
                <th>Date</th>
                <th>Reference</th>
                <th>Method</th>
                <th>Description</th>
                <th class="text-right">Amount</th>
                <th class="text-center">Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach($payments as $payment)
            <tr>
                <td>{{ $payment->paid_at ? \Carbon\Carbon::parse($payment->paid_at)->format('M d, Y') : 'N/A' }}</td>
                <td style="font-family:monospace; font-size:10px;">{{ $payment->reference_number ?? '—' }}</td>
                <td>{{ strtoupper(str_replace('_', ' ', $payment->payment_method)) }}</td>
                <td>{{ $payment->description }}</td>
                <td class="text-right">₱{{ number_format($payment->amount, 2) }}</td>
                <td class="text-center">{{ ucfirst($payment->status) }}</td>
            </tr>
            @endforeach
            <tr class="row-total">
                <td colspan="4" class="text-right">Total Paid:</td>
                <td class="text-right">₱{{ number_format($payments->sum('amount'), 2) }}</td>
                <td></td>
            </tr>
        </tbody>
    </table>
</div>
@endif
 
{{-- ══ Balance Summary ══ --}}
<div class="section">
    <div class="section-title">Balance Summary</div>
    <div class="balance-box">
        <div class="balance-row">
            <span>Total Assessment:</span>
            <span>₱{{ number_format($assessment->total_assessment, 2) }}</span>
        </div>
        <div class="balance-row">
            <span>Total Paid:</span>
            <span>₱{{ number_format($payments->sum('amount'), 2) }}</span>
        </div>
        <div class="balance-row grand">
            <span>Current Balance:</span>
            {{-- Use payment terms balance sum as source of truth when available, fall back to account balance --}}
            @php
                $termBalance = $paymentTerms->sum('balance');
                $displayBalance = $termBalance > 0
                    ? $termBalance
                    : abs($student->account->balance ?? 0);
            @endphp
            <span>₱{{ number_format($displayBalance, 2) }}</span>
        </div>
    </div>
</div>
 
{{-- ══ Signatures ══ --}}
<div class="signature-section">
    <div class="sig-box">
        <div class="sig-line"></div>
        <div class="sig-label">Cashier / Accounting Staff</div>
    </div>
    <div class="sig-box">
        <div class="sig-line"></div>
        <div class="sig-label">Registrar</div>
    </div>
    <div class="sig-box">
        <div class="sig-line"></div>
        <div class="sig-label">Student's Signature</div>
    </div>
</div>
 
{{-- ══ Footer ══ --}}
<div class="footer">
    <p>Generated on {{ now()->format('F d, Y h:i A') }}</p>
    <p>This is a computer-generated document. Please keep this for your records.</p>
</div>
 
</body>
</html>
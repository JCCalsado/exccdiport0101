<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Certificate of Matriculation — {{ $student->account_id }}</title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 9px;
            color: #222;
            margin: 0;
            padding: 8px 10px;
            page-break-after: avoid;
        }

        .header {
            text-align: center;
            margin-bottom: 6px;
            border-bottom: 1px solid #222;
            padding-bottom: 4px;
        }
        .header h1 {
            margin: 0 0 2px;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: bold;
        }
        .school-branch {
            font-size: 9px;
            color: #555;
            margin: 0;
        }
        .header .doc-title {
            margin-top: 2px;
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
        }

        .student-info {
            display: table;
            width: 100%;
            margin-bottom: 6px;
            border-collapse: collapse;
        }
        .info-col {
            display: table-cell;
            width: 50%;
            vertical-align: top;
            padding-right: 10px;
        }
        .info-col:last-child {
            padding-right: 0;
            padding-left: 10px;
        }
        .info-row {
            display: flex;
            margin-bottom: 3px;
            font-size: 8px;
        }
        .info-label {
            font-weight: bold;
            width: 100px;
            margin-right: 5px;
        }
        .info-value {
            flex: 1;
            border-bottom: 1px solid #222;
            padding-bottom: 1px;
        }

        .content-wrapper {
            display: table;
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 6px;
        }
        .main-col {
            display: table-cell;
            width: 65%;
            vertical-align: top;
            border-right: 1px solid #ccc;
            padding-right: 8px;
        }
        .side-col {
            display: table-cell;
            width: 35%;
            vertical-align: top;
            padding-left: 8px;
        }

        table.subjects {
            width: 100%;
            border-collapse: collapse;
            border: 1px solid #222;
            margin-bottom: 6px;
        }
        table.subjects th,
        table.subjects td {
            border: 1px solid #222;
            padding: 3px 4px;
            font-size: 8px;
            text-align: left;
        }
        table.subjects th {
            background-color: #f0f0f0;
            font-weight: bold;
            text-transform: uppercase;
        }
        table.subjects th.code { width: 12%; }
        table.subjects th.title { width: 40%; }
        table.subjects th.units { width: 10%; text-align: center; }
        table.subjects th.time { width: 18%; }
        table.subjects th.day { width: 20%; }

        .total-row {
            font-weight: bold;
            background-color: #f9f9f9;
        }

        .student-copy {
            font-size: 7px; 
            margin: 2px 0;
            text-align: left;
        }

        .fees-section {
            margin-bottom: 6px;
        }
        .fees-title {
            font-weight: bold;
            text-transform: uppercase;
            font-size: 9px;
            margin-bottom: 3px;
            border-bottom: 1px solid #ccc;
            padding-bottom: 2px;
        }
        .fee-row {
            display: flex;
            justify-content: space-between;
            font-size: 8px;
            padding: 2px 0;
            border-bottom: 0.5px dotted #ccc;
        }
        .fee-row.total {
            font-weight: bold;
            border-bottom: 1px solid #222;
            padding-top: 3px;
        }
        .fee-label {
            flex: 1;
        }
        .fee-amount {
            text-align: right;
            width: 50px;
        }

        .terms-section {
            margin-bottom: 6px;
        }
        .terms-title {
            font-weight: bold;
            text-transform: uppercase;
            font-size: 9px;
            margin-bottom: 2px;
            border-bottom: 1px solid #ccc;
            padding-bottom: 2px;
        }
        .term-row {
            display: flex;
            justify-content: space-between;
            font-size: 8px;
            padding: 2px 0;
        }
        .term-label {
            flex: 1;
        }
        .term-amount {
            text-align: right;
            width: 50px;
        }

        .signature-section {
            display: table;
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        .sig-block {
            display: table-cell;
            width: 33%;
            text-align: center;
            padding: 0 5px;
        }
        .sig-line {
            border-top: 1px solid #222;
            margin: 20px 0 2px;
            height: 20px;
        }
        .sig-label {
            font-size: 8px;
            font-weight: bold;
        }
    </style>
</head>
<body>

{{-- ══ Header ══ --}}
<div class="header">
    <h1>{{ strtoupper(config('school.name', 'COMPUTER COMMUNICATION DEVELOPMENT INSTITUTE')) }}</h1>
    <p class="school-branch">{{ config('school.annex_address', 'Main Campus') }}</p>
    <p class="doc-title">Certificate of Matriculation Form</p>
</div>

{{-- ══ Student Information ══ --}}
<div class="student-info">
    <div class="info-col">
        <div class="info-row">
            <div class="info-label">Name:</div>
            <div class="info-value">{{ $student->name }}</div>
        </div>
        <div class="info-row">
            <div class="info-label">Course & Yr.:</div>
            <div class="info-value">{{ strtoupper($student->course) }} {{ $assessment->year_level }}</div>
        </div>
        <div class="info-row">
            <div class="info-label">Major:</div>
            <div class="info-value"></div>
        </div>
    </div>
    <div class="info-col">
        <div class="info-row">
            <div class="info-label">Semester/Summer:</div>
            <div class="info-value">{{ $assessment->semester }}</div>
        </div>
        <div class="info-row">
            <div class="info-label">School Year:</div>
            <div class="info-value">{{ $assessment->school_year }}</div>
        </div>
        <div class="info-row">
            <div class="info-label">Registration Date:</div>
            <div class="info-value"></div>
        </div>
    </div>
</div>

{{-- ══ Main Content (Subjects Table + Fees) ══ --}}
<div class="content-wrapper">

    {{-- ══ Subjects Table (Left) ══ --}}
    <div class="main-col">
        <table class="subjects">
            <thead>
                <tr>
                    <th class="code">Subj. Code</th>
                    <th class="title">Subject Title</th>
                    <th class="units">Units</th>
                    <th class="time">Time</th>
                    <th class="day">Day</th>
                </tr>
            </thead>
            <tbody>
                @php
                    $subjects = $assessment->subjects ?? [];
                @endphp

                {{-- Display all subjects --}}
                @foreach($subjects as $subject)
                <tr>
                    <td class="code">{{ $subject['code'] ?? '—' }}</td>
                    <td class="title">{{ $subject['name'] ?? 'Subject' }}</td>
                    <td class="units" style="text-align:center;">{{ $subject['units'] ?? '—' }}</td>
                    <td class="time" style="text-align:center;">{{ $subject['time'] ?? '—' }}</td>
                    <td class="day" style="text-align:center;">{{ $subject['day'] ?? '—' }}</td>
                </tr>
                @endforeach

                {{-- Total Row --}}
                <tr class="total-row">
                    <td colspan="5" style="text-align:right; padding-right:6px;">Total:</td>
                </tr>
            </tbody>
        </table>
        <p class="student-copy">Student's Copy</p>
    </div>

    {{-- ══ Fees Section (Right) ══ --}}
    <div class="side-col">

        {{-- Fees Breakdown --}}
        <div class="fees-section">
            <div class="fees-title">Fees</div>

            @php
                $feeBreak = $assessment->fee_breakdown ?? [];
                $tuitionFee = $assessment->tuition_fee ?? 0;
                $totalAssessment = $assessment->total_assessment ?? 0;
            @endphp

            {{-- Registration Fee --}}
            @php $regFee = null; foreach($feeBreak as $f) { if(stripos($f['name'] ?? '', 'registration') !== false) { $regFee = $f; break; } } @endphp
            @if($regFee)
            <div class="fee-row">
                <div class="fee-label">Registration Fee:</div>
                <div class="fee-amount">{{ number_format($regFee['amount'] ?? 0, 2) }}</div>
            </div>
            @endif

            {{-- Tuition Fee --}}
            <div class="fee-row">
                <div class="fee-label">Tuition Fee:</div>
                <div class="fee-amount">{{ number_format($tuitionFee, 2) }}</div>
            </div>

            {{-- Lab Fee --}}
            @php $labFee = null; foreach($feeBreak as $f) { if(stripos($f['name'] ?? '', 'lab') !== false) { $labFee = $f; break; } } @endphp
            @if($labFee)
            <div class="fee-row">
                <div class="fee-label">Lab. Fee:</div>
                <div class="fee-amount">{{ number_format($labFee['amount'] ?? 0, 2) }}</div>
            </div>
            @endif

            {{-- Misc Fee --}}
            @php $miscFee = null; foreach($feeBreak as $f) { if(stripos($f['name'] ?? '', 'misc') !== false) { $miscFee = $f; break; } } @endphp
            @if($miscFee)
            <div class="fee-row">
                <div class="fee-label">Misc. Fee:</div>
                <div class="fee-amount">{{ number_format($miscFee['amount'] ?? 0, 2) }}</div>
            </div>
            @endif

            {{-- Total Assessment Fee --}}
            <div class="fee-row total">
                <div class="fee-label">Total Assessment Fee:</div>
                <div class="fee-amount">{{ number_format($totalAssessment, 2) }}</div>
            </div>
        </div>

        {{-- Terms of Payment --}}
        <div class="terms-section">
            <div class="terms-title">Terms of Payment</div>

            @php
                $paymentTerms = $paymentTerms ?? collect([]);
            @endphp

            @foreach($paymentTerms as $term)
            <div class="term-row">
                <div class="term-label">{{ $term->term_name }}:</div>
                <div class="term-amount">{{ number_format($term->amount, 2) }}</div>
            </div>
            @endforeach
        </div>

    </div>

</div>

{{-- ══ Signature Section ══ --}}
<div class="signature-section">
    <div class="sig-block">
        <div class="sig-line"></div>
        <div class="sig-label">Student Signature</div>
    </div>
    <div class="sig-block">
        <div class="sig-line"></div>
        <div class="sig-label">Approved by:</div>
    </div>
    <div class="sig-block">
        <div class="sig-line"></div>
        <div class="sig-label">Registrar</div>
    </div>
</div>

</body>
</html>

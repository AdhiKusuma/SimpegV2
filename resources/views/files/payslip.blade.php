@php
    $formatAmount = static function (mixed $value): string {
        $amount = (float) ($value ?? 0);

        return abs($amount) < 0.005 ? '-' : number_format($amount, 0, ',', '.');
    };
    $monthLabels = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    $month = $monthLabels[max(0, min(11, ((int) ($slip['bulan'] ?? 1)) - 1))].'-'.str_pad((string) (((int) ($slip['tahun'] ?? 0)) % 100), 2, '0', STR_PAD_LEFT);
    $logoPath = public_path('images/logo-simpeg.png');
    $logo = is_file($logoPath) ? 'data:image/png;base64,'.base64_encode((string) file_get_contents($logoPath)) : null;
    $earnings = array_values($slip['earnings'] ?? []);
    $deductions = array_values($slip['deductions'] ?? []);
    $rowCount = max(count($earnings), count($deductions), 11);
    $earnings = array_pad($earnings, $rowCount, null);
    $deductions = array_pad($deductions, $rowCount, null);
@endphp
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Slip Gaji {{ $slip['period'] }}</title>
    <style>
        @page { size: A4 portrait; margin: 0; }
        * { box-sizing: border-box; }
        body { margin: 0; padding: 0; color: #111; font-family: Arial, Helvetica, sans-serif; font-size: 10.5pt; }
        .page { margin: 0 51pt 0 62pt; padding-top: 38pt; }
        .header { position: relative; height: 220pt; }
        .logo { position: absolute; top: 21pt; left: 3pt; width: 53pt; height: 54pt; object-fit: contain; }
        .confidential { position: absolute; top: 43pt; right: 3pt; color: #ff0000; font-size: 10pt; font-style: italic; font-weight: bold; }
        .company { position: absolute; top: 88pt; left: 3pt; font-size: 10.5pt; }
        .title { position: absolute; top: 100pt; right: 0; font-size: 10.5pt; }
        .employee-info { position: absolute; top: 127pt; left: 3pt; border-collapse: collapse; width: 360pt; font-size: 10.5pt; line-height: 1.3; }
        .employee-info td { padding: 0; vertical-align: top; }
        .employee-info .label { width: 112pt; }
        .employee-info .colon { width: 11pt; text-align: center; }
        .payroll-table { width: 100%; border-collapse: collapse; table-layout: fixed; font-size: 9.5pt; line-height: 12pt; }
        .payroll-table col.label { width: 28%; }
        .payroll-table col.currency { width: 8%; }
        .payroll-table col.amount { width: 14%; }
        .payroll-table th { height: 27pt; padding: 4pt; background: #d9d9d9; border: 1pt solid #333; text-align: left; font-size: 10.5pt; }
        .payroll-table td { height: 16.3pt; padding: 0 4pt; vertical-align: middle; white-space: nowrap; line-height: 12pt; }
        .payroll-table td:first-child, .payroll-table td:nth-child(4) { border-left: 1pt solid #333; }
        .payroll-table td:last-child { border-right: 1pt solid #333; }
        .payroll-table td.currency { text-align: center; }
        .payroll-table td.amount { text-align: right; }
        .payroll-table tr.total td { height: 27pt; border-top: 1pt solid #333; border-bottom: 1pt solid #333; font-weight: bold; }
        .take-home { width: 60%; margin: 15pt 0 0 auto; display: table; table-layout: fixed; font-size: 11pt; font-weight: bold; }
        .take-home > span { display: table-cell; padding: 0 4pt; }
        .take-home .label { width: 58%; text-align: right; }
        .take-home .currency { width: 15%; text-align: center; }
        .take-home .amount { width: 27%; text-align: right; }
    </style>
</head>
<body>
    <main class="page">
        <header class="header">
            @if ($logo)
                <img class="logo" src="{{ $logo }}" alt="Logo BKK Jawa Tengah">
            @endif
            <div class="confidential">*CONFIDENTIAL</div>
            <div class="company">PT. BPR BKK JATENG (Perseroda)</div>
            <div class="title">SLIP GAJI</div>
            <table class="employee-info">
                <tr><td class="label">Bulan</td><td class="colon">:</td><td>{{ $month }}</td></tr>
                <tr><td class="label">Nama</td><td class="colon">:</td><td>{{ $slip['employee_name'] }}</td></tr>
                <tr><td class="label">ID Pegawai</td><td class="colon">:</td><td>{{ $slip['employee_id'] }}</td></tr>
                <tr><td class="label">Jabatan</td><td class="colon">:</td><td>{{ $slip['position_name'] ?: '-' }}</td></tr>
                <tr><td class="label">Unit Kerja</td><td class="colon">:</td><td>{{ $slip['unit_name'] ?: ($slip['office_name'] ?: '-') }}</td></tr>
                <tr><td class="label">Status Pegawai</td><td class="colon">:</td><td>{{ $slip['employment_status'] ?: '-' }}</td></tr>
            </table>
        </header>

        <table class="payroll-table">
            <colgroup><col class="label"><col class="currency"><col class="amount"><col class="label"><col class="currency"><col class="amount"></colgroup>
            <thead><tr><th colspan="3">Penerimaan</th><th colspan="3">Potongan</th></tr></thead>
            <tbody>
                @for ($index = 0; $index < $rowCount; $index++)
                    @php $earning = $earnings[$index] ?? null; $deduction = $deductions[$index] ?? null; @endphp
                    <tr>
                        <td>{{ $earning['label'] ?? '' }}</td><td class="currency">{{ $earning ? 'Rp' : '' }}</td><td class="amount">{{ $earning ? $formatAmount($earning['amount']) : '' }}</td>
                        <td>{{ $deduction['label'] ?? '' }}</td><td class="currency">{{ $deduction ? 'Rp' : '' }}</td><td class="amount">{{ $deduction ? $formatAmount($deduction['amount']) : '' }}</td>
                    </tr>
                @endfor
                <tr class="total">
                    <td>Total Penerimaan</td><td class="currency">Rp</td><td class="amount">{{ $formatAmount($slip['gross_salary'] ?? 0) }}</td>
                    <td>Total Potongan</td><td class="currency">Rp</td><td class="amount">{{ $formatAmount($slip['total_deductions'] ?? 0) }}</td>
                </tr>
            </tbody>
        </table>
        <div class="take-home"><span class="label">Take Home Pay</span><span class="currency">Rp</span><span class="amount">{{ $formatAmount($slip['net_salary'] ?? 0) }}</span></div>
    </main>
</body>
</html>

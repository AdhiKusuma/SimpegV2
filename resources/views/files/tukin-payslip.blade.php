@php
    $formatAmount = static function (mixed $value): string {
        $amount = (float) ($value ?? 0);

        return abs($amount) < 0.005 ? '-' : number_format($amount, 0, ',', '.');
    };
    $logoPath = public_path('images/logo-simpeg-256.png');
    $logo = is_file($logoPath) ? 'data:image/png;base64,'.base64_encode((string) file_get_contents($logoPath)) : null;
    $modeLabel = $mode === '75' ? 'TUKIN 75%' : 'TUKIN REAL / KPI';
    $income = [
        ['Gaji Pokok', $slip['gaji_pokok']],
        ['Tunj. Suami/Istri', $slip['tunj_suami_istri']],
        ['Tunj. Anak', $slip['tunj_anak']],
        ['Tunj. Jabatan', $slip['tunj_jabatan']],
        ['Tunj. Operasional', $slip['tunj_ops']],
        ['Tunj. Kinerja 100%', $slip['tunj_kinerja_100']],
        ['Tunj. Kinerja 75%', $slip['tunj_kinerja_75']],
        ['Tunj. Kinerja dipakai', $slip['tunj_kinerja']],
        ['Tunj. Masa Kerja', $slip['tunj_masa_kerja']],
        ['Tunj. Fungsional', $slip['tunj_fungsional']],
        ['Honor Kontrak / THL', $slip['honor_kontrak']],
    ];
    $deductions = [
        ['Potongan Angsuran', $slip['potongan_angsuran']],
        ['Potongan Absen', $slip['potongan_absen']],
        ['Potongan BPJS', $slip['potongan_bpjs']],
        ['Pengembalian Tunjangan', $slip['pengembalian_tunjangan']],
        ['Pengalihan Keris', $slip['pengalihan_keris']],
        ['Uang Makan Kontrak', $slip['uang_makan_kontrak']],
    ];
    $rowCount = max(count($income), count($deductions));
    $income = array_pad($income, $rowCount, ['', '']);
    $deductions = array_pad($deductions, $rowCount, ['', '']);
@endphp
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Slip Tukin {{ $slip['period'] }}</title>
    <style>
        @page { size: A4 landscape; margin: 0; }
        * { box-sizing: border-box; }
        body { margin: 0; color: #10254d; font-family: Arial, Helvetica, sans-serif; font-size: 9pt; }
        .page { padding: 28pt 34pt 24pt; }
        .header { display: table; width: 100%; margin-bottom: 17pt; }
        .brand, .meta { display: table-cell; vertical-align: top; }
        .brand { width: 60%; }
        .meta { width: 40%; text-align: right; }
        .logo { width: 39pt; height: 39pt; object-fit: contain; vertical-align: middle; margin-right: 8pt; }
        .brand-copy { display: inline-block; vertical-align: middle; }
        .company { font-size: 12pt; font-weight: bold; }
        .subtitle { margin-top: 3pt; color: #58709b; font-size: 8.5pt; }
        .title { font-size: 17pt; font-weight: bold; color: #0a5fa0; }
        .mode { display: inline-block; margin-top: 5pt; padding: 4pt 8pt; border-radius: 8pt; background: #e5efff; color: #0a5fa0; font-weight: bold; }
        .meta-line { margin-top: 4pt; color: #58709b; }
        .identity { width: 100%; border-collapse: collapse; margin-bottom: 14pt; }
        .identity td { padding: 4pt 6pt; border: .7pt solid #d2def3; }
        .identity .label { width: 11%; color: #58709b; font-weight: bold; text-transform: uppercase; font-size: 7.5pt; }
        .identity .value { width: 22%; font-weight: bold; }
        .payroll { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .payroll th { padding: 7pt 6pt; background: #e5efff; border: .7pt solid #c8d8f0; color: #50678f; text-align: left; text-transform: uppercase; font-size: 8pt; letter-spacing: .25pt; }
        .payroll td { padding: 5pt 6pt; border: .7pt solid #d2def3; vertical-align: middle; }
        .payroll td.amount { width: 14%; text-align: right; font-variant-numeric: tabular-nums; }
        .payroll td.currency { width: 4%; color: #58709b; text-align: center; }
        .payroll .total td { border-top: 1.2pt solid #8aa6d2; font-weight: bold; background: #f5f8fd; }
        .footer { display: table; width: 100%; margin-top: 14pt; }
        .footer > div { display: table-cell; width: 50%; }
        .footer .right { text-align: right; }
        .net { font-size: 14pt; font-weight: bold; color: #0a5fa0; }
        .note { color: #58709b; font-size: 8pt; }
    </style>
</head>
<body>
    <main class="page">
        <header class="header">
            <div class="brand">
                @if ($logo)<img class="logo" src="{{ $logo }}" alt="Logo">@endif
                <span class="brand-copy"><span class="company">PT. BPR BKK JATENG (Perseroda)</span><br><span class="subtitle">Slip penghasilan dan tunjangan kinerja pegawai</span></span>
            </div>
            <div class="meta"><div class="title">SLIP GAJI TUKIN</div><div class="mode">{{ $modeLabel }}</div><div class="meta-line">Periode {{ $slip['period'] }} · KPI {{ number_format($slip['kpi'] * 100, 0) }}%</div></div>
        </header>

        <table class="identity">
            <tr><td class="label">Pegawai</td><td class="value">{{ $slip['employee_name'] }} · {{ $slip['employee_id'] }}</td><td class="label">Status</td><td class="value">{{ $slip['employment_status'] ?: '-' }}</td><td class="label">Gol</td><td class="value">{{ $slip['grade'] ?: '-' }}</td></tr>
            <tr><td class="label">Jabatan</td><td class="value">{{ $slip['position_name'] ?: '-' }}</td><td class="label">Unit Kerja</td><td class="value">{{ $slip['unit_name'] ?: '-' }}</td><td class="label">MK / S-I / Anak</td><td class="value">{{ $slip['service_years'] }} / {{ $slip['spouse_count'] }} / {{ $slip['child_count'] }}</td></tr>
        </table>

        <table class="payroll">
            <thead><tr><th colspan="3">Pendapatan</th><th colspan="3">Potongan dan pengalihan</th></tr></thead>
            <tbody>
                @for ($index = 0; $index < $rowCount; $index++)
                    <tr><td>{{ $income[$index][0] }}</td><td class="currency">{{ $income[$index][0] ? 'Rp' : '' }}</td><td class="amount">{{ $income[$index][0] ? $formatAmount($income[$index][1]) : '' }}</td><td>{{ $deductions[$index][0] }}</td><td class="currency">{{ $deductions[$index][0] ? 'Rp' : '' }}</td><td class="amount">{{ $deductions[$index][0] ? $formatAmount($deductions[$index][1]) : '' }}</td></tr>
                @endfor
                <tr class="total"><td colspan="2">Jumlah Pendapatan</td><td class="amount">Rp {{ $formatAmount($slip['jumlah_pendapatan']) }}</td><td colspan="2">Total Potongan</td><td class="amount">Rp {{ $formatAmount($slip['potongan_total']) }}</td></tr>
            </tbody>
        </table>

        <div class="footer"><div><span class="note">Dokumen ini merupakan proyeksi Tukin dari payroll periode berjalan. Payroll reguler tidak diubah.</span></div><div class="right"><span>Gaji diterima</span><br><span class="net">Rp {{ $formatAmount($slip['gaji_diterima']) }}</span></div></div>
    </main>
</body>
</html>

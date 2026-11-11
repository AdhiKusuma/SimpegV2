<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Laporan Data Pegawai</title>
    <style>
        @page { size: landscape; margin: 10mm; }
        :root { color-scheme: light; }
        body { color: #172033; font-family: Arial, sans-serif; font-size: 11px; margin: 0; }
        .print-actions { display: flex; justify-content: flex-end; margin-bottom: 12px; }
        button { background: #16a34a; border: 0; border-radius: 6px; color: #fff; cursor: pointer; font-size: 12px; font-weight: 700; padding: 10px 14px; }
        h1 { font-size: 20px; margin: 0 0 5px; text-align: center; text-transform: uppercase; }
        .subtitle { color: #526176; margin: 0 0 4px; text-align: center; }
        .meta { color: #526176; margin: 0 0 14px; text-align: center; }
        table { border-collapse: collapse; table-layout: fixed; width: 100%; }
        th, td { border: 1px solid #1f2937; padding: 6px; vertical-align: top; }
        th { background: #1e3a70; color: #fff; text-align: left; }
        th.number, td.number { text-align: center; width: 3%; }
        th.id, td.id { white-space: nowrap; width: 8%; }
        th.name { width: 15%; }
        th.ttl { width: 13%; }
        th.position { width: 17%; }
        th.division { width: 14%; }
        th.office { width: 12%; }
        th.status { width: 8%; }
        th.start-date, td.start-date { white-space: nowrap; width: 8%; }
        th.phone, td.phone { white-space: nowrap; width: 10%; }
        td { overflow-wrap: anywhere; }
        tbody tr { page-break-inside: avoid; }
        tfoot td { background: #f1f5f9; font-weight: 700; }
        .empty { text-align: center; }
        @media print {
            .print-actions { display: none; }
            body { font-size: 10px; }
        }
    </style>
</head>
<body>
    <div class="print-actions">
        <button type="button" onclick="window.print()">🖨 Cetak / Simpan PDF</button>
    </div>

    <h1>Laporan Data Pegawai</h1>
    <p class="subtitle">Daftar pegawai sesuai filter yang dipilih di menu Data Pegawai.</p>
    <p class="meta">
        Status: {{ $filters['tab'] }}
        @if ($filters['year'] !== '') · Tahun mulai kerja: {{ $filters['year'] }} @endif
        @if ($filters['kantor'] !== '') · Kantor: {{ $filters['kantor'] }} @endif
        @if ($filters['divisi'] !== '' && $filters['divisi'] !== '__all__') · Divisi/unit: {{ preg_replace('/^(unit:|division:)/', '', $filters['divisi']) }} @endif
        @if ($filters['jabatan'] !== '') · Jabatan: {{ $filters['jabatan'] }} @endif
        @if ($filters['search'] !== '') · Pencarian: {{ $filters['search'] }} @endif
    </p>

    <table>
        <thead>
        <tr>
            <th class="number">No</th>
            <th class="id">ID Pegawai</th>
            <th class="name">Nama Pegawai</th>
            <th class="ttl">Tempat, Tanggal Lahir</th>
            <th class="position">Jabatan</th>
            <th class="division">Divisi / Unit Kerja</th>
            <th class="office">Kantor / Area</th>
            <th class="status">Status</th>
            <th class="start-date">Mulai Kerja</th>
            <th class="phone">Kontak</th>
        </tr>
        </thead>
        <tbody>
        @forelse ($employees as $index => $employee)
            <tr>
                <td class="number">{{ $index + 1 }}</td>
                <td class="id">{{ $employee['id'] }}</td>
                <td>{{ $employee['name'] }}</td>
                <td>{{ $employee['ttl'] ?: '—' }}</td>
                <td>{{ $employee['position'] ?: '—' }}</td>
                <td>{{ $employee['division'] ?: '—' }}</td>
                <td>{{ $employee['office'] ?: '—' }}</td>
                <td>{{ $employee['status'] ?: '—' }}</td>
                <td class="start-date">{{ $employee['start_date'] ?: '—' }}</td>
                <td class="phone">{{ $employee['phone'] ?: '—' }}</td>
            </tr>
        @empty
            <tr><td class="empty" colspan="10">Data pegawai tidak ditemukan.</td></tr>
        @endforelse
        </tbody>
        <tfoot>
        <tr>
            <td colspan="9">Total pegawai</td>
            <td class="number">{{ number_format($total, 0, ',', '.') }}</td>
        </tr>
        </tfoot>
    </table>
    <p class="meta">Dicetak pada: {{ $generatedAt }}</p>
</body>
</html>

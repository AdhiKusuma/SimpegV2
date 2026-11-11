<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Laporan Formasi Pegawai</title>
    <style>
        @page { size: landscape; margin: 12mm; }
        body { color: #172033; font-family: Arial, sans-serif; font-size: 12px; margin: 0; }
        h1 { font-size: 20px; margin: 0 0 5px; }
        p { color: #526176; margin: 0 0 14px; }
        button { background: #1d4ed8; border: 0; border-radius: 5px; color: white; cursor: pointer; font-weight: 700; margin-bottom: 14px; padding: 9px 13px; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #cbd5e1; padding: 7px; vertical-align: top; }
        th { background: #1e293b; color: white; text-align: left; }
        td.number, th.number { text-align: right; }
        td.center, th.center { text-align: center; }
        tfoot td { background: #eff6ff; font-weight: 700; }
        @media print { button { display: none; } }
    </style>
</head>
<body>
    <h1>Laporan Formasi Pegawai</h1>
    <p>Kantor: {{ $filters['kode_cabang'] ?: 'Semua kantor / global' }}</p>
    <button type="button" onclick="window.print()">Cetak / Simpan PDF</button>
    <table>
        <thead>
        <tr>
            <th class="number">No</th>
            <th>Jabatan</th>
            <th class="center">Kuota</th>
            <th class="center">Terisi</th>
            <th class="center">Sisa</th>
            <th>Status Formasi</th>
        </tr>
        </thead>
        <tbody>
        @forelse ($rows as $index => $row)
            <tr>
                <td class="number">{{ $index + 1 }}</td>
                <td>{{ $row['nama_jabatan'] }}{{ $row['sumber'] === 'NonMaster' ? ' *' : '' }}</td>
                <td class="center">{{ number_format($row['kuota'], 0, ',', '.') }}</td>
                <td class="center">{{ number_format($row['terisi'], 0, ',', '.') }}</td>
                <td class="center">{{ $row['sisa'] < 0 ? '+' : '' }}{{ number_format(abs($row['sisa']), 0, ',', '.') }}</td>
                <td>{{ $row['status'] }}</td>
            </tr>
        @empty
            <tr><td colspan="6">Data formasi belum tersedia.</td></tr>
        @endforelse
        </tbody>
        <tfoot>
        <tr>
            <td colspan="2">Grand Total</td>
            <td class="center">{{ number_format($totals['kuota'], 0, ',', '.') }}</td>
            <td class="center">{{ number_format($totals['terisi'], 0, ',', '.') }}</td>
            <td class="center">{{ $totals['sisa'] < 0 ? '+' : '' }}{{ number_format(abs($totals['sisa']), 0, ',', '.') }}</td>
            <td></td>
        </tr>
        </tfoot>
    </table>
    <p>* Jabatan belum terdaftar pada master jabatan.</p>
</body>
</html>

<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Rekap Biaya Pendidikan</title>
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
        tfoot td { background: #eff6ff; font-weight: 700; }
        @media print { button { display: none; } }
    </style>
</head>
<body>
    <h1>Rekapitulasi Biaya Pendidikan</h1>
    <p>Tahun: {{ $filters['tahun'] === 'Semua' ? 'Semua tahun' : $filters['tahun'] }} | Kuartal: {{ $filters['kuartal'] === 'Semua' ? 'Semua kuartal' : $filters['kuartal'] }}</p>
    <button type="button" onclick="window.print()">Cetak / Simpan PDF</button>
    <table>
        <thead>
        <tr>
            <th>No</th>
            <th>Kategori Pengembangan</th>
            <th>Detail Kegiatan</th>
            <th>Penyelenggara</th>
            <th>Tanggal</th>
            <th class="number">Peserta</th>
            <th class="number">Total Biaya</th>
        </tr>
        </thead>
        <tbody>
        @forelse ($rows as $index => $row)
            <tr>
                <td>{{ $index + 1 }}</td>
                <td>{{ $row['kategori'] }}</td>
                <td>{{ $row['pengembangan_sdm'] }}</td>
                <td>{{ $row['pihak_pelaksana'] }}</td>
                <td>{{ $row['tanggal'] }}</td>
                <td class="number">{{ number_format($row['jumlah_sdm'], 0, ',', '.') }}</td>
                <td class="number">Rp {{ number_format($row['total_biaya'], 0, ',', '.') }}</td>
            </tr>
        @empty
            <tr><td colspan="7">Data rekap tidak ditemukan.</td></tr>
        @endforelse
        </tbody>
        <tfoot>
        <tr>
            <td colspan="5">Grand Total</td>
            <td class="number">{{ number_format($totals['peserta'], 0, ',', '.') }}</td>
            <td class="number">Rp {{ number_format($totals['biaya'], 0, ',', '.') }}</td>
        </tr>
        </tfoot>
    </table>
</body>
</html>

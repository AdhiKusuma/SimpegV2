<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Laporan Keadaan Pegawai</title>
    <style>
        @page { size: landscape; margin: 10mm; }
        body { color: #172033; font-family: Arial, sans-serif; font-size: 10px; margin: 0; }
        h1 { font-size: 19px; margin: 0 0 5px; }
        p { color: #526176; margin: 0 0 12px; }
        button { background: #1d4ed8; border: 0; border-radius: 5px; color: white; cursor: pointer; font-weight: 700; margin-bottom: 12px; padding: 9px 13px; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #cbd5e1; padding: 5px; vertical-align: middle; }
        th { background: #1e293b; color: white; text-align: left; }
        th.center, td.center { text-align: center; }
        td.right { text-align: right; }
        tbody tr.category td { background: #e2e8f0; font-weight: 700; }
        tbody tr.subtotal td { background: #dbeafe; font-weight: 700; }
        tbody tr:nth-child(even):not(.category) td { background: #f8fafc; }
        @media print { button { display: none; } }
    </style>
</head>
<body>
    <h1>Laporan Keadaan Pegawai</h1>
    <p>Kantor: {{ $filters['kantor'] ?: 'Semua kantor' }} | Tahun hukuman: {{ $year }}</p>
    <button type="button" onclick="window.print()">Cetak / Simpan PDF</button>
    <table>
        <thead>
        <tr>
            <th rowspan="2" class="center">No</th>
            <th rowspan="2">Jenis Laporan</th>
            <th colspan="{{ count($ranges) }}" class="center">Rentang Usia (tahun)</th>
            <th rowspan="2" class="center">Total</th>
        </tr>
        <tr>
            @foreach ($ranges as $range)
                <th class="center">{{ $range }}</th>
            @endforeach
        </tr>
        </thead>
        <tbody>
        @foreach ($categories as $categoryIndex => $category)
            <tr class="category">
                <td class="center">{{ $categoryIndex + 1 }}</td>
                <td colspan="{{ count($ranges) + 2 }}">{{ $category['label'] }}</td>
            </tr>
            @forelse ($category['sub'] as $sub)
                @php
                    $values = array_map(fn ($range) => (int) ($counts[$range][$category['key']][$sub] ?? 0), $ranges);
                    $total = array_sum($values);
                @endphp
                <tr>
                    <td></td>
                    <td>{{ $sub }}</td>
                    @foreach ($values as $value)
                        <td class="center">{{ $value ?: '-' }}</td>
                    @endforeach
                    <td class="center"><strong>{{ $total ?: '-' }}</strong></td>
                </tr>
            @empty
                <tr><td></td><td colspan="{{ count($ranges) + 1 }}"><em>Tidak ada data</em></td></tr>
            @endforelse
            @php($subtotal = $subtotals[$category['key']])
            <tr class="subtotal">
                <td colspan="2" class="right">SUBTOTAL {{ $category['label'] }}</td>
                @foreach ($ranges as $range)
                    <td class="center">{{ $subtotal[$range] ?: '-' }}</td>
                @endforeach
                <td class="center">{{ $subtotal['total'] ?: '-' }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
</body>
</html>

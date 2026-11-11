<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Laporan Nominatif Pegawai</title>
    <style>
        @page { size: landscape; margin: 12mm; }
        body { color: #172033; font-family: Arial, sans-serif; font-size: 11px; margin: 0; }
        h1 { font-size: 20px; margin: 0 0 5px; }
        p { color: #526176; margin: 0 0 14px; }
        button { background: #1d4ed8; border: 0; border-radius: 5px; color: white; cursor: pointer; font-weight: 700; margin-bottom: 14px; padding: 9px 13px; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #cbd5e1; padding: 6px; vertical-align: top; }
        th { background: #1e293b; color: white; text-align: left; }
        td.number, th.number { text-align: right; }
        tbody tr:nth-child(even) td { background: #f8fafc; }
        @media print { button { display: none; } }
    </style>
</head>
<body>
    <h1>Laporan Nominatif Pegawai</h1>
    <p>
        Kantor: {{ $filters['kantor'] ?: 'Semua kantor' }} |
        Jabatan: {{ $filters['jabatan'] ?: 'Semua jabatan' }} |
        Status: {{ $filters['status_kepeg'] ?: 'Semua status' }}
        @if ($filters['search']) | Pencarian: {{ $filters['search'] }} @endif
    </p>
    <button type="button" onclick="window.print()">Cetak / Simpan PDF</button>
    <table>
        <thead>
        <tr>
            <th class="number">No</th>
            <th>Nama Pegawai</th>
            <th>ID Pegawai</th>
            <th>Jabatan</th>
            <th>TMT Jabatan</th>
            <th>Unit Kerja</th>
            <th>Status Kepegawaian</th>
            <th>Jenjang Pendidikan</th>
            <th>Sekolah/Universitas</th>
        </tr>
        </thead>
        <tbody>
        @forelse ($employees as $index => $employee)
            <tr>
                <td class="number">{{ $index + 1 }}</td>
                <td>{{ $employee->nama ?: '-' }}</td>
                <td>{{ $employee->id_peg ?: '-' }}</td>
                <td>{{ $employee->jabatan ?: '-' }}</td>
                <td>{{ $employee->tmt_jabatan ? date('d/m/Y', strtotime((string) $employee->tmt_jabatan)) : '-' }}</td>
                <td>{{ $employee->nama_kantor ?: '-' }}</td>
                <td>{{ $employee->status_kepeg ?: '-' }}</td>
                <td>{{ $employee->jenjang ?: '-' }}</td>
                <td>{{ $employee->nama_sekolah ?: '-' }}</td>
            </tr>
        @empty
            <tr><td colspan="9">Data nominatif tidak ditemukan.</td></tr>
        @endforelse
        </tbody>
    </table>
</body>
</html>

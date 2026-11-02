<?php

namespace App\Http\Controllers;

use App\Models\Legacy\TbDiklat;
use App\Models\Legacy\TbKantor;
use App\Models\Legacy\TbPegawai;
use App\Models\Legacy\TbRecycleBin;
use App\Services\XlsxExportService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Shuchkin\SimpleXLSX;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DiklatController extends Controller
{
    public function index(Request $request): Response
    {
        $this->ensureStaffAccess($request);

        $role = strtolower((string) $request->session()->get('hak_akses'));
        $unit = $role === 'kepala' ? (string) $request->session()->get('kode_kantor') : '';
        $year = $request->has('tahun') ? $request->string('tahun')->trim()->value() : date('Y');
        $training = $request->string('diklat')->trim()->value();
        $office = $request->string('kantor')->trim()->value();
        $search = $request->string('search')->trim()->value();

        $query = TbDiklat::query()
            ->from('tb_diklat as d')
            ->join('tb_pegawai as p', 'p.id_peg', '=', 'd.id_peg')
            ->leftJoin('tb_jabatan as j', function ($join): void {
                $join->on('p.id_peg', '=', 'j.id_peg')->where('j.status_jab', 'Aktif');
            })
            ->leftJoin('tb_kantor as k', 'j.unit_kerja', '=', 'k.kode_kantor_detail')
            ->when($year !== '', fn (Builder $builder) => $builder->where('d.tahun', $year))
            ->when($training !== '', fn (Builder $builder) => $builder->where('d.diklat', $training))
            ->when($office !== '', fn (Builder $builder) => $builder->where('j.unit_kerja', $office))
            ->when($unit !== '', fn (Builder $builder) => $builder->where('j.unit_kerja', $unit))
            ->when($search !== '', function (Builder $builder) use ($search): void {
                $like = "%{$search}%";
                $builder->where(function (Builder $scope) use ($like): void {
                    $scope->where('p.nama', 'like', $like)
                        ->orWhere('d.id_peg', 'like', $like)
                        ->orWhere('d.diklat', 'like', $like)
                        ->orWhere('d.penyelenggara', 'like', $like)
                        ->orWhere('d.tempat', 'like', $like);
                });
            });

        $items = $query
            ->select('d.*', 'p.nama as nama_pegawai', 'k.nama_kantor')
            ->orderByDesc('d.date_reg')
            ->orderByDesc('d.id_diklat')
            ->paginate(min(max($request->integer('per_page', 10), 1), 50))
            ->withQueryString()
            ->through(fn (TbDiklat $item): array => [
                'id_diklat' => $item->id_diklat,
                'id_peg' => $item->id_peg,
                'nama_pegawai' => $item->nama_pegawai,
                'diklat' => $item->diklat,
                'penyelenggara' => $item->penyelenggara,
                'tempat' => $item->tempat,
                'biaya' => $item->biaya,
                'angkatan' => $item->angkatan,
                'tahun' => $item->tahun,
                'date_reg' => $item->date_reg?->format('d-m-Y'),
                'nama_kantor' => $item->nama_kantor,
            ]);

        return Inertia::render('Diklat/Index', [
            'items' => $items,
            'years' => $this->years($unit),
            'trainingTypes' => $this->trainingTypes($unit),
            'offices' => $this->offices($unit),
            'filters' => [
                'tahun' => $year,
                'diklat' => $training,
                'kantor' => $office,
                'search' => $search,
            ],
            'canManage' => $this->canManage($request),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->ensureCanManage($request);

        return Inertia::render('Diklat/Form', [
            'mode' => 'create',
            'record' => null,
            'employees' => $this->employees(),
        ]);
    }

    public function importForm(Request $request): Response
    {
        $this->ensureCanManage($request);

        return Inertia::render('Diklat/Import');
    }

    public function downloadTemplate(Request $request, XlsxExportService $xlsxExport): StreamedResponse
    {
        $this->ensureCanManage($request);

        $content = $xlsxExport->make([
            ['NIK', 'NAMA_DIKLAT', 'PENYELENGGARA', 'TEMPAT', 'BIAYA', 'ANGKATAN', 'TAHUN', 'TGL_INPUT'],
            ['199001012022031001', 'Pelatihan Kepemimpinan', 'BPSDM', 'Jakarta', 0, 'Angkatan V', '2025', '2025-11-24'],
        ], 'Template Diklat');

        return response()->streamDownload(static function () use ($content): void {
            echo $content;
        }, 'Template_Import_Diklat.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function importXlsx(Request $request): RedirectResponse
    {
        $this->ensureCanManage($request);
        $request->validate([
            'file_xlsx' => ['required', 'file', 'mimes:xlsx', 'max:5120'],
        ]);

        $file = $request->file('file_xlsx');
        $xlsx = SimpleXLSX::parse($file->getRealPath());

        if ($xlsx === false) {
            throw ValidationException::withMessages(['file_xlsx' => 'File XLSX tidak dapat dibaca. '.SimpleXLSX::parseError()]);
        }

        $rawRows = $xlsx->rows();
        $header = array_map(fn ($value): string => trim((string) $value), array_shift($rawRows) ?: []);

        if ($header === false || count($header) < 7) {
            throw ValidationException::withMessages(['file_xlsx' => 'Header XLSX tidak sesuai. Minimal tujuh kolom diperlukan.']);
        }

        $rows = [];
        foreach ($rawRows as $row) {
            if (count(array_filter($row, fn ($value): bool => trim((string) $value) !== '')) === 0) {
                continue;
            }

            $rows[] = array_map(fn ($value): string => trim((string) $value), $row);
        }

        $summary = ['inserted' => 0, 'updated' => 0, 'skipped' => 0];
        $actor = $request->session()->get('id_user', $request->session()->get('nama_user', 'Admin'));

        DB::transaction(function () use (&$summary, $rows, $actor): void {
            foreach ($rows as $row) {
                $hasCostColumn = count($row) >= 8;
                $idInput = trim((string) ($row[0] ?? ''));
                $training = trim((string) ($row[1] ?? ''));
                $organizer = trim((string) ($row[2] ?? ''));
                $place = trim((string) ($row[3] ?? ''));
                $cost = $hasCostColumn ? trim((string) ($row[4] ?? '')) : '';
                $batch = $hasCostColumn ? trim((string) ($row[5] ?? '')) : trim((string) ($row[4] ?? ''));
                $year = $hasCostColumn ? trim((string) ($row[6] ?? '')) : trim((string) ($row[5] ?? ''));
                $date = $hasCostColumn ? trim((string) ($row[7] ?? '')) : trim((string) ($row[6] ?? ''));

                if ($idInput === '' || $training === '' || ! preg_match('/^\d{4}$/', $year)) {
                    $summary['skipped']++;

                    continue;
                }

                $employee = TbPegawai::query()
                    ->where('id_peg', $idInput)
                    ->orWhere('nik', $idInput)
                    ->first(['id_peg']);

                if (! $employee) {
                    $summary['skipped']++;

                    continue;
                }

                $payload = [
                    'id_peg' => $employee->id_peg,
                    'diklat' => mb_substr($training, 0, 255),
                    'penyelenggara' => $organizer !== '' ? mb_substr($organizer, 0, 255) : null,
                    'tempat' => $place !== '' ? mb_substr($place, 0, 128) : null,
                    'biaya' => $cost === '' ? null : (int) preg_replace('/[^0-9]/', '', $cost),
                    'angkatan' => $batch !== '' ? mb_substr($batch, 0, 64) : null,
                    'tahun' => $year,
                    'date_reg' => $this->parseImportDate($date),
                ];

                $record = TbDiklat::query()
                    ->where('id_peg', $employee->id_peg)
                    ->whereRaw('LOWER(TRIM(diklat)) = LOWER(TRIM(?))', [$training])
                    ->where('tahun', $year)
                    ->first();

                if ($record) {
                    $record->update([...$payload, 'updated_by' => $actor]);
                    $summary['updated']++;

                    continue;
                }

                TbDiklat::query()->create([...$payload, 'created_by' => $actor]);
                $summary['inserted']++;
            }
        });

        return to_route('diklat.index')->with(
            'success',
            "Import diklat selesai. Data baru: {$summary['inserted']}, diperbarui: {$summary['updated']}, dilewati: {$summary['skipped']}."
        );
    }

    public function edit(Request $request, int $id): Response
    {
        $this->ensureCanManage($request);
        $record = TbDiklat::query()->findOrFail($id);

        return Inertia::render('Diklat/Form', [
            'mode' => 'edit',
            'record' => [
                'id_diklat' => $record->id_diklat,
                'id_peg' => $record->id_peg,
                'diklat' => $record->diklat,
                'penyelenggara' => $record->penyelenggara,
                'tempat' => $record->tempat,
                'biaya' => $record->biaya,
                'angkatan' => $record->angkatan,
                'tahun' => $record->tahun,
                'date_reg' => $record->date_reg?->format('Y-m-d'),
            ],
            'employees' => $this->employees(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->ensureCanManage($request);
        $data = $this->validatedData($request);
        $data['created_by'] = $request->session()->get('id_user', $request->session()->get('nama_user'));

        TbDiklat::query()->create($data);

        return to_route('diklat.index')->with('success', 'Data diklat berhasil disimpan.');
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $this->ensureCanManage($request);
        $record = TbDiklat::query()->findOrFail($id);
        $data = $this->validatedData($request);
        $data['updated_by'] = $request->session()->get('id_user', $request->session()->get('nama_user'));
        $record->update($data);

        return to_route('diklat.index')->with('success', 'Data diklat berhasil diperbarui.');
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        $this->ensureCanManage($request);
        $validated = $request->validate(['reason' => ['required', 'string', 'max:1000']]);

        DB::transaction(function () use ($request, $id, $validated): void {
            $record = TbDiklat::query()->lockForUpdate()->findOrFail($id);

            TbRecycleBin::query()->create([
                'tabel_by' => 'tb_diklat',
                'data_json' => json_encode($record->getAttributes(), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                'alasan' => $validated['reason'],
                'created_by' => $request->session()->get('nama_user', 'Admin'),
                'created' => now(),
            ]);

            $record->delete();
        });

        return to_route('diklat.index')->with('success', 'Data diklat dipindahkan ke Recycle Bin.');
    }

    private function validatedData(Request $request): array
    {
        $validated = $request->validate([
            'id_peg' => ['required', 'string', 'max:12', Rule::exists('tb_pegawai', 'id_peg')],
            'diklat' => ['required', 'string', 'max:255'],
            'penyelenggara' => ['nullable', 'string', 'max:255'],
            'tempat' => ['nullable', 'string', 'max:128'],
            'biaya' => ['nullable', 'string', 'max:32'],
            'angkatan' => ['nullable', 'string', 'max:64'],
            'tahun' => ['required', 'digits:4'],
            'date_reg' => ['required', 'date'],
        ]);

        $validated['biaya'] = ($validated['biaya'] ?? '') === '' ? null : (int) preg_replace('/[^0-9]/', '', $validated['biaya']);

        return $validated;
    }

    private function parseImportDate(string $value): string
    {
        if ($value === '') {
            return now()->toDateString();
        }

        if (is_numeric($value) && (int) $value > 1000) {
            return Carbon::create(1899, 12, 30)->addDays((int) $value)->toDateString();
        }

        foreach (['Y-m-d', 'd-m-Y', 'd/m/Y', 'd.m.Y'] as $format) {
            try {
                return Carbon::createFromFormat($format, $value)->toDateString();
            } catch (\Throwable) {
                continue;
            }
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return now()->toDateString();
        }
    }

    private function employees(): array
    {
        return TbPegawai::query()
            ->orderBy('nama')
            ->get(['id_peg', 'nama'])
            ->map(fn (TbPegawai $employee): array => ['id_peg' => $employee->id_peg, 'nama' => $employee->nama])
            ->all();
    }

    private function years(string $unit): array
    {
        return TbDiklat::query()
            ->from('tb_diklat as d')
            ->leftJoin('tb_jabatan as j', function ($join): void {
                $join->on('j.id_peg', '=', 'd.id_peg')->where('j.status_jab', 'Aktif');
            })
            ->when($unit !== '', fn (Builder $builder) => $builder->where('j.unit_kerja', $unit))
            ->whereNotNull('d.tahun')
            ->where('d.tahun', '<>', '')
            ->distinct()
            ->orderByDesc('d.tahun')
            ->pluck('d.tahun')
            ->values()
            ->all();
    }

    private function trainingTypes(string $unit): array
    {
        return TbDiklat::query()
            ->from('tb_diklat as d')
            ->leftJoin('tb_jabatan as j', function ($join): void {
                $join->on('j.id_peg', '=', 'd.id_peg')->where('j.status_jab', 'Aktif');
            })
            ->when($unit !== '', fn (Builder $builder) => $builder->where('j.unit_kerja', $unit))
            ->whereNotNull('d.diklat')
            ->where('d.diklat', '<>', '')
            ->distinct()
            ->orderBy('d.diklat')
            ->pluck('d.diklat')
            ->values()
            ->all();
    }

    private function offices(string $unit): array
    {
        return TbKantor::query()
            ->whereIn('level', ['KC', 'KP'])
            ->when($unit !== '', fn (Builder $builder) => $builder->where('kode_kantor_detail', $unit))
            ->orderBy('nama_kantor')
            ->get(['kode_kantor_detail', 'nama_kantor'])
            ->map(fn (TbKantor $office): array => ['kode' => $office->kode_kantor_detail, 'nama' => $office->nama_kantor])
            ->all();
    }

    private function canManage(Request $request): bool
    {
        return strtolower((string) $request->session()->get('hak_akses')) === 'admin';
    }

    private function ensureCanManage(Request $request): void
    {
        abort_unless($this->canManage($request), 403);
    }

    private function ensureStaffAccess(Request $request): void
    {
        abort_unless(in_array(strtolower((string) $request->session()->get('hak_akses')), ['admin', 'superadmin', 'kepala'], true), 403);
    }
}

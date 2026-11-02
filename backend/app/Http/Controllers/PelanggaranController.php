<?php

namespace App\Http\Controllers;

use App\Models\Legacy\TbHukuman;
use App\Models\Legacy\TbJabatan;
use App\Models\Legacy\TbPegawai;
use App\Models\Legacy\TbRecycleBin;
use App\Services\XlsxExportService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PelanggaranController extends Controller
{
    private const TYPES = [
        'Surat Peringatan I',
        'Surat Peringatan II',
        'Surat Peringatan III',
        'Skorsing',
        'PTDH',
        'Teguran Lisan',
        'Teguran Tertulis',
    ];

    private const OFFICIAL_POSITIONS = [
        'Direktur Operasional',
        'Kepala Divisi SDM dan Umum',
    ];

    public function index(Request $request): Response
    {
        $this->ensureStaffAccess($request);

        $search = $request->string('search')->trim()->value();
        $items = $this->filteredPenaltyQuery($request, $search)
            ->select('h.*', 'p.nama as nama_pegawai', 'k.nama_kantor')
            ->orderByDesc('h.tgl_sk')
            ->orderByDesc('h.id_hukum')
            ->paginate(min(max($request->integer('per_page', 10), 1), 50))
            ->withQueryString()
            ->through(fn (TbHukuman $item): array => $this->presentPenalty($item));

        return Inertia::render('Pelanggaran/Index', [
            'items' => $items,
            'filters' => ['search' => $search],
            'canManage' => $this->canManage($request),
        ]);
    }

    public function export(Request $request, XlsxExportService $xlsxExport): StreamedResponse
    {
        $this->ensureStaffAccess($request);

        $search = $request->string('search')->trim()->value();
        $records = $this->filteredPenaltyQuery($request, $search)
            ->select('h.*', 'p.nama as nama_pegawai', 'k.nama_kantor')
            ->orderByDesc('h.tgl_sk')
            ->orderByDesc('h.id_hukum')
            ->get();

        $rows = [[
            'Nama pegawai', 'ID pegawai', 'Jenis sanksi', 'Keterangan', 'Tanggal SK',
            'Nomor SK', 'Kantor', 'Pejabat SK', 'Jabatan SK', 'Dokumen SK',
            'Pejabat pemulihan', 'Jabatan pemulihan', 'Nomor surat pemulihan', 'Tanggal pemulihan',
        ]];

        foreach ($records as $record) {
            $rows[] = [
                $record->nama_pegawai ?: 'Tanpa Nama',
                $record->id_peg,
                $record->hukuman,
                $record->keterangan,
                $record->tgl_sk?->format('d-m-Y'),
                $record->no_sk,
                $record->nama_kantor,
                $record->pejabat_sk,
                $record->jabatan_sk,
                $record->dokumen ? basename((string) $record->dokumen) : '-',
                $record->pejabat_pulih,
                $record->jabatan_pulih,
                $record->no_pulih,
                $record->tgl_pulih?->format('d-m-Y'),
            ];
        }

        $content = $xlsxExport->make($rows, 'Pelanggaran', true);
        $filename = 'data-pelanggaran-'.now()->format('Ymd_His').'.xlsx';

        return response()->streamDownload(static function () use ($content): void {
            echo $content;
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    private function basePenaltyQuery(Request $request): Builder
    {
        $role = strtolower((string) $request->session()->get('hak_akses'));

        return TbHukuman::query()
            ->from('tb_hukuman as h')
            ->join('tb_pegawai as p', 'p.id_peg', '=', 'h.id_peg')
            ->leftJoin('tb_jabatan as j', function ($join): void {
                $join->on('p.id_peg', '=', 'j.id_peg')->where('j.status_jab', 'Aktif');
            })
            ->leftJoin('tb_kantor as k', 'j.unit_kerja', '=', 'k.kode_kantor_detail')
            ->when($role === 'kepala', function (Builder $builder) use ($request): void {
                $builder->where('j.unit_kerja', $request->session()->get('kode_kantor'));
            });
    }

    private function filteredPenaltyQuery(Request $request, string $search): Builder
    {
        return $this->basePenaltyQuery($request)
            ->when($search !== '', function (Builder $builder) use ($search): void {
                $like = "%{$search}%";
                $builder->where(function (Builder $scope) use ($like): void {
                    $scope->where('p.nama', 'like', $like)
                        ->orWhere('h.id_peg', 'like', $like)
                        ->orWhere('h.hukuman', 'like', $like)
                        ->orWhere('h.keterangan', 'like', $like)
                        ->orWhere('h.no_sk', 'like', $like)
                        ->orWhere('k.nama_kantor', 'like', $like);
                });
            });
    }

    public function create(Request $request): Response
    {
        $this->ensureCanManage($request);

        return Inertia::render('Pelanggaran/Form', [
            'mode' => 'create',
            'record' => null,
            'employees' => $this->employees(),
            'officials' => $this->officials(),
            'types' => self::TYPES,
        ]);
    }

    public function edit(Request $request, int $id): Response
    {
        $this->ensureCanManage($request);
        $record = TbHukuman::query()->findOrFail($id);
        $officials = $this->officials();

        if ($record->pejabat_sk && ! collect($officials)->contains('nama', $record->pejabat_sk)) {
            $officials[] = ['nama' => $record->pejabat_sk, 'jabatan' => $record->jabatan_sk];
        }

        return Inertia::render('Pelanggaran/Form', [
            'mode' => 'edit',
            'record' => $this->formRecord($record),
            'employees' => $this->employees(),
            'officials' => $officials,
            'types' => self::TYPES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->ensureCanManage($request);
        $data = $this->validatedData($request);
        $storedFile = null;

        try {
            DB::transaction(function () use ($request, &$data, &$storedFile): void {
                $data['dokumen'] = $storedFile = $this->storeDocument($request, $data['id_peg']) ?: '';
                $data['date_reg'] = now()->toDateString();
                TbHukuman::query()->create($data);
            });
        } catch (\Throwable $exception) {
            $this->deleteDocument($storedFile);
            throw $exception;
        }

        return to_route('pelanggaran.index')->with('success', 'Data pelanggaran berhasil disimpan.');
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $this->ensureCanManage($request);
        $record = TbHukuman::query()->findOrFail($id);
        $data = $this->validatedData($request);
        $oldFile = $record->dokumen;
        $storedFile = null;

        try {
            DB::transaction(function () use ($request, $record, $data, &$storedFile): void {
                $update = collect($data)->except('dokumen')->all();
                $update['date_update'] = now()->toDateString();

                if ($request->hasFile('dokumen')) {
                    $storedFile = $this->storeDocument($request, $data['id_peg']);
                    $update['dokumen'] = $storedFile;
                }

                $record->update($update);
            });
        } catch (\Throwable $exception) {
            $this->deleteDocument($storedFile);
            throw $exception;
        }

        if ($storedFile !== null && $oldFile !== $storedFile) {
            $this->deleteDocument($oldFile);
        }

        return to_route('pelanggaran.index')->with('success', 'Data pelanggaran berhasil diperbarui.');
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        $this->ensureCanManage($request);
        $validated = $request->validate(['reason' => ['required', 'string', 'max:1000']]);

        DB::transaction(function () use ($request, $id, $validated): void {
            $record = TbHukuman::query()->lockForUpdate()->findOrFail($id);

            TbRecycleBin::query()->create([
                'tabel_by' => 'tb_hukuman',
                'data_json' => json_encode($record->getAttributes(), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                'alasan' => $validated['reason'],
                'created_by' => $request->session()->get('nama_user', 'Admin'),
                'created' => now(),
            ]);

            $record->delete();
        });

        return to_route('pelanggaran.index')->with('success', 'Data pelanggaran dipindahkan ke Recycle Bin.');
    }

    public function document(Request $request, int $id): BinaryFileResponse
    {
        $this->ensureStaffAccess($request);
        $record = TbHukuman::query()->findOrFail($id);
        $this->ensurePenaltyVisible($request, $record);

        $filename = basename((string) $record->dokumen);
        abort_if($filename === '' || $filename === '.', 404);

        $paths = [
            Storage::disk('public')->path('dokumen_hukuman/'.$filename),
            base_path('../pages/assets/dokumen_hukuman/'.$filename),
        ];

        foreach ($paths as $path) {
            if (is_file($path)) {
                return response()->file($path);
            }
        }

        abort(404);
    }

    private function validatedData(Request $request): array
    {
        return $request->validate([
            'id_peg' => ['required', 'string', 'max:12', Rule::exists('tb_pegawai', 'id_peg')],
            'hukuman' => ['required', Rule::in(self::TYPES)],
            'keterangan' => ['nullable', 'string'],
            'pejabat_sk' => ['required', 'string', 'max:64'],
            'jabatan_sk' => ['required', 'string', 'max:64'],
            'no_sk' => ['required', 'string', 'max:32'],
            'tgl_sk' => ['required', 'date'],
            'dokumen' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'pejabat_pulih' => ['nullable', 'string', 'max:64'],
            'jabatan_pulih' => ['nullable', 'string', 'max:64'],
            'no_pulih' => ['nullable', 'string', 'max:32'],
            'tgl_pulih' => ['nullable', 'date'],
        ]);
    }

    private function employees(): array
    {
        return TbPegawai::query()
            ->orderBy('nama')
            ->get(['id_peg', 'nama'])
            ->map(fn (TbPegawai $employee): array => ['id_peg' => $employee->id_peg, 'nama' => $employee->nama])
            ->all();
    }

    private function officials(): array
    {
        return TbPegawai::query()
            ->from('tb_pegawai as p')
            ->join('tb_jabatan as j', 'j.id_peg', '=', 'p.id_peg')
            ->where('j.status_jab', 'Aktif')
            ->whereIn('j.jabatan', self::OFFICIAL_POSITIONS)
            ->select(['p.nama', 'j.jabatan'])
            ->distinct()
            ->orderBy('j.jabatan')
            ->orderBy('p.nama')
            ->get()
            ->map(fn ($official): array => ['nama' => $official->nama, 'jabatan' => $official->jabatan])
            ->all();
    }

    private function storeDocument(Request $request, string $idPeg): ?string
    {
        if (! $request->hasFile('dokumen')) {
            return null;
        }

        $extension = strtolower($request->file('dokumen')->getClientOriginalExtension() ?: 'bin');
        $filename = 'SK_HUKUM_'.$idPeg.'_'.now()->format('YmdHis').'_'.Str::lower(Str::random(6)).'.'.$extension;
        Storage::disk('public')->putFileAs('dokumen_hukuman', $request->file('dokumen'), $filename);

        return $filename;
    }

    private function deleteDocument(?string $filename): void
    {
        if ($filename) {
            Storage::disk('public')->delete('dokumen_hukuman/'.basename($filename));
        }
    }

    private function presentPenalty(TbHukuman $item): array
    {
        $hasDocument = $item->dokumen && $this->documentExists($item->dokumen);

        return [
            'id_hukum' => $item->id_hukum,
            'id_peg' => $item->id_peg,
            'nama_pegawai' => $item->nama_pegawai,
            'hukuman' => $item->hukuman,
            'keterangan' => $item->keterangan,
            'tgl_sk' => $item->tgl_sk?->format('d-m-Y'),
            'nama_kantor' => $item->nama_kantor,
            'dokumen_url' => $hasDocument ? route('pelanggaran.document', $item->id_hukum) : null,
        ];
    }

    private function formRecord(TbHukuman $record): array
    {
        return collect($record->getAttributes())->only([
            'id_hukum', 'id_peg', 'hukuman', 'keterangan', 'pejabat_sk', 'jabatan_sk', 'no_sk', 'tgl_sk', 'dokumen',
            'pejabat_pulih', 'jabatan_pulih', 'no_pulih', 'tgl_pulih',
        ])->map(function ($value, string $key) {
            return $value instanceof \DateTimeInterface ? $value->format('Y-m-d') : $value;
        })->all();
    }

    private function documentExists(string $filename): bool
    {
        $safeFilename = basename($filename);

        return is_file(Storage::disk('public')->path('dokumen_hukuman/'.$safeFilename))
            || is_file(base_path('../pages/assets/dokumen_hukuman/'.$safeFilename));
    }

    private function ensurePenaltyVisible(Request $request, TbHukuman $record): void
    {
        if (strtolower((string) $request->session()->get('hak_akses')) !== 'kepala') {
            return;
        }

        abort_unless(TbJabatan::query()
            ->where('id_peg', $record->id_peg)
            ->where('status_jab', 'Aktif')
            ->where('unit_kerja', $request->session()->get('kode_kantor'))
            ->exists(), 403);
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

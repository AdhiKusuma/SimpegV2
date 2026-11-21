<?php

namespace App\Http\Controllers;

use App\Models\Legacy\TbJabatan;
use App\Models\Legacy\TbKantor;
use App\Models\Legacy\TbMasterJabatan;
use App\Models\Legacy\TbMutasi;
use App\Models\Legacy\TbPegawai;
use App\Models\Legacy\TbRecycleBin;
use App\Services\XlsxExportService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\JsonResponse;
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

class MutationController extends Controller
{
    private const TYPES = [
        'Pensiun',
        'Pensiun Dini',
        'Meninggal Dunia',
        'Pengunduran Diri',
        'PTDH',
        'Pemberhentian Lainnya',
    ];

    public function index(Request $request): Response
    {
        $this->ensureStaffAccess($request);

        $search = $request->string('search')->trim()->value();
        $office = $request->string('kantor')->trim()->value();
        $division = $request->string('divisi')->trim()->value();
        $position = $request->string('jabatan')->trim()->value();
        $requestedYear = $request->string('year')->trim()->value();
        $year = preg_match('/^\d{4}$/', $requestedYear) === 1 ? $requestedYear : '';
        $baseQuery = $this->baseMutationQuery($request);
        $yearsQuery = clone $baseQuery;
        $years = $yearsQuery
            ->whereNotNull('p.tmt_kerja')
            ->selectRaw('YEAR(p.tmt_kerja) as year')
            ->distinct()
            ->orderByDesc('year')
            ->pluck('year')
            ->map(fn ($value): string => (string) $value)
            ->values();

        $filterOptions = $office !== ''
            ? $this->mutationFilterOptions($request, $office, $division)
            : ['divisions' => [], 'positions' => []];

        $items = $this->filteredMutationQuery($request, $year, $search, $office, $division, $position)
            ->select('m.*', 'p.nama as nama_pegawai')
            ->orderByDesc('m.tgl_mutasi')
            ->orderByDesc('m.id_mutasi')
            ->paginate(min(max($request->integer('per_page', 10), 1), 50))
            ->withQueryString()
            ->through(fn (TbMutasi $item): array => $this->presentMutation($item));

        return Inertia::render('Mutation/Index', [
            'items' => $items,
            'offices' => $this->mutationFilterOffices($request),
            'divisions' => $filterOptions['divisions'],
            'positions' => $filterOptions['positions'],
            'years' => $years,
            'filters' => [
                'search' => $search,
                'kantor' => $office,
                'divisi' => $division,
                'jabatan' => $position,
                'year' => $year,
            ],
            'canManage' => $this->canManage($request),
        ]);
    }

    public function filterOptions(Request $request): JsonResponse
    {
        $this->ensureStaffAccess($request);

        $office = $request->string('kantor')->trim()->value();
        $division = $request->string('divisi')->trim()->value();

        if ($office === '') {
            return response()->json(['divisions' => [], 'positions' => []]);
        }

        return response()->json($this->mutationFilterOptions($request, $office, $division));
    }

    public function export(Request $request, XlsxExportService $xlsxExport): StreamedResponse
    {
        $this->ensureStaffAccess($request);

        $search = $request->string('search')->trim()->value();
        $office = $request->string('kantor')->trim()->value();
        $division = $request->string('divisi')->trim()->value();
        $position = $request->string('jabatan')->trim()->value();
        $requestedYear = $request->string('year')->trim()->value();
        $year = preg_match('/^\d{4}$/', $requestedYear) === 1 ? $requestedYear : '';
        $records = $this->filteredMutationQuery($request, $year, $search, $office, $division, $position)
            ->select('m.*', 'p.nama as nama_pegawai')
            ->orderByDesc('m.tgl_mutasi')
            ->orderByDesc('m.id_mutasi')
            ->get();

        $rows = [[
            'Nama pegawai', 'ID pegawai', 'Jabatan terakhir', 'Jenis penonaktifan',
            'Tanggal SK', 'Nomor SK', 'TMT', 'Dokumen SK',
        ]];

        foreach ($records as $record) {
            $rows[] = [
                $record->nama_pegawai ?: 'Tanpa Nama',
                $record->id_peg,
                $record->jabatan,
                $record->jns_mutasi,
                $record->tgl_mutasi?->format('d-m-Y'),
                $record->no_mutasi,
                $record->tmt?->format('d-m-Y'),
                $record->sk_mutasi ? basename((string) $record->sk_mutasi) : '-',
            ];
        }

        $content = $xlsxExport->make($rows, 'Penonaktifan', true);
        $filename = 'data-penonaktifan-'.now()->format('Ymd_His').'.xlsx';

        return response()->streamDownload(static function () use ($content): void {
            echo $content;
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    private function baseMutationQuery(Request $request): Builder
    {
        $role = strtolower((string) $request->session()->get('hak_akses'));

        return TbMutasi::query()
            ->from('tb_mutasi as m')
            ->leftJoin('tb_pegawai as p', 'p.id_peg', '=', 'm.id_peg')
            ->when($role === 'kepala', function (Builder $builder) use ($request): void {
                $builder->whereExists(function (QueryBuilder $scope) use ($request): void {
                    $scope->selectRaw('1')
                        ->from('tb_jabatan as j_scope')
                        ->whereColumn('j_scope.id_peg', 'm.id_peg')
                        ->where('j_scope.status_jab', 'Aktif')
                        ->where('j_scope.unit_kerja', $request->session()->get('kode_kantor'));
                });
            });
    }

    private function filteredMutationQuery(Request $request, string $year, string $search, string $office = '', string $division = '', string $position = ''): Builder
    {
        return $this->baseMutationQuery($request)
            ->when($year !== '', fn (Builder $builder) => $builder->whereYear('p.tmt_kerja', $year))
            ->when($office !== '', function (Builder $builder) use ($office): void {
                $builder->whereExists(function (QueryBuilder $scope) use ($office): void {
                    $scope->selectRaw('1')
                        ->from('tb_jabatan as j_filter')
                        ->whereColumn('j_filter.id_peg', 'm.id_peg')
                        ->where('j_filter.unit_kerja', $office);
                });
            })
            ->when($division !== '', function (Builder $builder) use ($division): void {
                $selectedDivision = $this->normalizeMutationDivision($division);

                $builder->whereExists(function (QueryBuilder $scope) use ($selectedDivision, $office): void {
                    $scope->selectRaw('1')
                        ->from('tb_jabatan as j_filter')
                        ->leftJoin('tb_master_jabatan as mj_filter', 'j_filter.kode_jabatan', '=', 'mj_filter.kode_jabatan')
                        ->whereColumn('j_filter.id_peg', 'm.id_peg')
                        ->when($office !== '', fn (QueryBuilder $query) => $query->where('j_filter.unit_kerja', $office))
                        ->whereRaw('TRIM(mj_filter.nama_unit_kerja) = ?', [$selectedDivision]);
                });
            })
            ->when($position !== '', function (Builder $builder) use ($position): void {
                $builder->whereExists(function (QueryBuilder $scope) use ($position, $office): void {
                    $scope->selectRaw('1')
                        ->from('tb_jabatan as j_filter')
                        ->leftJoin('tb_master_jabatan as mj_filter', 'j_filter.kode_jabatan', '=', 'mj_filter.kode_jabatan')
                        ->whereColumn('j_filter.id_peg', 'm.id_peg')
                        ->when($office !== '', fn (QueryBuilder $query) => $query->where('j_filter.unit_kerja', $office))
                        ->where(function (QueryBuilder $positionScope) use ($position): void {
                            $positionScope->whereRaw('TRIM(mj_filter.nama_jabatan) = ?', [$position])
                                ->orWhereRaw('TRIM(j_filter.jabatan) = ?', [$position]);
                        });
                });
            })
            ->when($search !== '', function (Builder $builder) use ($search): void {
                $like = "%{$search}%";
                $builder->where(function (Builder $scope) use ($like): void {
                    $scope->where('p.nama', 'like', $like)
                        ->orWhere('p.id_peg', 'like', $like);
                });
            });
    }

    private function mutationFilterOffices(Request $request): \Illuminate\Support\Collection
    {
        $role = strtolower((string) $request->session()->get('hak_akses'));

        return TbKantor::query()
            ->whereNotNull('nama_kantor')
            ->whereRaw("TRIM(nama_kantor) <> ''")
            ->where(function (Builder $builder): void {
                $builder->whereIn('level', ['KP', 'KANWIL', 'KC'])
                    ->orWhere(function (Builder $regional): void {
                        $regional->whereNull('level')
                            ->whereRaw("UPPER(TRIM(nama_kantor)) LIKE 'KANTOR WILAYAH%'");
                    });
            })
            ->when($role === 'kepala', fn (Builder $builder) => $builder->where('kode_kantor_detail', $request->session()->get('kode_kantor')))
            ->orderBy('kode_kantor_detail')
            ->get(['kode_kantor_detail', 'nama_kantor', 'level', 'kode_cabang']);
    }

    private function mutationFilterOptions(Request $request, string $office, string $division = ''): array
    {
        $role = strtolower((string) $request->session()->get('hak_akses'));
        $scopeOffice = $role === 'kepala' ? $request->session()->get('kode_kantor') : null;

        if ($scopeOffice !== null && $office !== $scopeOffice) {
            return ['divisions' => [], 'positions' => []];
        }

        $divisions = TbMasterJabatan::query()
            ->from('tb_master_jabatan as mj')
            ->whereNotNull('mj.nama_unit_kerja')
            ->whereRaw("TRIM(mj.nama_unit_kerja) <> ''")
            ->whereExists(function (QueryBuilder $scope) use ($office): void {
                $scope->selectRaw('1')
                    ->from('tb_jabatan as j_filter')
                    ->join('tb_mutasi as m_filter', 'm_filter.id_peg', '=', 'j_filter.id_peg')
                    ->whereColumn('j_filter.kode_jabatan', 'mj.kode_jabatan')
                    ->where('j_filter.unit_kerja', $office);
            })
            ->selectRaw('TRIM(mj.nama_unit_kerja) as value')
            ->distinct()
            ->orderBy('value')
            ->get()
            ->map(fn ($row): array => [
                'value' => 'division:'.$row->value,
                'label' => $row->value,
                'description' => 'Bagian / Divisi',
            ])
            ->values();

        $selectedDivision = $this->normalizeMutationDivision($division);
        $positions = collect();

        if ($division !== '') {
            $positions = TbMasterJabatan::query()
                ->from('tb_master_jabatan as mj')
                ->whereNotNull('mj.nama_jabatan')
                ->whereRaw("TRIM(mj.nama_jabatan) <> ''")
                ->whereRaw('TRIM(mj.nama_unit_kerja) = ?', [$selectedDivision])
                ->whereExists(function (QueryBuilder $scope) use ($office): void {
                    $scope->selectRaw('1')
                        ->from('tb_jabatan as j_filter')
                        ->join('tb_mutasi as m_filter', 'm_filter.id_peg', '=', 'j_filter.id_peg')
                        ->whereColumn('j_filter.kode_jabatan', 'mj.kode_jabatan')
                        ->where('j_filter.unit_kerja', $office);
                })
                ->selectRaw('TRIM(mj.nama_jabatan) as value')
                ->distinct()
                ->orderBy('value')
                ->get()
                ->map(fn ($row): array => [
                    'value' => $row->value,
                    'label' => $row->value,
                    'description' => 'Jabatan pada divisi terpilih',
                ])
                ->values();
        }

        return ['divisions' => $divisions, 'positions' => $positions];
    }

    private function normalizeMutationDivision(string $division): string
    {
        return str_starts_with($division, 'division:')
            ? trim(substr($division, strlen('division:')))
            : trim($division);
    }

    public function create(Request $request): Response
    {
        $this->ensureCanManage($request);

        return Inertia::render('Mutation/Form', [
            'mode' => 'create',
            'record' => null,
            'employees' => $this->employeesForCreate(),
            'types' => self::TYPES,
        ]);
    }

    public function edit(Request $request, int $id): Response
    {
        $this->ensureCanManage($request);
        $record = TbMutasi::query()->findOrFail($id);
        $employees = $this->employeesForCreate();

        if (! collect($employees)->contains('id_peg', $record->id_peg)) {
            $employee = TbPegawai::query()->where('id_peg', $record->id_peg)->first(['id_peg', 'nama']);
            if ($employee) {
                $employees[] = ['id_peg' => $employee->id_peg, 'nama' => $employee->nama];
            }
        }

        return Inertia::render('Mutation/Form', [
            'mode' => 'edit',
            'record' => [
                'id_mutasi' => $record->id_mutasi,
                'id_peg' => $record->id_peg,
                'jns_mutasi' => $record->jns_mutasi,
                'no_mutasi' => $record->no_mutasi,
                'tgl_mutasi' => $record->tgl_mutasi?->format('Y-m-d'),
                'tmt' => $record->tmt?->format('Y-m-d'),
                'sk_mutasi' => $record->sk_mutasi,
            ],
            'employees' => $employees,
            'types' => self::TYPES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->ensureCanManage($request);
        $data = $this->validatedData($request, false);
        $storedFile = null;

        try {
            DB::transaction(function () use ($request, &$data, &$storedFile): void {
                $data['jabatan'] = $this->latestActivePosition($data['id_peg']);
                $data['date_reg'] = now()->toDateString();
                $data['created_by'] = substr((string) $request->session()->get('id_user', 'system'), 0, 20);
                $data['sk_mutasi'] = $storedFile = $this->storeDocument($request, $data['id_peg']);

                TbMutasi::query()->create($data);

                TbJabatan::query()
                    ->where('id_peg', $data['id_peg'])
                    ->where('status_jab', 'Aktif')
                    ->update(['status_jab' => 'Non', 'sampai_tgl' => $data['tmt']]);

                TbPegawai::query()
                    ->where('id_peg', $data['id_peg'])
                    ->update(['status_aktif' => 3]);
            });
        } catch (\Throwable $exception) {
            $this->deleteDocument($storedFile);
            throw $exception;
        }

        return to_route('mutasi.index')->with('success', 'Data mutasi berhasil disimpan.');
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $this->ensureCanManage($request);
        $data = $this->validatedData($request, true);
        $oldFile = null;
        $storedFile = null;

        try {
            DB::transaction(function () use ($request, $id, $data, &$oldFile, &$storedFile): void {
                $record = TbMutasi::query()->lockForUpdate()->findOrFail($id);
                $oldFile = $record->sk_mutasi;
                $update = [
                    'jns_mutasi' => $data['jns_mutasi'],
                    'no_mutasi' => $data['no_mutasi'],
                    'tgl_mutasi' => $data['tgl_mutasi'],
                    'tmt' => $data['tmt'],
                    'updated_at' => now(),
                    'updated_by' => substr((string) $request->session()->get('id_user', 'system'), 0, 20),
                ];

                if ($request->hasFile('sk_mutasi')) {
                    $storedFile = $this->storeDocument($request, (string) $record->id_peg);
                    $update['sk_mutasi'] = $storedFile;
                }

                $record->forceFill($update)->saveOrFail();
            });
        } catch (\Throwable $exception) {
            $this->deleteDocument($storedFile);
            report($exception);

            return back()
                ->withInput()
                ->with('error', 'Sistem gagal menyimpan perubahan. Data sebelumnya tetap aman. Silakan coba lagi; jika berulang, hubungi administrator.');
        }

        if ($storedFile !== null && $oldFile !== $storedFile) {
            $this->deleteDocument($oldFile);
        }

        return to_route('mutasi.index')->with('success', 'Data mutasi berhasil diperbarui.');
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        $this->ensureCanManage($request);
        $validated = $request->validate(['reason' => ['required', 'string', 'max:1000']]);

        DB::transaction(function () use ($request, $id, $validated): void {
            $record = TbMutasi::query()->lockForUpdate()->findOrFail($id);
            $idPeg = $record->id_peg;

            TbRecycleBin::query()->create([
                'tabel_by' => 'tb_mutasi',
                'data_json' => json_encode($record->getAttributes(), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                'alasan' => $validated['reason'],
                'created_by' => $request->session()->get('nama_user', 'Admin'),
                'created' => now(),
            ]);

            $record->delete();

            if ($idPeg) {
                TbPegawai::query()->where('id_peg', $idPeg)->update(['status_aktif' => 1]);
                TbJabatan::query()
                    ->where('id_peg', $idPeg)
                    ->orderByDesc('tmt_jabatan')
                    ->orderByDesc('id_jab')
                    ->limit(1)
                    ->update(['status_jab' => 'Aktif', 'sampai_tgl' => null]);
            }
        });

        return to_route('mutasi.index')->with('success', 'Data mutasi dipindahkan ke Recycle Bin dan status pegawai dipulihkan.');
    }

    public function document(Request $request, int $id): BinaryFileResponse
    {
        $this->ensureStaffAccess($request);
        $record = TbMutasi::query()->findOrFail($id);
        $this->ensureMutationVisible($request, $record);

        $filename = basename((string) $record->sk_mutasi);
        abort_if($filename === '' || $filename === '.', 404);

        $paths = [
            Storage::disk('public')->path('sk_mutasi/'.$filename),
        ];

        foreach ($paths as $path) {
            if (is_file($path)) {
                return response()->file($path);
            }
        }

        abort(404);
    }

    private function validatedData(Request $request, bool $isEdit): array
    {
        $rules = [
            'jns_mutasi' => ['required', Rule::in(self::TYPES)],
            'no_mutasi' => ['required', 'string', 'max:255'],
            'tgl_mutasi' => ['required', 'date'],
            'tmt' => ['required', 'date'],
            'sk_mutasi' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ];

        if (! $isEdit) {
            $rules['id_peg'] = ['required', 'string', 'max:12', Rule::exists('tb_pegawai', 'id_peg')->where(fn ($query) => $query->where('status_aktif', 1))];
        }

        return $request->validate($rules);
    }

    private function employeesForCreate(): array
    {
        return TbPegawai::query()
            ->where('status_aktif', 1)
            ->orderBy('nama')
            ->get(['id_peg', 'nama'])
            ->map(fn (TbPegawai $employee): array => [
                'id_peg' => $employee->id_peg,
                'nama' => $employee->nama,
            ])
            ->all();
    }

    private function latestActivePosition(string $idPeg): string
    {
        return (string) (TbJabatan::query()
            ->where('id_peg', $idPeg)
            ->where('status_jab', 'Aktif')
            ->orderByDesc('tmt_jabatan')
            ->orderByDesc('id_jab')
            ->value('jabatan') ?: '-');
    }

    private function storeDocument(Request $request, string $idPeg): ?string
    {
        if (! $request->hasFile('sk_mutasi')) {
            return null;
        }

        $extension = strtolower($request->file('sk_mutasi')->getClientOriginalExtension() ?: 'bin');
        $filename = 'SK_STOP_'.$idPeg.'_'.now()->format('YmdHis').'_'.Str::lower(Str::random(6)).'.'.$extension;
        Storage::disk('public')->putFileAs('sk_mutasi', $request->file('sk_mutasi'), $filename);

        return $filename;
    }

    private function deleteDocument(?string $filename): void
    {
        if ($filename) {
            Storage::disk('public')->delete('sk_mutasi/'.basename($filename));
        }
    }

    private function presentMutation(TbMutasi $item): array
    {
        $hasDocument = $item->sk_mutasi && $this->documentExists($item->sk_mutasi);

        return [
            'id_mutasi' => $item->id_mutasi,
            'id_peg' => $item->id_peg,
            'nama_pegawai' => $item->nama_pegawai ?: 'Tanpa Nama',
            'jabatan' => $item->jabatan,
            'jns_mutasi' => $item->jns_mutasi,
            'tgl_mutasi' => $item->tgl_mutasi?->format('d-m-Y'),
            'no_mutasi' => $item->no_mutasi,
            'tmt' => $item->tmt?->format('d-m-Y'),
            'sk_mutasi' => $item->sk_mutasi,
            'document_url' => $hasDocument ? route('mutasi.document', $item->id_mutasi) : null,
        ];
    }

    private function documentExists(string $filename): bool
    {
        $safeFilename = basename($filename);

        return is_file(Storage::disk('public')->path('sk_mutasi/'.$safeFilename));
    }

    private function ensureMutationVisible(Request $request, TbMutasi $record): void
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
        return in_array(strtolower((string) $request->session()->get('hak_akses')), ['admin', 'it_admin'], true);
    }

    private function ensureCanManage(Request $request): void
    {
        abort_unless($this->canManage($request), 403);
    }

    private function ensureStaffAccess(Request $request): void
    {
        abort_unless(in_array(strtolower((string) $request->session()->get('hak_akses')), ['admin', 'superadmin', 'kepala', 'it_admin'], true), 403);
    }
}

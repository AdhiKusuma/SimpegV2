<?php

namespace App\Http\Controllers;

use App\Models\Legacy\TbAngkat;
use App\Models\Legacy\TbPegawai;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PengangkatanController extends Controller
{
    private const TYPES = ['Kontrak', 'Calon Pegawai', 'Tetap', 'Perubahan NIP'];

    private const HISTORY_TABLES = [
        'tb_anak',
        'tb_hukuman',
        'tb_jabatan',
        'tb_mutasi',
        'tb_ortu',
        'tb_pendidikan',
        'tb_suamiistri',
    ];

    public function index(Request $request): Response
    {
        $this->ensureStaffAccess($request);

        $role = strtolower((string) $request->session()->get('hak_akses'));
        $unit = $role === 'kepala' ? (string) $request->session()->get('kode_kantor') : '';
        $search = $request->string('search')->trim()->value();
        $year = $request->string('tahun')->trim()->value();

        $query = TbAngkat::query()
            ->from('tb_angkat as angkat')
            ->leftJoin('tb_pegawai as pegawai', 'pegawai.id_peg', '=', 'angkat.id_peg_baru')
            ->when($unit !== '', fn (Builder $builder) => $builder->whereExists(function (QueryBuilder $scope) use ($unit): void {
                $scope->selectRaw('1')
                    ->from('tb_jabatan as jabatan_scope')
                    ->whereColumn('jabatan_scope.id_peg', 'angkat.id_peg_baru')
                    ->where('jabatan_scope.status_jab', 'Aktif')
                    ->where('jabatan_scope.unit_kerja', $unit);
            }))
            ->when($year !== '', fn (Builder $builder) => $builder->whereYear('angkat.tgl_mutasi', $year))
            ->when($search !== '', function (Builder $builder) use ($search): void {
                $like = "%{$search}%";
                $builder->where(function (QueryBuilder $scope) use ($like): void {
                    $scope->where('pegawai.nama', 'like', $like)
                        ->orWhere('angkat.id_peg', 'like', $like)
                        ->orWhere('angkat.id_peg_baru', 'like', $like)
                        ->orWhere('angkat.jns_mutasi', 'like', $like)
                        ->orWhere('angkat.no_mutasi', 'like', $like);
                });
            });

        $items = $query
            ->select('angkat.*', 'pegawai.nama as nama_pegawai')
            ->orderByDesc('angkat.tgl_mutasi')
            ->orderByDesc('angkat.id_angkat')
            ->paginate(min(max($request->integer('per_page', 10), 1), 50))
            ->withQueryString()
            ->through(fn (TbAngkat $item): array => $this->present($item));

        return Inertia::render('Pengangkatan/Index', [
            'items' => $items,
            'years' => TbAngkat::query()->whereNotNull('tgl_mutasi')->selectRaw('YEAR(tgl_mutasi) as tahun')->distinct()->orderByDesc('tahun')->pluck('tahun')->values(),
            'filters' => ['search' => $search, 'tahun' => $year],
            'canManage' => $this->canManage($request),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->ensureCanManage($request);

        return Inertia::render('Pengangkatan/Form', [
            'mode' => 'create',
            'record' => null,
            'employees' => $this->employeesForCreate(),
            'types' => self::TYPES,
        ]);
    }

    public function edit(Request $request, int $id): Response
    {
        $this->ensureCanManage($request);
        $record = TbAngkat::query()->findOrFail($id);

        return Inertia::render('Pengangkatan/Form', [
            'mode' => 'edit',
            'record' => [
                'id_angkat' => $record->id_angkat,
                'id_peg' => $record->id_peg,
                'id_peg_baru' => $record->id_peg_baru,
                'jns_mutasi' => $record->jns_mutasi,
                'tgl_mutasi' => $record->tgl_mutasi?->format('Y-m-d'),
                'no_mutasi' => $record->no_mutasi,
                'tmt' => $record->tmt?->format('Y-m-d'),
                'sk_mutasi' => $record->sk_mutasi,
            ],
            'employees' => [],
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
                $oldId = $data['id_peg'];
                $newId = $data['id_peg_baru'];
                $storedFile = $this->storeDocument($request, $newId);

                TbAngkat::query()->create([
                    'id_peg' => $oldId,
                    'jns_mutasi' => $data['jns_mutasi'],
                    'id_peg_baru' => $newId,
                    'tgl_mutasi' => $data['tgl_mutasi'],
                    'tmt' => $data['tmt'],
                    'no_mutasi' => $data['no_mutasi'],
                    'sk_mutasi' => $storedFile,
                    'date_reg' => now(),
                ]);

                TbPegawai::query()->where('id_peg', $oldId)->update(array_filter([
                    'id_peg' => $newId,
                    'status_kepeg' => in_array($data['jns_mutasi'], ['Kontrak', 'Calon Pegawai', 'Tetap'], true) ? $data['jns_mutasi'] : null,
                ], static fn ($value): bool => $value !== null));

                foreach (self::HISTORY_TABLES as $table) {
                    DB::table($table)->where('id_peg', $newId)->update(['id_peg_old' => $oldId]);
                }
            });
        } catch (\Throwable $exception) {
            $this->deleteDocument($storedFile);
            throw $exception;
        }

        return to_route('pengangkatan.index')->with('success', 'Pengangkatan pegawai berhasil disimpan dan ID pegawai diperbarui.');
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $this->ensureCanManage($request);
        $record = TbAngkat::query()->findOrFail($id);
        $data = $request->validate(['tmt' => ['required', 'date']]);
        $record->update(['tmt' => $data['tmt']]);

        return to_route('pengangkatan.index')->with('success', 'TMT pengangkatan berhasil diperbarui.');
    }

    public function document(Request $request, int $id): BinaryFileResponse
    {
        $this->ensureStaffAccess($request);
        $record = TbAngkat::query()->findOrFail($id);
        $filename = basename((string) $record->sk_mutasi);
        abort_if($filename === '' || $filename === '.', 404);

        $paths = [
            Storage::disk('public')->path('sk_angkat/'.$filename),
            base_path('../pages/assets/sk_mutasi/'.$filename),
            base_path('../pages/asset/sk_mutasi/'.$filename),
            base_path('../assets/dokumen/sk_angkat/'.$filename),
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
            'id_peg' => ['required', 'string', 'max:12', Rule::exists('tb_pegawai', 'id_peg')->where(fn ($query) => $query->where('status_kepeg', '<>', 'Tetap'))],
            'jns_mutasi' => ['required', Rule::in(self::TYPES)],
            'id_peg_baru' => ['required', 'string', 'max:12', Rule::unique('tb_pegawai', 'id_peg')],
            'tgl_mutasi' => ['required', 'date'],
            'tmt' => ['required', 'date'],
            'no_mutasi' => ['required', 'string', 'max:50'],
            'sk_mutasi' => ['nullable', 'file', 'mimes:pdf', 'max:10240'],
        ]);
    }

    private function employeesForCreate(): array
    {
        return TbPegawai::query()
            ->where('status_kepeg', '<>', 'Tetap')
            ->orderBy('nama')
            ->get(['id_peg', 'nama', 'status_kepeg'])
            ->map(fn (TbPegawai $employee): array => ['id_peg' => $employee->id_peg, 'nama' => $employee->nama, 'status_kepeg' => $employee->status_kepeg])
            ->all();
    }

    private function present(TbAngkat $item): array
    {
        $hasDocument = $item->sk_mutasi && $this->documentExists($item->sk_mutasi);

        return [
            'id_angkat' => $item->id_angkat,
            'id_peg' => $item->id_peg,
            'id_peg_baru' => $item->id_peg_baru,
            'nama_pegawai' => $item->nama_pegawai ?: 'Tanpa Nama',
            'jns_mutasi' => $item->jns_mutasi,
            'tgl_mutasi' => $item->tgl_mutasi?->format('d-m-Y'),
            'no_mutasi' => $item->no_mutasi,
            'tmt' => $item->tmt?->format('d-m-Y'),
            'sk_mutasi' => $item->sk_mutasi,
            'document_url' => $hasDocument ? route('pengangkatan.document', $item->id_angkat) : null,
        ];
    }

    private function storeDocument(Request $request, string $newId): ?string
    {
        if (! $request->hasFile('sk_mutasi')) {
            return null;
        }

        $filename = 'SK_'.$newId.'_'.now()->format('YmdHis').'_'.Str::lower(Str::random(6)).'.pdf';
        Storage::disk('public')->putFileAs('sk_angkat', $request->file('sk_mutasi'), $filename);

        return $filename;
    }

    private function deleteDocument(?string $filename): void
    {
        if ($filename) {
            Storage::disk('public')->delete('sk_angkat/'.basename($filename));
        }
    }

    private function documentExists(string $filename): bool
    {
        $safeFilename = basename($filename);

        return is_file(Storage::disk('public')->path('sk_angkat/'.$safeFilename))
            || is_file(base_path('../pages/assets/sk_mutasi/'.$safeFilename))
            || is_file(base_path('../pages/asset/sk_mutasi/'.$safeFilename))
            || is_file(base_path('../assets/dokumen/sk_angkat/'.$safeFilename));
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

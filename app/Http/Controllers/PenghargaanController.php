<?php

namespace App\Http\Controllers;

use App\Models\Legacy\TbPegawai;
use App\Models\Legacy\TbPenghargaan;
use App\Models\Legacy\TbRecycleBin;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PenghargaanController extends Controller
{
    public function index(Request $request): Response
    {
        $this->ensureStaffAccess($request);

        $role = strtolower((string) $request->session()->get('hak_akses'));
        $unit = $role === 'kepala' ? (string) $request->session()->get('kode_kantor') : '';
        $search = $request->string('search')->trim()->value();
        $year = $request->string('tahun')->trim()->value();

        $query = TbPenghargaan::query()
            ->from('tb_penghargaan as penghargaan')
            ->leftJoin('tb_pegawai as pegawai', 'pegawai.id_peg', '=', 'penghargaan.id_peg')
            ->when($unit !== '', fn (Builder $builder) => $builder->whereExists(function (QueryBuilder $scope) use ($unit): void {
                $scope->selectRaw('1')
                    ->from('tb_jabatan as jabatan_scope')
                    ->whereColumn('jabatan_scope.id_peg', 'penghargaan.id_peg')
                    ->where('jabatan_scope.status_jab', 'Aktif')
                    ->where('jabatan_scope.unit_kerja', $unit);
            }))
            ->when($year !== '', fn (Builder $builder) => $builder->where('penghargaan.tahun', $year))
            ->when($search !== '', function (Builder $builder) use ($search): void {
                $like = "%{$search}%";
                $builder->where(function (QueryBuilder $scope) use ($like): void {
                    $scope->where('pegawai.nama', 'like', $like)
                        ->orWhere('penghargaan.id_peg', 'like', $like)
                        ->orWhere('penghargaan.penghargaan', 'like', $like)
                        ->orWhere('penghargaan.pemberi', 'like', $like);
                });
            });

        $items = $query
            ->select('penghargaan.*', 'pegawai.nama as nama_pegawai')
            ->orderByDesc('penghargaan.tahun')
            ->orderByDesc('penghargaan.id_penghargaan')
            ->paginate(min(max($request->integer('per_page', 10), 1), 50))
            ->withQueryString()
            ->through(fn (TbPenghargaan $item): array => [
                'id_penghargaan' => $item->id_penghargaan,
                'id_peg' => $item->id_peg,
                'nama_pegawai' => $item->nama_pegawai ?: 'Tanpa Nama',
                'penghargaan' => $item->penghargaan,
                'tahun' => $item->tahun,
                'pemberi' => $item->pemberi,
            ]);

        return Inertia::render('Penghargaan/Index', [
            'items' => $items,
            'years' => TbPenghargaan::query()->whereNotNull('tahun')->where('tahun', '<>', '')->distinct()->orderByDesc('tahun')->pluck('tahun')->values(),
            'filters' => ['search' => $search, 'tahun' => $year],
            'canManage' => $this->canManage($request),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->ensureCanManage($request);

        return Inertia::render('Penghargaan/Form', [
            'mode' => 'create',
            'record' => ['id_peg' => $request->string('uid')->trim()->value()],
            'employees' => $this->employees(),
        ]);
    }

    public function edit(Request $request, string $id): Response
    {
        $this->ensureCanManage($request);
        $record = TbPenghargaan::query()->findOrFail($id);

        return Inertia::render('Penghargaan/Form', [
            'mode' => 'edit',
            'record' => collect($record->getAttributes())->only(['id_penghargaan', 'id_peg', 'penghargaan', 'tahun', 'pemberi'])->all(),
            'employees' => $this->employees(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->ensureCanManage($request);
        $data = $this->validatedData($request, false);
        $data['id_penghargaan'] = $this->nextId();
        $data['created_by'] = $request->session()->get('nama_user', $request->session()->get('id_user'));
        TbPenghargaan::query()->create($data);

        return to_route('penghargaan.index')->with('success', 'Data penghargaan berhasil disimpan.');
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $this->ensureCanManage($request);
        $record = TbPenghargaan::query()->findOrFail($id);
        $data = $this->validatedData($request, true);
        unset($data['id_peg']);
        $data['updated_at'] = now();
        $data['updated_by'] = $request->session()->get('nama_user', $request->session()->get('id_user'));
        $record->update($data);

        return to_route('penghargaan.index')->with('success', 'Data penghargaan berhasil diperbarui.');
    }

    public function destroy(Request $request, string $id): RedirectResponse
    {
        $this->ensureCanManage($request);
        $validated = $request->validate(['reason' => ['required', 'string', 'max:1000']]);

        DB::transaction(function () use ($request, $id, $validated): void {
            $record = TbPenghargaan::query()->lockForUpdate()->findOrFail($id);
            TbRecycleBin::query()->create([
                'tabel_by' => 'tb_penghargaan',
                'data_json' => json_encode($record->getAttributes(), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                'alasan' => $validated['reason'],
                'created_by' => $request->session()->get('nama_user', 'Admin'),
                'created' => now(),
            ]);
            $record->delete();
        });

        return to_route('penghargaan.index')->with('success', 'Data penghargaan dipindahkan ke Recycle Bin.');
    }

    private function validatedData(Request $request, bool $isEdit): array
    {
        $rules = [
            'penghargaan' => ['required', 'string', 'max:64'],
            'tahun' => ['required', 'string', 'max:4'],
            'pemberi' => ['required', 'string', 'max:64'],
        ];

        if (! $isEdit) {
            $rules['id_peg'] = ['required', 'string', 'max:12', Rule::exists('tb_pegawai', 'id_peg')];
        }

        return $request->validate($rules);
    }

    private function employees(): array
    {
        return TbPegawai::query()->orderBy('nama')->get(['id_peg', 'nama'])->map(fn (TbPegawai $employee): array => ['id_peg' => $employee->id_peg, 'nama' => $employee->nama])->all();
    }

    private function nextId(): string
    {
        $last = TbPenghargaan::query()->lockForUpdate()->orderByDesc('id_penghargaan')->value('id_penghargaan');
        $next = max(1, ((int) $last) + 1);

        do {
            $id = str_pad((string) $next, 8, '0', STR_PAD_LEFT);
            $next++;
        } while (TbPenghargaan::query()->where('id_penghargaan', $id)->exists());

        return $id;
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

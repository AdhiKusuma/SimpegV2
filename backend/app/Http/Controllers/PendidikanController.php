<?php

namespace App\Http\Controllers;

use App\Models\Legacy\TbKantor;
use App\Models\Legacy\TbPegawai;
use App\Models\Legacy\TbPendidikan;
use App\Models\Legacy\TbRecycleBin;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PendidikanController extends Controller
{
    private const LEVELS = ['SD', 'SMP', 'SMA', 'D1', 'D2', 'D3', 'D4', 'S1', 'S2', 'S3'];

    private const STATUSES = ['Aktif', 'Non', 'Lulus', 'Belum Lulus'];

    public function index(Request $request): Response
    {
        $this->ensureStaffAccess($request);

        $role = strtolower((string) $request->session()->get('hak_akses'));
        $unit = $role === 'kepala' ? (string) $request->session()->get('kode_kantor') : '';
        $uid = $request->string('uid')->trim()->value();
        $year = $request->string('tahun')->trim()->value();
        $level = $request->string('jenjang')->trim()->value();
        $office = $request->string('kantor')->trim()->value();
        $search = $request->string('search')->trim()->value();

        $query = TbPendidikan::query()
            ->from('tb_pendidikan as edu')
            ->leftJoin('tb_pegawai as p', 'p.id_peg', '=', 'edu.id_peg')
            ->leftJoin('tb_jabatan as j', function ($join): void {
                $join->on('p.id_peg', '=', 'j.id_peg')->where('j.status_jab', 'Aktif');
            })
            ->leftJoin('tb_kantor as k', 'j.unit_kerja', '=', 'k.kode_kantor_detail')
            ->when($uid !== '', fn (Builder $builder) => $builder->where('edu.id_peg', $uid))
            ->when($year !== '', fn (Builder $builder) => $builder->where('edu.th_lulus', $year))
            ->when($level !== '', fn (Builder $builder) => $builder->where('edu.jenjang', $level))
            ->when($office !== '', fn (Builder $builder) => $builder->where('j.unit_kerja', $office))
            ->when($unit !== '', fn (Builder $builder) => $builder->where('j.unit_kerja', $unit))
            ->when($search !== '', function (Builder $builder) use ($search): void {
                $like = "%{$search}%";
                $builder->where(function (Builder $scope) use ($like): void {
                    $scope->where('p.nama', 'like', $like)
                        ->orWhere('edu.id_peg', 'like', $like)
                        ->orWhere('edu.jenjang', 'like', $like)
                        ->orWhere('edu.nama_sekolah', 'like', $like)
                        ->orWhere('edu.jurusan', 'like', $like);
                });
            });

        $items = $query
            ->select('edu.*', 'p.nama as nama_pegawai', 'k.nama_kantor')
            ->orderByDesc('edu.th_lulus')
            ->orderByDesc('edu.id_pendidikan')
            ->paginate(min(max($request->integer('per_page', 10), 1), 50))
            ->withQueryString()
            ->through(fn (TbPendidikan $item): array => [
                'id_pendidikan' => $item->id_pendidikan,
                'id_peg' => $item->id_peg,
                'nama_pegawai' => $item->nama_pegawai,
                'jenjang' => $item->jenjang,
                'nama_sekolah' => $item->nama_sekolah,
                'lokasi' => $item->lokasi,
                'jurusan' => $item->jurusan,
                'no_ijazah' => $item->no_ijazah,
                'tgl_ijazah' => $item->tgl_ijazah?->format('d-m-Y'),
                'th_masuk' => $item->th_masuk,
                'th_lulus' => $item->th_lulus,
                'status' => $item->status,
                'nama_kantor' => $item->nama_kantor,
            ]);

        return Inertia::render('Pendidikan/Index', [
            'items' => $items,
            'years' => $this->years($unit),
            'offices' => $this->offices($unit),
            'filters' => ['uid' => $uid, 'tahun' => $year, 'jenjang' => $level, 'kantor' => $office, 'search' => $search],
            'levels' => self::LEVELS,
            'canManage' => $this->canManage($request),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->ensureCanManage($request);

        return Inertia::render('Pendidikan/Form', [
            'mode' => 'create',
            'record' => ['id_peg' => $request->string('uid')->trim()->value()],
            'employees' => $this->employees(),
            'levels' => self::LEVELS,
            'statuses' => self::STATUSES,
        ]);
    }

    public function edit(Request $request, int $id): Response
    {
        $this->ensureCanManage($request);
        $record = TbPendidikan::query()->findOrFail($id);

        return Inertia::render('Pendidikan/Form', [
            'mode' => 'edit',
            'record' => $this->formRecord($record),
            'employees' => $this->employees(),
            'levels' => self::LEVELS,
            'statuses' => self::STATUSES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->ensureCanManage($request);
        $data = $this->validatedData($request);
        $data['date_reg'] = now()->toDateString();
        $data['created_by'] = $request->session()->get('id_user', $request->session()->get('nama_user'));

        TbPendidikan::query()->create($data);

        return to_route('pendidikan.index')->with('success', 'Data pendidikan berhasil disimpan.');
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $this->ensureCanManage($request);
        $record = TbPendidikan::query()->findOrFail($id);
        $data = $this->validatedData($request);
        unset($data['id_peg']);
        $data['updated_by'] = $request->session()->get('id_user', $request->session()->get('nama_user'));
        $record->update($data);

        return to_route('pendidikan.index')->with('success', 'Data pendidikan berhasil diperbarui.');
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        $this->ensureCanManage($request);
        $validated = $request->validate(['reason' => ['required', 'string', 'max:1000']]);

        DB::transaction(function () use ($request, $id, $validated): void {
            $record = TbPendidikan::query()->lockForUpdate()->findOrFail($id);

            TbRecycleBin::query()->create([
                'tabel_by' => 'tb_pendidikan',
                'data_json' => json_encode($record->getAttributes(), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                'alasan' => $validated['reason'],
                'created_by' => $request->session()->get('nama_user', 'Admin'),
                'created' => now(),
            ]);

            $record->delete();
        });

        return to_route('pendidikan.index')->with('success', 'Data pendidikan dipindahkan ke Recycle Bin.');
    }

    private function validatedData(Request $request): array
    {
        return $request->validate([
            'id_peg' => ['required', 'string', 'max:12', Rule::exists('tb_pegawai', 'id_peg')],
            'id_sekolah' => ['nullable', 'string', 'max:8'],
            'jenjang' => ['required', Rule::in(self::LEVELS)],
            'nama_sekolah' => ['required', 'string', 'max:64'],
            'lokasi' => ['nullable', 'string', 'max:32'],
            'jurusan' => ['nullable', 'string', 'max:32'],
            'no_ijazah' => ['nullable', 'string', 'max:32'],
            'tgl_ijazah' => ['nullable', 'date'],
            'kepala' => ['nullable', 'string', 'max:64'],
            'status' => ['nullable', 'string', 'max:5'],
            'th_masuk' => ['nullable', 'string', 'max:4'],
            'th_lulus' => ['nullable', 'string', 'max:4'],
        ]);
    }

    private function formRecord(TbPendidikan $record): array
    {
        return collect($record->getAttributes())->only([
            'id_pendidikan', 'id_peg', 'id_sekolah', 'jenjang', 'nama_sekolah', 'lokasi', 'jurusan', 'no_ijazah', 'tgl_ijazah', 'kepala', 'status', 'th_masuk', 'th_lulus',
        ])->map(fn ($value) => $value instanceof \DateTimeInterface ? $value->format('Y-m-d') : $value)->all();
    }

    private function employees(): array
    {
        return TbPegawai::query()->orderBy('nama')->get(['id_peg', 'nama'])->map(fn (TbPegawai $employee): array => ['id_peg' => $employee->id_peg, 'nama' => $employee->nama])->all();
    }

    private function years(string $unit): array
    {
        return TbPendidikan::query()->from('tb_pendidikan as edu')->leftJoin('tb_jabatan as j', function ($join): void {
            $join->on('j.id_peg', '=', 'edu.id_peg')->where('j.status_jab', 'Aktif');
        })->when($unit !== '', fn (Builder $builder) => $builder->where('j.unit_kerja', $unit))->whereNotNull('edu.th_lulus')->where('edu.th_lulus', '<>', '')->distinct()->orderByDesc('edu.th_lulus')->pluck('edu.th_lulus')->values()->all();
    }

    private function offices(string $unit): array
    {
        return TbKantor::query()->whereIn('level', ['KC', 'KP'])->when($unit !== '', fn (Builder $builder) => $builder->where('kode_kantor_detail', $unit))->orderBy('nama_kantor')->get(['kode_kantor_detail', 'nama_kantor'])->map(fn (TbKantor $office): array => ['kode' => $office->kode_kantor_detail, 'nama' => $office->nama_kantor])->all();
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

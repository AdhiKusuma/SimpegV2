<?php

namespace App\Http\Controllers;

use App\Models\Legacy\TbKantor;
use App\Models\Legacy\TbPegawai;
use App\Models\Legacy\TbRecycleBin;
use App\Models\Legacy\TbSertifikasi;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SertifikasiController extends Controller
{
    public function index(Request $request): Response
    {
        $this->ensureStaffAccess($request);
        $role = strtolower((string) $request->session()->get('hak_akses'));
        $unit = $role === 'kepala' ? (string) $request->session()->get('kode_kantor') : '';
        $year = $request->has('tahun') ? $request->string('tahun')->trim()->value() : date('Y');
        $type = $request->string('sertifikasi')->trim()->value();
        $office = $request->string('kantor')->trim()->value();
        $search = $request->string('search')->trim()->value();

        $query = TbSertifikasi::query()
            ->from('tb_sertifikasi as s')
            ->join('tb_pegawai as p', 'p.id_peg', '=', 's.id_peg')
            ->leftJoin('tb_jabatan as j', function ($join): void {
                $join->on('p.id_peg', '=', 'j.id_peg')->where('j.status_jab', 'Aktif');
            })
            ->leftJoin('tb_kantor as k', 'j.unit_kerja', '=', 'k.kode_kantor_detail')
            ->when($year !== '', fn (Builder $builder) => $builder->where(function (Builder $scope) use ($year): void {
                $scope->whereYear('s.tgl_sertifikat', (int) $year)->orWhereNull('s.tgl_sertifikat');
            }))
            ->when($type !== '', fn (Builder $builder) => $builder->where('s.sertifikasi', $type))
            ->when($office !== '', fn (Builder $builder) => $builder->where('j.unit_kerja', $office))
            ->when($unit !== '', fn (Builder $builder) => $builder->where('j.unit_kerja', $unit))
            ->when($search !== '', function (Builder $builder) use ($search): void {
                $like = "%{$search}%";
                $builder->where(function (Builder $scope) use ($like): void {
                    $scope->where('p.nama', 'like', $like)->orWhere('s.sertifikasi', 'like', $like)->orWhere('s.penyelenggara', 'like', $like)->orWhere('s.sertifikat', 'like', $like);
                });
            });

        $items = $query->select('s.*', 'p.nama as nama_pegawai', 'k.nama_kantor')->orderByDesc('s.tgl_sertifikat')->orderByDesc('s.id_sertif')->paginate(min(max($request->integer('per_page', 10), 1), 50))->withQueryString()->through(fn (TbSertifikasi $item): array => $this->present($item));

        return Inertia::render('Sertifikasi/Index', [
            'items' => $items,
            'years' => $this->years($unit),
            'types' => $this->types($unit),
            'offices' => $this->offices($unit),
            'filters' => ['tahun' => $year, 'sertifikasi' => $type, 'kantor' => $office, 'search' => $search],
            'canManage' => $this->canManage($request),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->ensureCanManage($request);

        return Inertia::render('Sertifikasi/Form', ['mode' => 'create', 'record' => ['id_peg' => $request->string('uid')->trim()->value()], 'employees' => $this->employees()]);
    }

    public function edit(Request $request, int $id): Response
    {
        $this->ensureCanManage($request);
        $record = TbSertifikasi::query()->findOrFail($id);

        return Inertia::render('Sertifikasi/Form', ['mode' => 'edit', 'record' => $this->formRecord($record), 'employees' => $this->employees()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->ensureCanManage($request);
        $data = $this->validatedData($request);
        $data['date_reg'] = now();
        $data['created_by'] = $request->session()->get('id_user', $request->session()->get('nama_user'));
        TbSertifikasi::query()->create($data);

        return to_route('sertifikasi.index')->with('success', 'Data sertifikasi berhasil disimpan.');
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $this->ensureCanManage($request);
        $record = TbSertifikasi::query()->findOrFail($id);
        $data = $this->validatedData($request);
        unset($data['id_peg']);
        $data['updated_by'] = $request->session()->get('id_user', $request->session()->get('nama_user'));
        $record->update($data);

        return to_route('sertifikasi.index')->with('success', 'Data sertifikasi berhasil diperbarui.');
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        $this->ensureCanManage($request);
        $validated = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        DB::transaction(function () use ($request, $id, $validated): void {
            $record = TbSertifikasi::query()->lockForUpdate()->findOrFail($id);
            TbRecycleBin::query()->create(['tabel_by' => 'tb_sertifikasi', 'data_json' => json_encode($record->getAttributes(), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), 'alasan' => $validated['reason'], 'created_by' => $request->session()->get('nama_user', 'Admin'), 'created' => now()]);
            $record->delete();
        });

        return to_route('sertifikasi.index')->with('success', 'Data sertifikasi dipindahkan ke Recycle Bin.');
    }

    private function validatedData(Request $request): array
    {
        return $request->validate([
            'id_peg' => ['required', 'string', 'max:12', Rule::exists('tb_pegawai', 'id_peg')],
            'sertifikasi' => ['required', 'string', 'max:255'],
            'penyelenggara' => ['nullable', 'string', 'max:255'],
            'sertifikat' => ['nullable', 'string', 'max:255'],
            'tgl_sertifikat' => ['nullable', 'date'],
            'tgl_expired' => ['nullable', 'date'],
        ]);
    }

    private function present(TbSertifikasi $item): array
    {
        return ['id_sertif' => $item->id_sertif, 'id_peg' => $item->id_peg, 'nama_pegawai' => $item->nama_pegawai, 'sertifikasi' => $item->sertifikasi, 'sertifikat' => $item->sertifikat, 'penyelenggara' => $item->penyelenggara, 'tgl_sertifikat' => $item->tgl_sertifikat?->format('d-m-Y'), 'tgl_expired' => $item->tgl_expired?->format('d-m-Y'), 'nama_kantor' => $item->nama_kantor, 'status' => $item->tgl_expired === null ? 'Seumur Hidup' : ($item->tgl_expired->isBefore(today()) ? 'Expired' : 'Aktif')];
    }

    private function formRecord(TbSertifikasi $record): array
    {
        return collect($record->getAttributes())->only(['id_sertif', 'id_peg', 'sertifikasi', 'penyelenggara', 'sertifikat', 'tgl_sertifikat', 'tgl_expired'])->map(fn ($value) => $value instanceof \DateTimeInterface ? $value->format('Y-m-d') : $value)->all();
    }

    private function employees(): array
    {
        return TbPegawai::query()->where('status_aktif', 1)->orderBy('nama')->get(['id_peg', 'nama'])->map(fn (TbPegawai $employee): array => ['id_peg' => $employee->id_peg, 'nama' => $employee->nama])->all();
    }

    private function years(string $unit): array
    {
        return TbSertifikasi::query()->from('tb_sertifikasi as s')->leftJoin('tb_jabatan as j', function ($join): void {
            $join->on('j.id_peg', '=', 's.id_peg')->where('j.status_jab', 'Aktif');
        })->when($unit !== '', fn (Builder $builder) => $builder->where('j.unit_kerja', $unit))->whereNotNull('s.tgl_sertifikat')->selectRaw('YEAR(s.tgl_sertifikat) as year')->distinct()->orderByDesc('year')->pluck('year')->values()->all();
    }

    private function types(string $unit): array
    {
        return TbSertifikasi::query()->from('tb_sertifikasi as s')->leftJoin('tb_jabatan as j', function ($join): void {
            $join->on('j.id_peg', '=', 's.id_peg')->where('j.status_jab', 'Aktif');
        })->when($unit !== '', fn (Builder $builder) => $builder->where('j.unit_kerja', $unit))->whereNotNull('s.sertifikasi')->where('s.sertifikasi', '<>', '')->distinct()->orderBy('s.sertifikasi')->pluck('s.sertifikasi')->values()->all();
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

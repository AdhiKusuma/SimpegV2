<?php

namespace App\Http\Controllers;

use App\Models\Legacy\TbKantor;
use App\Models\Legacy\TbRefJabatan;
use App\Models\QuotaJabatan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class QuotaJabatanController extends Controller
{
    public function index(Request $request): Response
    {
        $this->ensureAdmin($request);

        $branchOptions = $this->branchOptions();
        $positionOptions = $this->positionOptions();
        $branchNames = collect($branchOptions)->keyBy('kode_cabang');
        $positionNames = collect($positionOptions)->keyBy(fn (array $position): string => (string) $position['kode_jabatan']);

        $storedQuotas = Schema::hasTable('kuota_jabatan')
            ? QuotaJabatan::query()
                ->orderBy('kode_cabang')
                ->orderBy('jabatan_id')
                ->get()
            : collect();

        $overrides = $storedQuotas->keyBy(fn (QuotaJabatan $quota): string => $this->quotaKey(
            (string) $quota->kode_cabang,
            (string) $quota->jabatan_id,
        ));
        $quotas = collect();

        // The dashboard also uses master quotas for occupied positions without a branch override.
        // Include those effective values here so admins can edit or remove them from this page.
        if (Schema::hasTable('tb_jabatan') && Schema::hasTable('tb_pegawai') && Schema::hasTable('tb_kantor')) {
            $defaults = DB::table('tb_jabatan as j')
                ->join('tb_pegawai as p', 'p.id_peg', '=', 'j.id_peg')
                ->join('tb_kantor as k', 'k.kode_kantor_detail', '=', 'j.unit_kerja')
                ->join('tb_ref_jabatan as m', 'm.kode_jabatan', '=', 'j.kode_jabatan')
                ->where('j.status_jab', 'Aktif')
                ->where('p.status_aktif', 1)
                ->whereIn('m.group', ['PE', 'PS'])
                ->select(['k.kode_cabang', 'm.kode_jabatan'])
                ->selectRaw('m.jabatan as nama_jabatan, m.`group` as group_jabatan, m.lingkup')
                ->selectRaw('MAX(m.kuota) as kuota_bawaan')
                ->groupBy('k.kode_cabang', 'm.kode_jabatan', 'm.jabatan', 'm.`group`', 'm.lingkup')
                ->get();

            foreach ($defaults as $default) {
                $branchCode = (string) $default->kode_cabang;
                $positionId = (string) $default->kode_jabatan;
                $key = $this->quotaKey($branchCode, $positionId);
                $override = $overrides->get($key);
                $position = $positionNames->get($positionId);

                // A zero override means this branch has removed the available quota.
                if ($override && (int) $override->kuota_pegawai === 0) {
                    continue;
                }

                $branch = $branchNames->get($branchCode);
                $quotas->push([
                    'id' => $override?->id,
                    'row_key' => $key,
                    'source' => $override ? 'cabang' : 'master',
                    'kode_cabang' => $branchCode,
                    'nama_cabang' => $branch['nama_cabang'] ?? $branchCode,
                    'jabatan_id' => (int) $positionId,
                    'nama_jabatan' => (string) $default->nama_jabatan,
                    'lingkup' => (string) ($default->lingkup ?: '-'),
                    'group_jabatan' => (string) ($default->group_jabatan ?: '-'),
                    'kuota_pegawai' => $override ? (int) $override->kuota_pegawai : max((int) $default->kuota_bawaan, 1),
                    'updated_at' => $override?->updated_at?->format('d-m-Y H:i'),
                ]);
            }
        }

        foreach ($storedQuotas as $quota) {
            $key = $this->quotaKey((string) $quota->kode_cabang, (string) $quota->jabatan_id);
            if ($quotas->contains('row_key', $key) || (int) $quota->kuota_pegawai === 0) {
                continue;
            }

            $branch = $branchNames->get((string) $quota->kode_cabang);
            $position = $positionNames->get((string) $quota->jabatan_id);
            $quotas->push([
                'id' => $quota->id,
                'row_key' => $key,
                'source' => 'cabang',
                'kode_cabang' => (string) $quota->kode_cabang,
                'nama_cabang' => $branch['nama_cabang'] ?? (string) $quota->kode_cabang,
                'jabatan_id' => (int) $quota->jabatan_id,
                'nama_jabatan' => $position['nama_jabatan'] ?? 'Jabatan tidak ditemukan',
                'lingkup' => $position['lingkup'] ?? '-',
                'group_jabatan' => $position['group_jabatan'] ?? null,
                'kuota_pegawai' => (int) $quota->kuota_pegawai,
                'updated_at' => $quota->updated_at?->format('d-m-Y H:i'),
            ]);
        }

        $quotas = $quotas->sortBy(['kode_cabang', 'jabatan_id'])->values()->all();

        return Inertia::render('Config/QuotaJabatan', [
            'branchOptions' => $branchOptions,
            'positionOptions' => $positionOptions,
            'quotas' => $quotas,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->ensureAdmin($request);

        $validated = $request->validate([
            'kode_cabang' => ['required', 'string', 'max:20', 'exists:tb_kantor,kode_cabang'],
            'jabatan_id' => ['required', 'integer', 'exists:tb_ref_jabatan,kode_jabatan'],
            'kuota_pegawai' => ['required', 'integer', 'min:0', 'max:100000'],
        ]);

        QuotaJabatan::query()->updateOrCreate(
            [
                'kode_cabang' => $validated['kode_cabang'],
                'jabatan_id' => (int) $validated['jabatan_id'],
            ],
            [
                'kuota_pegawai' => (int) $validated['kuota_pegawai'],
            ],
        );

        $message = (int) $validated['kuota_pegawai'] === 0
            ? 'Kuota cabang disetel menjadi 0. Data jabatan master tetap tersimpan.'
            : 'Kuota jabatan berhasil disimpan.';

        return to_route('config.quota.index')->with('success', $message);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $this->ensureAdmin($request);

        $quota = QuotaJabatan::query()->findOrFail($id);
        $validated = $request->validate([
            'kode_cabang' => ['required', 'string', 'max:20', 'exists:tb_kantor,kode_cabang'],
            'jabatan_id' => [
                'required',
                'integer',
                'exists:tb_ref_jabatan,kode_jabatan',
                Rule::unique('kuota_jabatan', 'jabatan_id')
                    ->where('kode_cabang', $request->input('kode_cabang'))
                    ->ignore($quota->getKey()),
            ],
            'kuota_pegawai' => ['required', 'integer', 'min:0', 'max:100000'],
        ]);

        $quota->update([
            'kode_cabang' => $validated['kode_cabang'],
            'jabatan_id' => (int) $validated['jabatan_id'],
            'kuota_pegawai' => (int) $validated['kuota_pegawai'],
        ]);

        return to_route('config.quota.index')->with('success', 'Kuota jabatan berhasil diperbarui.');
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        $this->ensureAdmin($request);

        QuotaJabatan::query()->findOrFail($id)->update(['kuota_pegawai' => 0]);

        return to_route('config.quota.index')->with('success', 'Kuota cabang dihapus dari daftar aktif dan kapasitasnya disetel menjadi 0.');
    }

    private function branchOptions(): array
    {
        return TbKantor::query()
            ->whereNotNull('kode_cabang')
            ->where('kode_cabang', '<>', '')
            ->select('kode_cabang')
            ->selectRaw('MIN(nama_kantor) as nama_kantor')
            ->groupBy('kode_cabang')
            ->orderBy('kode_cabang')
            ->get()
            ->map(fn ($branch): array => [
                'kode_cabang' => (string) $branch->kode_cabang,
                'nama_cabang' => trim((string) ($branch->nama_kantor ?: 'Cabang '.$branch->kode_cabang)),
                'label' => sprintf('%s - %s', $branch->kode_cabang, trim((string) ($branch->nama_kantor ?: 'Cabang'))),
            ])
            ->values()
            ->all();
    }

    private function positionOptions(): array
    {
        return TbRefJabatan::query()
            ->selectRaw('kode_jabatan, jabatan as nama_jabatan, `group` as group_jabatan, lingkup')
            ->whereIn('group', ['PE', 'PS'])
            ->orderBy('group')
            ->orderBy('jabatan')
            ->get()
            ->map(fn (TbRefJabatan $position): array => [
                'kode_jabatan' => (string) $position->kode_jabatan,
                'nama_jabatan' => (string) $position->nama_jabatan,
                'group_jabatan' => (string) ($position->group_jabatan ?: '-'),
                'lingkup' => (string) ($position->lingkup ?: '-'),
            ])
            ->values()
            ->all();
    }

    private function ensureAdmin(Request $request): void
    {
        abort_unless(strtolower((string) $request->session()->get('hak_akses')) === 'admin', 403);
    }

    private function quotaKey(string $branchCode, string $positionId): string
    {
        return $branchCode.':'.$positionId;
    }
}

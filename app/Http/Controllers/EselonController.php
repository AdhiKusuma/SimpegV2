<?php

namespace App\Http\Controllers;

use App\Models\Legacy\TbMasteresl;
use App\Models\Legacy\TbRecycleBin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class EselonController extends Controller
{
    public function index(Request $request): Response
    {
        $this->ensureStaffAccess($request);

        return Inertia::render('Eselon/Index', [
            'items' => TbMasteresl::query()->orderByDesc('nama_masteresl')->get(['id_masteresl', 'nama_masteresl']),
            'record' => null,
            'canManage' => $this->canManage($request),
        ]);
    }

    public function edit(Request $request, string $id): Response
    {
        $this->ensureCanManage($request);

        return Inertia::render('Eselon/Index', [
            'items' => TbMasteresl::query()->orderByDesc('nama_masteresl')->get(['id_masteresl', 'nama_masteresl']),
            'record' => TbMasteresl::query()->findOrFail($id),
            'canManage' => true,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->ensureCanManage($request);
        $data = $request->validate(['nama_masteresl' => ['required', 'string', 'max:6', Rule::unique('tb_masteresl', 'nama_masteresl')]]);

        DB::transaction(function () use ($data): void {
            TbMasteresl::query()->create([
                'id_masteresl' => $this->nextId(),
                'nama_masteresl' => $data['nama_masteresl'],
            ]);
        });

        return to_route('eselon.index')->with('success', 'Master eselon berhasil disimpan.');
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $this->ensureCanManage($request);
        $record = TbMasteresl::query()->findOrFail($id);
        $data = $request->validate(['nama_masteresl' => ['required', 'string', 'max:6', Rule::unique('tb_masteresl', 'nama_masteresl')->ignore($record->id_masteresl, 'id_masteresl')]]);
        $record->update($data);

        return to_route('eselon.index')->with('success', 'Master eselon berhasil diperbarui.');
    }

    public function destroy(Request $request, string $id): RedirectResponse
    {
        $this->ensureCanManage($request);
        $validated = $request->validate(['reason' => ['required', 'string', 'max:1000']]);

        DB::transaction(function () use ($request, $id, $validated): void {
            $record = TbMasteresl::query()->lockForUpdate()->findOrFail($id);
            TbRecycleBin::query()->create([
                'tabel_by' => 'tb_masteresl',
                'data_json' => json_encode($record->getAttributes(), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                'alasan' => $validated['reason'],
                'created_by' => $request->session()->get('nama_user', 'Admin'),
                'created' => now(),
            ]);
            $record->delete();
        });

        return to_route('eselon.index')->with('success', 'Data eselon dipindahkan ke Recycle Bin.');
    }

    private function nextId(): string
    {
        $last = TbMasteresl::query()->lockForUpdate()->orderByDesc('id_masteresl')->value('id_masteresl');
        $next = max(1, ((int) $last) + 1);

        do {
            $id = str_pad((string) $next, 4, '0', STR_PAD_LEFT);
            $next++;
        } while (TbMasteresl::query()->where('id_masteresl', $id)->exists());

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

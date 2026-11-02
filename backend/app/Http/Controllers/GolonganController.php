<?php

namespace App\Http\Controllers;

use App\Models\Legacy\TbMastergol;
use App\Models\Legacy\TbRecycleBin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class GolonganController extends Controller
{
    public function index(Request $request): Response
    {
        $this->ensureStaffAccess($request);

        return Inertia::render('Golongan/Index', [
            'items' => TbMastergol::query()->orderByDesc('nama_mastergol')->get(['id_mastergol', 'nama_mastergol']),
            'record' => null,
            'canManage' => $this->canManage($request),
        ]);
    }

    public function edit(Request $request, string $id): Response
    {
        $this->ensureCanManage($request);

        return Inertia::render('Golongan/Index', [
            'items' => TbMastergol::query()->orderByDesc('nama_mastergol')->get(['id_mastergol', 'nama_mastergol']),
            'record' => TbMastergol::query()->findOrFail($id),
            'canManage' => true,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->ensureCanManage($request);
        $data = $request->validate(['nama_mastergol' => ['required', 'string', 'max:6', Rule::unique('tb_mastergol', 'nama_mastergol')]]);

        DB::transaction(function () use ($data): void {
            TbMastergol::query()->create(['id_mastergol' => $this->nextId(), 'nama_mastergol' => $data['nama_mastergol']]);
        });

        return to_route('golongan.index')->with('success', 'Master golongan berhasil disimpan.');
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $this->ensureCanManage($request);
        $record = TbMastergol::query()->findOrFail($id);
        $data = $request->validate(['nama_mastergol' => ['required', 'string', 'max:6', Rule::unique('tb_mastergol', 'nama_mastergol')->ignore($record->id_mastergol, 'id_mastergol')]]);
        $record->update($data);

        return to_route('golongan.index')->with('success', 'Master golongan berhasil diperbarui.');
    }

    public function destroy(Request $request, string $id): RedirectResponse
    {
        $this->ensureCanManage($request);
        $validated = $request->validate(['reason' => ['required', 'string', 'max:1000']]);

        DB::transaction(function () use ($request, $id, $validated): void {
            $record = TbMastergol::query()->lockForUpdate()->findOrFail($id);
            TbRecycleBin::query()->create([
                'tabel_by' => 'tb_mastergol',
                'data_json' => json_encode($record->getAttributes(), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                'alasan' => $validated['reason'],
                'created_by' => $request->session()->get('nama_user', 'Admin'),
                'created' => now(),
            ]);
            $record->delete();
        });

        return to_route('golongan.index')->with('success', 'Master golongan dipindahkan ke Recycle Bin.');
    }

    private function nextId(): string
    {
        $last = TbMastergol::query()->lockForUpdate()->orderByDesc('id_mastergol')->value('id_mastergol');
        $next = max(1, ((int) $last) + 1);

        do {
            $id = str_pad((string) $next, 4, '0', STR_PAD_LEFT);
            $next++;
        } while (TbMastergol::query()->where('id_mastergol', $id)->exists());

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

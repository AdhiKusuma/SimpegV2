<?php

namespace App\Services;

use App\Models\Legacy\TbEditPending;
use App\Models\Legacy\TbJabatan;
use App\Models\Legacy\TbNotifikasi;
use App\Models\Legacy\TbPegawai;
use App\Models\Legacy\TbUser;
use App\Models\PayrollChangeRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class NotificationService
{
    private const FAMILY_LABELS = [
        'keluarga_anak_create' => 'tambah data anak',
        'keluarga_anak_update' => 'perubahan data anak',
        'keluarga_anak_delete' => 'penghapusan data anak',
        'keluarga_ortu_create' => 'tambah data orang tua',
        'keluarga_ortu_update' => 'perubahan data orang tua',
        'keluarga_ortu_delete' => 'penghapusan data orang tua',
        'keluarga_pasangan_create' => 'tambah data pasangan',
        'keluarga_pasangan_update' => 'perubahan data pasangan',
        'keluarga_pasangan_delete' => 'penghapusan data pasangan',
    ];

    public function summary(string $userId): array
    {
        if ($userId === '' || ! Schema::hasTable('tb_notifikasi')) {
            return ['unread_count' => 0, 'latest' => []];
        }

        $query = TbNotifikasi::query()->where('id_user', $userId);

        return [
            'unread_count' => (clone $query)->where('status_baca', 'unread')->count(),
            'latest' => (clone $query)
                ->orderByDesc('waktu_notif')
                ->limit(5)
                ->get()
                ->map(fn (TbNotifikasi $notification): array => $this->present($notification))
                ->all(),
        ];
    }

    public function markAllRead(string $userId): void
    {
        if ($userId === '' || ! Schema::hasTable('tb_notifikasi')) {
            return;
        }

        DB::table('tb_notifikasi')
            ->where('id_user', $userId)
            ->where('status_baca', 'unread')
            ->update(['status_baca' => 'read']);
    }

    public function present(TbNotifikasi $notification): array
    {
        return [
            'id_notif' => $notification->id_notif,
            'judul' => $notification->judul,
            'pesan' => $notification->pesan,
            'waktu' => $notification->waktu_notif?->format('d-m-Y H:i'),
            'status_baca' => $notification->status_baca,
            'action_url' => $this->actionUrl($notification->link_aksi),
        ];
    }

    public function notifyPendingCreated(TbEditPending $pending): void
    {
        if (! Schema::hasTable('tb_notifikasi')) {
            return;
        }

        $recipients = $this->approverIds($pending);
        if ($recipients === []) {
            return;
        }

        $requester = TbUser::query()
            ->where('id_user', $pending->id_user)
            ->value('nama_user') ?: (string) $pending->id_user;
        $employee = TbPegawai::query()
            ->where('id_peg', $pending->id_peg)
            ->value('nama') ?: (string) $pending->id_peg;
        $label = self::FAMILY_LABELS[$pending->jenis_data] ?? ucwords(str_replace('_', ' ', (string) $pending->jenis_data));
        $now = now();

        DB::table('tb_notifikasi')->insert(array_map(
            fn (string $recipient): array => [
                'id_user' => $recipient,
                'judul' => 'Pengajuan menunggu persetujuan',
                'pesan' => "{$requester} mengajukan {$label} untuk {$employee}.",
                'link_aksi' => route('authorizations.show', ['id' => $pending->id_edit]),
                'status_baca' => 'unread',
                'waktu_notif' => $now,
            ],
            $recipients,
        ));
    }

    public function notifyPendingResult(TbEditPending $pending, string $status): void
    {
        if ($pending->id_user === null || ! Schema::hasTable('tb_notifikasi')) {
            return;
        }

        $notificationLink = str_starts_with((string) $pending->jenis_data, 'keluarga_')
            ? route('profile')
            : route('authorizations.show', ['id' => $pending->id_edit]);

        DB::table('tb_notifikasi')->insert([
            'id_user' => $pending->id_user,
            'judul' => 'Status Permintaan Edit',
            'pesan' => "Permintaan perubahan data pegawai telah {$status}.",
            'link_aksi' => $notificationLink,
            'status_baca' => 'unread',
            'waktu_notif' => now(),
        ]);
    }

    public function notifyPayrollRequestCreated(PayrollChangeRequest $changeRequest): void
    {
        if (! Schema::hasTable('tb_notifikasi')) {
            return;
        }

        $officeCodes = array_values(array_filter(array_map('strval', $changeRequest->office_codes ?? [])));
        $recipients = $this->payrollApproverIds($officeCodes, (string) $changeRequest->requested_by);
        if ($recipients === []) {
            return;
        }

        $requester = TbUser::query()
            ->where('id_user', $changeRequest->requested_by)
            ->value('nama_user') ?: (string) $changeRequest->requested_by;
        $entryCount = $changeRequest->entries()->count();
        $description = match ($changeRequest->change_type) {
            'import' => 'import payroll untuk '.number_format($entryCount, 0, ',', '.').' pegawai periode '.sprintf('%02d-%04d', $changeRequest->bulan, $changeRequest->tahun),
            'manual_create' => 'penambahan Master Gaji untuk 1 pegawai',
            'manual_delete' => 'penghapusan Master Gaji untuk 1 pegawai',
            default => 'perubahan Master Gaji',
        };

        DB::table('tb_notifikasi')->insert(array_map(
            fn (string $recipient): array => [
                'id_user' => $recipient,
                'judul' => 'Pengajuan payroll menunggu persetujuan',
                'pesan' => "{$requester} mengajukan {$description}.",
                'link_aksi' => route('payroll.approvals.show', ['id' => $changeRequest->id]),
                'status_baca' => 'unread',
                'waktu_notif' => now(),
            ],
            $recipients,
        ));
    }

    public function notifyPayrollRequestResult(PayrollChangeRequest $changeRequest): void
    {
        if ($changeRequest->requested_by === '' || ! Schema::hasTable('tb_notifikasi')) {
            return;
        }

        $status = $changeRequest->status === 'approved' ? 'disetujui' : 'ditolak';
        $detail = $changeRequest->decision_reason ? ' Catatan: '.$changeRequest->decision_reason : '';
        DB::table('tb_notifikasi')->insert([
            'id_user' => $changeRequest->requested_by,
            'judul' => 'Status pengajuan payroll',
            'pesan' => "Pengajuan payroll Anda telah {$status}.{$detail}",
            'link_aksi' => route('payroll.index', array_filter(['tahun' => $changeRequest->tahun, 'bulan' => $changeRequest->bulan])),
            'status_baca' => 'unread',
            'waktu_notif' => now(),
        ]);
    }

    private function approverIds(TbEditPending $pending): array
    {
        $office = TbJabatan::query()
            ->where('id_peg', $pending->id_peg)
            ->where('status_jab', 'Aktif')
            ->orderByDesc('tmt_jabatan')
            ->value('unit_kerja');

        $admins = TbUser::query()
            ->where('status_aktif', 'Y')
            ->whereRaw('LOWER(hak_akses) = ?', ['admin'])
            ->pluck('id_user')
            ->all();

        $heads = [];
        if ($office !== null && trim((string) $office) !== '') {
            $headEmployeeIds = TbJabatan::query()
                ->where('status_jab', 'Aktif')
                ->where('unit_kerja', $office)
                ->pluck('id_peg');

            $heads = TbUser::query()
                ->where('status_aktif', 'Y')
                ->whereRaw('LOWER(hak_akses) = ?', ['kepala'])
                ->whereIn('id_pegawai', $headEmployeeIds)
                ->pluck('id_user')
                ->all();
        }

        $requesterId = (string) $pending->id_user;

        return array_values(array_unique(array_filter(
            array_map(fn (mixed $id): string => (string) $id, [...$admins, ...$heads]),
            fn (string $id): bool => $id !== '' && $id !== $requesterId,
        )));
    }

    private function payrollApproverIds(array $officeCodes, string $requesterId): array
    {
        if ($officeCodes === []) {
            return [];
        }

        $offices = DB::table('tb_kantor')
            ->whereIn('kode_kantor_detail', $officeCodes)
            ->get(['kode_kantor_detail', 'kode_cabang', 'level']);
        $detailCodes = $offices->pluck('kode_kantor_detail')->map(fn ($code): string => (string) $code)->all();
        $branchCodes = $offices
            ->filter(fn ($office): bool => trim((string) $office->kode_cabang) !== '')
            ->pluck('kode_cabang')
            ->map(fn ($code): string => (string) $code)
            ->unique()
            ->values()
            ->all();

        if ($detailCodes === [] && $branchCodes === []) {
            return [];
        }

        $heads = DB::table('tb_jabatan as j')
            ->join('tb_user as u', 'u.id_pegawai', '=', 'j.id_peg')
            ->where('j.status_jab', 'Aktif')
            ->whereRaw('LOWER(u.hak_akses) = ?', ['kepala'])
            ->where('u.status_aktif', 'Y')
            ->whereIn('j.unit_kerja', $detailCodes)
            ->pluck('u.id_user')
            ->map(fn ($id): string => (string) $id)
            ->all();

        if ($branchCodes !== []) {
            $branchHeads = DB::table('tb_jabatan as j')
                ->join('tb_user as u', 'u.id_pegawai', '=', 'j.id_peg')
                ->join('tb_kantor as k', 'k.kode_kantor_detail', '=', 'j.unit_kerja')
                ->where('j.status_jab', 'Aktif')
                ->whereRaw('LOWER(u.hak_akses) = ?', ['kepala'])
                ->where('u.status_aktif', 'Y')
                ->whereIn('k.kode_cabang', $branchCodes)
                ->pluck('u.id_user')
                ->map(fn ($id): string => (string) $id)
                ->all();
            $heads = [...$heads, ...$branchHeads];
        }

        return array_values(array_unique(array_filter(
            $heads,
            fn (string $id): bool => $id !== '' && $id !== $requesterId,
        )));
    }

    private function actionUrl(?string $legacyLink): ?string
    {
        if (! $legacyLink) {
            return null;
        }

        $parts = parse_url($legacyLink);
        $query = [];
        parse_str((string) ($parts['query'] ?? ''), $query);

        if (($query['page'] ?? null) === 'otorisasi-detail' && isset($query['id_edit'])) {
            return route('authorizations.show', ['id' => (int) $query['id_edit']]);
        }

        if (($query['page'] ?? null) === 'notifikasi-user') {
            return route('notifications.index');
        }

        return str_starts_with($legacyLink, '/') ? $legacyLink : null;
    }
}

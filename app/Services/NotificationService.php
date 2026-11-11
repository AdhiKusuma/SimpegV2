<?php

namespace App\Services;

use App\Models\DataDiriDocument;
use App\Models\Legacy\TbEditPending;
use App\Models\Legacy\TbJabatan;
use App\Models\Legacy\TbNotifikasi;
use App\Models\Legacy\TbPegawai;
use App\Models\Legacy\TbUser;
use App\Models\PayrollChangeRequest;
use App\Models\PayrollImport;
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

    public function markReadForUser(int|string $notificationId, string $userId): bool
    {
        if ($userId === '' || ! Schema::hasTable('tb_notifikasi')) {
            return false;
        }

        return DB::table('tb_notifikasi')
            ->where('id_notif', $notificationId)
            ->where('id_user', $userId)
            ->where('status_baca', 'unread')
            ->update(['status_baca' => 'read']) > 0;
    }

    public function deleteReadForUser(string $userId, int $batchSize = 500): int
    {
        if ($userId === '' || ! Schema::hasTable('tb_notifikasi')) {
            return 0;
        }

        return $this->deleteInBatches(
            fn () => DB::table('tb_notifikasi')
                ->where('id_user', $userId)
                ->where('status_baca', 'read'),
            $batchSize,
        );
    }

    public function pruneRead(int $days = 90, int $batchSize = 500): int
    {
        if (! Schema::hasTable('tb_notifikasi')) {
            return 0;
        }

        $cutoff = now()->subDays(max($days, 1));

        return $this->deleteInBatches(
            fn () => DB::table('tb_notifikasi')
                ->where('status_baca', 'read')
                ->where('waktu_notif', '<', $cutoff),
            $batchSize,
        );
    }

    private function deleteInBatches(callable $queryFactory, int $batchSize): int
    {
        $batchSize = max($batchSize, 1);
        $deleted = 0;

        do {
            $ids = $queryFactory()
                ->orderBy('id_notif')
                ->limit($batchSize)
                ->pluck('id_notif')
                ->all();

            if ($ids === []) {
                break;
            }

            $deleted += DB::table('tb_notifikasi')
                ->whereIn('id_notif', $ids)
                ->delete();
        } while (count($ids) === $batchSize);

        return $deleted;
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
            'read_url' => route('notifications.open', ['notification' => $notification->id_notif]),
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

    /**
     * Notify each account linked to an employee in a published payroll batch.
     * The current import is the publication boundary: pending approvals never
     * create payroll_entries and therefore cannot notify this method.
     */
    public function payrollRecipients(PayrollImport $payrollImport): array
    {
        if (! $payrollImport->is_current || ! Schema::hasTable('tb_notifikasi')) {
            return [];
        }

        $employeeIds = DB::table('payroll_entries')
            ->where('payroll_import_id', $payrollImport->id)
            ->pluck('employee_id')
            ->map(fn ($id): string => (string) $id)
            ->unique()
            ->values();
        if ($employeeIds->isEmpty()) {
            return [];
        }

        return TbUser::query()
            ->whereIn('id_pegawai', $employeeIds->all())
            ->whereNotNull('id_user')
            ->pluck('id_user')
            ->map(fn ($id): string => (string) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Compare payroll employees with local accounts and, for published batches,
     * with the actual payroll notification log.
     */
    public function payrollRecipientStatus(PayrollImport $payrollImport, bool $checkDelivery = true): array
    {
        if (! Schema::hasTable('payroll_entries')) {
            return [
                'payroll_count' => 0,
                'account_count' => 0,
                'notified_count' => 0,
                'missing_count' => 0,
                'missing_recipients' => [],
            ];
        }

        $employees = DB::table('payroll_entries as e')
            ->leftJoin('tb_pegawai as p', 'p.id_peg', '=', 'e.employee_id')
            ->leftJoin('tb_user as u', 'u.id_pegawai', '=', 'e.employee_id')
            ->where('e.payroll_import_id', $payrollImport->id)
            ->select([
                'e.employee_id',
                DB::raw('MAX(p.nama) as employee_name'),
                DB::raw('MAX(u.id_user) as user_id'),
            ])
            ->groupBy('e.employee_id')
            ->orderBy('employee_name')
            ->get();

        $notifiedUserIds = [];
        if ($checkDelivery && Schema::hasTable('tb_notifikasi')) {
            $link = route('files.index', ['tahun' => $payrollImport->tahun, 'bulan' => $payrollImport->bulan]);
            $notifiedUserIds = DB::table('tb_notifikasi')
                ->where('judul', 'Slip gaji tersedia')
                ->where('link_aksi', $link)
                ->pluck('id_user')
                ->map(fn ($id): string => (string) $id)
                ->unique()
                ->flip()
                ->all();
        }

        $accountCount = $employees->filter(fn (object $employee): bool => trim((string) ($employee->user_id ?? '')) !== '')->count();
        $missing = $employees
            ->filter(function (object $employee) use ($checkDelivery, $notifiedUserIds): bool {
                $userId = trim((string) ($employee->user_id ?? ''));
                if ($userId === '') {
                    return true;
                }

                return $checkDelivery && ! isset($notifiedUserIds[$userId]);
            })
            ->map(function (object $employee) use ($checkDelivery): array {
                $hasAccount = trim((string) ($employee->user_id ?? '')) !== '';

                return [
                    'employee_id' => (string) $employee->employee_id,
                    'employee_name' => trim((string) ($employee->employee_name ?? '')) ?: 'Nama pegawai tidak ditemukan',
                    'reason' => $hasAccount && $checkDelivery
                        ? 'Notifikasi belum tercatat pada batch ini.'
                        : 'Akun lokal belum terhubung. Pegawai perlu login SSO satu kali.',
                ];
            })
            ->values()
            ->all();

        return [
            'payroll_count' => $employees->count(),
            'account_count' => $accountCount,
            'notified_count' => $checkDelivery ? $employees->count() - count($missing) : 0,
            'missing_count' => count($missing),
            'missing_recipients' => $missing,
        ];
    }

    public function notifyPublishedPayroll(PayrollImport $payrollImport): int
    {
        if (! $payrollImport->is_current || ! Schema::hasTable('tb_notifikasi')) {
            return 0;
        }

        $recipients = collect($this->payrollRecipients($payrollImport));
        if ($recipients->isEmpty()) {
            return 0;
        }

        $link = route('files.index', ['tahun' => $payrollImport->tahun, 'bulan' => $payrollImport->bulan]);
        $alreadyNotified = DB::table('tb_notifikasi')
            ->whereIn('id_user', $recipients->all())
            ->where('judul', 'Slip gaji tersedia')
            ->where('link_aksi', $link)
            ->pluck('id_user')
            ->map(fn ($id): string => (string) $id)
            ->unique()
            ->all();
        $alreadyNotified = array_fill_keys($alreadyNotified, true);
        $period = sprintf('%02d-%04d', $payrollImport->bulan, $payrollImport->tahun);
        $now = now();

        $rows = $recipients
            ->reject(fn (string $recipient): bool => isset($alreadyNotified[$recipient]))
            ->map(fn (string $recipient): array => [
                'id_user' => $recipient,
                'judul' => 'Slip gaji tersedia',
                'pesan' => "Slip gaji periode {$period} sudah tersedia di FILES.",
                'link_aksi' => $link,
                'status_baca' => 'unread',
                'waktu_notif' => $now,
            ])
            ->values()
            ->all();

        if ($rows !== []) {
            DB::table('tb_notifikasi')->insert($rows);
        }

        return count($rows);
    }

    public function deletePublishedPayrollNotifications(PayrollImport $payrollImport): int
    {
        if (! Schema::hasTable('tb_notifikasi')) {
            return 0;
        }

        $link = route('files.index', ['tahun' => $payrollImport->tahun, 'bulan' => $payrollImport->bulan]);

        return DB::table('tb_notifikasi')
            ->where('judul', 'Slip gaji tersedia')
            ->where('link_aksi', $link)
            ->delete();
    }

    public function notifyDataDiriChanged(DataDiriDocument $document, string $action, string $actorId = ''): void
    {
        if (! Schema::hasTable('tb_notifikasi') || ! Schema::hasTable('tb_user')) {
            return;
        }

        $recipients = TbUser::query()
            ->where('id_pegawai', $document->pegawai_id)
            ->whereNotNull('id_user')
            ->pluck('id_user')
            ->map(fn ($id): string => (string) $id)
            ->filter(fn (string $id): bool => $id !== '' && $id !== $actorId)
            ->unique()
            ->values()
            ->all();

        if ($recipients === []) {
            return;
        }

        $isDeleted = $action === 'deleted';
        $label = $document->typeLabel();
        $rows = array_map(
            fn (string $recipient): array => [
                'id_user' => $recipient,
                'judul' => $isDeleted ? 'Dokumen data diri dihapus' : 'Dokumen data diri diperbarui',
                'pesan' => $isDeleted
                    ? "Dokumen {$label} Anda telah dihapus oleh admin."
                    : "Dokumen {$label} Anda telah diperbarui oleh admin.",
                'link_aksi' => '/profile/data-diri',
                'status_baca' => 'unread',
                'waktu_notif' => now(),
            ],
            $recipients,
        );

        DB::table('tb_notifikasi')->insert($rows);
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

    public function actionUrl(?string $legacyLink): ?string
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

        if (str_starts_with($legacyLink, '/')) {
            return $legacyLink;
        }

        // Notifikasi lama dapat menyimpan URL absolut dari route().
        // Normalisasi ke path lokal agar redirect tetap aman dan konsisten.
        $path = (string) ($parts['path'] ?? '');
        if ($path === '') {
            return null;
        }

        $configuredHost = parse_url((string) config('app.url'), PHP_URL_HOST);
        $notificationHost = $parts['host'] ?? null;
        if ($notificationHost !== null && $configuredHost !== null && strcasecmp($notificationHost, (string) $configuredHost) !== 0) {
            return null;
        }

        return $path.($parts['query'] ?? '' ? '?'.$parts['query'] : '').($parts['fragment'] ?? '' ? '#'.$parts['fragment'] : '');
    }
}

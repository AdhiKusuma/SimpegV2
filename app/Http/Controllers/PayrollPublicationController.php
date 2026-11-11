<?php

namespace App\Http\Controllers;

use App\Models\Legacy\TbJabatan;
use App\Models\Legacy\TbKantor;
use App\Models\PayrollImport;
use App\Models\PayrollPublication;
use App\Services\NotificationService;
use App\Services\PayrollPublicationService;
use App\Services\RbacService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

class PayrollPublicationController extends Controller
{
    public function index(Request $request, RbacService $rbac, NotificationService $notifications): Response
    {
        abort_unless($rbac->can($request, 'payroll.view'), 403);
        if (! Schema::hasTable('payroll_publications')) {
            return Inertia::render('Payroll/Publications', [
                'publications' => [],
                'scopeLabel' => null,
                'canManage' => false,
                'flash' => ['error' => 'Modul publikasi belum siap. Jalankan migration aplikasi terlebih dahulu.'],
            ]);
        }
        $scope = $this->officeScope($request);
        $publications = PayrollPublication::query()
            ->with(['payrollImport.officeSummaries'])
            ->where('status', '<>', 'deleted')
            ->when($scope['restricted'], fn ($query) => $query->whereHas(
                'payrollImport.officeSummaries',
                fn ($summaries) => $summaries->whereIn('office_code', $scope['codes']),
            ))
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        $rows = $publications->map(fn (PayrollPublication $publication): array => $this->present($publication, $notifications))->values()->all();
        $knownImportIds = PayrollPublication::query()
            ->pluck('payroll_import_id')
            ->map(fn ($id): int => (int) $id)
            ->all();
        $legacyImports = PayrollImport::query()
            ->where('is_current', true)
            ->when($knownImportIds !== [], fn ($query) => $query->whereNotIn('id', $knownImportIds))
            ->when($scope['restricted'], fn ($query) => $query->whereHas(
                'officeSummaries',
                fn ($summaries) => $summaries->whereIn('office_code', $scope['codes']),
            ))
            ->orderByDesc('id')
            ->get();
        foreach ($legacyImports as $import) {
            $recipientStatus = $notifications->payrollRecipientStatus($import);
            $rows[] = [
                'id' => null,
                'period' => sprintf('%02d-%04d', $import->bulan, $import->tahun),
                'source_filename' => (string) ($import->source_filename ?: 'Batch payroll aktif'),
                'status' => 'legacy',
                'status_label' => 'Aktif (publikasi lama)',
                'mode' => 'immediate',
                'scheduled_at' => null,
                'scheduled_at_input' => null,
                'sent_at' => null,
                'total_recipients' => $recipientStatus['account_count'],
                'sent_count' => $recipientStatus['notified_count'],
                'failed_count' => null,
                'payroll_entry_count' => $recipientStatus['payroll_count'],
                'account_count' => $recipientStatus['account_count'],
                'notified_count' => $recipientStatus['notified_count'],
                'missing_recipient_count' => $recipientStatus['missing_count'],
                'missing_recipients' => $recipientStatus['missing_recipients'],
                'import_id' => (int) $import->id,
                'is_legacy' => true,
            ];
        }

        return Inertia::render('Payroll/Publications', [
            'publications' => $rows,
            'scopeLabel' => $scope['label'],
            'canManage' => $rbac->can($request, 'payroll.import'),
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
            ],
        ]);
    }

    public function schedule(Request $request, int $publication, RbacService $rbac, PayrollPublicationService $publications): RedirectResponse
    {
        $this->authorizeManage($request, $rbac);
        $validated = $request->validate(['scheduled_at' => ['required', 'date']]);
        $item = PayrollPublication::query()->with('payrollImport.officeSummaries')->findOrFail($publication);
        $this->authorizeScope($request, $item);
        $scheduledAt = $this->parseJakartaDate((string) $validated['scheduled_at']);
        $publications->schedule($item, $scheduledAt->toDateTimeString(), (string) $request->session()->get('id_user'));

        return to_route('payroll.publications.index')->with('success', 'Pengiriman slip gaji berhasil dijadwalkan.');
    }

    public function send(Request $request, int $publication, RbacService $rbac, PayrollPublicationService $publications): RedirectResponse
    {
        $this->authorizeManage($request, $rbac);
        $item = PayrollPublication::query()->with('payrollImport.officeSummaries')->findOrFail($publication);
        $this->authorizeScope($request, $item);
        $publications->send($item);

        return to_route('payroll.publications.index')->with('success', 'Slip gaji berhasil dikirim ke akun pegawai terkait.');
    }

    public function cancel(Request $request, int $publication, RbacService $rbac, PayrollPublicationService $publications): RedirectResponse
    {
        $this->authorizeManage($request, $rbac);
        $item = PayrollPublication::query()->with('payrollImport.officeSummaries')->findOrFail($publication);
        $this->authorizeScope($request, $item);
        $publications->cancel($item, (string) $request->session()->get('id_user'));

        return to_route('payroll.publications.index')->with('success', 'Jadwal pengiriman slip gaji berhasil dibatalkan.');
    }

    public function destroy(Request $request, int $publication, RbacService $rbac, PayrollPublicationService $publications): RedirectResponse
    {
        $this->authorizeManage($request, $rbac);
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:1000'],
        ]);
        $item = PayrollPublication::query()->with('payrollImport.officeSummaries')->findOrFail($publication);
        $this->authorizeScope($request, $item);
        $publications->delete($item, (string) $request->session()->get('id_user'), trim($validated['reason']));

        return to_route('payroll.publications.index')->with('success', 'Riwayat publikasi dan slip gaji terkait berhasil dihapus.');
    }

    public function destroyImport(Request $request, int $payrollImport, RbacService $rbac, PayrollPublicationService $publications): RedirectResponse
    {
        $this->authorizeManage($request, $rbac);
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:1000'],
        ]);
        $item = PayrollImport::query()->with('officeSummaries')->findOrFail($payrollImport);
        $this->authorizeImportScope($request, $item);
        $publications->deleteImport($item, (string) $request->session()->get('id_user'), trim($validated['reason']));

        return to_route('payroll.publications.index')->with('success', 'Riwayat publikasi dan slip gaji terkait berhasil dihapus.');
    }

    private function present(PayrollPublication $publication, NotificationService $notifications): array
    {
        $formatDate = static fn ($value): ?string => $value
            ? Carbon::parse($value)->timezone('Asia/Jakarta')->format('d-m-Y H:i')
            : null;
        $statusLabels = [
            'scheduled' => 'Terjadwal',
            'processing' => 'Diproses',
            'sent' => 'Terkirim',
            'failed' => 'Gagal',
            'cancelled' => 'Dibatalkan',
        ];
        $recipientStatus = $notifications->payrollRecipientStatus(
            $publication->payrollImport,
            in_array($publication->status, ['sent', 'failed'], true),
        );

        return [
            'id' => (int) $publication->id,
            'period' => sprintf('%02d-%04d', $publication->payrollImport->bulan, $publication->payrollImport->tahun),
            'source_filename' => (string) ($publication->payrollImport->source_filename ?: 'Batch payroll'),
            'status' => (string) $publication->status,
            'status_label' => $statusLabels[$publication->status] ?? ucfirst((string) $publication->status),
            'mode' => (string) $publication->mode,
            'scheduled_at' => $formatDate($publication->scheduled_at),
            'scheduled_at_input' => $publication->scheduled_at
                ? Carbon::parse($publication->scheduled_at)->timezone('Asia/Jakarta')->format('Y-m-d\TH:i')
                : now('Asia/Jakarta')->addDay()->setTime(8, 0)->format('Y-m-d\TH:i'),
            'sent_at' => $formatDate($publication->sent_at),
            'total_recipients' => (int) $publication->total_recipients,
            'sent_count' => (int) $publication->sent_count,
            'failed_count' => (int) $publication->failed_count,
            'payroll_entry_count' => $recipientStatus['payroll_count'],
            'account_count' => $recipientStatus['account_count'],
            'notified_count' => $recipientStatus['notified_count'],
            'missing_recipient_count' => $recipientStatus['missing_count'],
            'missing_recipients' => $recipientStatus['missing_recipients'],
            'is_legacy' => false,
        ];
    }

    private function authorizeManage(Request $request, RbacService $rbac): void
    {
        abort_unless($rbac->can($request, 'payroll.import'), 403, 'Akun Anda tidak memiliki izin mengelola pengiriman slip gaji.');
    }

    private function authorizeScope(Request $request, PayrollPublication $publication): void
    {
        $this->authorizeImportScope($request, $publication->payrollImport);
    }

    private function authorizeImportScope(Request $request, PayrollImport $import): void
    {
        $scope = $this->officeScope($request);
        if ($scope['restricted']) {
            $offices = $import->officeSummaries->pluck('office_code')->map(fn ($code): string => (string) $code)->all();
            abort_if($scope['codes'] === [] || array_diff($offices, $scope['codes']) !== [], 403, 'Batch payroll berada di luar cakupan kantor Anda.');
        }
    }

    private function parseJakartaDate(string $value): Carbon
    {
        try {
            return Carbon::createFromFormat('Y-m-d\TH:i', $value, 'Asia/Jakarta')->setTimezone(config('app.timezone'));
        } catch (\Throwable) {
            abort(422, 'Format jadwal pengiriman tidak valid.');
        }
    }

    private function officeScope(Request $request): array
    {
        $role = strtolower((string) $request->session()->get('hak_akses'));
        if (in_array($role, ['admin', 'superadmin'], true)) {
            return ['restricted' => false, 'codes' => [], 'label' => null];
        }
        $officeCode = $role === 'kepala'
            ? (string) $request->session()->get('kode_kantor', '')
            : (string) TbJabatan::query()->where('id_peg', (string) $request->session()->get('id_pegawai', ''))->where('status_jab', 'Aktif')->orderByDesc('id_jab')->value('unit_kerja');
        $office = TbKantor::query()->where('kode_kantor_detail', $officeCode)->first(['kode_kantor_detail', 'kode_cabang', 'nama_kantor', 'level']);
        if (! $office) {
            return ['restricted' => true, 'codes' => [], 'label' => 'Kantor akun belum tercatat'];
        }
        $codes = strtoupper((string) $office->level) === 'KC' && $office->kode_cabang
            ? TbKantor::query()->where('kode_cabang', $office->kode_cabang)->pluck('kode_kantor_detail')->map(fn ($code): string => (string) $code)->all()
            : [(string) $office->kode_kantor_detail];

        return ['restricted' => true, 'codes' => $codes, 'label' => 'Wilayah: '.trim((string) $office->nama_kantor)];
    }
}
